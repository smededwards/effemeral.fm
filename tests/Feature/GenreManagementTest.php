<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Genre;
use App\Services\Catalog\GenreWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_genre_changes_are_normalised_and_preserved_on_resync(): void
    {
        $artist = Artist::query()->create([
            'name' => 'An Artist',
            'normalised_name' => 'an artist',
            'spotify_id' => 'artist-id',
        ]);
        $writer = app(GenreWriter::class);

        $writer->syncSpotify($artist, ['post-metal', 'doom']);
        $this->post(route('artists.genres.update', $artist), ['genres' => 'post metal, sludge']);
        $writer->syncSpotify($artist, ['post metal', 'doom']);

        $artist->load('genres');
        $acceptedGenres = $artist->genres
            ->filter(fn (Genre $genre): bool => ! $genre->pivot->suppressed)
            ->unique('normalised_name')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['post-metal', 'sludge'], $acceptedGenres);
        $this->assertSame(1, (int) $artist->genres->firstWhere('normalised_name', 'doom')->pivot->suppressed);
    }
}
