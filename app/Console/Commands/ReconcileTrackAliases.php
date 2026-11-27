<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Track;
use App\Services\Catalog\TrackAliasReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Audits and optionally reconciles Last.fm-only tracks with Spotify tracks.
 */
#[Signature('catalog:reconcile-track-aliases {--apply : Apply safe reconciliations instead of reporting candidates} {--limit=0 : Limit the number of candidates inspected} {--track-id= : Limit reconciliation to one source track ID}')]
#[Description('Reconcile Last.fm-only track titles with existing Spotify tracks')]
class ReconcileTrackAliases extends Command
{
    /**
     * Run the track alias reconciliation.
     *
     * @param  TrackAliasReconciliationService  $reconciliation  The reconciliation service.
     * @return int The command exit status.
     */
    public function handle(TrackAliasReconciliationService $reconciliation): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $trackId = $this->option('track-id');
        $trackId = is_numeric($trackId) ? (int) $trackId : null;
        $candidates = $reconciliation->candidates($limit > 0 ? $limit : null, $trackId);

        if ($candidates->isEmpty()) {
            $this->info('No Last.fm-only track aliases found.');

            return self::SUCCESS;
        }

        foreach ($candidates as $candidate) {
            /** @var Track $source */
            $source = $candidate['source'];
            /** @var Track $canonical */
            $canonical = $candidate['canonical'];
            /** @var Album $sourceAlbum */
            $sourceAlbum = $source->album;
            $label = $source->title.' -> '.$canonical->title.' ('.$sourceAlbum->title.')';

            if (! $this->option('apply')) {
                $this->line('SAFE '.$label.'; no changes made.');

                continue;
            }

            $reconciliation->reconcile($source, $canonical);
            $this->info('RECONCILED '.$label.'.');
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to reconcile SAFE candidates.');
        }

        return self::SUCCESS;
    }
}
