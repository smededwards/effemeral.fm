<?php

namespace App\Services\Catalog;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Scrobble;
use App\Models\SpotifySavedAlbum;
use App\Services\Recommendations\RecommendationListeningWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Builds catalogue search, filter, and selection data for the catalogue pages.
 */
class CataloguePageService
{
    /**
     * @param  RecommendationListeningWindow  $recommendationListeningWindow  The listening-window resolver.
     */
    public function __construct(private RecommendationListeningWindow $recommendationListeningWindow) {}

    /**
     * @return Collection<int, Genre> The filterable catalogue genres.
     */
    public function genres(): Collection
    {
        return Genre::query()
            ->filterable()
            ->whereHas('artists', fn ($query) => $query->where('artist_genre.suppressed', false))
            ->withCount(['artists' => fn ($query) => $query->where('artist_genre.suppressed', false)])
            ->orderBy('normalised_name')
            ->get();
    }

    /**
     * @return array<string, mixed> The data required by the catalogue view.
     */
    public function data(Request $request): array
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
                ->withCount('releaseTracks as tracks_count')
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
            $artists = Artist::query()->withCount('albums')->orderBy('name')->paginate(24);
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
                ->withCount('releaseTracks as tracks_count')
                ->whereHas('releaseTracks')
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

        return [
            'search' => $search,
            'artists' => $artists,
            'albums' => $albums,
            'filteredAlbums' => $filteredAlbums,
            'catalogueGenres' => $this->genres(),
            'catalogueCountries' => Artist::query()->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code'),
            'albumPlaylists' => AlbumPlaylist::query()->orderBy('name')->get(['id', 'name']),
            'recommendationListeningWindows' => $this->recommendationListeningWindow->options(),
            'filters' => $filters,
            'selectedArtist' => $artistId > 0
                ? Artist::query()->with(['albums' => fn ($query) => $query->withCount('releaseTracks as tracks_count')->orderBy('title'), 'acceptedGenres'])->find($artistId)
                : null,
            'selectedAlbum' => $albumId > 0
                ? Album::query()->with('artists.acceptedGenres', 'releaseTracks')->find($albumId)
                : null,
        ];
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
