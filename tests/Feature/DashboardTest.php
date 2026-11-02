<?php

namespace Tests\Feature;

use App\Models\Scrobble;
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

    public function test_incomplete_saved_albums_show_lastfm_completion_progress(): void
    {
        SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'incomplete-album-id',
            'spotify_uri' => 'spotify:album:incomplete-album-id',
            'spotify_url' => 'https://open.spotify.com/album/incomplete-album-id',
            'name' => 'An Album',
            'album_type' => 'album',
            'total_tracks' => 3,
            'primary_artist_name' => 'An Artist',
            'tracks' => ['items' => [
                ['name' => 'Heard Track'],
                ['name' => 'Unheard Track'],
                ['name' => 'Another Unheard Track'],
            ]],
        ]);
        Scrobble::query()->create([
            'source_key' => 'heard-track',
            'artist_name' => 'An Artist',
            'track_name' => 'Heard Track',
            'album_name' => 'An Album',
            'listened_at' => '2026-01-02 12:00:00',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertSee('Albums to complete');
        $response->assertSee('1 / 3 tracks heard');
        $response->assertSee('An Album');
    }
}
