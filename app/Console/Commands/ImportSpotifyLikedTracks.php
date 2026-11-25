<?php

namespace App\Console\Commands;

use App\Jobs\ImportSpotifyLikedTracks as ImportSpotifyLikedTracksJob;
use App\Models\SpotifyImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Imports Spotify liked tracks through the existing idempotent job.
 */
#[Signature('spotify:import-liked-tracks {--limit=0 : Maximum liked tracks, or 0 for all} {--enrich : Fetch artist genres during import}')]
#[Description('Import Spotify liked tracks')]
class ImportSpotifyLikedTracks extends Command
{
    /**
     * Run a liked-track import synchronously with visible progress.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        $lock = Cache::lock('spotify-liked-track-import-command', 3600);
        if (! $lock->get()) {
            $this->error('A Spotify liked-track import is already in progress.');

            return self::FAILURE;
        }

        try {
            $limit = max(0, (int) $this->option('limit'));
            $run = SpotifyImportRun::query()->create([
                'source' => 'liked_tracks',
                'status' => 'queued',
                'requested_limit' => $limit > 0 ? $limit : null,
            ]);
            $this->info('Starting Spotify liked-track import run #'.$run->id.'.');
            ImportSpotifyLikedTracksJob::dispatchSync($run->id, (bool) $this->option('enrich'));
            $result = $run->fresh();
            $this->info('Completed: '.$result->fetched_count.' fetched, '.$result->imported_count.' imported, '
                .$result->updated_count.' updated, '.$result->skipped_count.' skipped.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
