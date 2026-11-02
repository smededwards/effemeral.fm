<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartLastFmImportRequest;
use App\Jobs\ImportLastFmScrobbles;
use App\Jobs\ImportSpotifySavedAlbums;
use App\Models\LastFmImportRun;
use App\Models\Scrobble;
use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifyConnection;
use App\Models\SpotifySavedAlbum;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use stdClass;

class DashboardController extends Controller
{
    public function index(): View
    {
        $artists = Scrobble::query()
            ->select('artist_name')
            ->selectRaw('COUNT(*) as scrobble_count')
            ->selectRaw('MAX(listened_at) as latest_listened_at')
            ->groupBy('artist_name')
            ->orderByDesc('scrobble_count')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn (stdClass $artist): array => [
                'name' => (string) $artist->artist_name,
                'scrobbles' => (int) $artist->scrobble_count,
                'last_listened_at' => CarbonImmutable::parse((string) $artist->latest_listened_at)->toDateString(),
            ]);

        $albums = Scrobble::query()
            ->select('artist_name', 'album_name')
            ->selectRaw('COUNT(*) as scrobble_count')
            ->selectRaw('MAX(listened_at) as latest_listened_at')
            ->whereNotNull('album_name')
            ->where('album_name', '<>', '')
            ->groupBy('artist_name', 'album_name')
            ->orderByDesc('scrobble_count')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn (stdClass $album): array => [
                'artist' => (string) $album->artist_name,
                'name' => (string) $album->album_name,
                'scrobbles' => (int) $album->scrobble_count,
                'last_listened_at' => CarbonImmutable::parse((string) $album->latest_listened_at)->toDateString(),
            ]);

        return view('dashboard', [
            'latestImport' => LastFmImportRun::query()->latest()->first(),
            'recentImports' => LastFmImportRun::query()->latest()->limit(10)->get(),
            'latestSpotifyImport' => SpotifyAlbumImportRun::query()->latest()->first(),
            'spotifyConnection' => SpotifyConnection::query()->latest()->first(),
            'spotifySavedAlbums' => SpotifySavedAlbum::query()->latest('saved_at')->get(),
            'topArtists' => $artists,
            'topAlbums' => $albums,
            'scrobbleCount' => Scrobble::query()->count(),
            'defaultFrom' => now()->subDays(90)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    public function import(StartLastFmImportRequest $request): RedirectResponse
    {
        return $this->queueImport(
            CarbonImmutable::parse($request->date('from'))->startOfDay(),
            CarbonImmutable::parse($request->date('to'))->endOfDay(),
            $request->integer('limit'),
            'Last.fm import queued.',
        );
    }

    public function fullImport(): RedirectResponse
    {
        return $this->queueImport(
            CarbonImmutable::parse(config('services.lastfm.import_start'))->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
            null,
            'Full Last.fm import queued.',
        );
    }

    public function retry(LastFmImportRun $importRun): RedirectResponse
    {
        if ($importRun->status !== 'failed') {
            return back()->with('error', 'Only failed imports can be retried.');
        }

        return $this->queueImport(
            CarbonImmutable::parse((string) $importRun->getRawOriginal('from_at')),
            CarbonImmutable::parse((string) $importRun->getRawOriginal('to_at')),
            $importRun->requested_limit === null ? null : (int) $importRun->requested_limit,
            'Last.fm import retry queued.',
            $importRun->id,
        );
    }

    public function spotifyAlbumsImport(): RedirectResponse
    {
        $activeImport = SpotifyAlbumImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($activeImport) {
            return back()->with('error', 'A Spotify album import is already in progress.');
        }

        $run = SpotifyAlbumImportRun::query()->create([
            'source' => 'spotify',
            'status' => 'queued',
            'requested_limit' => 100,
        ]);

        ImportSpotifySavedAlbums::dispatch($run->id);

        return back()->with('success', 'Spotify saved albums import queued.');
    }

    private function queueImport(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $limit,
        string $successMessage,
        ?int $retryOfId = null,
    ): RedirectResponse {
        $activeImport = LastFmImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($activeImport) {
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
}
