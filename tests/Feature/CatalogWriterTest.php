<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Services\Catalog\CatalogWriter;
use App\Services\Catalog\TrackAliasReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_spotify_track_enrichment_updates_an_existing_track_without_creating_a_duplicate(): void
    {
        $album = Album::query()->create([
            'title' => 'Les Chants de l’Aurore',
            'normalised_title' => 'les chants de laurore',
            'spotify_id' => 'spotify-album-id',
        ]);
        /** @var Track $track */
        $track = $album->tracks()->create([
            'title' => 'Komorebi',
            'normalised_title' => 'komorebi',
        ]);

        app(CatalogWriter::class)->spotifyAlbumTracks($album, [[
            'id' => 'spotify-track-id',
            'name' => 'Komorebi',
            'uri' => 'spotify:track:spotify-track-id',
            'disc_number' => 1,
            'track_number' => 1,
            'duration_ms' => 399226,
        ]]);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertDatabaseHas('tracks', [
            'id' => $track->id,
            'spotify_id' => 'spotify-track-id',
            'spotify_uri' => 'spotify:track:spotify-track-id',
            'disc_number' => 1,
            'track_number' => 1,
            'duration_ms' => 399226,
        ]);
    }

    public function test_spotify_artist_matching_treats_ampersand_and_and_as_equivalent(): void
    {
        Artist::query()->create([
            'name' => ':Of the Wand & the Moon:',
            'normalised_name' => Artist::normaliseName(':Of the Wand & the Moon:'),
        ]);

        app(CatalogWriter::class)->spotifyAlbum([
            'id' => 'spotify-album-id',
            'name' => 'The Empty Moon',
            'artists' => [[
                'id' => 'spotify-artist-id',
                'name' => ':Of The Wand And The Moon:',
            ]],
            'tracks' => ['items' => []],
        ]);

        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseHas('artists', [
            'normalised_name' => ':of the wand and the moon:',
            'spotify_id' => 'spotify-artist-id',
        ]);
    }

    public function test_last_fm_title_variant_is_saved_as_an_alias_of_the_spotify_track(): void
    {
        /** @var Artist $artist */
        $artist = Artist::query()->create([
            'name' => 'The Kilimanjaro Darkjazz Ensemble',
            'normalised_name' => 'the kilimanjaro darkjazz ensemble',
        ]);
        /** @var Album $album */
        $album = Album::query()->create([
            'title' => 'Here Be Dragons',
            'normalised_title' => 'here be dragons',
            'spotify_id' => 'spotify-album-id',
            'total_tracks' => 1,
        ]);
        $album->artists()->attach($artist);
        /** @var Track $track */
        $track = $album->tracks()->create([
            'title' => 'Caravan',
            'normalised_title' => 'caravan',
            'spotify_id' => 'spotify-track-id',
        ]);

        $result = app(CatalogWriter::class)->lastFmScrobble([
            'artist_name' => $artist->name,
            'album_name' => $album->title,
            'track_name' => 'Caravan!',
            'track_mbid' => 'lastfm-track-id',
        ]);

        $this->assertSame($track->id, $result?->id);
        $this->assertDatabaseCount('tracks', 1);
        $this->assertDatabaseHas('track_aliases', [
            'track_id' => $track->id,
            'name' => 'Caravan!',
            'source' => 'lastfm',
            'provider_id' => 'lastfm-track-id',
        ]);
    }

    public function test_track_alias_reconciliation_moves_scrobbles_and_retires_the_source_track(): void
    {
        /** @var Album $album */
        $album = Album::query()->create([
            'title' => 'Here Be Dragons',
            'normalised_title' => 'here be dragons',
            'spotify_id' => 'spotify-album-id',
        ]);
        /** @var Track $canonical */
        $canonical = $album->tracks()->create([
            'title' => 'Caravan',
            'normalised_title' => 'caravan',
            'spotify_id' => 'spotify-track-id',
        ]);
        /** @var Track $source */
        $source = $album->tracks()->create([
            'title' => 'Caravan!',
            'normalised_title' => 'caravan',
            'lastfm_mbid' => 'lastfm-track-id',
        ]);
        DB::table('scrobbles')->insert([
            'source_key' => 'lastfm-scrobble-1',
            'artist_name' => 'The Kilimanjaro Darkjazz Ensemble',
            'track_name' => 'Caravan!',
            'track_mbid' => 'lastfm-track-id',
            'album_name' => 'Here Be Dragons',
            'album_id' => $album->id,
            'track_id' => $source->id,
            'listened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TrackAliasReconciliationService::class)->reconcile($source, $canonical);

        $this->assertDatabaseMissing('tracks', ['id' => $source->id]);
        $this->assertDatabaseHas('scrobbles', [
            'source_key' => 'lastfm-scrobble-1',
            'track_id' => $canonical->id,
        ]);
        $this->assertDatabaseHas('track_aliases', [
            'track_id' => $canonical->id,
            'name' => 'Caravan!',
        ]);
    }
}
