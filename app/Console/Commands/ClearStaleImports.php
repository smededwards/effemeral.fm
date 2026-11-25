<?php

namespace App\Console\Commands;

use App\Models\LastFmImportRun;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Models\SpotifyAlbumEnrichmentRun;
use App\Models\SpotifyImportRun;
use App\Models\SpotifyPlaylistImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Marks abandoned queued or running imports as failed.
 */
#[Signature('imports:clear-stale {--hours=1 : Mark runs older than this many hours as failed} {--dry-run : Report stale runs without changing them}')]
#[Description('Clear stale import and enrichment runs')]
class ClearStaleImports extends Command
{
    /**
     * Mark stale runs consistently across all import sources.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);
        $query = fn ($model) => $model::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<', $cutoff);
        $models = [
            LastFmImportRun::class,
            SpotifyImportRun::class,
            SpotifyAlbumEnrichmentRun::class,
            SpotifyPlaylistImportRun::class,
            MusicBrainzDiscographyImportRun::class,
        ];
        $total = 0;

        foreach ($models as $model) {
            $stale = $query($model);
            $count = $stale->count();
            $total += $count;
            $this->line($model::query()->getModel()->getTable().': '.$count.' stale run(s).');

            if (! $this->option('dry-run') && $count > 0) {
                $stale->update([
                    'status' => 'failed',
                    'error' => 'Import marked failed because its worker heartbeat expired.',
                    'completed_at' => now(),
                ]);
            }
        }

        $this->info($this->option('dry-run')
            ? "Found {$total} stale run(s); no changes made."
            : "Marked {$total} stale run(s) as failed.");

        return self::SUCCESS;
    }
}
