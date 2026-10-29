<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartLastFmImportRequest;
use App\Jobs\ImportLastFmScrobbles;
use App\Models\LastFmImportRun;
use App\Models\Scrobble;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'latestImport' => LastFmImportRun::query()->latest()->first(),
            'scrobbleCount' => Scrobble::query()->count(),
            'defaultFrom' => now()->subDays(90)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    public function import(StartLastFmImportRequest $request): RedirectResponse
    {
        $activeImport = LastFmImportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($activeImport) {
            return back()->with('error', 'A Last.fm import is already in progress.');
        }

        $run = LastFmImportRun::query()->create([
            'source' => 'lastfm',
            'status' => 'queued',
            'from_at' => CarbonImmutable::parse($request->date('from'))->startOfDay(),
            'to_at' => CarbonImmutable::parse($request->date('to'))->endOfDay(),
            'requested_limit' => $request->integer('limit'),
        ]);

        ImportLastFmScrobbles::dispatch($run->id);

        return back()->with('success', 'Last.fm import queued.');
    }
}
