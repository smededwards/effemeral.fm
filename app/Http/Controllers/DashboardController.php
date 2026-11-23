<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\AlbumRecommendation;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\LastFmImportRun;
use App\Models\RecommendationRun;
use App\Models\Scrobble;
use App\Models\SpotifyConnection;
use App\Models\SpotifyImportRun;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Models\SpotifyPlaylistItem;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use App\Services\Catalog\GenreWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
                ->withCount('albums')
                ->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('normalised_name', 'like', '%'.$this->normalise($search).'%');
                })
                ->orderBy('name')
                ->limit(25)
                ->get();
            $albums = Album::query()
                ->with('artists')
                ->withCount('tracks')
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
        } else {
            $artists = Artist::query()
                ->withCount('albums')
                ->orderBy('name')
                ->paginate(24);
        }

        $filters = $request->validate([
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'country' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'completion' => ['nullable', 'in:incomplete,partial,complete'],
            'include_non_albums' => ['nullable', 'boolean'],
        ]);
        $filteredAlbums = null;

        if ($request->hasAny(['filter', 'genre', 'country', 'year', 'completion', 'include_completed', 'include_non_albums'])) {
            $catalogueQuery = Album::query()
                ->withCount('tracks')
                ->selectSub(Scrobble::query()->selectRaw('COUNT(DISTINCT track_id)')
                    ->whereColumn('scrobbles.album_id', 'albums.id')
                    ->where('source', 'lastfm')->whereNotNull('track_id'), 'heard_tracks')
                ->selectSub(SpotifySavedAlbum::query()->select('completion_override')
                    ->whereColumn('spotify_saved_albums.spotify_album_id', 'albums.spotify_id')->limit(1), 'completion_override');

            if (! empty($filters['genre'])) {
                $catalogueQuery->whereHas('artists.acceptedGenres', fn ($query) => $query->whereKey($filters['genre']));
            }
            if (! empty($filters['country'])) {
                $catalogueQuery->whereHas('artists', fn ($query) => $query->where('country_code', $filters['country']));
            }
            if (! empty($filters['year'])) {
                $catalogueQuery->where('release_date', 'like', $filters['year'].'%');
            }
            if (empty($filters['include_non_albums'])) {
                $catalogueQuery->where(fn ($query) => $query->whereNull('album_type')->orWhere('album_type', 'album'));
            }

            $completionQuery = DB::query()
                ->fromSub($catalogueQuery, 'catalogue_albums')
                ->select('catalogue_albums.*')
                ->selectRaw("CASE
                    WHEN completion_override = 1 THEN 'complete'
                    WHEN completion_override = 0 THEN 'incomplete'
                    WHEN tracks_count > 0 AND (total_tracks IS NULL OR tracks_count = total_tracks) AND heard_tracks >= tracks_count THEN 'complete'
                    WHEN heard_tracks > 0 THEN 'partial'
                    ELSE 'incomplete'
                END AS completion_status");
            $albumQuery = Album::query()
                ->fromSub($completionQuery, 'albums')
                ->with(['artists.acceptedGenres', 'artists'])
                ->when(isset($filters['completion']), fn ($query) => $query->where('completion_status', $filters['completion']))
                ->when(! isset($filters['completion']) && ! $request->boolean('include_completed'), fn ($query) => $query->where('completion_status', '!=', 'complete'))
                ->orderByRaw("CASE
                    WHEN LOWER(title) LIKE '%anniversary%' THEN 3
                    WHEN LOWER(title) LIKE '%deluxe%' OR LOWER(title) LIKE '%expanded%' OR LOWER(title) LIKE '%special edition%' OR LOWER(title) LIKE '%bonus track%' THEN 2
                    WHEN LOWER(title) LIKE '%remaster%' THEN 1
                    ELSE 0
                END")
                ->orderBy('title');
            $filteredAlbums = $albumQuery->paginate(24)->withQueryString();
            $filteredAlbums->getCollection()->each(function (Album $album): void {
                $album->setAttribute('completion_summary', (int) $album->getAttribute('heard_tracks').' of '.($album->total_tracks ?? $album->tracks_count).' tracks heard');
            });
        }

        return view('catalogue.index', [
            'search' => $search,
            'artists' => $artists,
            'albums' => $albums,
            'filteredAlbums' => $filteredAlbums,
            'catalogueGenres' => Genre::query()->filterable()->whereHas('artists')->orderBy('normalised_name')->get(),
            'catalogueCountries' => Artist::query()->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code'),
            'albumPlaylists' => AlbumPlaylist::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'selectedArtist' => $artistId > 0
                ? Artist::query()->with(['albums' => fn ($query) => $query->withCount('tracks')->orderBy('title'), 'acceptedGenres'])->find($artistId)
                : null,
            'selectedAlbum' => $albumId > 0
                ? Album::query()->with('artists.acceptedGenres', 'tracks')->find($albumId)
                : null,
        ]);
    }

    public function discoverRecommendations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['nullable', 'integer', 'exists:artists,id'],
            'album_id' => ['nullable', 'integer', 'exists:albums,id'],
            'genre' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1900,2100'],
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
        $heardTrackIds = Scrobble::query()
            ->whereNotNull('track_id')
            ->pluck('track_id')
            ->flip();
        /** @var \Illuminate\Database\Eloquent\Collection<int, Album> $albums */
        $albums = Album::query()
            ->with(['artists.acceptedGenres', 'tracks'])
            ->where('release_date', 'like', $validated['year'].'%')
            ->whereHas('spotifyCollections', fn ($query) => $query->where('collection_type', 'saved'))
            ->get();
        $savedAlbums = SpotifySavedAlbum::query()
            ->whereIn('spotify_album_id', $albums->pluck('spotify_id')->filter())
            ->get()
            ->keyBy('spotify_album_id');

        $candidates = $albums
            ->map(function (Album $album) use ($contextArtistIds, $genre, $heardTrackIds, $savedAlbums, $validated): ?array {
                /** @var \Illuminate\Database\Eloquent\Collection<int, Artist> $artists */
                $artists = $album->artists;
                /** @var \Illuminate\Database\Eloquent\Collection<int, Track> $tracks */
                $tracks = $album->tracks;

                if ($artists->pluck('id')->intersect($contextArtistIds)->isNotEmpty()) {
                    return null;
                }

                $hasGenre = $artists
                    ->flatMap(fn (Artist $artist) => $artist->acceptedGenres->pluck('normalised_name'))
                    ->contains($genre);
                $savedAlbum = $savedAlbums->get($album->spotify_id);

                if (! $hasGenre || $savedAlbum === null || $savedAlbum->completion_override === true
                    || $tracks->isEmpty() || ($album->total_tracks !== null && $tracks->count() !== $album->total_tracks)) {
                    return null;
                }

                $heard = $tracks->filter(fn (Track $track): bool => $heardTrackIds->has($track->id))->count();
                if ($savedAlbum->completion_override !== false && $heard === $tracks->count()) {
                    return null;
                }

                return [
                    'album' => $album,
                    'saved_album' => $savedAlbum,
                    'heard' => $heard,
                    'total' => $tracks->count(),
                    'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                    'match_reason' => 'Genre: '.$validated['genre'].' · Release year: '.$validated['year'].($savedAlbum->completion_override === false ? ' · Manual incomplete override' : ''),
                ];
            })
            ->filter()
            ->sortBy(fn (array $candidate): string => sprintf('%03d-%s', $candidate['percentage'], $candidate['album']->title))
            ->take(10)
            ->values();

        if ($candidates->isEmpty()) {
            return back()->with('error', 'No incomplete saved albums matched that genre and year.');
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
                    'spotify_saved_album_id' => $candidate['saved_album']->id,
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

    public function discographyRecommendations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'include_non_albums' => ['nullable', 'boolean'],
        ]);
        $artist = Artist::query()->findOrFail($validated['artist_id']);
        $includeNonAlbums = (bool) ($validated['include_non_albums'] ?? false);
        /** @var \Illuminate\Database\Eloquent\Collection<int, Album> $albums */
        $albums = $artist->albums()
            ->with('tracks')
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
        $candidates = $albums->filter(fn (Album $album): bool => $album->tracks->pluck('spotify_uri')->contains(fn (mixed $uri): bool => is_string($uri) && str_starts_with($uri, 'spotify:track:'))
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
                /** @var Album|null $album */
                $album = $candidate['album'];
                /** @var SpotifySavedAlbum|null $savedAlbum */
                $savedAlbum = $candidate['saved_album'];
                $tracks = $album === null ? collect() : $album->tracks;
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

    public function imports(): View
    {
        return view('imports.index', $this->importsData());
    }

    public function library(): View
    {
        return view('library.index', $this->libraryData());
    }

    public function playlists(): View
    {
        $search = trim((string) request('playlist_search'));
        $playlists = SpotifyPlaylist::query()
            ->with(['items.album'])
            ->withCount([
                'items',
                'items as album_items_count' => fn ($query) => $query->whereNotNull('album_id'),
            ])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $playlists->getCollection()->transform(function (SpotifyPlaylist $playlist): SpotifyPlaylist {
            foreach ($this->playlistOverviewData($playlist) as $key => $value) {
                $playlist->setAttribute($key, $value);
            }

            return $playlist;
        });

        return view('playlists.index', [
            'albumPlaylists' => AlbumPlaylist::query()->withCount('albums')->orderBy('name')->get(),
            'playlists' => $playlists,
            'playlistSearch' => $search,
            'latestPlaylistImport' => SpotifyPlaylistImportRun::query()->latest()->first(),
            'failedPlaylists' => SpotifyPlaylist::query()->whereNotNull('last_import_error')->latest('last_import_attempted_at')->get(),
        ]);
    }

    /** @return array{import_status: string, album_status: string, complete_album_count: int} */
    private function playlistOverviewData(SpotifyPlaylist $playlist): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, SpotifyPlaylistItem> $items */
        $items = $playlist->items;
        $albumGroups = $items
            ->filter(fn (SpotifyPlaylistItem $item): bool => $item->album !== null)
            ->groupBy('album_id');
        $completeAlbumCount = $albumGroups->filter(function (Collection $items): bool {
            $firstItem = $items->first();

            if (! $firstItem instanceof SpotifyPlaylistItem) {
                return false;
            }

            /** @var Album|null $album */
            $album = $firstItem->album;

            if ($album === null || $album->total_tracks === null) {
                return false;
            }

            return $items
                ->map(fn (SpotifyPlaylistItem $item): ?string => $item->spotify_track_id ?? ($item->track_id === null ? null : (string) $item->track_id))
                ->filter()
                ->unique()
                ->count() >= $album->total_tracks;
        })->count();

        return [
            'import_status' => $playlist->last_import_error !== null
                ? 'failed'
                : ($playlist->last_synced_at === null
                    ? 'pending'
                    : ($playlist->items_count === 0
                        ? 'empty'
                        : ((int) $playlist->getAttribute('items_count') < $playlist->tracks_total ? 'partial' : 'imported'))),
            'album_status' => $completeAlbumCount > 0
                ? 'complete'
                : ((int) $playlist->getAttribute('album_items_count') > 0 ? 'partial' : 'none'),
            'complete_album_count' => $completeAlbumCount,
        ];
    }

    public function recommendations(): View
    {
        return view('recommendations.index', $this->recommendationsData());
    }

    /** @return array<string, mixed> */
    private function importsData(): array
    {
        return [
            'latestImport' => LastFmImportRun::query()->latest()->first(),
            'recentImports' => LastFmImportRun::query()->latest()->limit(10)->get(),
            'latestSpotifyImport' => SpotifyImportRun::query()->latest()->first(),
            'latestSpotifyPlaylistImport' => SpotifyPlaylistImportRun::query()->latest()->first(),
            'spotifyConnection' => SpotifyConnection::query()->latest()->first(),
            'scrobbleCount' => Scrobble::query()->count(),
            'defaultFrom' => now()->subDays(90)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function libraryData(): array
    {
        $librarySearch = trim((string) request('library_search'));
        $savedAlbums = SpotifySavedAlbum::query()->latest('saved_at');

        if ($librarySearch !== '') {
            $savedAlbums->where(function ($query) use ($librarySearch): void {
                $query->where('name', 'like', '%'.$librarySearch.'%')
                    ->orWhere('primary_artist_name', 'like', '%'.$librarySearch.'%');
            });
        }

        $spotifySavedAlbums = $savedAlbums->limit(24)->get();
        $canonicalAlbums = Album::query()
            ->whereIn('spotify_id', $spotifySavedAlbums->pluck('spotify_album_id'))
            ->get(['id', 'spotify_id'])
            ->keyBy('spotify_id');
        $canonicalArtists = Artist::query()
            ->whereIn('spotify_id', $spotifySavedAlbums->pluck('primary_artist_id')->filter())
            ->get(['id', 'spotify_id'])
            ->keyBy('spotify_id');

        foreach ($spotifySavedAlbums as $savedAlbum) {
            $savedAlbum->setAttribute('canonical_album_id', $canonicalAlbums->get($savedAlbum->spotify_album_id)?->id);
            $savedAlbum->setAttribute('canonical_artist_id', $canonicalArtists->get($savedAlbum->primary_artist_id)?->id);
        }

        return [
            'librarySearch' => $librarySearch,
            'spotifyPlaylists' => SpotifyPlaylist::query()->with('items.album')->latest('last_synced_at')->limit(24)->get(),
            'spotifySavedAlbums' => $spotifySavedAlbums,
            'spotifySavedAlbumCount' => SpotifySavedAlbum::query()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function recommendationsData(): array
    {
        return [
            'incompleteAlbums' => $this->incompleteAlbums(),
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
}
