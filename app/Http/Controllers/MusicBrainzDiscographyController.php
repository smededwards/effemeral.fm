<?php

namespace App\Http\Controllers;

use App\Jobs\ImportMusicBrainzArtistDiscography;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Starts MusicBrainz enrichment for an existing artist.
 */
class MusicBrainzDiscographyController extends Controller
{
    /**
     * Queue a discography refresh for an artist with a MusicBrainz identity.
     *
     * @param  Artist  $artist  The existing artist to enrich.
     * @return RedirectResponse The artist page redirect.
     */
    public function store(Artist $artist): RedirectResponse
    {
        if ($artist->musicbrainz_mbid === null || ! Artist::query()->withSourceEvidence()->whereKey($artist->id)->exists()) {
            return back()->with('error', 'The artist needs MusicBrainz identity and Last.fm or Spotify source evidence before enrichment.');
        }

        $run = DB::transaction(function () use ($artist): ?MusicBrainzDiscographyImportRun {
            $lockedArtist = Artist::query()->lockForUpdate()->findOrFail($artist->id);
            $lockedArtist->musicBrainzDiscographyImportRuns()
                ->whereIn('status', ['queued', 'running'])
                ->where('updated_at', '<', now()->subHours(2))
                ->update(['status' => 'failed', 'error' => 'This import did not finish. Retry it to continue.']);

            if ($lockedArtist->musicBrainzDiscographyImportRuns()->whereIn('status', ['queued', 'running'])->exists()) {
                return null;
            }

            return MusicBrainzDiscographyImportRun::query()->create([
                'artist_id' => $lockedArtist->id,
                'status' => 'queued',
            ]);
        });

        if ($run === null) {
            return back()->with('error', 'A MusicBrainz discography import is already in progress for this artist.');
        }

        ImportMusicBrainzArtistDiscography::dispatch($run->id);

        return back()->with('success', 'MusicBrainz discography enrichment queued.');
    }
}
