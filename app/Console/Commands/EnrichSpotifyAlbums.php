<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\SpotifyAlbumEnrichmentRun;
use App\Services\Catalog\CatalogWriter;
use App\Services\Spotify\SpotifyClient;
use App\Services\Spotify\SpotifyRateLimitException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Enriches existing Spotify-linked albums with missing canonical tracks.
 */
#[Signature('albums:enrich-spotify {--scope=priority : Selection scope: priority, canonical, or all} {--artist-id= : Only process albums for one artist ID} {--after-id=0 : Start after this album ID} {--limit=10 : Albums per batch} {--pause-every=3 : Pause after this many albums} {--pause=10 : Pause duration in seconds} {--resume : Resume the latest paused or failed run} {--continuous : Continue through successive batches until no eligible albums remain} {--dry-run : List eligible albums without requesting Spotify data}')]
#[Description('Enrich existing Spotify-linked albums with missing tracks')]
class EnrichSpotifyAlbums extends Command
{
    private bool $retryFailed = false;

    /**
     * Run a resumable Spotify album enrichment batch or continuous process.
     *
     * @param  SpotifyClient  $client  The Spotify API client.
     * @param  CatalogWriter  $catalog  The canonical catalogue writer.
     * @return int The command exit status.
     */
    public function handle(SpotifyClient $client, CatalogWriter $catalog): int
    {
        $lock = Cache::lock('spotify-album-enrichment', 86400);
        if (! $lock->get()) {
            $this->error('A Spotify album enrichment is already in progress.');

            return self::FAILURE;
        }

        try {
            $this->retryFailed = (bool) $this->option('resume');
            $run = $this->option('dry-run') && ! $this->option('resume')
                ? $this->newRun()
                : $this->resolveRun();

            if ($this->option('dry-run')) {
                return $this->dryRun($run);
            }

            $this->prepareRun($run);

            do {
                $processedThisBatch = $this->processBatch($run, $client, $catalog);
                $run->refresh();
                $this->retryFailed = false;

                if (! $this->option('continuous') || $processedThisBatch === 0 || $run->status === 'paused') {
                    break;
                }

                $this->line('Batch complete; continuing after a short pause.');
                $this->pause($run->pause_seconds);
            } while (true);

            if ($run->status !== 'paused') {
                $run->update([
                    'status' => $this->hasEligibleAlbums($run) ? 'paused' : 'completed',
                    'completed_at' => now(),
                ]);
            }

            $this->info('Run #'.$run->id.' '.$run->status.': '.$run->enriched_count.' enriched, '
                .$run->failed_count.' failed, cursor '.($run->last_album_id ?? 0).'.');

            return $run->status === 'completed' || $run->failed_count === 0 ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Create a new run or recover the latest resumable run.
     */
    private function resolveRun(): SpotifyAlbumEnrichmentRun
    {
        if ($this->option('resume')) {
            $run = SpotifyAlbumEnrichmentRun::query()
                ->whereIn('status', ['paused', 'failed'])
                ->latest()
                ->first();

            if ($run !== null) {
                return $run;
            }
        }

        $run = $this->newRun();
        $run->saveOrFail();

        return $run;
    }

    /**
     * Build a run from the current command options without persisting it.
     */
    private function newRun(): SpotifyAlbumEnrichmentRun
    {
        $scope = (string) $this->option('scope');
        if (! in_array($scope, ['priority', 'canonical', 'all'], true)) {
            $this->fail('The scope must be priority, canonical, or all.');
        }

        return new SpotifyAlbumEnrichmentRun([
            'scope' => $scope,
            'status' => 'queued',
            'requested_limit' => max(1, (int) $this->option('limit')),
            'pause_every' => max(1, (int) $this->option('pause-every')),
            'pause_seconds' => max(0, (int) $this->option('pause')),
        ]);
    }

    /**
     * Apply command state and calculate the candidate total once.
     */
    private function prepareRun(SpotifyAlbumEnrichmentRun $run): void
    {
        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'error' => null,
            'completed_at' => null,
        ]);

        if ($run->total_candidates === null) {
            $run->update(['total_candidates' => $this->candidateQuery($run)->count()]);
        }

        $this->info('Spotify enrichment run #'.$run->id.' ('.$run->scope.') with '
            .$run->total_candidates.' candidate(s).');
        $this->line('Completed albums leave the queue automatically; use --resume to retry failures.');
    }

    /**
     * List the next candidate records without contacting Spotify.
     */
    private function dryRun(SpotifyAlbumEnrichmentRun $run): int
    {
        $albums = $this->candidateQuery($run)->limit($run->requested_limit)->get();
        $albums->each(fn (Album $album) => $this->line($album->id.' '.$album->displayTitle()));
        $this->info('Listed '.$albums->count().' eligible albums.');

        return self::SUCCESS;
    }

    /**
     * Process one bounded batch and persist progress after every album.
     */
    private function processBatch(SpotifyAlbumEnrichmentRun $run, SpotifyClient $client, CatalogWriter $catalog): int
    {
        $albums = $this->candidateQuery($run)->limit($run->requested_limit)->get();
        if ($albums->isEmpty() && $run->scope === 'priority' && $run->priority_phase === 0) {
            $run->priority_phase = 1;
            $run->last_album_id = null;
            $run->save();
            $this->info('Priority albums complete; continuing with the remaining canonical albums.');
            $albums = $this->candidateQuery($run)->limit($run->requested_limit)->get();
        }

        if ($albums->isEmpty()) {
            $this->info('No eligible Spotify albums remain.');

            return 0;
        }

        $this->info('Processing batch of '.$albums->count().' album(s).');
        foreach ($albums as $position => $album) {
            try {
                $this->line('['.($position + 1).'/'.$albums->count().'] Fetching '
                    .$album->displayTitle().' (ID '.$album->id.').');
                $tracks = $client->albumTracks((string) $album->spotify_id);
                $this->line('Received '.count($tracks).' Spotify track(s); writing catalogue associations.');
                $catalog->spotifyAlbumTracks($album, $tracks);
                $run->enriched_count++;
                $run->failed_album_ids = array_values(array_diff($run->failed_album_ids ?? [], [$album->id]));
                $this->info('Completed '.$album->displayTitle().'.');
            } catch (SpotifyRateLimitException $exception) {
                $run->status = 'paused';
                $run->error = $exception->getMessage();
                $run->save();
                $this->error('Paused at '.$album->displayTitle().': '.$exception->getMessage());

                return $position + 1;
            } catch (Throwable $exception) {
                $run->failed_count++;
                $failedIds = array_values(array_unique([...($run->failed_album_ids ?? []), $album->id]));
                $run->failed_album_ids = $failedIds;
                $this->warn('Failed '.$album->displayTitle().': '.$exception->getMessage());
            }

            $run->last_album_id = max((int) ($run->last_album_id ?? 0), $album->id);
            $run->processed_count++;
            $run->save();

            if (($position + 1) % $run->pause_every === 0 && $position + 1 < $albums->count()) {
                $this->line('Pausing for '.$run->pause_seconds.' second(s) to protect Spotify quota.');
                $this->pause($run->pause_seconds);
            }
        }

        return $albums->count();
    }

    /**
     * Build the priority-aware query for incomplete canonical albums.
     *
     * @return Builder<Album> The eligible album query.
     */
    private function candidateQuery(SpotifyAlbumEnrichmentRun $run): Builder
    {
        $query = Album::query()
            ->whereNotNull('spotify_id')
            ->when($this->option('artist-id'), fn ($builder, $artistId) => $builder->whereHas(
                'artists',
                fn ($artistQuery) => $artistQuery->whereKey((int) $artistId),
            ))
            ->where(function ($builder): void {
                $builder->whereNull('total_tracks')
                    ->orWhereRaw('(
                        SELECT COUNT(*)
                        FROM album_track_releases
                        INNER JOIN tracks ON tracks.id = album_track_releases.track_id
                        WHERE album_track_releases.album_id = albums.id
                        AND tracks.spotify_uri IS NOT NULL
                    ) < albums.total_tracks');
            });

        if ($run->scope !== 'all') {
            $query->whereIn('album_type', ['album', 'ep'])
                ->whereNot(function ($builder): void {
                    foreach (['live', 'demo', 'bootleg', 'compilation', 'b-side', 'soundtrack', 'remix', 'sampler', 'session', 'radio', 'unplugged', 'box set', 'special'] as $term) {
                        $builder->orWhereRaw('LOWER(title) LIKE ?', ['%'.$term.'%']);
                    }
                });
        }

        if ((int) $this->option('after-id') > 0) {
            $query->where('albums.id', '>', (int) $this->option('after-id'));
        } elseif ($this->retryFailed && ($failedIds = $run->failed_album_ids ?? []) !== []) {
            $query->whereIn('albums.id', $failedIds);
        } elseif ($run->last_album_id !== null) {
            $query->where('albums.id', '>', $run->last_album_id);
        }

        if ($run->scope === 'priority' && $run->priority_phase === 0) {
            $query->where(function ($builder): void {
                $builder->whereExists(function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('album_playlist_items')
                        ->whereColumn('album_playlist_items.album_id', 'albums.id');
                })->orWhereExists(function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('spotify_album_collections')
                        ->whereColumn('spotify_album_collections.album_id', 'albums.id');
                });
            });
        }

        $query->orderBy('albums.id');

        return $query;
    }

    /**
     * Check whether another eligible album remains after the current cursor.
     */
    private function hasEligibleAlbums(SpotifyAlbumEnrichmentRun $run): bool
    {
        return $this->candidateQuery($run)->exists();
    }

    /**
     * Pause between provider request groups when configured.
     */
    private function pause(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
