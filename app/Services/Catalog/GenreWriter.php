<?php

namespace App\Services\Catalog;

use App\Models\Artist;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GenreWriter
{
    /** @param list<string> $genres */
    public function syncSpotify(Artist $artist, array $genres): void
    {
        $this->syncImported($artist, $genres, 'spotify');
    }

    /** @param list<string> $genres */
    public function syncLastFm(Artist $artist, array $genres): void
    {
        $allowlist = config('services.lastfm.genre_allowlist', []);
        $allowedGenres = $this->normalisedGenres($allowlist);
        $genres = $allowlist === []
            ? $genres
            : array_filter($genres, fn (string $genre): bool => array_key_exists($this->normalise($genre), $allowedGenres));

        $this->syncImported($artist, $genres, 'lastfm');
    }

    /** @param list<string> $genres */
    private function syncImported(Artist $artist, array $genres, string $source): void
    {
        foreach ($this->filterAllowedGenres($genres) as $normalisedName => $name) {
            $genre = Genre::query()->firstOrCreate(
                ['normalised_name' => $normalisedName],
                ['name' => $name],
            );
            $artistGenre = DB::table('artist_genre')
                ->where('artist_id', $artist->id)
                ->where('genre_id', $genre->id)
                ->where('source', $source)
                ->exists();

            if (! $artistGenre) {
                DB::table('artist_genre')->insert([
                    'artist_id' => $artist->id,
                    'genre_id' => $genre->id,
                    'source' => $source,
                    'suppressed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** @param list<string> $genres */
    public function applyManualOverride(Artist $artist, array $genres): void
    {
        $normalisedGenres = $this->filterAllowedGenres($genres);
        /** @var Collection<int, Genre> $existingGenres */
        $existingGenres = $artist->genres()->get();
        $existing = $existingGenres->keyBy('normalised_name');

        foreach ($existing as $normalisedName => $genre) {
            if (! array_key_exists($normalisedName, $normalisedGenres)) {
                DB::table('artist_genre')
                    ->where('artist_id', $artist->id)
                    ->where('genre_id', $genre->id)
                    ->update(['suppressed' => true, 'updated_at' => now()]);
            }
        }

        foreach ($normalisedGenres as $normalisedName => $name) {
            $genre = Genre::query()->firstOrCreate(
                ['normalised_name' => $normalisedName],
                ['name' => $name],
            );
            $manualGenre = DB::table('artist_genre')
                ->where('artist_id', $artist->id)
                ->where('genre_id', $genre->id)
                ->where('source', 'manual')
                ->exists();

            if ($manualGenre) {
                DB::table('artist_genre')
                    ->where('artist_id', $artist->id)
                    ->where('genre_id', $genre->id)
                    ->where('source', 'manual')
                    ->update(['suppressed' => false, 'updated_at' => now()]);
            } else {
                DB::table('artist_genre')->insert([
                    'artist_id' => $artist->id,
                    'genre_id' => $genre->id,
                    'source' => 'manual',
                    'suppressed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('artist_genre')
                ->where('artist_id', $artist->id)
                ->where('genre_id', $genre->id)
                ->update(['suppressed' => false, 'updated_at' => now()]);
        }
    }

    /** @param array<mixed> $genres */
    private function normalisedGenres(array $genres): array
    {
        $normalisedGenres = [];

        foreach ($genres as $genre) {
            if (! is_string($genre) || trim($genre) === '') {
                continue;
            }

            $name = trim($genre);
            $normalisedName = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($name));
            $normalisedName = trim(preg_replace('/\s+/', ' ', (string) $normalisedName));

            if ($normalisedName !== '' && ! array_key_exists($normalisedName, $normalisedGenres)) {
                $normalisedGenres[$normalisedName] = $name;
            }
        }

        return $normalisedGenres;
    }

    /** @param list<string> $genres
     * @return array<string, string>
     */
    private function filterAllowedGenres(array $genres): array
    {
        return array_filter(
            $this->normalisedGenres($genres),
            fn (string $name, string $normalisedName): bool => Genre::isFilterableName($normalisedName),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function normalise(string $genre): string
    {
        $normalised = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($genre)));

        return trim(preg_replace('/\s+/', ' ', (string) $normalised));
    }
}
