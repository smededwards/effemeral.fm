<?php

namespace Tests\Feature;

use App\Models\AlbumRecommendation;
use App\Models\RecommendationRun;
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
}
