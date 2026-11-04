<?php

namespace Tests\Feature;

use App\Jobs\ImportSpotifyPlaylists;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotifyPlaylistImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_playlist_import_can_be_queued(): void
    {
        Queue::fake();

        $response = $this->post(route('imports.spotify.playlists'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Spotify playlists import queued.');
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'status' => 'queued',
            'requested_limit' => 10,
        ]);
        Queue::assertPushed(ImportSpotifyPlaylists::class);
    }

    public function test_playlists_and_items_are_imported_idempotently(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/me/playlists?limit=10&offset=0')) {
                return Http::response([
                    'next' => null,
                    'items' => [[
                        'id' => 'playlist-id',
                        'name' => 'A Playlist',
                        'description' => 'Albums to audit',
                        'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/playlist-id'],
                        'owner' => ['id' => 'owner-id'],
                        'public' => false,
                        'collaborative' => false,
                        'tracks' => ['total' => 1],
                        'snapshot_id' => 'snapshot-1',
                    ]],
                ]);
            }

            return Http::response([
                'next' => null,
                'items' => [[
                    'added_at' => '2026-11-04T12:00:00Z',
                    'is_local' => false,
                    'track' => [
                        'id' => 'track-id',
                        'uri' => 'spotify:track:track-id',
                        'name' => 'A Track',
                        'album' => [
                            'id' => 'album-id',
                            'name' => 'An Album',
                            'album_type' => 'album',
                            'artists' => [['id' => 'artist-id', 'name' => 'An Artist']],
                            'images' => [['url' => 'https://i.scdn.co/image/cover']],
                        ],
                    ],
                ]],
            ]);
        });

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 10,
        ]);

        $job = new ImportSpotifyPlaylists($run->id);
        $job->handle(app(SpotifyClient::class));
        $job->handle(app(SpotifyClient::class));

        $this->assertDatabaseCount('spotify_playlists', 1);
        $this->assertDatabaseCount('spotify_playlist_items', 1);
        $this->assertDatabaseHas('spotify_playlists', [
            'spotify_playlist_id' => 'playlist-id',
            'name' => 'A Playlist',
        ]);
        $this->assertDatabaseHas('spotify_playlist_items', [
            'spotify_track_id' => 'track-id',
            'position' => 0,
        ]);
        $this->assertDatabaseHas('spotify_album_collections', [
            'collection_type' => 'playlist',
            'spotify_playlist_id' => 'playlist-id',
        ]);
        $this->assertSame('A Playlist', SpotifyPlaylist::query()->firstOrFail()->name);
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'fetched_count' => 1,
            'updated_count' => 1,
        ]);
    }
}
