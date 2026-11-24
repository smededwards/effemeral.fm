<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use App\Services\Playlists\AlbumPlaylistExporter;
use App\Services\Recommendations\PlaylistNameSuggester;
use App\Services\Spotify\AlbumPlaylistSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles album-first playlist creation, review, ordering, and Spotify sync.
 */
class AlbumPlaylistController extends Controller
{
    public function __construct(private PlaylistNameSuggester $playlistNameSuggester) {}

    /**
     * Show a playlist and the optional catalogue search results.
     *
     * @param  Request  $request  The current search request.
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being viewed.
     * @return View The playlist page.
     */
    public function show(Request $request, AlbumPlaylist $albumPlaylist): View
    {
        $search = trim((string) $request->query('q', ''));
        $searchTerm = '%'.$search.'%';
        $normalisedSearch = mb_strtolower($search);
        $artistSearch = $search !== '' && Artist::query()
            ->where('normalised_name', 'like', '%'.$normalisedSearch.'%')
            ->exists();
        $sort = $this->suggestionSort((string) $request->query('sort', ''));
        $albumQuery = Album::query()
            ->with('artists')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($albumQuery) use ($search): void {
                    $albumQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('artists', fn ($artistQuery) => $artistQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('artists.acceptedGenres', fn ($genreQuery) => $genreQuery->where('genres.name', 'like', '%'.$search.'%'));
                });
            })
            ->whereDoesntHave('playlists', fn ($query) => $query->whereKey($albumPlaylist->id))
            ->when($search !== '', function ($query) use ($artistSearch, $normalisedSearch, $searchTerm, $sort): void {
                $query->orderByRaw(
                    "CASE
                        WHEN LOWER(albums.title) = ? THEN 0
                        WHEN LOWER(albums.title) LIKE ? THEN 1
                        WHEN EXISTS (
                            SELECT 1
                            FROM album_artist
                            INNER JOIN artists ON artists.id = album_artist.artist_id
                            WHERE album_artist.album_id = albums.id
                            AND (LOWER(artists.name) = ? OR LOWER(artists.normalised_name) = ?
                                OR LOWER(artists.normalised_name) LIKE ?)
                        ) THEN 2
                        ELSE 3
                    END",
                    [$normalisedSearch, $searchTerm, $normalisedSearch, $normalisedSearch, $searchTerm]
                );

                if ($sort === 'release_oldest' || $artistSearch && $sort === 'relevance') {
                    $query->orderByRaw('CASE WHEN release_date IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('release_date')
                        ->orderBy('title');

                    return;
                }

                if ($sort === 'release_newest') {
                    $query->orderByRaw('CASE WHEN release_date IS NULL THEN 1 ELSE 0 END')
                        ->orderByDesc('release_date')
                        ->orderBy('title');

                    return;
                }

                if ($sort === 'recently_added') {
                    $query->orderByDesc('albums.created_at')->orderBy('title');

                    return;
                }

                $query->orderByRaw('CASE WHEN release_date IS NULL THEN 1 ELSE 0 END')
                    ->orderByDesc('release_date')
                    ->orderByDesc('albums.created_at')
                    ->orderBy('title');
            });

        $albums = $search === ''
            ? collect()
            : $albumQuery->limit(150)->get()
                ->groupBy(fn (Album $album): string => $this->editionKey($album->title))
                ->map(fn ($variants): Album => $variants->sortBy(fn (Album $album): int => $this->editionPenalty($album->title))->first())
                ->take(50)
                ->values();

        return view('playlists.show', [
            'albumPlaylist' => $albumPlaylist->load('albums.artists'),
            'search' => $search,
            'artistSearch' => $artistSearch,
            'sort' => $sort,
            'albums' => $albums,
            'spotifyPlaylists' => $albumPlaylist->spotify_playlist_id === null
                ? SpotifyPlaylist::query()->orderBy('name')->get(['spotify_playlist_id', 'name', 'public', 'collaborative'])
                : collect(),
            'linkedSpotifyPlaylist' => $albumPlaylist->spotify_playlist_id === null
                ? null
                : SpotifyPlaylist::query()->where('spotify_playlist_id', $albumPlaylist->spotify_playlist_id)->first(),
        ]);
    }

    /**
     * Resolve the supported album suggestion sort order.
     *
     * @param  string  $sort  The requested sort order.
     * @return string The normalised sort order.
     */
    private function suggestionSort(string $sort): string
    {
        return match ($sort) {
            'release_oldest', 'release_newest', 'recently_added' => $sort,
            default => 'relevance',
        };
    }

    /**
     * Return a grouping key that treats common editions as one album.
     *
     * @param  string  $title  The album title.
     * @return string The normalised edition-independent title.
     */
    private function editionKey(string $title): string
    {
        $title = preg_replace(
            '/\b(?:remaster(?:ed)?|deluxe|expanded|anniversary|special edition|bonus tracks?)\b/i',
            '',
            $title,
        ) ?? $title;

        return preg_replace('/\s+/', ' ', mb_strtolower(trim($title, " \t\n\r\0\x0B()[]-"))) ?? '';
    }

    /**
     * Prefer original editions when duplicate album variants are grouped.
     *
     * @param  string  $title  The album title.
     * @return int The edition penalty.
     */
    private function editionPenalty(string $title): int
    {
        return preg_match('/\b(?:remaster(?:ed)?|deluxe|expanded|anniversary|special edition|bonus tracks?)\b/i', $title) === 1
            ? 1
            : 0;
    }

    /**
     * Stream the playlist as a portable CSV export.
     *
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being exported.
     * @return StreamedResponse The CSV response.
     */
    public function export(AlbumPlaylist $albumPlaylist, AlbumPlaylistExporter $exporter): StreamedResponse
    {
        return $exporter->download($albumPlaylist);
    }

    /**
     * Build a reviewable Spotify sync preview without changing Spotify.
     *
     * @param  Request  $request  The requested Spotify destination.
     * @param  AlbumPlaylist  $albumPlaylist  The playlist to preview.
     * @param  AlbumPlaylistSyncService  $syncService  The sync preview service.
     * @return View|RedirectResponse The preview or an error redirect.
     */
    public function previewSpotifySync(Request $request, AlbumPlaylist $albumPlaylist, AlbumPlaylistSyncService $syncService): View|RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        $validated = $request->validate([
            'destination' => ['required', 'in:new,existing,linked'],
            'spotify_playlist_id' => ['required_if:destination,existing', 'nullable', 'string', 'exists:spotify_playlists,spotify_playlist_id'],
        ]);
        $destination = $albumPlaylist->spotify_playlist_id !== null ? 'linked' : $validated['destination'];
        $selectedPlaylist = $destination === 'existing'
            ? SpotifyPlaylist::query()->where('spotify_playlist_id', $validated['spotify_playlist_id'])->firstOrFail()
            : null;

        try {
            $preview = $syncService->preview($albumPlaylist, $destination, $selectedPlaylist);
        } catch (\Throwable $exception) {
            return to_route('album-playlists.show', $albumPlaylist)->with('error', $exception->getMessage());
        }

        session()->put($this->syncPreviewSessionKey($albumPlaylist), array_intersect_key($preview, array_flip([
            'destination',
            'target_playlist_id',
            'snapshot_id',
            'remote_name',
            'remove_uris',
            'add_uris',
            'desired_uris',
            'managed_uris',
            'local_hash',
        ])));

        return view('playlists.sync-review', [
            'albumPlaylist' => $albumPlaylist,
            'preview' => $preview,
        ]);
    }

    /**
     * Apply an approved playlist sync to Spotify.
     *
     * @param  Request  $request  The submitted preview confirmation.
     * @param  AlbumPlaylist  $albumPlaylist  The playlist to sync.
     * @param  AlbumPlaylistSyncService  $syncService  The sync service.
     * @return RedirectResponse The post-sync redirect.
     */
    public function syncSpotify(Request $request, AlbumPlaylist $albumPlaylist, AlbumPlaylistSyncService $syncService): RedirectResponse
    {
        $preview = session()->get($this->syncPreviewSessionKey($albumPlaylist));

        if (! is_array($preview)) {
            return to_route('album-playlists.show', $albumPlaylist)->with('error', 'Review the Spotify changes before confirming a sync.');
        }

        try {
            $syncService->sync($albumPlaylist, $preview);
        } catch (\Throwable $exception) {
            $albumPlaylist->refresh();
            if (! in_array($albumPlaylist->spotify_sync_status, ['syncing', 'failed'], true)) {
                $albumPlaylist->update([
                    'spotify_sync_status' => 'failed',
                    'spotify_sync_error' => $exception->getMessage(),
                ]);
            }
            session()->forget($this->syncPreviewSessionKey($albumPlaylist));

            return to_route('album-playlists.show', $albumPlaylist)->with('error', $exception->getMessage());
        }

        session()->forget($this->syncPreviewSessionKey($albumPlaylist));

        return to_route('album-playlists.show', $albumPlaylist)->with('success', 'Effemeral playlist synced to Spotify.');
    }

    /**
     * Create an Effemeral album playlist, generating a name when none is supplied.
     *
     * @param  Request  $request  The submitted playlist name.
     * @return RedirectResponse The playlist index redirect.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:100']]);
        $name = trim((string) ($validated['name'] ?? ''));
        $playlist = AlbumPlaylist::query()->create([
            'name' => $name !== '' ? $name : $this->playlistNameSuggester->suggestFromTitles([]),
        ]);

        return to_route('album-playlists.show', $playlist)->with('success', 'Album playlist created.');
    }

    /**
     * Rename an existing Effemeral album playlist.
     *
     * @param  Request  $request  The submitted replacement name.
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being renamed.
     * @return RedirectResponse The playlist detail redirect.
     */
    public function update(Request $request, AlbumPlaylist $albumPlaylist): RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $albumPlaylist->update(['name' => trim($validated['name'])]);
        $this->markSyncPending($albumPlaylist);

        return to_route('album-playlists.show', $albumPlaylist)->with('success', 'Playlist name updated.');
    }

    /**
     * Replace a playlist name with a new suggestion based on its albums.
     *
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being renamed.
     * @return RedirectResponse The playlist detail redirect.
     */
    public function refreshName(AlbumPlaylist $albumPlaylist): RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        $name = $this->playlistNameSuggester->suggestFromTitles(
            $albumPlaylist->albums()->pluck('albums.title'),
        );
        $albumPlaylist->update(['name' => $name]);
        $this->markSyncPending($albumPlaylist);

        return back()->with('success', 'Playlist name refreshed.');
    }

    /**
     * Add one album to a playlist and mark its Spotify sync as pending.
     *
     * @param  Request  $request  The submitted album ID.
     * @param  AlbumPlaylist  $albumPlaylist  The destination playlist.
     * @return RedirectResponse The playlist detail redirect.
     */
    public function addAlbum(Request $request, AlbumPlaylist $albumPlaylist): RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        $validated = $request->validate(['album_id' => ['required', 'integer', 'exists:albums,id']]);

        if ($albumPlaylist->albums()->whereKey($validated['album_id'])->exists()) {
            return back()->with('error', 'That album is already in this playlist.');
        }

        $albumPlaylist->albums()->attach($validated['album_id'], [
            'position' => (int) ($albumPlaylist->albums()->max('album_playlist_items.position') ?? 0) + 1,
        ]);
        $this->markSyncPending($albumPlaylist);

        return back()->with('success', 'Album added to playlist.');
    }

    /**
     * Add selected albums to an existing or newly named playlist.
     *
     * @param  Request  $request  The selected albums and destination.
     * @return RedirectResponse The destination playlist redirect.
     */
    public function addAlbums(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'album_ids' => ['required', 'array', 'min:1', 'max:50'],
            'album_ids.*' => ['required', 'integer', 'distinct', 'exists:albums,id'],
            'destination' => ['required', 'in:existing,new'],
            'playlist_id' => ['required_if:destination,existing', 'nullable', 'integer', 'exists:album_playlists,id'],
            'playlist_name' => ['nullable', 'string', 'max:100'],
        ]);

        $playlist = null;
        if ($validated['destination'] === 'existing') {
            $playlist = AlbumPlaylist::query()->findOrFail($validated['playlist_id']);

            if ($response = $this->syncInProgress($playlist)) {
                return $response;
            }
        }

        $albumTitles = Album::query()->whereKey($validated['album_ids'])->pluck('title');
        [$playlist, $addedCount] = DB::transaction(function () use ($playlist, $validated, $albumTitles): array {
            $playlist ??= AlbumPlaylist::query()->create([
                'name' => trim((string) ($validated['playlist_name'] ?? ''))
                    ?: $this->playlistNameSuggester->suggestFromTitles($albumTitles),
            ]);
            $existingIds = $playlist->albums()->whereKey($validated['album_ids'])->pluck('albums.id')->all();
            $newIds = array_values(array_diff($validated['album_ids'], $existingIds));
            $position = (int) ($playlist->albums()->max('album_playlist_items.position') ?? 0);

            foreach ($newIds as $albumId) {
                $playlist->albums()->attach($albumId, ['position' => ++$position]);
            }

            return [$playlist, count($newIds)];
        });

        if ($addedCount > 0) {
            $this->markSyncPending($playlist);
        }

        return to_route('album-playlists.show', $playlist)->with('success', $addedCount.' album'.($addedCount === 1 ? '' : 's').' added to playlist.');
    }

    /**
     * Remove one album from a playlist without deleting the album itself.
     *
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being edited.
     * @param  Album  $album  The album being removed.
     * @return RedirectResponse The playlist detail redirect.
     */
    public function removeAlbum(AlbumPlaylist $albumPlaylist, Album $album): RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        abort_unless($albumPlaylist->albums()->whereKey($album->id)->exists(), 404);

        DB::transaction(function () use ($albumPlaylist, $album): void {
            $albumPlaylist->albums()->detach($album->id);
            $this->saveOrder($albumPlaylist);
        });
        $this->markSyncPending($albumPlaylist);

        return back()->with('success', 'Album removed from playlist.');
    }

    /**
     * Move one album up or down while preserving playlist order.
     *
     * @param  Request  $request  The requested movement direction.
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being edited.
     * @param  Album  $album  The album being moved.
     * @return RedirectResponse The playlist detail redirect.
     */
    public function moveAlbum(Request $request, AlbumPlaylist $albumPlaylist, Album $album): RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);
        /**
         * @var Collection<int, Album> $albums
         */
        $albums = $albumPlaylist->albums()->get();
        $currentIndex = $albums->search(fn (Album $item): bool => $item->id === $album->id);

        abort_if($currentIndex === false, 404);

        $targetIndex = $currentIndex + ($validated['direction'] === 'up' ? -1 : 1);

        if ($targetIndex < 0 || $targetIndex >= $albums->count()) {
            return back();
        }

        $albums->splice($currentIndex, 1);
        $albums->splice($targetIndex, 0, [$album]);

        DB::transaction(function () use ($albumPlaylist, $albums): void {
            foreach ($albums->values() as $index => $orderedAlbum) {
                $albumPlaylist->albums()->updateExistingPivot($orderedAlbum->id, ['position' => $index + 1]);
            }
        });
        $this->markSyncPending($albumPlaylist);

        return back()->with('success', 'Album order updated.');
    }

    private function saveOrder(AlbumPlaylist $albumPlaylist): void
    {
        /**
         * @var Collection<int, Album> $albums
         */
        $albums = $albumPlaylist->albums()->get();

        foreach ($albums->values() as $index => $album) {
            $albumPlaylist->albums()->updateExistingPivot($album->id, ['position' => $index + 1]);
        }
    }

    private function markSyncPending(AlbumPlaylist $albumPlaylist): void
    {
        $albumPlaylist->update([
            'spotify_sync_status' => $albumPlaylist->spotify_playlist_id === null ? 'draft' : 'pending',
            'spotify_sync_error' => null,
        ]);
    }

    private function syncInProgress(AlbumPlaylist $albumPlaylist): ?RedirectResponse
    {
        return $albumPlaylist->spotify_sync_status === 'syncing'
            ? back()->with('error', 'Wait for the Spotify sync to finish before changing this playlist.')
            : null;
    }

    private function syncPreviewSessionKey(AlbumPlaylist $albumPlaylist): string
    {
        return 'album_playlist_sync_preview_'.$albumPlaylist->id;
    }
}
