<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use App\Services\Spotify\AlbumPlaylistSyncService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlbumPlaylistController extends Controller
{
    public function show(Request $request, AlbumPlaylist $albumPlaylist): View
    {
        $search = trim((string) $request->query('q', ''));
        $albumQuery = Album::query()
            ->with('artists')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('artists', fn ($artistQuery) => $artistQuery->where('name', 'like', '%'.$search.'%'));
            })
            ->whereDoesntHave('playlists', fn ($query) => $query->whereKey($albumPlaylist->id))
            ->orderBy('title');

        return view('playlists.show', [
            'albumPlaylist' => $albumPlaylist->load('albums.artists'),
            'search' => $search,
            'albums' => $search === '' ? collect() : $albumQuery->paginate(15)->withQueryString(),
            'spotifyPlaylists' => $albumPlaylist->spotify_playlist_id === null
                ? SpotifyPlaylist::query()->orderBy('name')->get(['spotify_playlist_id', 'name', 'public', 'collaborative'])
                : collect(),
            'linkedSpotifyPlaylist' => $albumPlaylist->spotify_playlist_id === null
                ? null
                : SpotifyPlaylist::query()->where('spotify_playlist_id', $albumPlaylist->spotify_playlist_id)->first(),
        ]);
    }

    public function export(AlbumPlaylist $albumPlaylist): StreamedResponse
    {
        $albumPlaylist->load('albums.artists.acceptedGenres');
        /** @var Collection<int, Album> $albums */
        $albums = $albumPlaylist->albums;

        return response()->streamDownload(function () use ($albums): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Position', 'Album', 'Artist', 'Release date', 'Styles', 'Spotify URL']);

            foreach ($albums as $position => $album) {
                /** @var Collection<int, Artist> $artists */
                $artists = $album->artists;
                fputcsv($output, [
                    $position + 1,
                    $album->displayTitle(),
                    $artists->pluck('name')->implode(', ') ?: 'Unknown artist',
                    $album->release_date ?: '',
                    $artists->flatMap(fn (Artist $artist) => $artist->acceptedGenres->pluck('name'))->unique()->sort()->implode(', '),
                    $album->spotify_url ?: '',
                ]);
            }

            fclose($output);
        }, str()->slug($albumPlaylist->name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $playlist = AlbumPlaylist::query()->create(['name' => trim($validated['name'])]);

        return to_route('album-playlists.show', $playlist)->with('success', 'Album playlist created.');
    }

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

    public function addAlbums(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'album_ids' => ['required', 'array', 'min:1', 'max:50'],
            'album_ids.*' => ['required', 'integer', 'distinct', 'exists:albums,id'],
            'destination' => ['required', 'in:existing,new'],
            'playlist_id' => ['required_if:destination,existing', 'nullable', 'integer', 'exists:album_playlists,id'],
            'playlist_name' => ['required_if:destination,new', 'nullable', 'string', 'max:100'],
        ]);

        $playlist = null;
        if ($validated['destination'] === 'existing') {
            $playlist = AlbumPlaylist::query()->findOrFail($validated['playlist_id']);

            if ($response = $this->syncInProgress($playlist)) {
                return $response;
            }
        }

        [$playlist, $addedCount] = DB::transaction(function () use ($playlist, $validated): array {
            $playlist ??= AlbumPlaylist::query()->create(['name' => trim($validated['playlist_name'])]);
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

    public function moveAlbum(Request $request, AlbumPlaylist $albumPlaylist, Album $album): RedirectResponse
    {
        if ($response = $this->syncInProgress($albumPlaylist)) {
            return $response;
        }

        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);
        /** @var Collection<int, Album> $albums */
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
        /** @var Collection<int, Album> $albums */
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
