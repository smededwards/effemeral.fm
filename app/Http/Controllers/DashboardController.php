<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartLastFmImportRequest;
use App\Jobs\ImportLastFmScrobbles;
use App\Jobs\ImportSpotifySavedAlbums;
use App\Models\AlbumRecommendation;
use App\Models\LastFmImportRun;
use App\Models\RecommendationRun;
use App\Models\Scrobble;
use App\Models\SpotifyAlbumImportRun;
use App\Models\SpotifyConnection;
use App\Models\SpotifySavedAlbum;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use stdClass;

class DashboardController extends Controller
{
    public function index(): View
    {
        $incompleteAlbums = $this->incompleteAlbums();
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
            'incompleteAlbums' => $incompleteAlbums,
            'topArtists' => $artists,
            'topAlbums' => $albums,
            'latestRecommendation' => RecommendationRun::query()
                ->with('albumRecommendations.spotifySavedAlbum')
                ->latest()
                ->first(),
            'scrobbleCount' => Scrobble::query()->count(),
            'defaultFrom' => now()->subDays(90)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }

    /**
     * @return Collection<int, array{id: int, artist: string, name: string, heard: int, total: int, percentage: int, spotify_url: string|null, cover_image_url: string|null, manual_override: bool}>
     */
    private function incompleteAlbums(): Collection
    {
        $heardTracks = [];

        foreach (Scrobble::query()->whereNotNull('album_name')->get(['artist_name', 'album_name', 'track_name']) as $scrobble) {
            $albumKey = $this->normalise($scrobble->artist_name).'|'.$this->normalise((string) $scrobble->album_name);
            $heardTracks[$albumKey][$this->normalise($scrobble->track_name)] = true;
        }

        return SpotifySavedAlbum::query()
            ->latest('saved_at')
            ->get()
            ->map(function (SpotifySavedAlbum $album) use ($heardTracks): ?array {
                $tracks = collect(data_get($album->tracks, 'items', []))
                    ->filter(fn (mixed $track): bool => is_array($track) && is_string($track['name'] ?? null))
                    ->values();

                // Albums with partial tracklists are deferred until Spotify track pagination exists.
                if ($album->completion_override === true || $tracks->isEmpty() || $tracks->count() !== $album->total_tracks) {
                    return null;
                }

                $albumKey = $this->normalise((string) $album->primary_artist_name).'|'.$this->normalise($album->name);
                $heard = $tracks
                    ->filter(fn (array $track): bool => isset($heardTracks[$albumKey][$this->normalise($track['name'])]))
                    ->count();

                if ($album->completion_override !== false && $heard === $tracks->count()) {
                    return null;
                }

                return [
                    'id' => $album->id,
                    'artist' => $album->primary_artist_name ?? 'Unknown artist',
                    'name' => $album->name,
                    'heard' => $heard,
                    'total' => $tracks->count(),
                    'percentage' => (int) floor(($heard / $tracks->count()) * 100),
                    'spotify_url' => $album->spotify_url,
                    'cover_image_url' => $album->cover_image_url,
                    'manual_override' => $album->completion_override !== null,
                ];
            })
            ->filter()
            ->sortBy('percentage')
            ->take(10)
            ->values();
    }

    public function generateRecommendations(): RedirectResponse
    {
        $albums = $this->incompleteAlbums();

        if ($albums->isEmpty()) {
            return back()->with('error', 'No incomplete albums are available for recommendation.');
        }

        $run = RecommendationRun::query()->create([
            'source' => 'lastfm_spotify',
            'status' => 'draft',
            'generated_at' => now(),
        ]);

        foreach ($albums as $position => $album) {
            AlbumRecommendation::query()->create([
                'recommendation_run_id' => $run->id,
                'spotify_saved_album_id' => $album['id'],
                'position' => $position + 1,
                'heard_tracks' => $album['heard'],
                'total_tracks' => $album['total'],
                'completion_percentage' => $album['percentage'],
            ]);
        }

        return back()->with('success', 'Draft album recommendations generated.');
    }

    public function markAlbumComplete(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => true]);

        return back()->with('success', 'Album marked as heard.');
    }

    public function markAlbumIncomplete(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => false]);

        return back()->with('success', 'Album marked as incomplete.');
    }

    public function clearAlbumCompletionOverride(SpotifySavedAlbum $album): RedirectResponse
    {
        $album->update(['completion_override' => null]);

        return back()->with('success', 'Album completion override cleared.');
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
