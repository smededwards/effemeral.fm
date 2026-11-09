<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\RecommendationRun;
use App\Models\Scrobble;
use App\Models\SpotifyAlbumCollection;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_creates_a_draft_of_matching_incomplete_albums(): void
    {
        $sourceArtist = Artist::query()->create([
            'name' => 'Cult of Luna',
            'normalised_name' => 'cult of luna',
        ]);
        $sourceAlbum = Album::query()->create([
            'title' => 'Mariner',
            'normalised_title' => 'mariner',
            'spotify_id' => 'source-album',
            'total_tracks' => 1,
            'release_date' => '2024-05-01',
        ]);
        $sourceAlbum->artists()->attach($sourceArtist->id);
        $genre = Genre::query()->create(['name' => 'post-metal', 'normalised_name' => 'post metal']);
        $sourceArtist->genres()->attach($genre->id, ['source' => 'spotify']);

        $candidateArtist = Artist::query()->create([
            'name' => 'A Similar Artist',
            'normalised_name' => 'a similar artist',
        ]);
        $candidateArtist->genres()->attach($genre->id, ['source' => 'spotify']);
        $candidateAlbum = Album::query()->create([
            'title' => 'A Similar Album',
            'normalised_title' => 'a similar album',
            'spotify_id' => 'candidate-album',
            'total_tracks' => 2,
            'release_date' => '2024-06-01',
        ]);
        $candidateAlbum->artists()->attach($candidateArtist->id);
        $candidateTrack = Track::query()->create([
            'album_id' => $candidateAlbum->id,
            'title' => 'A Track',
            'normalised_title' => 'a track',
            'spotify_id' => 'candidate-track',
            'spotify_uri' => 'spotify:track:candidate-track',
        ]);
        Track::query()->create([
            'album_id' => $candidateAlbum->id,
            'title' => 'Another Track',
            'normalised_title' => 'another track',
            'spotify_id' => 'candidate-track-2',
            'spotify_uri' => 'spotify:track:candidate-track-2',
        ]);
        $savedAlbum = SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'candidate-album',
            'spotify_uri' => 'spotify:album:candidate-album',
            'name' => 'A Similar Album',
            'album_type' => 'album',
            'primary_artist_name' => 'A Similar Artist',
            'total_tracks' => 2,
        ]);
        SpotifyAlbumCollection::query()->create([
            'album_id' => $candidateAlbum->id,
            'collection_type' => 'saved',
            'saved_at' => now(),
        ]);

        $completedArtist = Artist::query()->create([
            'name' => 'Completed Artist',
            'normalised_name' => 'completed artist',
        ]);
        $completedArtist->genres()->attach($genre->id, ['source' => 'spotify']);
        $completedAlbum = Album::query()->create([
            'title' => 'Completed Album',
            'normalised_title' => 'completed album',
            'spotify_id' => 'completed-album',
            'total_tracks' => 1,
            'release_date' => '2024-07-01',
        ]);
        $completedAlbum->artists()->attach($completedArtist->id);
        $completedTrack = Track::query()->create([
            'album_id' => $completedAlbum->id,
            'title' => 'Heard Track',
            'normalised_title' => 'heard track',
            'spotify_id' => 'completed-track',
        ]);
        SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'completed-album',
            'spotify_uri' => 'spotify:album:completed-album',
            'name' => 'Completed Album',
            'album_type' => 'album',
            'primary_artist_name' => 'Completed Artist',
            'total_tracks' => 1,
        ]);
        SpotifyAlbumCollection::query()->create([
            'album_id' => $completedAlbum->id,
            'collection_type' => 'saved',
        ]);
        Scrobble::query()->create([
            'source' => 'lastfm',
            'source_key' => 'heard-track',
            'artist_id' => $completedArtist->id,
            'album_id' => $completedAlbum->id,
            'track_id' => $completedTrack->id,
            'artist_name' => 'Completed Artist',
            'track_name' => 'Heard Track',
            'album_name' => 'Completed Album',
            'listened_at' => now(),
        ]);

        $response = $this->post(route('catalogue.discover'), [
            'album_id' => $sourceAlbum->id,
            'genre' => 'post metal',
            'year' => 2024,
        ]);

        $run = RecommendationRun::query()->latest()->firstOrFail();
        $response->assertRedirect(route('recommendations.review.page', $run));
        $this->assertSame('catalogue_discovery', $run->source);
        $this->assertSame([$savedAlbum->id], $run->albumRecommendations->pluck('spotify_saved_album_id')->all());
        $this->assertSame('Genre: post metal · Release year: 2024', $run->albumRecommendations->firstOrFail()->match_reason);
    }
}
