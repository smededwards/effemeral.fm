<?php

namespace App\Console\Commands;

use App\Services\Catalog\AlbumReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Audits and optionally merges safe duplicate album records.
 */
#[Signature('catalog:reconcile-albums {--apply : Apply safe merges instead of reporting candidates} {--limit=0 : Limit the number of duplicate groups inspected}')]
#[Description('Reconcile safe duplicate album records without merging distinct provider-backed records')]
class ReconcileAlbums extends Command
{
    /**
     * Run the duplicate album reconciliation.
     *
     * @param  AlbumReconciliationService  $reconciliation  The album reconciliation service.
     * @return int The command exit status.
     */
    public function handle(AlbumReconciliationService $reconciliation): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $groups = $reconciliation->groups($limit > 0 ? $limit : null);

        if ($groups->isEmpty()) {
            $this->info('No duplicate album groups found.');

            return self::SUCCESS;
        }

        $retired = 0;
        foreach ($groups as $group) {
            $label = $group['normalised_title'].' ('.$group['albums']->count().' records)';
            if (! $group['safe']) {
                $this->line('REVIEW '.$label.': '.$group['reason']);

                continue;
            }

            if (! $this->option('apply')) {
                $this->line('SAFE '.$label.'; no changes made.');

                continue;
            }

            $merged = $reconciliation->merge($group['albums']);
            $retired += $merged;
            $this->info('MERGED '.$label.'; retired '.$merged.' duplicate record(s).');
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to merge SAFE groups.');
        } else {
            $this->info('Retired '.$retired.' duplicate album record(s).');
        }

        return self::SUCCESS;
    }
}
