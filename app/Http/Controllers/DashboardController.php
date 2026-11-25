<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\AlbumRecommendation;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\RecommendationRun;
use App\Models\Scrobble;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use App\Services\Catalog\CataloguePageService;
use App\Services\Catalog\GenreWriter;
use App\Services\Imports\ImportStatusPageService;
use App\Services\Library\LibraryPageService;
use App\Services\Playlists\SpotifyPlaylistOverviewService;
use App\Services\Recommendations\AlbumCompletionService;
use App\Services\Recommendations\RecommendationListeningWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Serves the main discovery, catalogue, import, library, and playlist views.
 */
class DashboardController extends Controller
{
    /**
     * Show the accepted style catalogue.
     *
     * @return View The style catalogue page.
     */
    public function styles(CataloguePageService $cataloguePageService): View
    {
        return view('catalogue.styles', ['genres' => $cataloguePageService->genres()]);
    }

    /**
     * Search and filter canonical artists and albums.
     *
     * @param  Request  $request  The search and filter request.
     * @return View The catalogue page.
     */
    public function catalogue(Request $request, CataloguePageService $cataloguePageService): View
    {
        return view('catalogue.index', $cataloguePageService->data($request));
    }

    /**
     * Create a reviewable draft from matching incomplete catalogue albums.
     *
     * @param  Request  $request  The discovery criteria.
     * @param  RecommendationListeningWindow  $listeningWindow  The selected listening window.
     * @return RedirectResponse The recommendation review redirect.
     */
    public function discoverRecommendations(Request $request, RecommendationListeningWindow $listeningWindow): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['nullable', 'integer', 'exists:artists,id'],
            'album_id' => ['nullable', 'integer', 'exists:albums,id'],
            'genre' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1900,2100'],
            'listened_within' => ['nullable', 'string', 'in:7_days,1_month,this_year,1_year,all_time'],
        ]);

        if (empty($validated['artist_id']) === empty($validated['album_id'])) {
            return back()->withErrors(['context' => 'Choose one artist or album before generating discovery suggestions.']);
        }

        $selectedAlbum = isset($validated['album_id'])
            ? Album::query()->with('artists')->findOrFail($validated['album_id'])
            : null;
        $selectedArtist = isset($validated['artist_id'])
            ? Artist::query()->findOrFail($validated['artist_id'])
            : null;
        $contextArtistIds = $selectedAlbum?->artists->pluck('id')->all() ?? [$selectedArtist->id];
        $genre = $this->normalise($validated['genre']);
        $window = $validated['listened_within'] ?? RecommendationListeningWindow::DEFAULT;
        $heardTrackIds = Scrobble::query()
            ->whereNotNull('track_id')
            ->when($listeningWindow->cutoff($window), fn ($query, $cutoff) => $query->where('listened_at', '>=', $cutoff))
            ->pluck('track_id')
            ->flip();
        /**
         * @var \Illuminate\Database\Eloquent\Collection<int, Album> $albums
         */
        $albums = Album::query()
            ->with(['artists.acceptedGenres', 'releaseTracks'])
            ->where('release_date', 'like', $validated['year'].'%')
            ->get();
        $savedAlbums = SpotifySavedAlbum::query()
            ->whereIn('spotify_album_id', $albums->pluck('spotify_id')->filter())
            ->get()
            ->keyBy('spotify_album_id');

        $candidates = $albums
            ->map(function (Album $album) use ($contextArtistIds, $genre, $heardTrackIds, $savedAlbums, $validated): ?array {
                /**
                 * @var \Illuminate\Database\Eloquent\Collection<int, Artist> $artists
                 */
                $artists = $album->artists;
                /**
                 * @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks
                 */
                $tracks = $album->releaseTracks;

                if ($artists->pluck('id')->intersect($contextArtistIds)->isNotEmpty()) {
                    return null;
                }

                $hasGenre = $artists
                    ->flatMap(fn (Artist $artist) => $artist->acceptedGenres->pluck('normalised_name'))
                    ->contains($genre);
                $savedAlbum = $savedAlbums->get($album->spotify_id);

                if (! $hasGenre || $savedAlbum?->completion_override === true
                    || $tracks->isEmpty() || ($album->total_tracks !== null && $tracks->count() !== $album->total_tracks)) {
                    return null;
                }

                $heard = $tracks->filter(fn (Track $track): bool => $heardTrackIds->has($track->id))->count();
                if ($savedAlbum?->completion_override !== false && $heard === $tracks->count()) {
                    return null;
                }

                return [
                    'album' => $album,
                    'saved_album' => $savedAlbum,
                    'heard' => $heard,
                    'total' => $tracks->count(),
                    'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                    'match_reason' => 'Genre: '.$validated['genre'].' · Release year: '.$validated['year'].($savedAlbum?->completion_override === false ? ' · Manual incomplete override' : ''),
                ];
            })
            ->filter()
            ->sortBy(fn (array $candidate): string => sprintf('%03d-%s', $candidate['percentage'], $candidate['album']->title))
            ->take(10)
            ->values();

        if ($candidates->isEmpty()) {
            return back()->with('error', 'No incomplete catalogue albums matched that style and year.');
        }

        $recommendationRun = DB::transaction(function () use ($candidates): RecommendationRun {
            $run = RecommendationRun::query()->create([
                'source' => 'catalogue_discovery',
                'status' => 'draft',
                'generated_at' => now(),
            ]);

            foreach ($candidates as $position => $candidate) {
                AlbumRecommendation::query()->create([
                    'recommendation_run_id' => $run->id,
                    'album_id' => $candidate['album']->id,
                    'spotify_saved_album_id' => $candidate['saved_album']?->id,
                    'position' => $position + 1,
                    'heard_tracks' => $candidate['heard'],
                    'total_tracks' => $candidate['total'],
                    'completion_percentage' => $candidate['percentage'],
                    'match_reason' => $candidate['match_reason'],
                ]);
            }

            return $run;
        });

        return redirect()->route('recommendations.review.page', $recommendationRun);
    }

    /**
     * Create a reviewable album draft from an artist's selected discography.
     *
     * @param  Request  $request  The selected artist and release options.
     * @return RedirectResponse The recommendation review redirect.
     */
    public function discographyRecommendations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'include_non_albums' => ['nullable', 'boolean'],
        ]);
        $artist = Artist::query()->findOrFail($validated['artist_id']);
        $includeNonAlbums = (bool) ($validated['include_non_albums'] ?? false);
        /**
         * @var \Illuminate\Database\Eloquent\Collection<int, Album> $albums
         */
        $albums = $artist->albums()
            ->with('releaseTracks')
            ->when(! $includeNonAlbums, fn ($query) => $query->whereIn('album_type', ['album', 'ep']))
            ->where(function ($query): void {
                $query->whereHas('spotifyCollections', fn ($collectionQuery) => $collectionQuery->where('collection_type', 'saved'))
                    ->orWhere(fn ($catalogueQuery) => $catalogueQuery->whereNotNull('musicbrainz_release_group_mbid')->whereNotNull('spotify_id'));
            })
            ->orderBy('release_date')
            ->orderBy('title')
            ->get();
        $savedAlbums = SpotifySavedAlbum::query()
            ->whereIn('spotify_album_id', $albums->pluck('spotify_id')->filter())
            ->get()
            ->keyBy('spotify_album_id');
        $candidates = $albums->filter(fn (Album $album): bool => $album->releaseTracks->pluck('spotify_uri')->contains(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
            || data_get($savedAlbums->get($album->spotify_id)?->tracks, 'items', []) !== [])
            ->map(fn (Album $album): array => [
                'album' => $album,
                'saved_album' => $savedAlbums->get($album->spotify_id),
                'title' => $album->title,
                'release_date' => $album->release_date,
            ]);

        $matchedSavedAlbumIds = $candidates->pluck('saved_album.id')->filter();
        $unmatchedCandidates = SpotifySavedAlbum::query()
            ->whereNotIn('id', $matchedSavedAlbumIds)
            ->get()
            ->filter(function (SpotifySavedAlbum $savedAlbum) use ($artist, $includeNonAlbums): bool {
                return $this->normalise((string) $savedAlbum->primary_artist_name) === $artist->normalised_name
                    && ($includeNonAlbums || $savedAlbum->album_type === 'album')
                    && data_get($savedAlbum->tracks, 'items', []) !== [];
            })
            ->map(fn (SpotifySavedAlbum $savedAlbum): array => [
                'album' => null,
                'saved_album' => $savedAlbum,
                'title' => $savedAlbum->name,
                'release_date' => $savedAlbum->release_date,
            ]);

        $candidates = $candidates
            ->merge($unmatchedCandidates)
            ->sortBy(fn (array $candidate): string => sprintf('%s-%03d-%s', $candidate['release_date'] ?? '9999', $this->editionPenalty($candidate['title']), $candidate['title']))
            ->groupBy(fn (array $candidate): string => $this->editionTitle($candidate['title']))
            ->map(fn ($variants): array => $variants->sortBy(fn (array $candidate): string => sprintf('%03d-%s', $this->editionPenalty($candidate['title']), $candidate['title']))->first())
            ->sortBy(fn (array $candidate): string => sprintf('%s-%s', $candidate['release_date'] ?? '9999', $candidate['title']))
            ->values();

        if ($candidates->isEmpty()) {
            return back()->with('error', 'No Spotify-playable albums are available for this artist.');
        }

        $recommendationRun = DB::transaction(function () use ($candidates): RecommendationRun {
            $heardTrackIds = Scrobble::query()
                ->whereNotNull('track_id')
                ->pluck('track_id')
                ->flip();
            $run = RecommendationRun::query()->create([
                'source' => 'artist_discography',
                'status' => 'draft',
                'generated_at' => now(),
            ]);

            foreach ($candidates as $position => $candidate) {
                /**
                 * @var Album|null $album
                 */
                $album = $candidate['album'];
                /**
                 * @var SpotifySavedAlbum|null $savedAlbum
                 */
                $savedAlbum = $candidate['saved_album'];
                $tracks = $album === null ? collect() : $album->releaseTracks;
                $trackUris = $tracks->pluck('spotify_uri')
                    ->filter(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
                    ->values();
                $trackUris = $trackUris->isNotEmpty() ? $trackUris : collect(data_get($savedAlbum?->tracks, 'items', []))
                    ->filter(fn (mixed $track): bool => is_array($track) && is_string($track['uri'] ?? null))
                    ->map(fn (array $track): string => $track['uri'])
                    ->values();
                $totalTracks = $tracks->count() > 0 ? $tracks->count() : $trackUris->count();
                $heard = $tracks->filter(fn (Track $track): bool => $heardTrackIds->has($track->id))->count();

                AlbumRecommendation::query()->create([
                    'recommendation_run_id' => $run->id,
                    'album_id' => $album?->id,
                    'spotify_saved_album_id' => $savedAlbum?->id,
                    'position' => $position + 1,
                    'heard_tracks' => $heard,
                    'total_tracks' => $totalTracks,
                    'completion_percentage' => $totalTracks === 0 ? 0 : (int) floor(($heard / $totalTracks) * 100),
                ]);
            }

            return $run;
        });

        return redirect()->route('recommendations.review.page', $recommendationRun);
    }

    private function editionTitle(string $title): string
    {
        $title = preg_replace('/\b(?:remaster(?:ed)?|deluxe|expanded|anniversary|special edition|bonus tracks?)\b/i', '', $title) ?? $title;
        $title = preg_replace('/[^\pL\pN]+/u', ' ', $title) ?? $title;

        return $this->normalise($title);
    }

    private function editionPenalty(string $title): int
    {
        return match (true) {
            preg_match('/\banniversary\b/i', $title) === 1 => 3,
            preg_match('/\b(?:deluxe|expanded|special edition|bonus tracks?)\b/i', $title) === 1 => 2,
            preg_match('/\bremaster(?:ed)?\b/i', $title) === 1 => 1,
            default => 0,
        };
    }

    /**
     * Show the playlist-first application overview.
     *
     * @return View The dashboard page.
     */
    public function index(): View
    {
        $albumPlaylists = AlbumPlaylist::query()
            ->withCount('albums')
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'albumPlaylists' => $albumPlaylists,
            'playlistCount' => $albumPlaylists->count(),
            'playlistAlbumCount' => $albumPlaylists->sum('albums_count'),
        ]);
    }

    /**
     * Show import status and retry controls.
     *
     * @return View The imports page.
     */
    public function imports(ImportStatusPageService $importStatusPageService): View
    {
        return view('imports.index', $importStatusPageService->data());
    }

    /**
     * Show the imported saved-album library.
     *
     * @return View The library page.
     */
    public function library(Request $request, LibraryPageService $libraryPageService): View
    {
        return view('library.index', $libraryPageService->data($request));
    }

    /**
     * Show Effemeral album playlists.
     *
     * @return View The Effemeral playlist page.
     */
    public function playlists(Request $request): View
    {
        $playlistSearch = trim((string) $request->query('playlist_search'));
        $playlistSort = $this->playlistSort($request->query('playlist_sort'));
        $albumPlaylists = AlbumPlaylist::query()
            ->withCount('albums')
            ->when(
                $playlistSearch !== '',
                fn ($query) => $query->where('name', 'like', '%'.$playlistSearch.'%'),
            );

        return view('playlists.index', [
            'albumPlaylists' => $this->sortPlaylists($albumPlaylists, $playlistSort, 'updated_at')->get(),
            'playlistSearch' => $playlistSearch,
            'playlistSort' => $playlistSort,
        ]);
    }

    /**
     * Show imported Spotify playlists for browsing and audit work.
     *
     * @return View The Spotify playlist archive page.
     */
    public function spotifyPlaylists(Request $request, SpotifyPlaylistOverviewService $overviewService): View
    {
        $search = trim((string) $request->query('playlist_search'));
        $playlistSort = $this->playlistSort($request->query('playlist_sort'));
        $spotifyPlaylists = SpotifyPlaylist::query()
            ->with(['items.album'])
            ->withCount([
                'items',
                'items as album_items_count' => fn ($query) => $query->whereNotNull('album_id'),
            ])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
        $playlists = $this->sortPlaylists($spotifyPlaylists, $playlistSort, 'last_synced_at')
            ->paginate(25)
            ->withQueryString();

        $playlists->getCollection()->transform(function (SpotifyPlaylist $playlist) use ($overviewService): SpotifyPlaylist {
            foreach ($overviewService->status($playlist) as $key => $value) {
                $playlist->setAttribute($key, $value);
            }

            return $playlist;
        });

        return view('spotify-playlists.index', [
            'playlists' => $playlists,
            'playlistSearch' => $search,
            'playlistSort' => $playlistSort,
            'latestPlaylistImport' => SpotifyPlaylistImportRun::query()->latest()->first(),
            'failedPlaylists' => SpotifyPlaylist::query()->whereNotNull('last_import_error')->latest('last_import_attempted_at')->get(),
        ]);
    }

    /**
     * Resolve the supported playlist sort options.
     *
     * @param  mixed  $sort  The requested sort option.
     * @return string The normalised sort option.
     */
    private function playlistSort(mixed $sort): string
    {
        return match ($sort) {
            'oldest' => 'oldest',
            'a_z' => 'a_z',
            'z_a' => 'z_a',
            default => 'recent',
        };
    }

    /**
     * Apply a playlist sort to an Eloquent query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query  The playlist query.
     * @param  string  $sort  The normalised sort option.
     * @param  string  $updatedColumn  The model column representing its latest update.
     * @return Builder<TModel> The sorted query.
     */
    private function sortPlaylists(Builder $query, string $sort, string $updatedColumn): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy($updatedColumn)->orderBy('name'),
            'a_z' => $query->orderBy('name')->orderByDesc($updatedColumn),
            'z_a' => $query->orderByDesc('name')->orderByDesc($updatedColumn),
            default => $query->orderByDesc($updatedColumn)->orderBy('name'),
        };
    }

    /**
     * Show incomplete albums and recommendation controls.
     *
     * @return View The recommendation page.
     */
    public function recommendations(
        AlbumCompletionService $completionService,
        RecommendationListeningWindow $listeningWindow,
    ): View {
        return view('recommendations.index', $this->recommendationsData($completionService, $listeningWindow));
    }

    /**
     * @param  AlbumCompletionService  $completionService  The album completion service.
     * @param  RecommendationListeningWindow  $listeningWindow  The recommendation listening-window service.
     * @return array<string, mixed> The recommendation data.
     */
    private function recommendationsData(
        AlbumCompletionService $completionService,
        RecommendationListeningWindow $listeningWindow,
    ): array {
        return [
            'incompleteAlbums' => $completionService->incompleteAlbums(),
            'recommendationListeningWindows' => $listeningWindow->options(),
            'latestRecommendation' => RecommendationRun::query()
                ->with('albumRecommendations.album', 'albumRecommendations.spotifySavedAlbum')
                ->latest()
                ->first(),
        ];
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }

    /**
     * Create a recommendation draft from the current incomplete catalogue.
     *
     * @return RedirectResponse The recommendation review redirect.
     */
    public function generateRecommendations(Request $request, AlbumCompletionService $completionService): RedirectResponse
    {
        $validated = $request->validate([
            'listened_within' => ['nullable', 'string', 'in:7_days,1_month,this_year,1_year,all_time'],
        ]);
        $albums = $completionService->incompleteAlbums($validated['listened_within'] ?? RecommendationListeningWindow::DEFAULT);

        if ($albums->isEmpty()) {
            return back()->with('error', 'No incomplete albums are available for recommendation.');
        }

        $run = RecommendationRun::query()->create([
            'source' => 'lastfm_spotify',
            'status' => 'draft',
            'generated_at' => now(),
        ]);

        foreach ($albums as $position => $album) {
            AlbumRecommendation::query()->create([
                'recommendation_run_id' => $run->id,
                'album_id' => $album['canonical_id'] ?? null,
                'spotify_saved_album_id' => $album['id'],
                'position' => $position + 1,
                'heard_tracks' => $album['heard'],
                'total_tracks' => $album['total'],
                'completion_percentage' => $album['percentage'],
            ]);
        }

        return back()->with('success', 'Draft album recommendations generated.');
    }

    /**
     * Apply a manual genre override to an artist.
     *
     * @param  Request  $request  The submitted genre values.
     * @param  Artist  $artist  The artist being edited.
     * @param  GenreWriter  $genreWriter  The genre persistence service.
     * @return RedirectResponse The artist page redirect.
     */
    public function updateArtistGenres(Request $request, Artist $artist, GenreWriter $genreWriter): RedirectResponse
    {
        $validated = $request->validate(['genres' => ['nullable', 'string', 'max:2000']]);
        $genres = preg_split('/[,\n]+/', (string) ($validated['genres'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        $genreWriter->applyManualOverride($artist, array_map('trim', $genres ?: []));

        return back()->with('success', 'Artist genres updated.');
    }

    /**
     * Remove a genre from an artist's accepted catalogue metadata.
     *
     * @param  Artist  $artist  The artist being edited.
     * @param  Genre  $genre  The genre being removed.
     * @param  GenreWriter  $genreWriter  The genre persistence service.
     * @return RedirectResponse The artist page redirect.
     */
    public function removeArtistGenre(Artist $artist, Genre $genre, GenreWriter $genreWriter): RedirectResponse
    {
        $genreWriter->remove($artist, $genre);

        return back()->with('success', 'Artist genre removed.');
    }

    /**
     * Create a draft from albums selected by the user.
     *
     * @param  Request  $request  The selected album IDs.
     * @return RedirectResponse The recommendation review redirect.
     */
    public function createManualRecommendations(Request $request, AlbumCompletionService $completionService): RedirectResponse
    {
        $validated = $request->validate([
            'album_ids' => ['required', 'array', 'min:1'],
            'album_ids.*' => ['integer', 'distinct', 'exists:spotify_saved_albums,id'],
        ]);
        $albums = SpotifySavedAlbum::query()->whereIn('id', $validated['album_ids'])->get()->keyBy('id');
        $run = RecommendationRun::query()->create([
            'source' => 'spotify_saved_albums',
            'status' => 'draft',
            'generated_at' => now(),
        ]);

        foreach ($validated['album_ids'] as $position => $albumId) {
            $album = $albums->get($albumId);
            $progress = $completionService->progress($album);

            AlbumRecommendation::query()->create([
                'recommendation_run_id' => $run->id,
                'album_id' => $progress['canonical_id'],
                'spotify_saved_album_id' => $album->id,
                'position' => $position + 1,
                'heard_tracks' => $progress['heard'],
                'total_tracks' => $progress['total'],
                'completion_percentage' => $progress['percentage'],
            ]);
        }

        return back()->with('success', 'Manual album playlist draft created.');
    }

    /**
     * Append selected albums to an existing unsynced recommendation draft.
     *
     * @param  Request  $request  The selected album IDs.
     * @param  RecommendationRun  $recommendationRun  The draft being extended.
     * @return RedirectResponse The recommendation review redirect.
     */
    public function addAlbumsToRecommendations(Request $request, RecommendationRun $recommendationRun, AlbumCompletionService $completionService): RedirectResponse
    {
        if ($recommendationRun->spotify_playlist_id !== null || $recommendationRun->status === 'synced') {
            return back()->with('error', 'This recommendation draft has already been synced to Spotify.');
        }

        $validated = $request->validate([
            'album_ids' => ['required', 'array', 'min:1'],
            'album_ids.*' => ['integer', 'distinct', 'exists:spotify_saved_albums,id'],
        ]);
        $existingAlbumIds = $recommendationRun->albumRecommendations()->pluck('spotify_saved_album_id');
        $albums = SpotifySavedAlbum::query()
            ->whereIn('id', $validated['album_ids'])
            ->whereNotIn('id', $existingAlbumIds)
            ->get();

        if ($albums->count() !== count($validated['album_ids'])) {
            return back()->withErrors(['album_ids' => 'One or more selected albums are already in this draft.']);
        }

        $position = (int) ($recommendationRun->albumRecommendations()->max('position') ?? 0);

        DB::transaction(function () use ($albums, $position, $recommendationRun, $completionService): void {
            foreach ($albums as $offset => $album) {
                $progress = $completionService->progress($album);

                AlbumRecommendation::query()->create([
                    'recommendation_run_id' => $recommendationRun->id,
                    'album_id' => $progress['canonical_id'],
                    'spotify_saved_album_id' => $album->id,
                    'position' => $position + $offset + 1,
                    'heard_tracks' => $progress['heard'],
                    'total_tracks' => $progress['total'],
                    'completion_percentage' => $progress['percentage'],
                ]);
            }
        });

        return back()->with('success', 'Albums added to the recommendation draft.');
    }

    /**
     * Remove an album from an unsynced recommendation draft.
     *
     * @param  RecommendationRun  $recommendationRun  The draft being edited.
     * @param  AlbumRecommendation  $albumRecommendation  The recommendation to remove.
     * @return RedirectResponse The recommendation review redirect.
     */
    public function removeAlbumFromRecommendations(RecommendationRun $recommendationRun, AlbumRecommendation $albumRecommendation): RedirectResponse
    {
        if ($recommendationRun->spotify_playlist_id !== null || $recommendationRun->status === 'synced') {
            return back()->with('error', 'This recommendation draft has already been synced to Spotify.');
        }

        abort_unless($albumRecommendation->recommendation_run_id === $recommendationRun->id, 404);

        DB::transaction(function () use ($albumRecommendation, $recommendationRun): void {
            $albumRecommendation->delete();
            /**
             * @var \Illuminate\Database\Eloquent\Collection<int, AlbumRecommendation> $remaining
             */
            $remaining = $recommendationRun->albumRecommendations()->get();
            $offset = $remaining->count();

            foreach ($remaining as $recommendation) {
                $recommendation->update(['position' => $recommendation->position + $offset]);
            }

            foreach ($remaining->values() as $position => $recommendation) {
                $recommendation->update(['position' => $position + 1]);
            }
        });

        return back()->with('success', 'Album removed from the recommendation draft.');
    }

    /**
     * Save the reviewed order and playlist name for a recommendation draft.
     *
     * @param  Request  $request  The reviewed order and playlist name.
     * @param  RecommendationRun  $recommendationRun  The draft being reviewed.
     * @return RedirectResponse The recommendation review redirect.
     */
    public function reviewRecommendations(Request $request, RecommendationRun $recommendationRun): RedirectResponse
    {
        $validated = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['required', 'integer', 'min:1'],
            'included' => ['array'],
            'included.*' => ['integer'],
        ]);
        /**
         * @var Collection<int, AlbumRecommendation> $recommendations
         */
        $recommendations = $recommendationRun->albumRecommendations()->get();
        $positions = collect($validated['positions'])->mapWithKeys(fn (mixed $position, string $id): array => [(int) $id => (int) $position]);
        $recommendationIds = $recommendations->pluck('id')->map(fn (int|string $id): int => (int) $id)->all();

        if ($positions->keys()->sort()->values()->all() !== collect($recommendationIds)->sort()->values()->all()) {
            return back()->withErrors(['positions' => 'The recommendation list has changed. Reload the page and try again.']);
        }

        $positionValues = $positions->values();
        if ($positionValues->unique()->count() !== $recommendations->count() || $positionValues->min() !== 1 || $positionValues->max() !== $recommendations->count()) {
            return back()->withErrors(['positions' => 'Each album must have a unique position.']);
        }

        $includedIds = collect($validated['included'] ?? [])->map(fn (int $id): int => $id);
        if ($includedIds->diff($recommendationIds)->isNotEmpty()) {
            return back()->withErrors(['included' => 'The recommendation list has changed. Reload the page and try again.']);
        }

        DB::transaction(function () use ($includedIds, $positions, $recommendations): void {
            $offset = $recommendations->count();

            foreach ($recommendations as $recommendation) {
                $recommendation->update(['position' => $recommendation->position + $offset]);
            }

            foreach ($recommendations as $recommendation) {
                $recommendation->update([
                    'position' => $positions->get($recommendation->id),
                    'included' => $includedIds->contains($recommendation->id),
                ]);
            }
        });

        return back()->with('success', 'Recommendation draft review saved.');
    }

    /**
     * Manually mark a saved album as complete.
     *
     * @param  SpotifySavedAlbum  $album  The saved album being overridden.
     * @return RedirectResponse The recommendations redirect.
     */
    public function markAlbumComplete(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => true]);

        return back()->with('success', 'Album marked as heard.');
    }

    /**
     * Manually mark a saved album as incomplete.
     *
     * @param  SpotifySavedAlbum  $album  The saved album being overridden.
     * @return RedirectResponse The recommendations redirect.
     */
    public function markAlbumIncomplete(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => false]);

        return back()->with('success', 'Album marked as incomplete.');
    }

    /**
     * Remove a manual completion override and return to imported evidence.
     *
     * @param  SpotifySavedAlbum  $album  The saved album being reset.
     * @return RedirectResponse The recommendations redirect.
     */
    public function clearAlbumCompletionOverride(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => null]);

        return back()->with('success', 'Album completion override cleared.');
    }
}
