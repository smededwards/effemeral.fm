<?php

namespace App\Console\Commands;

use App\Jobs\ImportSpotifySavedAlbums as ImportSpotifySavedAlbumsJob;
use App\Models\SpotifyImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Imports Spotify saved albums through the resumable saved-album job.
 */
#[Signature('spotify:import-albums {--limit=100 : Maximum saved albums} {--resume : Resume the latest failed run} {--enrich : Fetch album tracks and artist genres during import}')]
#[Description('Import Spotify saved albums')]
class ImportSpotifySavedAlbums extends Command
{
    /**
     * Run a saved-album import synchronously with visible progress.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        return $this->runImport('spotify saved albums', function (): SpotifyImportRun {
            $run = $this->resumeRun();
            if ($run !== null) {
                return $run;
            }

            return SpotifyImportRun::query()->create([
                'source' => 'spotify',
                'status' => 'queued',
                'requested_limit' => max(1, (int) $this->option('limit')),
            ]);
        });
    }

    /**
     * Execute the selected import while applying a command lock.
     *
     * @param  string  $label  The human-readable import label.
     * @param  callable(): SpotifyImportRun  $createRun  The run factory.
     * @return int The command exit status.
     */
    private function runImport(string $label, callable $createRun): int
    {
        $lock = Cache::lock('spotify-library-import-command', 3600);
        if (! $lock->get()) {
            $this->error('A Spotify library import is already in progress.');

            return self::FAILURE;
        }

        try {
            $run = $createRun();
            $run->update(['status' => 'queued', 'error' => null, 'completed_at' => null]);
            $this->info('Starting '.$label.' import run #'.$run->id.'.');
            ImportSpotifySavedAlbumsJob::dispatchSync($run->id, (bool) $this->option('enrich'));
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

    /**
     * Return the latest resumable failed saved-album import.
     */
    private function resumeRun(): ?SpotifyImportRun
    {
        if (! $this->option('resume')) {
            return null;
        }

        return SpotifyImportRun::query()
            ->where('source', 'spotify')
            ->where('status', 'failed')
            ->where('next_offset', '>', 0)
            ->latest()
            ->first();
    }
}
