<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\RecommendationRun;
use App\Models\SpotifyAlbumCollection;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueDiscographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_discography_creates_an_ordered_full_album_draft(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
        ]);
        $first = $this->createSavedAlbum($artist, 'Blue Record', '2009-10-13', 'first');
        $second = $this->createSavedAlbum($artist, 'Yellow & Green', '2012-07-17', 'second');
        $single = Album::query()->create([
            'title' => 'Single',
            'normalised_title' => 'single',
            'album_type' => 'single',
            'spotify_id' => 'single',
            'total_tracks' => 1,
            'release_date' => '2011-01-01',
        ]);
        $single->artists()->attach($artist->id);

        $response = $this->post(route('catalogue.discography'), ['artist_id' => $artist->id]);

        $run = RecommendationRun::query()->latest()->firstOrFail();
        $response->assertRedirect(route('recommendations.review.page', $run));
        $this->assertSame('artist_discography', $run->source);
        $this->assertSame([$first->id, $second->id], $run->albumRecommendations->pluck('spotify_saved_album_id')->all());
    }

    public function test_discography_prefers_original_editions_and_can_include_unmatched_non_albums(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
        ]);
        $original = $this->createSavedAlbum($artist, 'Blue Record', '2009-10-13', 'original');
        $remaster = $this->createSavedAlbum($artist, 'Blue Record (Remastered)', '2020-01-01', 'remaster');
        $single = $this->createSavedAlbum($artist, 'Tour Single', '2011-01-01', 'single', 'single');
        $unmatched = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'unmatched',
            'spotify_uri' => 'spotify:album:unmatched',
            'name' => 'Unmatched Album',
            'album_type' => 'album',
            'primary_artist_name' => 'Baroness',
            'release_date' => '2012-01-01',
            'tracks' => ['items' => [['uri' => 'spotify:track:unmatched']]],
        ]);

        $response = $this->post(route('catalogue.discography'), [
            'artist_id' => $artist->id,
            'include_non_albums' => true,
        ]);

        $run = RecommendationRun::query()->latest()->firstOrFail();
        $response->assertRedirect(route('recommendations.review.page', $run));
        $recommendations = $run->albumRecommendations()->orderBy('position')->get();

        $this->assertSame([$original->id, $single->id, $unmatched->id], $recommendations->pluck('spotify_saved_album_id')->all());
        $this->assertNotSame($remaster->id, $recommendations->first()->spotify_saved_album_id);
        $this->assertNull($recommendations->last()->album_id);
    }

    public function test_discography_excludes_non_albums_by_default(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
        ]);
        $this->createSavedAlbum($artist, 'Tour Single', '2011-01-01', 'single', 'single');

        $response = $this->post(route('catalogue.discography'), ['artist_id' => $artist->id]);

        $response->assertSessionHas('error', 'No saved albums are available for this artist.');
        $this->assertDatabaseCount('recommendation_runs', 0);
    }

    private function createSavedAlbum(
        Artist $artist,
        string $title,
        string $releaseDate,
        string $key,
        string $albumType = 'album',
    ): SpotifySavedAlbum {
        $album = Album::query()->create([
            'title' => $title,
            'normalised_title' => strtolower($title),
            'album_type' => $albumType,
            'spotify_id' => 'album-'.$key,
            'total_tracks' => 1,
            'release_date' => $releaseDate,
        ]);
        $album->artists()->attach($artist->id);
        Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Track',
            'normalised_title' => 'track',
            'spotify_id' => 'track-'.$key,
            'spotify_uri' => 'spotify:track:track-'.$key,
        ]);
        $savedAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'album-'.$key,
            'spotify_uri' => 'spotify:album:album-'.$key,
            'name' => $title,
            'album_type' => $albumType,
            'total_tracks' => 1,
            'tracks' => ['items' => [['uri' => 'spotify:track:track-'.$key]]],
        ]);
        SpotifyAlbumCollection::query()->create([
            'album_id' => $album->id,
            'collection_type' => 'saved',
        ]);

        return $savedAlbum;
    }
}
