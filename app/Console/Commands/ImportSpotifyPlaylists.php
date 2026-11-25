<?php

namespace App\Console\Commands;

use App\Jobs\ImportSpotifyPlaylists as ImportSpotifyPlaylistsJob;
use App\Models\SpotifyPlaylistImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Imports Spotify playlists through the resumable playlist job.
 */
#[Signature('spotify:import-playlists {--limit=0 : Maximum playlists, or 0 for all} {--resume : Resume the latest failed run} {--only-new : Skip playlists already imported locally} {--enrich : Fetch missing album tracks and artist genres}')]
#[Description('Import Spotify playlists')]
class ImportSpotifyPlaylists extends Command
{
    /**
     * Run a playlist import synchronously with visible progress.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        $lock = Cache::lock('spotify-playlist-import-command', 3600);
        if (! $lock->get()) {
            $this->error('A Spotify playlist import is already in progress.');

            return self::FAILURE;
        }

        try {
            $run = $this->resumeRun();
            if ($run === null) {
                $limit = max(0, (int) $this->option('limit'));
                $run = SpotifyPlaylistImportRun::query()->create([
                    'source' => 'spotify',
                    'status' => 'queued',
                    'requested_limit' => $limit > 0 ? $limit : null,
                ]);
            }

            $this->info('Starting Spotify playlist import run #'.$run->id.'.');
            ImportSpotifyPlaylistsJob::dispatchSync(
                $run->id,
                null,
                (bool) $this->option('only-new'),
                (bool) $this->option('enrich'),
            );
            $result = $run->fresh();
            $this->info('Completed: '.$result->fetched_count.' tracks across '.$result->imported_count.' imported playlists, '
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
     * Return the latest resumable failed playlist import.
     */
    private function resumeRun(): ?SpotifyPlaylistImportRun
    {
        if (! $this->option('resume')) {
            return null;
        }

        return SpotifyPlaylistImportRun::query()
            ->where('source', 'spotify')
            ->where('status', 'failed')
            ->where('next_offset', '>', 0)
            ->latest()
            ->first();
    }
}
