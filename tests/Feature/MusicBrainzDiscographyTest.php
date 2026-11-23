<?php

namespace Tests\Feature;

use App\Jobs\ImportMusicBrainzArtistDiscography;
use App\Models\Album;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Models\RecommendationRun;
use App\Services\Catalog\CatalogWriter;
use App\Services\MusicBrainz\MusicBrainzClient;
use App\Services\Spotify\SpotifyClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MusicBrainzDiscographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_discography_refresh_queues_for_artists_with_a_musicbrainz_id(): void
    {
        Queue::fake();
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
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
            ->assertSee('Refresh Discography')
            ->assertSee('Latest refresh: Queued');
        $this->post(route('artists.musicbrainz-discography.store', $artist))
            ->assertSessionHas('error', 'A MusicBrainz discography import is already in progress for this artist.');
        Queue::assertPushed(ImportMusicBrainzArtistDiscography::class, 1);
    }

    public function test_refresh_imports_album_and_ep_matches_without_marking_them_saved_and_creates_a_draft(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        config()->set('services.spotify.access_token', 'test-token');
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        $run = MusicBrainzDiscographyImportRun::query()->create(['artist_id' => $artist->id, 'status' => 'queued']);
        $releaseGroups = [
            ['id' => 'album-group', 'title' => 'Blue Record', 'primary-type' => 'Album', 'first-release-date' => '2009-10-13'],
            ['id' => 'ep-group', 'title' => 'Bunker Sessions', 'primary-type' => 'EP', 'first-release-date' => '2010'],
            ['id' => 'single-group', 'title' => 'Tour Single', 'primary-type' => 'Single'],
        ];
        Http::fake(function (Request $request) use ($releaseGroups): PromiseInterface {
            if (str_contains($request->url(), '/release-group?')) {
                return Http::response(['release-group-count' => count($releaseGroups), 'release-groups' => $releaseGroups]);
            }

            if (str_contains($request->url(), '/search?')) {
                if (str_contains($request->url(), 'Bunker')) {
                    return Http::response(['albums' => ['items' => []]]);
                }

                return Http::response(['albums' => ['items' => [
                    $this->spotifyAlbum('remaster-id', 'Blue Record Remastered'),
                    $this->spotifyAlbum('original-id', 'Blue Record'),
                ]]]);
            }

            if (str_contains($request->url(), '/albums/original-id/tracks?')) {
                return Http::response(['items' => [[
                    'id' => 'track-id',
                    'name' => 'Track One',
                    'uri' => 'spotify:track:track-id',
                    'track_number' => 1,
                    'disc_number' => 1,
                    'duration_ms' => 180000,
                ]], 'next' => null]);
            }

            return Http::response([], 404);
        });

        $job = new ImportMusicBrainzArtistDiscography($run->id);
        $job->handle(app(MusicBrainzClient::class), app(SpotifyClient::class), app(CatalogWriter::class));

        $matchedAlbum = Album::query()->where('musicbrainz_release_group_mbid', 'album-group')->firstOrFail();
        $this->assertSame('original-id', $matchedAlbum->spotify_id);
        $this->assertSame('album', $matchedAlbum->album_type);
        $this->assertSame('spotify:track:track-id', $matchedAlbum->tracks()->value('spotify_uri'));
        $this->assertDatabaseMissing('spotify_album_collections', ['album_id' => $matchedAlbum->id]);
        $this->assertDatabaseHas('albums', [
            'musicbrainz_release_group_mbid' => 'ep-group',
            'album_type' => 'ep',
            'spotify_id' => null,
        ]);
        $this->assertDatabaseMissing('albums', ['musicbrainz_release_group_mbid' => 'single-group']);
        $this->assertSame(2, $run->fresh()->fetched_count);
        $this->assertSame(1, $run->fresh()->matched_count);
        $this->assertSame(1, $run->fresh()->unmatched_count);

        $response = $this->post(route('catalogue.discography'), ['artist_id' => $artist->id]);
        $recommendationRun = RecommendationRun::query()->latest()->firstOrFail();
        $recommendation = $recommendationRun->albumRecommendations()->firstOrFail();
        $response->assertRedirect(route('recommendations.review.page', $recommendationRun));
        $this->assertSame($matchedAlbum->id, $recommendation->album_id);
        $this->assertNull($recommendation->spotify_saved_album_id);
        $this->get(route('recommendations.review.page', $recommendationRun))->assertOk()->assertSee('Blue Record');

        $job->handle(app(MusicBrainzClient::class), app(SpotifyClient::class), app(CatalogWriter::class));
        $this->assertDatabaseCount('albums', 2);
        $this->assertDatabaseCount('tracks', 1);
        $this->assertDatabaseCount('spotify_saved_albums', 0);
    }

    public function test_musicbrainz_release_group_browse_paginates_and_excludes_non_album_types(): void
    {
        Sleep::fake();
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
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
            (new ImportMusicBrainzArtistDiscography($run->id))->handle(
                app(MusicBrainzClient::class),
                app(SpotifyClient::class),
                app(CatalogWriter::class),
            );
            $this->fail('The refresh should fail after MusicBrainz keeps rate-limiting the request.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $run->fresh()->status);
            $this->assertStringContainsString('rate limit persisted', $run->fresh()->error);
        }
    }

    /** @return array<string, mixed> */
    private function spotifyAlbum(string $id, string $title): array
    {
        return [
            'id' => $id,
            'name' => $title,
            'album_type' => 'album',
            'total_tracks' => 1,
            'release_date' => '2009-10-13',
            'artists' => [['id' => 'baroness-spotify', 'name' => 'Baroness']],
            'external_urls' => ['spotify' => 'https://open.spotify.com/album/'.$id],
            'images' => [],
        ];
    }
}
