<?php

namespace Tests\Feature;

use App\Jobs\ImportSpotifyPlaylists;
use App\Models\Album;
use App\Models\SpotifyConnection;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use App\Models\SpotifyPlaylistItem;
use App\Services\Spotify\SpotifyClient;
use App\Services\Spotify\SpotifyRateLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotifyPlaylistImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpotifyConnection::query()->create([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'spotify_user_id' => 'owner-id',
            'display_name' => 'Test owner',
        ]);
    }

    public function test_a_playlist_import_can_be_queued(): void
    {
        Queue::fake();

        $response = $this->post(route('imports.spotify.playlists'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Spotify playlists import queued.');
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'status' => 'queued',
            'requested_limit' => null,
        ]);
        Queue::assertPushed(ImportSpotifyPlaylists::class);
    }

    public function test_a_failed_playlist_import_resumes_from_its_saved_offset(): void
    {
        Queue::fake();
        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'failed',
            'next_offset' => 500,
            'total_count' => 1025,
            'error' => 'Spotify quota has been exceeded. Try again later.',
        ]);

        $this->post(route('imports.spotify.playlists'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Spotify playlist import resumed.');

        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'queued',
            'next_offset' => 500,
            'error' => null,
        ]);
        Queue::assertPushed(ImportSpotifyPlaylists::class, fn (ImportSpotifyPlaylists $job): bool => $job->importRunId === $run->id);
    }

    public function test_playlists_and_items_are_imported_idempotently(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        $playlistItemRequests = 0;
        Http::fake(function ($request) use (&$playlistItemRequests) {
            if (str_contains($request->url(), '/albums/album-id/tracks')) {
                return Http::response([
                    'items' => [[
                        'id' => 'track-id',
                        'uri' => 'spotify:track:track-id',
                        'name' => 'A Track',
                        'track_number' => 1,
                    ]],
                    'next' => null,
                ]);
            }

            if (str_contains($request->url(), '/playlists/playlist-id/items')) {
                $playlistItemRequests++;
            }

            if (str_ends_with($request->url(), '/me/playlists?limit=50&offset=0')) {
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
            'requested_limit' => null,
        ]);

        $job = new ImportSpotifyPlaylists($run->id, null, false, true);
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
        $this->assertDatabaseHas('tracks', [
            'spotify_id' => 'track-id',
            'spotify_uri' => 'spotify:track:track-id',
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
        $this->assertSame(1, $playlistItemRequests);
    }

    public function test_excluded_artists_do_not_enter_the_catalogue_from_owned_playlists(): void
    {
        config()->set('services.spotify.import_excluded_artists', ['mötley crüe']);
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/playlists')) {
                return Http::response([
                    'next' => null,
                    'total' => 1,
                    'items' => [[
                        'id' => 'owned-playlist',
                        'name' => 'Owned playlist',
                        'owner' => ['id' => 'owner-id'],
                        'tracks' => ['total' => 1],
                    ]],
                ]);
            }

            return Http::response(['items' => [[
                'track' => [
                    'id' => 'excluded-track',
                    'name' => 'Wild Side',
                    'album' => [
                        'id' => 'excluded-album',
                        'name' => 'Girls, Girls, Girls (Deluxe Version)',
                        'artists' => [['id' => 'motley-crue', 'name' => 'Mötley Crüe']],
                    ],
                ],
            ]]]);
        });

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
        ]);

        (new ImportSpotifyPlaylists($run->id))->handle(app(SpotifyClient::class));

        $this->assertDatabaseMissing('albums', ['spotify_id' => 'excluded-album']);
        $this->assertDatabaseMissing('spotify_playlist_items', ['spotify_track_id' => 'excluded-track']);
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'skipped_count' => 1,
        ]);
    }

    public function test_followed_playlists_owned_by_other_users_are_not_imported(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/playlists')) {
                return Http::response([
                    'next' => null,
                    'total' => 1,
                    'items' => [[
                        'id' => 'followed-playlist',
                        'name' => 'Public playlist I follow',
                        'owner' => ['id' => 'other-user'],
                    ]],
                ]);
            }

            $this->fail('The importer requested items for a playlist owned by another user.');
        });

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
        ]);

        (new ImportSpotifyPlaylists($run->id))->handle(app(SpotifyClient::class));

        $this->assertDatabaseMissing('spotify_playlists', ['spotify_playlist_id' => 'followed-playlist']);
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'skipped_count' => 1,
        ]);
    }

    public function test_a_bounded_new_playlist_import_skips_existing_playlists(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        $playlistItemRequests = [];
        SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'existing-playlist',
            'name' => 'Existing playlist',
        ]);

        Http::fake(function ($request) use (&$playlistItemRequests) {
            if (str_contains($request->url(), '/me/playlists')) {
                return Http::response([
                    'next' => null,
                    'total' => 3,
                    'items' => [
                        ['id' => 'existing-playlist', 'name' => 'Existing playlist', 'owner' => ['id' => 'owner-id']],
                        ['id' => 'new-playlist-one', 'name' => 'New playlist one', 'owner' => ['id' => 'owner-id']],
                        ['id' => 'new-playlist-two', 'name' => 'New playlist two', 'owner' => ['id' => 'owner-id']],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/artists')) {
                return Http::response(['artists' => []]);
            }

            if (str_contains($request->url(), '/playlists/')) {
                $playlistItemRequests[] = $request->url();
            }

            return Http::response([
                'items' => [[
                    'added_at' => '2026-11-04T12:00:00Z',
                    'track' => [
                        'id' => 'track-id',
                        'uri' => 'spotify:track:track-id',
                        'name' => 'A Track',
                        'album' => [
                            'id' => 'album-id',
                            'name' => 'An Album',
                            'album_type' => 'album',
                            'artists' => [['id' => 'artist-id', 'name' => 'An Artist']],
                        ],
                    ],
                ]],
            ]);
        });

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 2,
        ]);

        (new ImportSpotifyPlaylists($run->id, null, true))->handle(app(SpotifyClient::class));

        $this->assertDatabaseCount('spotify_playlists', 3);
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'imported_count' => 2,
            'skipped_count' => 1,
        ]);
        $this->assertCount(2, $playlistItemRequests);
        $this->assertNotContains('https://api.spotify.com/v1/playlists/existing-playlist/items', $playlistItemRequests);
    }

    public function test_playlist_import_persists_the_next_page_after_a_quota_failure(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/playlists')) {
                return str_contains($request->url(), 'offset=0')
                    ? Http::response([
                        'total' => 2,
                        'items' => [['id' => 'first-playlist', 'name' => 'First playlist', 'owner' => ['id' => 'owner-id'], 'tracks' => ['total' => 0]]],
                    ])
                    : Http::response(['error' => ['reason' => 'QUOTA_EXCEEDED']], 429);
            }

            return Http::response(['items' => [], 'next' => null]);
        });

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => null,
        ]);

        (new ImportSpotifyPlaylists($run->id))->handle(app(SpotifyClient::class));

        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'failed',
            'next_offset' => 1,
            'total_count' => 2,
        ]);
    }

    public function test_spotify_quota_errors_are_not_retried_or_reissued_during_cooldown(): void
    {
        $attempts = 0;
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response(['error' => ['reason' => 'QUOTA_EXCEEDED']], 429);
        });

        try {
            app(SpotifyClient::class)->savedAlbums(1);
            $this->fail('Expected Spotify quota exhaustion to stop the request.');
        } catch (SpotifyRateLimitException $exception) {
            $this->assertSame('Spotify quota has been exceeded. Try again later.', $exception->getMessage());
        }

        try {
            app(SpotifyClient::class)->savedAlbums(1);
            $this->fail('Expected the Spotify cooldown to stop the follow-up request.');
        } catch (SpotifyRateLimitException $exception) {
            $this->assertStringContainsString('cooldown is active', $exception->getMessage());
        }

        $this->assertSame(1, $attempts);
    }

    public function test_failed_item_refresh_preserves_the_previous_playlist_items(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        $playlist = SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'playlist-id',
            'name' => 'Existing playlist',
        ]);
        $album = Album::query()->create([
            'title' => 'Existing album',
            'normalised_title' => 'existing album',
        ]);
        SpotifyPlaylistItem::query()->create([
            'spotify_playlist_id' => $playlist->id,
            'album_id' => $album->id,
            'spotify_track_id' => 'existing-track-id',
            'position' => 0,
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/playlists')) {
                return Http::response([
                    'next' => null,
                    'items' => [[
                        'id' => 'playlist-id',
                        'name' => 'Updated playlist',
                        'owner' => ['id' => 'owner-id'],
                        'tracks' => ['total' => 1],
                    ]],
                ]);
            }

            return Http::response([], 500);
        });

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 10,
        ]);

        (new ImportSpotifyPlaylists($run->id))->handle(app(SpotifyClient::class));

        $this->assertDatabaseHas('spotify_playlist_items', [
            'spotify_playlist_id' => $playlist->id,
            'spotify_track_id' => 'existing-track-id',
        ]);
        $this->assertDatabaseHas('spotify_playlists', [
            'spotify_playlist_id' => 'playlist-id',
            'last_import_error' => 'Spotify returned HTTP 500.',
        ]);
        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'error' => 'Updated playlist: Spotify returned HTTP 500.',
        ]);
    }

    public function test_a_failed_playlist_can_be_retried_individually(): void
    {
        Queue::fake();
        $playlist = SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'failed-playlist-id',
            'name' => 'Failed playlist',
            'last_import_error' => 'Spotify returned HTTP 500.',
        ]);

        $this->post(route('imports.spotify.playlists.retry', $playlist))
            ->assertRedirect()
            ->assertSessionHas('success', 'Spotify playlist retry queued.');

        Queue::assertPushed(ImportSpotifyPlaylists::class, function (ImportSpotifyPlaylists $job) use ($playlist): bool {
            return $job->playlistId === $playlist->spotify_playlist_id;
        });
    }

    public function test_playlist_import_job_allows_long_running_imports(): void
    {
        $this->assertSame(3600, (new ImportSpotifyPlaylists(1))->timeout);
    }

    public function test_a_stale_spotify_playlist_import_is_cleared_before_a_new_import_is_queued(): void
    {
        Queue::fake();
        $staleRun = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'running',
        ]);
        SpotifyPlaylistImportRun::query()->whereKey($staleRun->id)->update([
            'updated_at' => now()->subHours(2),
        ]);

        $this->post(route('imports.spotify.playlists'))
            ->assertSessionHas('success', 'Spotify playlists import queued.');

        $this->assertDatabaseHas('spotify_playlist_import_runs', [
            'id' => $staleRun->id,
            'status' => 'failed',
            'error' => 'Import marked failed because its worker heartbeat expired.',
        ]);
    }
}
