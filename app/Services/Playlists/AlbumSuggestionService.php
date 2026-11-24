<?php

namespace App\Services\Playlists;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Searches, ranks, and deduplicates albums available to an Effemeral playlist.
 */
class AlbumSuggestionService
{
    /**
     * Search for playable albums that are not already in the playlist.
     *
     * @param  AlbumPlaylist  $albumPlaylist  The playlist being built.
     * @param  string  $search  The album, artist, or genre search term.
     * @param  string  $sort  The requested sort order.
     * @return array{albums: Collection<int, Album>, artist_search: bool, sort: string} The suggestion data.
     */
    public function search(AlbumPlaylist $albumPlaylist, string $search, string $sort = ''): array
    {
        $search = trim($search);
        $normalisedSearch = mb_strtolower($search);
        $searchTerm = '%'.$normalisedSearch.'%';
        $artistSearch = $search !== '' && Artist::query()
            ->where('normalised_name', 'like', $searchTerm)
            ->exists();
        $sort = $this->normaliseSort($sort);
        $albumQuery = Album::query()
            ->with('artists')
            ->withCount('tracks')
            ->whereHas('tracks')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($albumQuery) use ($search): void {
                    $albumQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('artists', fn ($artistQuery) => $artistQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('artists.acceptedGenres', fn ($genreQuery) => $genreQuery->where('genres.name', 'like', '%'.$search.'%'));
                });
            })
            ->whereDoesntHave('playlists', fn ($query) => $query->whereKey($albumPlaylist->id));

        if ($search !== '') {
            $this->applySort($albumQuery, $sort, $artistSearch, $normalisedSearch, $searchTerm);
        }

        $albums = $search === ''
            ? collect()
            : $albumQuery
                // shortcut: inspect 150 candidates before deduplication; increase this if suggestions become sparse.
                ->limit(150)
                ->get()
                ->groupBy(fn (Album $album): string => $this->editionKey($album->title))
                ->map(fn ($variants): Album => $variants->sortBy(fn (Album $album): int => $this->editionPenalty($album->title))->first())
                ->take(50)
                ->values();

        return [
            'albums' => $albums,
            'artist_search' => $artistSearch,
            'sort' => $sort,
        ];
    }

    /**
     * Apply the selected album suggestion sort.
     *
     * @param  Builder<Album>  $query  The album query.
     * @param  string  $sort  The normalised sort order.
     * @param  bool  $artistSearch  Whether the term matches an artist.
     * @param  string  $normalisedSearch  The normalised search term.
     * @param  string  $searchTerm  The wildcard search term.
     */
    private function applySort(Builder $query, string $sort, bool $artistSearch, string $normalisedSearch, string $searchTerm): void
    {
        if ($sort === 'release_oldest') {
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

        $query->orderByRaw('CASE WHEN release_date IS NULL THEN 1 ELSE 0 END')
            ->when($artistSearch, fn ($query) => $query->orderBy('release_date'), fn ($query) => $query->orderByDesc('release_date'))
            ->orderByDesc('albums.created_at')
            ->orderBy('title');
    }

    /**
     * Resolve the supported album suggestion sort order.
     *
     * @param  string  $sort  The requested sort order.
     * @return string The normalised sort order.
     */
    private function normaliseSort(string $sort): string
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
}
