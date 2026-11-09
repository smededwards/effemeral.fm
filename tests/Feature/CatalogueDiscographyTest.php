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

    private function createSavedAlbum(Artist $artist, string $title, string $releaseDate, string $key): SpotifySavedAlbum
    {
        $album = Album::query()->create([
            'title' => $title,
            'normalised_title' => strtolower($title),
            'album_type' => 'album',
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
            'album_type' => 'album',
            'total_tracks' => 1,
        ]);
        SpotifyAlbumCollection::query()->create([
            'album_id' => $album->id,
            'collection_type' => 'saved',
        ]);

        return $savedAlbum;
    }
}
