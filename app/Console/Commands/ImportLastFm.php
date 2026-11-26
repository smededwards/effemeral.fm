<?php

namespace App\Console\Commands;

use App\Jobs\ImportLastFmScrobbles;
use App\Models\LastFmImportRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Imports a bounded Last.fm listening-history range.
 */
#[Signature('lastfm:import {--from= : Start date or datetime} {--to= : End date or datetime} {--limit= : Maximum scrobbles} {--resume : Resume the latest failed run}')]
#[Description('Import Last.fm listening history')]
class ImportLastFm extends Command
{
    /**
     * Run a Last.fm import synchronously with visible progress.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        $lock = Cache::lock('lastfm-import-command', 3600);
        if (! $lock->get()) {
            $this->error('A Last.fm import is already in progress.');

            return self::FAILURE;
        }

        try {
            $run = $this->runToProcess();
            if ($run === null) {
                $run = LastFmImportRun::query()->create([
                    'source' => 'lastfm',
                    'status' => 'queued',
                    'from_at' => CarbonImmutable::parse($this->option('from') ?: config('services.lastfm.import_start'))->startOfDay(),
                    'to_at' => CarbonImmutable::parse($this->option('to') ?: now())->endOfDay(),
                    'requested_limit' => $this->option('limit') === null ? null : max(1, (int) $this->option('limit')),
                ]);
            } else {
                $run->update(['status' => 'queued', 'error' => null, 'completed_at' => null]);
            }

            $this->info('Starting Last.fm import run #'.$run->id);
            ImportLastFmScrobbles::dispatchSync($run->id);
            $result = $run->fresh();
            $this->info('Completed: '.$result->fetched_count.' fetched, '.$result->imported_count.' imported, '
                .$result->skipped_count.' skipped, '.$result->failed_count.' failed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Find a failed run that can be resumed when requested.
     */
    private function runToProcess(): ?LastFmImportRun
    {
        if (! $this->option('resume')) {
            return null;
        }

        return LastFmImportRun::query()->where('status', 'failed')->latest()->first();
    }
}
