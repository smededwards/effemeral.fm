<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Services\Catalog\CatalogWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_spotify_track_enrichment_updates_an_existing_track_without_creating_a_duplicate(): void
    {
        $album = Album::query()->create([
            'title' => 'Les Chants de l’Aurore',
            'normalised_title' => 'les chants de laurore',
            'spotify_id' => 'spotify-album-id',
        ]);
        $track = $album->tracks()->create([
            'title' => 'Komorebi',
            'normalised_title' => 'komorebi',
        ]);

        app(CatalogWriter::class)->spotifyAlbumTracks($album, [[
            'id' => 'spotify-track-id',
            'name' => 'Komorebi',
            'uri' => 'spotify:track:spotify-track-id',
            'disc_number' => 1,
            'track_number' => 1,
            'duration_ms' => 399226,
        ]]);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertDatabaseHas('tracks', [
            'id' => $track->id,
            'spotify_id' => 'spotify-track-id',
            'spotify_uri' => 'spotify:track:spotify-track-id',
            'disc_number' => 1,
            'track_number' => 1,
            'duration_ms' => 399226,
        ]);
    }
}
