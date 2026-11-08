<?php

namespace Tests\Feature;

use App\Jobs\ImportSpotifySavedAlbums;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
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
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/artists?ids=artist-id')) {
                return Http::response([
                    'artists' => [['id' => 'artist-id', 'genres' => ['post-metal', 'post metal']]],
                ]);
            }

            return Http::response([
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
            ]);
        });

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
        $this->assertDatabaseHas('artists', ['spotify_id' => 'artist-id', 'name' => 'An Artist']);
        $this->assertDatabaseHas('albums', ['spotify_id' => 'album-id', 'title' => 'A Saved Album']);
        $this->assertDatabaseHas('tracks', ['spotify_id' => 'track-id', 'title' => 'A Track']);
        $this->assertDatabaseHas('spotify_album_collections', ['collection_type' => 'saved']);
        $this->assertCount(1, Album::query()->firstOrFail()->artists);
        $this->assertSame('A Track', Track::query()->firstOrFail()->title);
        $this->assertSame('An Artist', Artist::query()->firstOrFail()->name);
        $this->assertSame(1, Genre::query()->count());
        $this->assertSame(['post-metal'], Artist::query()->firstOrFail()->genres->unique('normalised_name')->pluck('name')->all());
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
