<?php

namespace Tests\Feature;

use App\Jobs\ImportLastFmScrobbles;
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
    }
}
