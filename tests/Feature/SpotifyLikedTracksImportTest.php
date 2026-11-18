<?php

namespace Tests\Feature;

use App\Jobs\ImportSpotifyLikedTracks;
use App\Models\Album;
use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifyLikedTrack;
use App\Models\Track;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpotifyLikedTracksImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_liked_tracks_import_can_be_queued(): void
    {
        Queue::fake();

        $response = $this->post(route('imports.spotify.liked-tracks'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Spotify liked songs import queued.');
        $this->assertDatabaseHas('spotify_album_import_runs', [
            'source' => 'liked_tracks',
            'status' => 'queued',
            'requested_limit' => null,
        ]);
        Queue::assertPushed(ImportSpotifyLikedTracks::class);
    }

    public function test_liked_tracks_are_imported_idempotently_into_the_catalogue(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/artists?ids=artist-id')) {
                return Http::response(['artists' => [['id' => 'artist-id', 'genres' => ['post-metal']]]]);
            }

            return Http::response([
                'next' => null,
                'items' => [[
                    'added_at' => '2026-11-18T12:00:00Z',
                    'track' => [
                        'id' => 'track-id',
                        'uri' => 'spotify:track:track-id',
                        'name' => 'Liked Track',
                        'artists' => [['id' => 'artist-id', 'name' => 'Liked Artist']],
                        'album' => [
                            'id' => 'album-id',
                            'uri' => 'spotify:album:album-id',
                            'name' => 'Liked Album',
                            'album_type' => 'album',
                            'total_tracks' => 1,
                            'release_date' => '2026-01-01',
                            'artists' => [['id' => 'artist-id', 'name' => 'Liked Artist']],
                        ],
                    ],
                ]],
            ]);
        });

        $run = SpotifyAlbumImportRun::query()->create([
            'source' => 'liked_tracks',
            'status' => 'queued',
        ]);

        $job = new ImportSpotifyLikedTracks($run->id);
        $job->handle(app(SpotifyClient::class));
        $job->handle(app(SpotifyClient::class));

        $this->assertDatabaseCount('spotify_liked_tracks', 1);
        $this->assertDatabaseHas('spotify_liked_tracks', [
            'spotify_track_id' => 'track-id',
            'track_name' => 'Liked Track',
        ]);
        $this->assertDatabaseHas('albums', ['spotify_id' => 'album-id', 'title' => 'Liked Album']);
        $this->assertDatabaseHas('tracks', ['spotify_id' => 'track-id', 'title' => 'Liked Track']);
        $this->assertDatabaseHas('spotify_album_collections', ['collection_type' => 'liked']);
        $this->assertDatabaseHas('spotify_album_import_runs', [
            'id' => $run->id,
            'source' => 'liked_tracks',
            'status' => 'completed',
            'fetched_count' => 1,
            'imported_count' => 0,
            'updated_count' => 1,
        ]);
        $this->assertSame(1, SpotifyLikedTrack::query()->count());
        $this->assertSame(1, Album::query()->count());
        $this->assertSame(1, Track::query()->count());
    }
}
