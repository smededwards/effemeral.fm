<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartLastFmImportRequest;
use App\Jobs\ImportLastFmScrobbles;
use App\Jobs\ImportSpotifyPlaylists;
use App\Jobs\ImportSpotifySavedAlbums;
use App\Models\Album;
use App\Models\AlbumRecommendation;
use App\Models\Artist;
use App\Models\LastFmImportRun;
use App\Models\RecommendationRun;
use App\Models\Scrobble;
use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifyConnection;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use App\Services\Catalog\GenreWriter;
use App\Services\Spotify\SpotifyClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;
use Throwable;

class DashboardController extends Controller
{
    public function catalogue(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $artistId = $request->integer('artist');
        $albumId = $request->integer('album');
        $artists = collect();
        $albums = collect();

        if ($search !== '') {
            $artists = Artist::query()
                ->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('normalised_name', 'like', '%'.$this->normalise($search).'%');
                })
                ->orderBy('name')
                ->limit(25)
                ->get();
            $albums = Album::query()
                ->with('artists')
                ->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('normalised_title', 'like', '%'.$this->normalise($search).'%')
                        ->orWhereHas('artists', function ($artistQuery) use ($search): void {
                            $artistQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('normalised_name', 'like', '%'.$this->normalise($search).'%');
                        });
                })
                ->orderBy('title')
                ->limit(25)
                ->get();
        }

        return view('catalogue.index', [
            'search' => $search,
            'artists' => $artists,
            'albums' => $albums,
            'selectedArtist' => $artistId > 0
                ? Artist::query()->with(['albums' => fn ($query) => $query->orderBy('title')])->find($artistId)
                : null,
            'selectedAlbum' => $albumId > 0
                ? Album::query()->with('artists', 'tracks')->find($albumId)
                : null,
        ]);
    }

    public function index(): View
    {
        $incompleteAlbums = $this->incompleteAlbums();
        $latestRecommendation = RecommendationRun::query()
            ->with('albumRecommendations.album', 'albumRecommendations.spotifySavedAlbum')
            ->latest()
            ->first();
        $albumSearch = trim((string) request('album_search'));
        $albumArtist = trim((string) request('album_artist'));
        $spotifySavedAlbumQuery = SpotifySavedAlbum::query()->latest('saved_at');

        if ($albumSearch !== '') {
            $spotifySavedAlbumQuery->where(function ($query) use ($albumSearch): void {
                $query->where('name', 'like', '%'.$albumSearch.'%')
                    ->orWhere('primary_artist_name', 'like', '%'.$albumSearch.'%');
            });
        }

        if ($albumArtist !== '') {
            $spotifySavedAlbumQuery->where('primary_artist_name', $albumArtist);
        }

        $spotifySavedAlbums = $spotifySavedAlbumQuery->get();
        $artists = Scrobble::query()
            ->select('artist_name')
            ->selectRaw('COUNT(*) as scrobble_count')
            ->selectRaw('MAX(listened_at) as latest_listened_at')
            ->groupBy('artist_name')
            ->orderByDesc('scrobble_count')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn (stdClass $artist): array => [
                'name' => (string) $artist->artist_name,
                'scrobbles' => (int) $artist->scrobble_count,
                'last_listened_at' => CarbonImmutable::parse((string) $artist->latest_listened_at)->toDateString(),
            ]);

        $albums = Scrobble::query()
            ->select('artist_name', 'album_name')
            ->selectRaw('COUNT(*) as scrobble_count')
            ->selectRaw('MAX(listened_at) as latest_listened_at')
            ->whereNotNull('album_name')
            ->where('album_name', '<>', '')
            ->groupBy('artist_name', 'album_name')
            ->orderByDesc('scrobble_count')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn (stdClass $album): array => [
                'artist' => (string) $album->artist_name,
                'name' => (string) $album->album_name,
                'scrobbles' => (int) $album->scrobble_count,
                'last_listened_at' => CarbonImmutable::parse((string) $album->latest_listened_at)->toDateString(),
            ]);

        return view('dashboard', [
            'latestImport' => LastFmImportRun::query()->latest()->first(),
            'recentImports' => LastFmImportRun::query()->latest()->limit(10)->get(),
            'latestSpotifyImport' => SpotifyAlbumImportRun::query()->latest()->first(),
            'latestSpotifyPlaylistImport' => SpotifyPlaylistImportRun::query()->latest()->first(),
            'spotifyPlaylists' => SpotifyPlaylist::query()->with('items.album')->latest('last_synced_at')->get(),
            'spotifyConnection' => SpotifyConnection::query()->latest()->first(),
            'spotifySavedAlbums' => $spotifySavedAlbums,
            'spotifySavedAlbumCount' => SpotifySavedAlbum::query()->count(),
            'spotifyAlbumArtists' => SpotifySavedAlbum::query()
                ->whereNotNull('primary_artist_name')
                ->where('primary_artist_name', '<>', '')
                ->distinct()
                ->orderBy('primary_artist_name')
                ->pluck('primary_artist_name'),
            'albumSearch' => $albumSearch,
            'albumArtist' => $albumArtist,
            'incompleteAlbums' => $incompleteAlbums,
            'topArtists' => $artists,
            'topAlbums' => $albums,
            'genreArtists' => Artist::query()->with('genres')->whereHas('genres')->orderBy('name')->get(),
            'latestRecommendation' => $latestRecommendation,
            'scrobbleCount' => Scrobble::query()->count(),
            'defaultFrom' => now()->subDays(90)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function incompleteAlbums(): Collection
    {
        $legacyAlbums = SpotifySavedAlbum::query()->get()->keyBy('spotify_album_id');
        $heardTrackIds = Scrobble::query()
            ->whereNotNull('track_id')
            ->pluck('track_id')
            ->flip();
        $canonicalAlbums = Album::query()
            ->with(['artists', 'tracks', 'spotifyCollections'])
            ->whereHas('spotifyCollections', fn ($query) => $query->where('collection_type', 'saved'))
            ->get();

        if ($canonicalAlbums->isNotEmpty()) {
            /** @var Collection<int, array<string, mixed>> $incompleteAlbums */
            $incompleteAlbums = $canonicalAlbums
                ->map(function (Album $album) use ($heardTrackIds, $legacyAlbums): ?array {
                    /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
                    $tracks = $album->getRelation('tracks');
                    /** @var \Illuminate\Database\Eloquent\Collection<int, Artist> $artists */
                    $artists = $album->getRelation('artists');
                    $legacyAlbum = $legacyAlbums->get($album->spotify_id);
                    $manualOverride = $legacyAlbum?->completion_override;

                    if ($manualOverride === true || $tracks->isEmpty() || ($album->total_tracks !== null && $tracks->count() !== $album->total_tracks)) {
                        return null;
                    }

                    $heard = $tracks->filter(fn ($track): bool => $heardTrackIds->has($track->id))->count();
                    if ($manualOverride !== false && $heard === $tracks->count()) {
                        return null;
                    }

                    return [
                        'id' => $legacyAlbum?->id,
                        'canonical_id' => $album->id,
                        'artist' => $artists->sortBy('pivot.artist_position')->first()->name ?? 'Unknown artist',
                        'name' => $album->title,
                        'heard' => $heard,
                        'total' => $tracks->count(),
                        'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                        'spotify_url' => $album->spotify_url,
                        'cover_image_url' => $album->cover_image_url,
                        'manual_override' => $manualOverride !== null,
                    ];
                })
                ->filter(fn (?array $album): bool => $album !== null && $album['id'] !== null)
                ->sortBy('percentage')
                ->take(10)
                ->values();

            return $incompleteAlbums;
        }

        $heardTracks = [];

        foreach (Scrobble::query()->whereNotNull('album_name')->get(['artist_name', 'album_name', 'track_name']) as $scrobble) {
            $albumKey = $this->normalise($scrobble->artist_name).'|'.$this->normalise((string) $scrobble->album_name);
            $heardTracks[$albumKey][$this->normalise($scrobble->track_name)] = true;
        }

        /** @var Collection<int, array<string, mixed>> $incompleteAlbums */
        $incompleteAlbums = SpotifySavedAlbum::query()
            ->latest('saved_at')
            ->get()
            ->map(function (SpotifySavedAlbum $album) use ($heardTracks): ?array {
                $tracks = collect(data_get($album->tracks, 'items', []))
                    ->filter(fn (mixed $track): bool => is_array($track) && is_string($track['name'] ?? null))
                    ->values();

                // Albums with partial tracklists are deferred until Spotify track pagination exists.
                if ($album->completion_override === true || $tracks->isEmpty() || $tracks->count() !== $album->total_tracks) {
                    return null;
                }

                $albumKey = $this->normalise((string) $album->primary_artist_name).'|'.$this->normalise($album->name);
                $heard = $tracks
                    ->filter(fn (array $track): bool => isset($heardTracks[$albumKey][$this->normalise($track['name'])]))
                    ->count();

                if ($album->completion_override !== false && $heard === $tracks->count()) {
                    return null;
                }

                return [
                    'id' => $album->id,
                    'canonical_id' => null,
                    'artist' => $album->primary_artist_name ?? 'Unknown artist',
                    'name' => $album->name,
                    'heard' => $heard,
                    'total' => $tracks->count(),
                    'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                    'spotify_url' => $album->spotify_url,
                    'cover_image_url' => $album->cover_image_url,
                    'manual_override' => $album->completion_override !== null,
                ];
            })
            ->filter()
            ->sortBy('percentage')
            ->take(10)
            ->values();

        return $incompleteAlbums;
    }

    /**
     * @return array{canonical_id: int|null, heard: int, total: int, percentage: int}
     */
    private function recommendationProgress(SpotifySavedAlbum $savedAlbum): array
    {
        $canonicalAlbum = Album::query()->with('tracks')->where('spotify_id', $savedAlbum->spotify_album_id)->first();

        if ($canonicalAlbum !== null && $canonicalAlbum->tracks->isNotEmpty()) {
            $heardTrackIds = Scrobble::query()
                ->whereNotNull('track_id')
                ->pluck('track_id')
                ->flip();
            /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
            $tracks = $canonicalAlbum->getRelation('tracks');
            $total = $tracks->count();
            $heard = $tracks->filter(fn (Track $track): bool => $heardTrackIds->has($track->id))->count();

            return [
                'canonical_id' => $canonicalAlbum->id,
                'heard' => $heard,
                'total' => $total,
                'percentage' => (int) floor(($heard / $total) * 100),
            ];
        }

        $tracks = collect(data_get($savedAlbum->tracks, 'items', []))
            ->filter(fn (mixed $track): bool => is_array($track) && is_string($track['name'] ?? null))
            ->values();
        $heardTracks = Scrobble::query()
            ->where('artist_name', $savedAlbum->primary_artist_name)
            ->where('album_name', $savedAlbum->name)
            ->pluck('track_name')
            ->map(fn (string $track): string => $this->normalise($track))
            ->flip();
        $total = $tracks->count();
        $heard = $tracks->filter(fn (array $track): bool => $heardTracks->has($this->normalise($track['name'])))->count();

        return [
            'canonical_id' => null,
            'heard' => $heard,
            'total' => $total,
            'percentage' => $total > 0 ? (int) floor(($heard / $total) * 100) : 0,
        ];
    }

    public function generateRecommendations(): RedirectResponse
    {
        $albums = $this->incompleteAlbums();

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

    public function updateArtistGenres(Request $request, Artist $artist, GenreWriter $genreWriter): RedirectResponse
    {
        $validated = $request->validate(['genres' => ['nullable', 'string', 'max:2000']]);
        $genres = preg_split('/[,\n]+/', (string) ($validated['genres'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        $genreWriter->applyManualOverride($artist, array_map('trim', $genres ?: []));

        return back()->with('success', 'Artist genres updated.');
    }

    public function createManualRecommendations(Request $request): RedirectResponse
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
            $progress = $this->recommendationProgress($album);

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

    public function recommendationReview(RecommendationRun $recommendationRun): View
    {
        /** @var Collection<int, AlbumRecommendation> $recommendations */
        $recommendations = $recommendationRun->albumRecommendations()
            ->with('album.tracks', 'spotifySavedAlbum')
            ->get();
        $reviewAlbums = $recommendations->map(function (AlbumRecommendation $recommendation): array {
            return [
                'recommendation' => $recommendation,
                'track_count' => count($this->recommendationTrackUris($recommendation)),
            ];
        });

        return view('recommendations.review', [
            'recommendationRun' => $recommendationRun,
            'reviewAlbums' => $reviewAlbums,
            'includedAlbums' => $reviewAlbums->where('recommendation.included', true)->count(),
            'excludedAlbums' => $reviewAlbums->where('recommendation.included', false)->count(),
            'includedTracks' => $reviewAlbums
                ->where('recommendation.included', true)
                ->sum('track_count'),
        ]);
    }

    public function playlistAudit(): View
    {
        $recommendationRuns = RecommendationRun::query()->latest('generated_at')->get();
        $selectedRun = $recommendationRuns->firstWhere('id', (int) request('run'));
        $auditAlbums = collect();

        if ($selectedRun !== null) {
            /** @var Collection<int, AlbumRecommendation> $recommendations */
            $recommendations = $selectedRun->albumRecommendations()
                ->with('album.tracks', 'spotifySavedAlbum')
                ->get();
            $playlists = SpotifyPlaylist::query()->with('items')->latest('last_synced_at')->get();
            $auditAlbums = $recommendations->map(function (AlbumRecommendation $recommendation) use ($playlists): array {
                $expectedUris = $this->recommendationTrackUris($recommendation);
                $coveredUris = collect();
                $playlistMatches = collect();

                foreach ($playlists as $playlist) {
                    $playlistUris = $playlist->items
                        ->pluck('spotify_uri')
                        ->filter(fn (mixed $uri): bool => is_string($uri))
                        ->unique();
                    $matches = $playlistUris->intersect($expectedUris)->values();

                    if ($matches->isNotEmpty()) {
                        $coveredUris = $coveredUris->merge($matches);
                        $playlistMatches->push([
                            'name' => $playlist->name,
                            'url' => $playlist->spotify_url,
                            'tracks' => $matches->count(),
                        ]);
                    }
                }

                $coveredCount = $coveredUris->unique()->count();
                $status = $expectedUris === [] ? 'unavailable' : match (true) {
                    $coveredCount === count($expectedUris) => 'complete',
                    $coveredCount > 0 => 'partial',
                    default => 'missing',
                };

                return [
                    'recommendation' => $recommendation,
                    'status' => $status,
                    'expected_tracks' => count($expectedUris),
                    'covered_tracks' => $coveredCount,
                    'missing_tracks' => max(0, count($expectedUris) - $coveredCount),
                    'playlists' => $playlistMatches,
                ];
            });
        }

        return view('recommendations.audit', [
            'recommendationRuns' => $recommendationRuns,
            'selectedRun' => $selectedRun,
            'auditAlbums' => $auditAlbums,
            'completeAlbums' => $auditAlbums->where('status', 'complete')->count(),
            'partialAlbums' => $auditAlbums->where('status', 'partial')->count(),
            'missingAlbums' => $auditAlbums->where('status', 'missing')->count(),
        ]);
    }

    public function syncRecommendations(Request $request, RecommendationRun $recommendationRun, SpotifyClient $client): RedirectResponse
    {
        if ($recommendationRun->status === 'synced') {
            return back()->with('error', 'This recommendation draft has already been synced to Spotify.');
        }

        if ($recommendationRun->status === 'syncing') {
            return back()->with('error', 'This recommendation draft is already syncing to Spotify.');
        }

        if ($request->boolean('confirmed') !== true) {
            return back()->with('error', 'Confirm the reviewed albums before syncing to Spotify.');
        }

        /** @var Collection<int, AlbumRecommendation> $recommendations */
        $recommendations = $recommendationRun->albumRecommendations()
            ->with('album.tracks', 'spotifySavedAlbum')
            ->where('included', true)
            ->get();
        $trackUris = [];

        foreach ($recommendations as $recommendation) {
            $trackUris = [...$trackUris, ...$this->recommendationTrackUris($recommendation)];
        }
        $trackUris = array_values(array_unique($trackUris));

        if ($trackUris === []) {
            return back()->with('error', 'No Spotify tracks are available in the included albums.');
        }

        $recommendationRun->update(['status' => 'syncing', 'sync_error' => null]);

        try {
            $playlistId = $recommendationRun->spotify_playlist_id;

            if ($playlistId === null) {
                $playlist = $client->createPrivatePlaylist(
                    'Effemeral recommendations - '.now()->toDateString(),
                    'Reviewed album recommendations generated by Effemeral.',
                );
                $playlistId = $playlist['id'];
                $recommendationRun->update([
                    'spotify_playlist_id' => $playlistId,
                    'spotify_playlist_url' => $playlist['external_urls']['spotify'] ?? null,
                ]);
            }

            $existingTrackUris = collect($client->playlistItems($playlistId))
                ->map(fn (array $item): mixed => data_get($item, 'track.uri'))
                ->filter(fn (mixed $uri): bool => is_string($uri))
                ->all();
            $trackUrisToAdd = array_values(array_diff($trackUris, $existingTrackUris));

            if ($trackUrisToAdd !== []) {
                $client->addPlaylistItems($playlistId, $trackUrisToAdd);
            }
            $recommendationRun->update(['status' => 'synced', 'synced_at' => now()]);
        } catch (Throwable $exception) {
            $recommendationRun->update(['status' => 'sync_failed', 'sync_error' => $exception->getMessage()]);

            return back()->with('error', 'Spotify playlist sync failed. Check the recommendation status.');
        }

        return back()->with('success', 'Recommendation draft synced to Spotify.');
    }

    /** @return list<string> */
    private function recommendationTrackUris(AlbumRecommendation $recommendation): array
    {
        /** @var Album|null $album */
        $album = $recommendation->getRelation('album');
        /** @var SpotifySavedAlbum|null $legacyAlbum */
        $legacyAlbum = $recommendation->getRelation('spotifySavedAlbum');

        if ($album !== null && $album->getRelation('tracks')->isNotEmpty()) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
            $tracks = $album->getRelation('tracks');

            return $tracks
                ->pluck('spotify_uri')
                ->filter(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
                ->values()
                ->all();
        }

        if ($legacyAlbum === null) {
            return [];
        }

        return collect(data_get($legacyAlbum->tracks, 'items', []))
            ->map(fn (mixed $track): mixed => is_array($track) ? ($track['uri'] ?? null) : null)
            ->filter(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
            ->values()
            ->all();
    }

    public function addAlbumsToRecommendations(Request $request, RecommendationRun $recommendationRun): RedirectResponse
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

        DB::transaction(function () use ($albums, $position, $recommendationRun): void {
            foreach ($albums as $offset => $album) {
                $progress = $this->recommendationProgress($album);

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

    public function removeAlbumFromRecommendations(RecommendationRun $recommendationRun, AlbumRecommendation $albumRecommendation): RedirectResponse
    {
        if ($recommendationRun->spotify_playlist_id !== null || $recommendationRun->status === 'synced') {
            return back()->with('error', 'This recommendation draft has already been synced to Spotify.');
        }

        abort_unless($albumRecommendation->recommendation_run_id === $recommendationRun->id, 404);

        DB::transaction(function () use ($albumRecommendation, $recommendationRun): void {
            $albumRecommendation->delete();
            /** @var \Illuminate\Database\Eloquent\Collection<int, AlbumRecommendation> $remaining */
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

    public function reviewRecommendations(Request $request, RecommendationRun $recommendationRun): RedirectResponse
    {
        $validated = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['required', 'integer', 'min:1'],
            'included' => ['array'],
            'included.*' => ['integer'],
        ]);
        /** @var Collection<int, AlbumRecommendation> $recommendations */
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

    public function markAlbumComplete(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => true]);

        return back()->with('success', 'Album marked as heard.');
    }

    public function markAlbumIncomplete(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => false]);

        return back()->with('success', 'Album marked as incomplete.');
    }

    public function clearAlbumCompletionOverride(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => null]);

        return back()->with('success', 'Album completion override cleared.');
    }

    public function import(StartLastFmImportRequest $request): RedirectResponse
    {
        return $this->queueImport(
            CarbonImmutable::parse($request->date('from'))->startOfDay(),
            CarbonImmutable::parse($request->date('to'))->endOfDay(),
            $request->integer('limit'),
            'Last.fm import queued.',
        );
    }

    public function fullImport(): RedirectResponse
    {
        return $this->queueImport(
            CarbonImmutable::parse(config('services.lastfm.import_start'))->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
            null,
            'Full Last.fm import queued.',
        );
    }

    public function retry(LastFmImportRun $importRun): RedirectResponse
    {
        if ($importRun->status !== 'failed') {
            return back()->with('error', 'Only failed imports can be retried.');
        }

        return $this->queueImport(
            CarbonImmutable::parse((string) $importRun->getRawOriginal('from_at')),
            CarbonImmutable::parse((string) $importRun->getRawOriginal('to_at')),
            $importRun->requested_limit === null ? null : (int) $importRun->requested_limit,
            'Last.fm import retry queued.',
            $importRun->id,
        );
    }

    public function spotifyAlbumsImport(): RedirectResponse
    {
        $activeImport = SpotifyAlbumImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($activeImport) {
            return back()->with('error', 'A Spotify album import is already in progress.');
        }

        $run = SpotifyAlbumImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 100,
        ]);

        ImportSpotifySavedAlbums::dispatch($run->id);

        return back()->with('success', 'Spotify saved albums import queued.');
    }

    public function spotifyPlaylistsImport(): RedirectResponse
    {
        $activeImport = SpotifyPlaylistImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($activeImport) {
            return back()->with('error', 'A Spotify playlist import is already in progress.');
        }

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 10,
        ]);

        ImportSpotifyPlaylists::dispatch($run->id);

        return back()->with('success', 'Spotify playlists import queued.');
    }

    private function queueImport(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $limit,
        string $successMessage,
        ?int $retryOfId = null,
    ): RedirectResponse {
        $activeImport = LastFmImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($activeImport) {
            return back()->with('error', 'A Last.fm import is already in progress.');
        }

        $run = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'queued',
            'from_at' => $from,
            'to_at' => $to,
            'requested_limit' => $limit,
            'retry_of_id' => $retryOfId,
        ]);

        ImportLastFmScrobbles::dispatch($run->id);

        return back()->with('success', $successMessage);
    }
}
