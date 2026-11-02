<?php

namespace Tests\Feature;

use App\Models\SpotifySavedAlbum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_spotify_albums_are_shown_on_the_dashboard(): void
    {
        SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'album-id',
            'spotify_uri' => 'spotify:album:album-id',
            'spotify_url' => 'https://open.spotify.com/album/album-id',
            'name' => 'A Saved Album',
            'album_type' => 'album',
            'primary_artist_name' => 'An Artist',
            'release_date' => '2020-01-01',
            'saved_at' => now(),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Imported Spotify albums');
        $response->assertSee('A Saved Album');
        $response->assertSee('An Artist');
        $response->assertSee('https://open.spotify.com/album/album-id');
    }
}
