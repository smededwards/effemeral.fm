<?php

namespace App\Console\Commands;

use App\Services\Spotify\SpotifyClient;
use App\Services\Spotify\SpotifyImportPolicy;
use App\Services\Spotify\SpotifyPlaylistPurgeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Audits and optionally purges locally imported Spotify playlists outside the allowlist.
 */
#[Signature('spotify:purge-playlists {--apply : Apply the purge instead of previewing it}')]
#[Description('Purge unapproved Spotify playlists and catalogue-only records')]
class PurgeSpotifyPlaylists extends Command
{
    /**
     * Run the Spotify playlist purge preview or application.
     *
     * @param  SpotifyClient  $client  The authenticated Spotify client.
     * @param  SpotifyImportPolicy  $policy  The Spotify import policy.
     * @param  SpotifyPlaylistPurgeService  $purge  The purge service.
     * @return int The command exit status.
     */
    public function handle(
        SpotifyClient $client,
        SpotifyImportPolicy $policy,
        SpotifyPlaylistPurgeService $purge,
    ): int {
        $ownerId = $policy->ownerId($client);
        $summary = $this->option('apply') ? $purge->purge($ownerId) : $purge->preview($ownerId);
        $prefix = $this->option('apply') ? 'Purged' : 'Would purge';

        $this->info($prefix.' '.$summary['playlists'].' playlist(s), '.$summary['playlist_items'].' item(s), '
            .$summary['albums'].' album(s), and '.$summary['tracks'].' track(s).');

        if (! $this->option('apply')) {
            $this->line('Dry run only. Re-run with --apply to remove these local records.');
        }

        return self::SUCCESS;
    }
}
