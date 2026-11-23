<?php

namespace App\Http\Controllers;

use App\Models\Scrobble;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use stdClass;

/**
 * Presents Last.fm listening history as ranked artists, albums, and tracks.
 */
class ListeningArchiveController extends Controller
{
    private const PERIODS = [
        '7days' => '7 Days',
        '1month' => '1 Month',
        '3months' => '3 Months',
        '6months' => '6 Months',
        '12months' => '12 Months',
        'overall' => 'All Time',
    ];

    private const TYPES = [
        'artists' => 'Artists',
        'albums' => 'Albums',
        'tracks' => 'Tracks',
    ];

    /**
     * Show the listening archive for the selected date range and filters.
     *
     * @param  Request  $request  The archive filters.
     * @return View The listening archive page.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'period' => ['sometimes', Rule::in(array_keys(self::PERIODS))],
            'type' => ['sometimes', Rule::in(array_keys(self::TYPES))],
        ]);
        $period = $filters['period'] ?? 'overall';
        $type = $filters['type'] ?? 'artists';
        $now = CarbonImmutable::now();
        $since = match ($period) {
            '7days' => $now->subDays(7),
            '1month' => $now->subMonth(),
            '3months' => $now->subMonths(3),
            '6months' => $now->subMonths(6),
            '12months' => $now->subYear(),
            default => null,
        };

        $scrobbles = Scrobble::query()
            ->when($since, fn ($query) => $query->where('listened_at', '>=', $since))
            ->where('listened_at', '<=', $now);

        $query = match ($type) {
            'albums' => (clone $scrobbles)
                ->selectRaw('album_name as name, artist_name, COUNT(*) as scrobble_count, MAX(listened_at) as latest_listened_at')
                ->whereNotNull('album_name')
                ->where('album_name', '<>', '')
                ->groupBy('artist_name', 'album_name'),
            'tracks' => (clone $scrobbles)
                ->selectRaw('track_name as name, artist_name, COUNT(*) as scrobble_count, MAX(listened_at) as latest_listened_at')
                ->whereNotNull('track_name')
                ->where('track_name', '<>', '')
                ->groupBy('artist_name', 'track_name'),
            default => (clone $scrobbles)
                ->selectRaw('artist_name as name, COUNT(*) as scrobble_count, MAX(listened_at) as latest_listened_at')
                ->whereNotNull('artist_name')
                ->where('artist_name', '<>', '')
                ->groupBy('artist_name'),
        };

        $results = $query
            ->orderByDesc('scrobble_count')
            ->orderBy('name')
            ->when($type !== 'artists', fn ($query) => $query->orderBy('artist_name'))
            ->toBase()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (stdClass $result): array => [
                'name' => (string) $result->name,
                'artist' => isset($result->artist_name) ? (string) $result->artist_name : null,
                'scrobbles' => (int) $result->scrobble_count,
                'last_heard' => CarbonImmutable::parse((string) $result->latest_listened_at)->format('Y.m.d'),
            ]);

        return view('listening.index', [
            'period' => $period,
            'periods' => self::PERIODS,
            'type' => $type,
            'types' => self::TYPES,
            'results' => $results,
            'scrobbleCount' => (clone $scrobbles)->count(),
        ]);
    }
}
