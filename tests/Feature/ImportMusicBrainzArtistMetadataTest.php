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
        Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent', 'Effemeral/0.1 (https://github.com/smededwards/effemeral.fm)')
            && $request->url() === 'https://musicbrainz.org/ws/2/artist/artist-mbid?fmt=json');
        Sleep::assertNeverSlept();
    }

    public function test_musicbrainz_search_only_imports_a_unique_exact_name_match(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/artist*' => Http::response([
                'count' => 2,
                'artists' => [
                    ['id' => 'artist-one', 'name' => 'The Artist', 'begin-area' => ['name' => 'Leeds'], 'country' => 'GB'],
                    ['id' => 'near-match', 'name' => 'The Artist Project'],
                ],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
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
                    ['id' => 'artist-one', 'name' => 'The Artist', 'begin-area' => ['name' => 'Leeds']],
                    ['id' => 'artist-two', 'name' => 'The Artist', 'begin-area' => ['name' => 'Bristol']],
                ],
            ]),
        ]);
        $artist = Artist::query()->create([
            'name' => 'The Artist',
            'normalised_name' => 'the artist',
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
