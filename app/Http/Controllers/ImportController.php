<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartLastFmImportRequest;
use App\Jobs\ImportLastFmScrobbles;
use App\Jobs\ImportSpotifyLikedTracks;
use App\Jobs\ImportSpotifyPlaylists;
use App\Jobs\ImportSpotifySavedAlbums;
use App\Models\LastFmImportRun;
use App\Models\SpotifyImportRun;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistImportRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

/**
 * Coordinates user-triggered Last.fm and Spotify import jobs.
 */
class ImportController extends Controller
{
    /**
     * Queue a bounded Last.fm import for the requested date range.
     *
     * @param  StartLastFmImportRequest  $request  The validated import request.
     * @return RedirectResponse The import status redirect.
     */
    public function lastFm(StartLastFmImportRequest $request): RedirectResponse
    {
        return $this->queueLastFmImport(
            CarbonImmutable::parse($request->date('from'))->startOfDay(),
            CarbonImmutable::parse($request->date('to'))->endOfDay(),
            $request->integer('limit'),
            'Last.fm import queued.',
        );
    }

    /**
     * Queue the complete Last.fm history import from the configured start date.
     *
     * @return RedirectResponse The import status redirect.
     */
    public function fullLastFm(): RedirectResponse
    {
        return $this->queueLastFmImport(
            CarbonImmutable::parse(config('services.lastfm.import_start'))->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
            null,
            'Full Last.fm import queued.',
        );
    }

    /**
     * Retry a failed Last.fm import using its original range and limit.
     *
     * @param  LastFmImportRun  $importRun  The failed import to retry.
     * @return RedirectResponse The import status redirect.
     */
    public function retryLastFm(LastFmImportRun $importRun): RedirectResponse
    {
        if ($importRun->status !== 'failed') {
            return back()->with('error', 'Only failed imports can be retried.');
        }

        return $this->queueLastFmImport(
            CarbonImmutable::parse((string) $importRun->getRawOriginal('from_at')),
            CarbonImmutable::parse((string) $importRun->getRawOriginal('to_at')),
            $importRun->requested_limit === null ? null : (int) $importRun->requested_limit,
            'Last.fm import retry queued.',
            $importRun->id,
        );
    }

    /**
     * Queue or resume the Spotify saved-album import.
     *
     * @return RedirectResponse The import status redirect.
     */
    public function spotifyAlbums(): RedirectResponse
    {
        $this->clearStaleImportRuns();

        if (SpotifyImportRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('error', 'A Spotify album import is already in progress.');
        }

        $run = SpotifyImportRun::query()
            ->where('source', 'spotify')
            ->where('status', 'failed')
            ->where('next_offset', '>', 0)
            ->latest()
            ->first();

        if ($run !== null) {
            $run->update(['status' => 'queued', 'error' => null, 'completed_at' => null]);
            ImportSpotifySavedAlbums::dispatch($run->id);

            return back()->with('success', 'Spotify saved albums import resumed.');
        }

        $run = SpotifyImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 100,
        ]);

        ImportSpotifySavedAlbums::dispatch($run->id);

        return back()->with('success', 'Spotify saved albums import queued.');
    }

    /**
     * Queue the Spotify liked-track import.
     *
     * @return RedirectResponse The import status redirect.
     */
    public function spotifyLikedTracks(): RedirectResponse
    {
        $this->clearStaleImportRuns();

        if (SpotifyImportRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('error', 'A Spotify library import is already in progress.');
        }

        $run = SpotifyImportRun::query()->create([
            'source' => 'liked_tracks',
            'status' => 'queued',
            'requested_limit' => null,
        ]);

        ImportSpotifyLikedTracks::dispatch($run->id);

        return back()->with('success', 'Spotify liked songs import queued.');
    }

    /**
     * Queue or resume the Spotify playlist import.
     *
     * @return RedirectResponse The import status redirect.
     */
    public function spotifyPlaylists(): RedirectResponse
    {
        $this->clearStaleImportRuns();

        if (SpotifyPlaylistImportRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('error', 'A Spotify playlist import is already in progress.');
        }

        $run = SpotifyPlaylistImportRun::query()
            ->where('source', 'spotify')
            ->where('status', 'failed')
            ->where('next_offset', '>', 0)
            ->latest()
            ->first();

        if ($run !== null) {
            $run->update(['status' => 'queued', 'error' => null, 'completed_at' => null]);
            ImportSpotifyPlaylists::dispatch($run->id);

            return back()->with('success', 'Spotify playlist import resumed.');
        }

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => null,
        ]);

        ImportSpotifyPlaylists::dispatch($run->id);

        return back()->with('success', 'Spotify playlists import queued.');
    }

    /**
     * Retry importing one failed Spotify playlist.
     *
     * @param  SpotifyPlaylist  $playlist  The failed playlist to retry.
     * @return RedirectResponse The import status redirect.
     */
    public function retrySpotifyPlaylist(SpotifyPlaylist $playlist): RedirectResponse
    {
        $this->clearStaleImportRuns();

        if (SpotifyPlaylistImportRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('error', 'A Spotify playlist import is already in progress.');
        }

        $run = SpotifyPlaylistImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 1,
        ]);

        ImportSpotifyPlaylists::dispatch($run->id, $playlist->spotify_playlist_id);

        return back()->with('success', 'Spotify playlist retry queued.');
    }

    private function queueLastFmImport(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $limit,
        string $successMessage,
        ?int $retryOfId = null,
    ): RedirectResponse {
        $this->clearStaleImportRuns();

        if (LastFmImportRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('error', 'A Last.fm import is already in progress.');
        }

        $run = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'queued',
            'from_at' => $from,
            'to_at' => $to,
            'requested_limit' => $limit,
            'retry_of_id' => $retryOfId,
        ]);

        ImportLastFmScrobbles::dispatch($run->id);

        return back()->with('success', $successMessage);
    }

    private function clearStaleImportRuns(): void
    {
        $cutoff = now()->subSeconds(max(3600, (int) config('queue.connections.redis.retry_after', 3900)));
        $message = 'Import marked failed because its worker heartbeat expired.';

        LastFmImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'failed', 'error' => $message, 'completed_at' => now()]);
        SpotifyImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'failed', 'error' => $message, 'completed_at' => now()]);
        SpotifyPlaylistImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'failed', 'error' => $message, 'completed_at' => now()]);
    }
}
