<?php

namespace Tests\Feature;

use App\Jobs\ImportLastFmScrobbles;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\LastFmImportRun;
use App\Models\Scrobble;
use App\Services\LastFm\LastFmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LastFmImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_import_can_be_queued_from_the_dashboard(): void
    {
        Queue::fake();

        $response = $this->post(route('imports.lastfm'), [
            'from' => '2026-01-01',
            'to' => '2026-03-31',
            'limit' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('last_fm_import_runs', [
            'status' => 'queued',
            'requested_limit' => 100,
        ]);
        Queue::assertPushed(ImportLastFmScrobbles::class);
    }

    public function test_an_import_stores_scrobbles_and_is_idempotent(): void
    {
        config()->set('services.lastfm.api_key', 'test-key');
        config()->set('services.lastfm.username', 'test-user');
        Http::fake([
            '*' => Http::response([
                'recenttracks' => [
                    '@attr' => ['totalPages' => '1'],
                    'track' => [
                        [
                            'name' => 'The First Track',
                            'mbid' => 'track-mbid',
                            'artist' => ['#text' => 'The Artist', 'mbid' => 'artist-mbid'],
                            'album' => ['#text' => 'The Album', 'mbid' => 'album-mbid'],
                            'url' => 'https://last.fm/track/1',
                            'date' => ['uts' => '1760000000'],
                        ],
                    ],
                ],
            ]),
        ]);

        $run = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'queued',
            'from_at' => '2026-01-01 00:00:00',
            'to_at' => '2026-03-31 23:59:59',
            'requested_limit' => 100,
        ]);

        $job = new ImportLastFmScrobbles($run->id);
        $job->handle(app(LastFmClient::class));
        $job->handle(app(LastFmClient::class));

        $this->assertDatabaseCount('scrobbles', 1);
        $this->assertDatabaseHas('last_fm_import_runs', [
            'id' => $run->id,
            'status' => 'completed',
            'fetched_count' => 1,
            'imported_count' => 0,
            'skipped_count' => 1,
        ]);
        $this->assertSame('The Album', Scrobble::query()->firstOrFail()->album_name);
        $this->assertNotNull(Scrobble::query()->firstOrFail()->track_id);
        $this->assertDatabaseHas('artists', ['lastfm_mbid' => 'artist-mbid']);
        $this->assertDatabaseHas('albums', ['lastfm_mbid' => 'album-mbid']);
        $this->assertDatabaseHas('tracks', ['lastfm_mbid' => 'track-mbid']);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), config('services.lastfm.endpoint')));
    }

    public function test_lastfm_artist_tags_are_imported_with_allowlist_filtering(): void
    {
        config()->set('services.lastfm.api_key', 'test-key');
        config()->set('services.lastfm.username', 'test-user');
        config()->set('services.lastfm.genre_allowlist', ['post-metal']);
        Http::fake(function ($request) {
            if ($request['method'] === 'artist.getInfo') {
                return Http::response([
                    'artist' => ['tags' => ['tag' => [
                        ['name' => 'post metal'],
                        ['name' => 'nonsense tag'],
                    ]]],
                ]);
            }

            return Http::response([
                'recenttracks' => [
                    '@attr' => ['totalPages' => '1'],
                    'track' => [[
                        'name' => 'Track',
                        'artist' => ['#text' => 'The Artist', 'mbid' => 'artist-mbid'],
                        'album' => ['#text' => 'The Album'],
                        'url' => 'https://last.fm/track/genre',
                        'date' => ['uts' => '1760000000'],
                    ]],
                ],
            ]);
        });

        $run = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'queued',
            'from_at' => '2026-01-01 00:00:00',
            'to_at' => '2026-03-31 23:59:59',
            'requested_limit' => 100,
        ]);

        (new ImportLastFmScrobbles($run->id))->handle(app(LastFmClient::class));

        $artist = Artist::query()->firstOrFail();
        $genre = Genre::query()->firstOrFail();
        $this->assertSame('post metal', $genre->normalised_name);
        $this->assertDatabaseHas('artist_genre', [
            'artist_id' => $artist->id,
            'genre_id' => $genre->id,
            'source' => 'lastfm',
            'suppressed' => false,
        ]);
        $this->assertDatabaseCount('genres', 1);
    }

    public function test_a_full_import_can_be_queued_without_an_item_limit(): void
    {
        Queue::fake();

        $response = $this->post(route('imports.lastfm.full'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Full Last.fm import queued.');
        $this->assertDatabaseHas('last_fm_import_runs', [
            'status' => 'queued',
            'requested_limit' => null,
            'from_at' => '1970-01-02 00:00:00',
        ]);
        Queue::assertPushed(ImportLastFmScrobbles::class);
    }

    public function test_a_failed_import_can_be_retried_as_a_new_run(): void
    {
        Queue::fake();

        $failedRun = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'failed',
            'from_at' => '2026-01-01 00:00:00',
            'to_at' => '2026-03-31 23:59:59',
            'requested_limit' => 100,
            'error' => 'Temporary API failure',
        ]);

        $response = $this->post(route('imports.lastfm.retry', $failedRun));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Last.fm import retry queued.');
        $this->assertDatabaseHas('last_fm_import_runs', [
            'retry_of_id' => $failedRun->id,
            'status' => 'queued',
            'requested_limit' => 100,
        ]);
        Queue::assertPushed(ImportLastFmScrobbles::class);
    }

    public function test_a_stale_import_is_cleared_before_a_new_import_is_queued(): void
    {
        Queue::fake();
        $staleRun = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'running',
            'from_at' => '2026-01-01 00:00:00',
            'to_at' => '2026-03-31 23:59:59',
        ]);
        LastFmImportRun::query()->whereKey($staleRun->id)->update([
            'updated_at' => now()->subHours(2),
        ]);

        $this->post(route('imports.lastfm'), [
            'from' => '2026-04-01',
            'to' => '2026-04-30',
            'limit' => 10,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('last_fm_import_runs', [
            'id' => $staleRun->id,
            'status' => 'failed',
            'error' => 'Import marked failed because its worker heartbeat expired.',
        ]);
    }

    public function test_lastfm_rate_limit_is_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                return Http::response(['error' => 29, 'message' => 'Rate limit exceeded'], 200, ['Retry-After' => '0']);
            }

            return Http::response([
                'recenttracks' => [
                    '@attr' => ['totalPages' => '1'],
                    'track' => [],
                ],
            ]);
        });

        app(LastFmClient::class)->recentTracks(
            now()->subDay()->toImmutable(),
            now()->toImmutable(),
            1,
        );

        $this->assertSame(2, $attempts);
    }

    public function test_lastfm_artist_tag_rate_limit_is_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts === 1
                ? Http::response(['error' => 29, 'message' => 'Rate limit exceeded'], 200, ['Retry-After' => '0'])
                : Http::response(['artist' => ['tags' => ['tag' => [['name' => 'post-metal']]]]]);
        });

        $tags = app(LastFmClient::class)->artistTags('An Artist');

        $this->assertSame(['post-metal'], $tags);
        $this->assertSame(2, $attempts);
    }
}
