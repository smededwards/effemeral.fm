<?php

namespace Tests\Feature;

use App\Models\Artist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ImportMusicBrainzArtistMetadataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It does not enrich an artist whose only identity is MusicBrainz.
     */
    public function test_musicbrainz_only_artists_are_not_enriched(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        $artist = Artist::query()->create([
            'name' => 'MusicBrainz Only Artist',
            'normalised_name' => 'musicbrainz only artist',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);

        $this->artisan('artists:import-musicbrainz --refresh')->assertSuccessful();

        $this->assertNull($artist->fresh()->musicbrainz_checked_at);
        Http::assertNothingSent();
    }

    /**
     * It enriches only artists that already have a MusicBrainz ID when requested.
     */
    public function test_known_only_import_skips_artists_without_a_musicbrainz_id(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist/artist-mbid*' => Http::response([
                'id' => 'artist-mbid',
                'name' => 'Known Artist',
                'area' => ['name' => 'England'],
                'country' => 'GB',
                'genres' => [],
                'aliases' => [
                    ['name' => 'The Artist Project', 'locale' => 'en', 'type' => 'Artist name'],
                ],
                'relations' => [
                    [
                        'type' => 'member of band',
                        'artist' => ['id' => 'band-mbid', 'name' => 'The Band'],
                        'attributes' => [],
                    ],
                ],
            ]),
        ]);
        $unknownArtist = Artist::query()->create([
            'name' => 'Unknown Artist',
            'normalised_name' => 'unknown artist',
        ]);
        $knownArtist = Artist::query()->create([
            'name' => 'Known Artist',
            'normalised_name' => 'known artist',
            'spotify_id' => 'spotify-known-artist',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);

        $this->artisan('artists:import-musicbrainz --known-only --limit=1')->assertSuccessful();

        $this->assertNull($unknownArtist->fresh()->musicbrainz_checked_at);
        $this->assertNotNull($knownArtist->fresh()->musicbrainz_checked_at);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    /**
     * It starts a known-artist batch after the supplied cursor.
     */
    public function test_known_only_import_can_continue_after_an_artist_id(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist/artist-two*' => Http::response([
                'id' => 'artist-two',
                'name' => 'Second Artist',
                'genres' => [],
            ]),
        ]);
        $firstArtist = Artist::query()->create([
            'name' => 'First Artist',
            'normalised_name' => 'first artist',
            'spotify_id' => 'spotify-first-artist',
            'musicbrainz_mbid' => 'artist-one',
        ]);
        $secondArtist = Artist::query()->create([
            'name' => 'Second Artist',
            'normalised_name' => 'second artist',
            'spotify_id' => 'spotify-second-artist',
            'musicbrainz_mbid' => 'artist-two',
        ]);

        $this->artisan('artists:import-musicbrainz --refresh --known-only --after-id='.$firstArtist->id.' --limit=1')->assertSuccessful();

        $this->assertNull(Artist::query()->where('name', 'First Artist')->value('musicbrainz_checked_at'));
        $this->assertNotNull($secondArtist->fresh()->musicbrainz_checked_at);
        Http::assertSentCount(1);
    }

    public function test_musicbrainz_metadata_is_imported_for_artists_with_a_known_mbid(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist/artist-mbid*' => Http::response([
                'id' => 'artist-mbid',
                'name' => 'The Artist',
                'begin-area' => ['name' => 'Manchester'],
                'area' => ['name' => 'England'],
                'country' => 'GB',
                'genres' => [],
                'aliases' => [
                    ['name' => 'The Artist Project', 'locale' => 'en', 'type' => 'Artist name'],
                ],
                'relations' => [
                    [
                        'type' => 'member of band',
                        'artist' => ['id' => 'band-mbid', 'name' => 'The Band'],
                        'attributes' => [],
                    ],
                ],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
            'lastfm_mbid' => 'artist-mbid',
        ]);

        $this->artisan('artists:import-musicbrainz --limit=1')->assertSuccessful();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'location' => 'Manchester',
            'country_code' => 'GB',
            'musicbrainz_mbid' => 'artist-mbid',
            'location_manual' => false,
            'country_manual' => false,
        ]);
        $this->assertDatabaseHas('artist_aliases', [
            'artist_id' => $artist->id,
            'name' => 'The Artist Project',
            'source' => 'musicbrainz',
        ]);
        $this->assertDatabaseHas('artist_relationships', [
            'artist_id' => $artist->id,
            'related_provider_id' => 'band-mbid',
            'related_name' => 'The Band',
            'relationship_type' => 'member of band',
            'source' => 'musicbrainz',
        ]);
        Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent', 'Effemeral/0.1 (https://github.com/smededwards/effemeral.fm)')
            && $request->url() === 'https://musicbrainz.org/ws/2/artist/artist-mbid?fmt=json&inc=aliases%2Bartist-rels');
        Sleep::assertNeverSlept();
    }

    public function test_provider_errors_skip_an_artist_and_continue_the_batch(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fakeSequence('musicbrainz.org/ws/2/artist*')
            ->pushStatus(503)
            ->pushStatus(503)
            ->pushStatus(503)
            ->push([
                'count' => 1,
                'artists' => [['id' => 'artist-mbid', 'name' => 'The Artist', 'country' => 'GB', 'genres' => []]],
            ]);
        Artist::query()->create([
            'name' => 'Blocked Artist',
            'normalised_name' => 'blocked artist',
            'spotify_id' => 'spotify-blocked-artist',
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
            'spotify_id' => 'spotify-the-artist',
        ]);

        $this->artisan('artists:import-musicbrainz --limit=2')->assertSuccessful();

        $this->assertNull(Artist::query()->where('name', 'Blocked Artist')->value('musicbrainz_checked_at'));
        $this->assertNotNull($artist->fresh()->musicbrainz_checked_at);
    }

    public function test_musicbrainz_search_only_imports_a_unique_exact_name_match(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist*' => Http::response([
                'count' => 2,
                'artists' => [
                    ['id' => 'artist-one', 'name' => 'The Artist', 'begin-area' => ['name' => 'Leeds'], 'country' => 'GB', 'genres' => []],
                    ['id' => 'near-match', 'name' => 'The Artist Project'],
                ],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
            'spotify_id' => 'spotify-the-artist',
        ]);

        $this->artisan('artists:import-musicbrainz --limit=1')->assertSuccessful();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'location' => 'Leeds',
            'country_code' => 'GB',
            'musicbrainz_mbid' => 'artist-one',
        ]);
        Http::assertSent(fn ($request): bool => $request['query'] === 'artist:"The Artist"');
    }

    public function test_a_missing_musicbrainz_id_falls_back_to_exact_name_search(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist/stale-mbid*' => Http::response([], 404),
            'musicbrainz.org/ws/2/artist*' => Http::response([
                'count' => 1,
                'artists' => [[
                    'id' => 'artist-mbid',
                    'name' => 'The Artist',
                    'begin-area' => ['name' => 'Manchester'],
                    'country' => 'US',
                    'genres' => [],
                ]],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
            'lastfm_mbid' => 'stale-mbid',
        ]);

        $this->artisan('artists:import-musicbrainz --limit=1')->assertSuccessful();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'location' => 'Manchester',
            'country_code' => 'US',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
    }

    public function test_musicbrainz_search_does_not_guess_between_duplicate_artist_names(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist*' => Http::response([
                'count' => 2,
                'artists' => [
                    ['id' => 'artist-one', 'name' => 'The Artist', 'begin-area' => ['name' => 'Leeds'], 'genres' => []],
                    ['id' => 'artist-two', 'name' => 'The Artist', 'begin-area' => ['name' => 'Bristol'], 'genres' => []],
                ],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
            'spotify_id' => 'spotify-the-artist',
        ]);

        $this->artisan('artists:import-musicbrainz --limit=1')->assertSuccessful();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'location' => null,
            'region' => null,
            'country_code' => null,
            'musicbrainz_mbid' => null,
        ]);
        $this->assertNotNull($artist->fresh()->musicbrainz_checked_at);
    }

    public function test_manual_location_and_region_are_preserved_during_refresh(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist/artist-mbid*' => Http::response([
                'id' => 'artist-mbid',
                'name' => 'The Artist',
                'begin-area' => ['name' => 'Manchester'],
                'area' => ['name' => 'England'],
                'country' => 'GB',
                'genres' => [],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
            'lastfm_mbid' => 'artist-mbid',
            'location' => 'Bristol',
            'location_manual' => true,
            'region' => 'England',
            'region_manual' => true,
            'country_code' => 'US',
            'country_manual' => true,
        ]);

        $this->artisan('artists:import-musicbrainz --refresh --limit=1')->assertSuccessful();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'location' => 'Bristol',
            'region' => 'England',
            'location_manual' => true,
            'region_manual' => true,
            'country_code' => 'US',
            'country_manual' => true,
        ]);
    }

    public function test_artist_country_and_locality_can_be_manually_edited(): void
    {
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
        ]);

        $this->patch(route('artists.location.update', $artist), [
            'location' => 'Bristol',
            'country_code' => 'GB',
        ])->assertRedirect();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'location' => 'Bristol',
            'location_manual' => true,
            'country_code' => 'GB',
            'country_manual' => true,
        ]);
    }
}
