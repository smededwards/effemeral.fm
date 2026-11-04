<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumRecommendation;
use App\Models\Artist;
use App\Models\RecommendationRun;
use App\Models\Scrobble;
use App\Models\SpotifyAlbumCollection;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_dashboard_forms_use_the_forwarded_https_scheme(): void
    {
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'app.effemeral.local',
        ])->get(route('dashboard'));

        $response->assertSee('action="https://app.effemeral.local/imports/lastfm"', false);
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

    public function test_canonical_saved_albums_use_linked_scrobbles_for_completion_and_recommendations(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Canonical Artist',
            'normalised_name' => 'canonical artist',
        ]);
        $album = Album::query()->create([
            'title' => 'Canonical Album',
            'normalised_title' => 'canonical album',
            'spotify_id' => 'canonical-album-id',
            'spotify_url' => 'https://open.spotify.com/album/canonical-album-id',
            'total_tracks' => 2,
        ]);
        $album->artists()->attach($artist->id, ['artist_position' => 0]);
        $heardTrack = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Heard Track',
            'normalised_title' => 'heard track',
            'spotify_id' => 'heard-track-id',
            'spotify_uri' => 'spotify:track:heard-track-id',
            'track_number' => 1,
        ]);
        Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Unheard Track',
            'normalised_title' => 'unheard track',
            'spotify_id' => 'unheard-track-id',
            'spotify_uri' => 'spotify:track:unheard-track-id',
            'track_number' => 2,
        ]);
        SpotifyAlbumCollection::query()->create([
            'album_id' => $album->id,
            'collection_type' => 'saved',
            'saved_at' => now(),
        ]);
        $legacyAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'canonical-album-id',
            'spotify_uri' => 'spotify:album:canonical-album-id',
            'name' => 'Canonical Album',
            'album_type' => 'album',
            'total_tracks' => 2,
            'primary_artist_name' => 'Canonical Artist',
        ]);
        Scrobble::query()->create([
            'source_key' => 'canonical-heard-track',
            'artist_name' => 'Canonical Artist',
            'track_name' => 'Heard Track',
            'album_name' => 'Canonical Album',
            'track_id' => $heardTrack->id,
            'listened_at' => '2026-01-02 12:00:00',
        ]);

        $this->get(route('dashboard'))
            ->assertSee('1 / 2 tracks heard')
            ->assertSee('Canonical Album');

        $this->post(route('recommendations.generate'))
            ->assertSessionHas('success', 'Draft album recommendations generated.');

        $this->assertDatabaseHas('album_recommendations', [
            'album_id' => $album->id,
            'spotify_saved_album_id' => $legacyAlbum->id,
        ]);
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

    public function test_album_completion_can_be_overridden_and_cleared(): void
    {
        $album = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'override-album-id',
            'spotify_uri' => 'spotify:album:override-album-id',
            'name' => 'Override Album',
            'album_type' => 'album',
            'total_tracks' => 1,
            'primary_artist_name' => 'An Artist',
            'tracks' => ['items' => [['name' => 'Only Track']]],
        ]);

        $this->post(route('albums.completion.complete', $album))
            ->assertSessionHas('success', 'Album marked as heard.');
        $this->assertTrue($album->fresh()->completion_override);

        $this->post(route('albums.completion.incomplete', $album))
            ->assertSessionHas('success', 'Album marked as incomplete.');
        $this->assertFalse($album->fresh()->completion_override);

        $this->post(route('albums.completion.clear', $album))
            ->assertSessionHas('success', 'Album completion override cleared.');
        $this->assertNull($album->fresh()->completion_override);
    }

    public function test_a_draft_recommendation_run_is_generated_from_incomplete_albums(): void
    {
        $album = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'recommendation-album-id',
            'spotify_uri' => 'spotify:album:recommendation-album-id',
            'spotify_url' => 'https://open.spotify.com/album/recommendation-album-id',
            'name' => 'Recommendation Album',
            'album_type' => 'album',
            'total_tracks' => 2,
            'primary_artist_name' => 'An Artist',
            'tracks' => ['items' => [['name' => 'Heard Track'], ['name' => 'Unheard Track']]],
        ]);
        Scrobble::query()->create([
            'source_key' => 'recommendation-heard-track',
            'artist_name' => 'An Artist',
            'track_name' => 'Heard Track',
            'album_name' => 'Recommendation Album',
            'listened_at' => '2026-01-02 12:00:00',
        ]);

        $this->post(route('recommendations.generate'))
            ->assertSessionHas('success', 'Draft album recommendations generated.');

        $run = RecommendationRun::query()->firstOrFail();
        $this->assertDatabaseHas('album_recommendations', [
            'recommendation_run_id' => $run->id,
            'spotify_saved_album_id' => $album->id,
            'position' => 1,
            'heard_tracks' => 1,
            'total_tracks' => 2,
            'completion_percentage' => 50,
        ]);
        $this->assertSame(1, AlbumRecommendation::query()->count());

        $this->get(route('dashboard'))
            ->assertSee('Latest recommendation draft')
            ->assertSee('Recommendation Album');
    }

    public function test_a_recommendation_draft_can_be_reordered_and_reviewed(): void
    {
        $run = RecommendationRun::factory()->create();
        $firstAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'first-review-album-id',
            'spotify_uri' => 'spotify:album:first-review-album-id',
            'name' => 'First Review Album',
            'album_type' => 'album',
            'total_tracks' => 1,
        ]);
        $secondAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'second-review-album-id',
            'spotify_uri' => 'spotify:album:second-review-album-id',
            'name' => 'Second Review Album',
            'album_type' => 'album',
            'total_tracks' => 1,
        ]);
        $first = AlbumRecommendation::factory()->create([
            'recommendation_run_id' => $run->id,
            'spotify_saved_album_id' => $firstAlbum->id,
            'position' => 1,
            'heard_tracks' => 0,
            'total_tracks' => 1,
            'completion_percentage' => 0,
            'included' => true,
        ]);
        $second = AlbumRecommendation::factory()->create([
            'recommendation_run_id' => $run->id,
            'spotify_saved_album_id' => $secondAlbum->id,
            'position' => 2,
            'heard_tracks' => 0,
            'total_tracks' => 1,
            'completion_percentage' => 0,
            'included' => true,
        ]);

        $this->post(route('recommendations.review', $run), [
            'positions' => [$first->id => 2, $second->id => 1],
            'included' => [$second->id],
        ])
            ->assertSessionHas('success', 'Recommendation draft review saved.');

        $this->assertDatabaseHas('album_recommendations', [
            'id' => $first->id,
            'position' => 2,
            'included' => false,
        ]);
        $this->assertDatabaseHas('album_recommendations', [
            'id' => $second->id,
            'position' => 1,
            'included' => true,
        ]);
    }

    public function test_included_recommendations_are_synced_to_a_private_spotify_playlist_in_reviewed_order(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        Http::fake([
            config('services.spotify.api_endpoint').'/me/playlists' => Http::response([
                'id' => 'playlist-id',
                'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/playlist-id'],
            ], 201),
            config('services.spotify.api_endpoint').'/playlists/playlist-id/items' => Http::response([
                'snapshot_id' => 'snapshot-id',
            ], 201),
        ]);
        $run = RecommendationRun::factory()->create();
        $includedAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'included-album-id',
            'spotify_uri' => 'spotify:album:included-album-id',
            'name' => 'Included Album',
            'album_type' => 'album',
            'tracks' => ['items' => [['uri' => 'spotify:track:first'], ['uri' => 'spotify:track:second']]],
        ]);
        $excludedAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'excluded-album-id',
            'spotify_uri' => 'spotify:album:excluded-album-id',
            'name' => 'Excluded Album',
            'album_type' => 'album',
            'tracks' => ['items' => [['uri' => 'spotify:track:excluded']]],
        ]);
        AlbumRecommendation::factory()->create([
            'recommendation_run_id' => $run->id,
            'spotify_saved_album_id' => $includedAlbum->id,
            'position' => 1,
            'heard_tracks' => 0,
            'total_tracks' => 2,
            'completion_percentage' => 0,
            'included' => true,
        ]);
        AlbumRecommendation::factory()->create([
            'recommendation_run_id' => $run->id,
            'spotify_saved_album_id' => $excludedAlbum->id,
            'position' => 2,
            'heard_tracks' => 0,
            'total_tracks' => 1,
            'completion_percentage' => 0,
            'included' => false,
        ]);

        $this->post(route('recommendations.sync', $run))
            ->assertSessionHas('success', 'Recommendation draft synced to Spotify.');

        $run->refresh();
        $this->assertSame('synced', $run->status);
        $this->assertSame('playlist-id', $run->spotify_playlist_id);
        $this->assertSame('https://open.spotify.com/playlist/playlist-id', $run->spotify_playlist_url);
        Http::assertSent(function ($request): bool {
            return $request->url() === config('services.spotify.api_endpoint').'/me/playlists'
                && $request['public'] === false;
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === config('services.spotify.api_endpoint').'/playlists/playlist-id/items'
                && $request['uris'] === ['spotify:track:first', 'spotify:track:second'];
        });
    }
}
