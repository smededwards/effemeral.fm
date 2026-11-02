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

    public function test_lastfm_scrobbles_are_summarised_by_artist_and_album(): void
    {
        Scrobble::query()->create([
            'source_key' => 'first-scrobble',
            'artist_name' => 'An Artist',
            'track_name' => 'First Track',
            'album_name' => 'An Album',
            'listened_at' => '2026-01-01 12:00:00',
        ]);
        Scrobble::query()->create([
            'source_key' => 'second-scrobble',
            'artist_name' => 'An Artist',
            'track_name' => 'Second Track',
            'album_name' => 'An Album',
            'listened_at' => '2026-01-02 12:00:00',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertSee('Top artists');
        $response->assertSee('Top albums');
        $response->assertSee('An Artist');
        $response->assertSee('An Album');
        $response->assertSee('2 plays');
        $response->assertSee('Last heard 2026-01-02');
    }
}
