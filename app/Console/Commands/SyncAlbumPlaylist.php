<?php

namespace App\Console\Commands;

use App\Models\AlbumPlaylist;
use App\Models\SpotifyPlaylist;
use App\Services\Spotify\AlbumPlaylistSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Reviews and optionally syncs one Effemeral album playlist to Spotify.
 */
#[Signature('playlists:sync {playlist-id : Effemeral album playlist ID} {--destination=new : Spotify destination: new, existing, or linked} {--spotify-playlist-id= : Existing imported Spotify playlist ID} {--confirm : Apply the reviewed preview to Spotify}')]
#[Description('Preview or sync an Effemeral album playlist to Spotify')]
class SyncAlbumPlaylist extends Command
{
    /**
     * Preview a playlist by default and sync only with explicit confirmation.
     *
     * @param  AlbumPlaylistSyncService  $syncService  The playlist sync service.
     * @return int The command exit status.
     */
    public function handle(AlbumPlaylistSyncService $syncService): int
    {
        $lock = Cache::lock('spotify-album-playlist-sync-command', 3600);
        if (! $lock->get()) {
            $this->error('A Spotify playlist sync is already in progress.');

            return self::FAILURE;
        }

        try {
            $playlist = AlbumPlaylist::query()->findOrFail((int) $this->argument('playlist-id'));
            $destination = (string) $this->option('destination');
            if (! in_array($destination, ['new', 'existing', 'linked'], true)) {
                throw new RuntimeException('The destination must be new, existing, or linked.');
            }

            $selectedPlaylist = $this->selectedPlaylist($destination);
            $preview = $syncService->preview($playlist, $destination, $selectedPlaylist);
            $this->displayPreview($preview);

            if (! $this->option('confirm')) {
                $this->warn('Preview only. Re-run with --confirm to apply these changes to Spotify.');

                return self::SUCCESS;
            }

            if ($preview['sync_allowed'] !== true) {
                $this->error('Sync blocked because one or more albums are incomplete.');

                return self::FAILURE;
            }

            $this->info('Applying reviewed changes to Spotify.');
            $syncService->sync($playlist, $preview);
            $this->info('Playlist synced successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Resolve the imported Spotify destination requested by the operator.
     *
     * @param  string  $destination  The requested destination mode.
     * @return SpotifyPlaylist|null The selected destination playlist.
     */
    private function selectedPlaylist(string $destination): ?SpotifyPlaylist
    {
        if ($destination !== 'existing') {
            return null;
        }

        $spotifyPlaylistId = $this->option('spotify-playlist-id');
        if (! is_string($spotifyPlaylistId) || $spotifyPlaylistId === '') {
            throw new RuntimeException('--spotify-playlist-id is required with --destination=existing.');
        }

        return SpotifyPlaylist::query()
            ->where('spotify_playlist_id', $spotifyPlaylistId)
            ->firstOrFail();
    }

    /**
     * Print the review summary before any Spotify mutation is allowed.
     *
     * @param  array<string, mixed>  $preview  The sync preview.
     */
    private function displayPreview(array $preview): void
    {
        $this->line('Destination: '.($preview['destination_name'] ?? 'Unknown Spotify playlist'));
        $this->line('Tracks to add: '.count($preview['add_uris'] ?? []));
        $this->line('Tracks to remove: '.count($preview['remove_uris'] ?? []));
        $this->line('Albums incomplete: '.count($preview['incomplete_albums'] ?? []));
        $this->line('Unmanaged Spotify tracks kept: '.($preview['unmanaged_count'] ?? 0));
    }
}
