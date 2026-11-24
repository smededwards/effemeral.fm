<?php

namespace App\Services\Imports;

use App\Models\LastFmImportRun;
use App\Models\Scrobble;
use App\Models\SpotifyConnection;
use App\Models\SpotifyImportRun;
use App\Models\SpotifyPlaylistImportRun;

/**
 * Builds the import status data shown in the imports page.
 */
class ImportStatusPageService
{
    /**
     * @return array<string, mixed> The import status data.
     */
    public function data(): array
    {
        return [
            'latestImport' => LastFmImportRun::query()->latest()->first(),
            'recentImports' => LastFmImportRun::query()->latest()->limit(10)->get(),
            'latestSpotifyImport' => SpotifyImportRun::query()->latest()->first(),
            'latestSpotifyPlaylistImport' => SpotifyPlaylistImportRun::query()->latest()->first(),
            'spotifyConnection' => SpotifyConnection::query()->latest()->first(),
            'scrobbleCount' => Scrobble::query()->count(),
            'defaultFrom' => now()->subDays(90)->toDateString(),
            'defaultTo' => now()->toDateString(),
        ];
    }
}
