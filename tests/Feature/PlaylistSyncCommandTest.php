<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Verifies the explicit-confirmation playlist sync command.
 */
class PlaylistSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Preview the playlist without changing Spotify when confirmation is absent.
     */
    public function test_playlist_sync_command_previews_without_confirmation(): void
    {
        $playlist = $this->createCompletePlaylist();

        $this->mock(SpotifyClient::class);
        $exitCode = Artisan::call('playlists:sync', ['playlist-id' => $playlist->id]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Preview only', Artisan::output());
        $this->assertNull($playlist->fresh()->spotify_playlist_id);
    }

    /**
     * Apply a confirmed playlist sync to a newly created Spotify playlist.
     */
    public function test_playlist_sync_command_requires_confirmation_before_writing(): void
    {
        $playlist = $this->createCompletePlaylist();

        $this->mock(SpotifyClient::class, function ($mock): void {
            $mock->shouldReceive('createPrivatePlaylist')
                ->once()
                ->andReturn([
                    'id' => 'spotify-playlist-id',
                    'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/spotify-playlist-id'],
                ]);
            $mock->shouldReceive('addPlaylistItems')
                ->once()
                ->with('spotify-playlist-id', ['spotify:track:track-id']);
        });

        $exitCode = Artisan::call('playlists:sync', [
            'playlist-id' => $playlist->id,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('spotify-playlist-id', $playlist->fresh()->spotify_playlist_id);
        $this->assertSame('synced', $playlist->fresh()->spotify_sync_status);
    }

    /**
     * Build a local playlist containing one complete Spotify-linked album.
     */
    private function createCompletePlaylist(): AlbumPlaylist
    {
        $album = Album::query()->create([
            'title' => 'Blue Record',
            'normalised_title' => 'blue record',
            'total_tracks' => 1,
        ]);
        $album->tracks()->create([
            'title' => 'Track One',
            'normalised_title' => 'track one',
            'spotify_id' => 'track-id',
            'spotify_uri' => 'spotify:track:track-id',
            'disc_number' => 1,
            'track_number' => 1,
        ]);
        $playlist = AlbumPlaylist::query()->create(['name' => 'Blue Hour']);
        $playlist->albums()->attach($album->id, ['position' => 1]);

        return $playlist;
    }
}
