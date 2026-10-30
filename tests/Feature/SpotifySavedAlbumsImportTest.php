<?php

namespace Tests\Feature;

use App\Jobs\ImportSpotifySavedAlbums;
use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifySavedAlbum;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotifySavedAlbumsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_albums_import_can_be_queued(): void
    {
        Queue::fake();

        $response = $this->post(route('imports.spotify.albums'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Spotify saved albums import queued.');
        $this->assertDatabaseHas('spotify_album_import_runs', [
            'status' => 'queued',
            'requested_limit' => 100,
        ]);
        Queue::assertPushed(ImportSpotifySavedAlbums::class);
    }

    public function test_saved_albums_are_imported_and_existing_albums_are_updated(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake([
            '*' => Http::response([
                'next' => null,
                'items' => [
                    [
                        'added_at' => '2026-10-29T12:00:00Z',
                        'album' => [
                            'id' => 'album-id',
                            'uri' => 'spotify:album:album-id',
                            'name' => 'A Saved Album',
                            'album_type' => 'album',
                            'total_tracks' => 8,
                            'release_date' => '2020-01-01',
                            'release_date_precision' => 'day',
                            'artists' => [['id' => 'artist-id', 'name' => 'An Artist']],
                            'tracks' => ['items' => [['id' => 'track-id', 'name' => 'A Track']]],
                            'images' => [['url' => 'https://i.scdn.co/image/cover']],
                            'external_urls' => ['spotify' => 'https://open.spotify.com/album/album-id'],
                        ],
                    ],
                ],
            ]),
        ]);

        $run = SpotifyAlbumImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 100,
        ]);

        $job = new ImportSpotifySavedAlbums($run->id);
        $job->handle(app(SpotifyClient::class));
        $job->handle(app(SpotifyClient::class));

        $this->assertDatabaseCount('spotify_saved_albums', 1);
        $this->assertDatabaseHas('spotify_saved_albums', [
            'spotify_album_id' => 'album-id',
            'primary_artist_name' => 'An Artist',
            'name' => 'A Saved Album',
        ]);
        $this->assertSame('A Saved Album', SpotifySavedAlbum::query()->firstOrFail()->name);
        $this->assertDatabaseHas('spotify_album_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'fetched_count' => 1,
            'imported_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 1,
        ]);
    }
}
