<?php

namespace Tests\Feature;

use App\Jobs\ImportMusicBrainzArtistDiscography;
use App\Models\Album;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MusicBrainzDiscographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_discography_refresh_rejects_musicbrainz_only_artists(): void
    {
        Queue::fake();
        $artist = Artist::query()->create([
            'name' => 'MusicBrainz Only Artist',
            'normalised_name' => 'musicbrainz only artist',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);

        $this->post(route('artists.musicbrainz-discography.store', $artist))
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('music_brainz_discography_import_runs', [
            'artist_id' => $artist->id,
        ]);
    }

    public function test_artist_discography_refresh_queues_for_artists_with_a_musicbrainz_id(): void
    {
        Queue::fake();
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'spotify_id' => 'spotify-baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);

        $this->post(route('artists.musicbrainz-discography.store', $artist))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ImportMusicBrainzArtistDiscography::class);
        $this->assertDatabaseHas('music_brainz_discography_import_runs', [
            'artist_id' => $artist->id,
            'status' => 'queued',
        ]);
        $this->get(route('artists.show', $artist))
            ->assertOk()
            ->assertSee('Refresh album metadata')
            ->assertSee('Latest refresh: Queued');
        $this->post(route('artists.musicbrainz-discography.store', $artist))
            ->assertSessionHas('error', 'A MusicBrainz discography import is already in progress for this artist.');
        Queue::assertPushed(ImportMusicBrainzArtistDiscography::class, 1);
    }

    public function test_refresh_matches_existing_albums_without_creating_new_catalogue_records(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        config()->set('services.spotify.access_token', 'test-token');
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'spotify_id' => 'spotify-baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        $existingAlbum = Album::query()->create([
            'title' => 'Studio Album',
            'normalised_title' => 'studio album',
            'spotify_id' => 'spotify-studio-album',
        ]);
        $existingAlbum->artists()->attach($artist->id);
        $run = MusicBrainzDiscographyImportRun::query()->create(['artist_id' => $artist->id, 'status' => 'queued']);
        $releaseGroups = [
            ['id' => 'studio-album', 'title' => 'Studio Album', 'primary-type' => 'Album'],
            ['id' => 'live-album', 'title' => 'Live Album', 'primary-type' => 'Album', 'secondary-types' => ['Live']],
            ['id' => 'demo-album', 'title' => 'Demo Album', 'primary-type' => 'Album', 'secondary-types' => ['Demo']],
            ['id' => 'compilation', 'title' => 'Compilation', 'primary-type' => 'Album', 'secondary-types' => ['Compilation']],
            ['id' => 'ep', 'title' => 'Eligible EP', 'primary-type' => 'EP'],
        ];
        Http::fake(function (Request $request) use ($releaseGroups) {
            if (str_contains($request->url(), '/release-group?')) {
                return Http::response(['release-group-count' => count($releaseGroups), 'release-groups' => $releaseGroups]);
            }

            if (str_contains($request->url(), '/search?')) {
                return Http::response(['albums' => ['items' => []]]);
            }

            return Http::response([], 404);
        });

        (new ImportMusicBrainzArtistDiscography($run->id))->handle(app(MusicBrainzClient::class));

        $this->assertDatabaseHas('albums', ['musicbrainz_release_group_mbid' => 'studio-album']);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'ep']);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'live-album']);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'demo-album']);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'compilation']);
        $this->assertSame(2, $run->fresh()->fetched_count);
        $this->assertSame(1, $run->fresh()->matched_count);
        $this->assertSame(1, $run->fresh()->unmatched_count);
        $this->assertSame(1, Album::query()->count());
    }

    public function test_refresh_updates_existing_album_metadata_without_requesting_spotify_data(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        config()->set('services.spotify.access_token', 'test-token');
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'spotify_id' => 'spotify-baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        $album = Album::query()->create([
            'title' => 'Blue Record',
            'normalised_title' => 'blue record',
            'spotify_id' => 'existing-spotify-id',
        ]);
        $album->artists()->attach($artist->id);
        $run = MusicBrainzDiscographyImportRun::query()->create(['artist_id' => $artist->id, 'status' => 'queued']);
        $releaseGroups = [
            ['id' => 'album-group', 'title' => 'Blue Record', 'primary-type' => 'Album', 'first-release-date' => '2009-10-13'],
            ['id' => 'ep-group', 'title' => 'Bunker Sessions', 'primary-type' => 'EP', 'first-release-date' => '2010'],
            ['id' => 'single-group', 'title' => 'Tour Single', 'primary-type' => 'Single'],
        ];
        Http::fake(function (Request $request) use ($releaseGroups) {
            if (str_contains($request->url(), '/release-group?')) {
                return Http::response(['release-group-count' => count($releaseGroups), 'release-groups' => $releaseGroups]);
            }

            return Http::response([], 404);
        });

        $job = new ImportMusicBrainzArtistDiscography($run->id);
        $job->handle(app(MusicBrainzClient::class));

        $matchedAlbum = Album::query()->where('musicbrainz_release_group_mbid', 'album-group')->firstOrFail();
        $this->assertSame('existing-spotify-id', $matchedAlbum->spotify_id);
        $this->assertSame('album', $matchedAlbum->album_type);
        $this->assertSame('2009-10-13', $matchedAlbum->release_date);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'ep-group']);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'single-group']);
        $this->assertSame(2, $run->fresh()->fetched_count);
        $this->assertSame(1, $run->fresh()->matched_count);
        $this->assertSame(1, $run->fresh()->unmatched_count);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.spotify.com'));
    }

    public function test_musicbrainz_release_group_browse_paginates_and_excludes_non_album_types(): void
    {
        Sleep::fake();
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'spotify_id' => 'spotify-baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        $page = array_fill(0, 100, ['id' => 'album-group', 'primary-type' => 'Album']);
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/release-group*' => Http::sequence()
                ->push(['release-group-count' => 101, 'release-groups' => $page])
                ->push(['release-group-count' => 101, 'release-groups' => [['id' => 'single-group', 'primary-type' => 'Single']]]),
        ]);

        $groups = app(MusicBrainzClient::class)->artistReleaseGroups($artist);

        $this->assertCount(100, $groups);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'offset=100'));
    }

    public function test_musicbrainz_artist_metadata_includes_location_and_cross_references(): void
    {
        Sleep::fake();
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        Http::fake([
            'musicbrainz.org/ws/2/artist/artist-mbid*' => Http::response([
                'id' => 'artist-mbid',
                'name' => 'Baroness',
                'country' => 'US',
                'begin-area' => ['name' => 'Savannah'],
                'genres' => [
                    ['name' => 'sludge metal', 'count' => 5],
                    ['name' => 'metal', 'count' => 2],
                ],
            ]),
        ]);

        $metadata = app(MusicBrainzClient::class)->artistMetadata($artist);

        $this->assertSame('US', $metadata['country_code']);
        $this->assertSame('Savannah', $metadata['location']);
        $this->assertSame([], $metadata['aliases']);
        $this->assertSame([], $metadata['relationships']);
    }

    public function test_persistent_musicbrainz_rate_limits_mark_the_refresh_as_failed(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'musicbrainz.org/ws/2/release-group*' => Http::sequence()
                ->push([], 503)
                ->push([], 503)
                ->push([], 503),
        ]);
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        $run = MusicBrainzDiscographyImportRun::query()->create(['artist_id' => $artist->id, 'status' => 'queued']);

        try {
            (new ImportMusicBrainzArtistDiscography($run->id))->handle(app(MusicBrainzClient::class));
            $this->fail('The refresh should fail after MusicBrainz keeps rate-limiting the request.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $run->fresh()->status);
            $this->assertStringContainsString('rate limit persisted', $run->fresh()->error);
        }
    }
}
