<?php

namespace App\Console\Commands;

use App\Models\LastFmImportRun;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Models\SpotifyAlbumEnrichmentRun;
use App\Models\SpotifyImportRun;
use App\Models\SpotifyPlaylistImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Displays recent import and enrichment progress.
 */
#[Signature('imports:status {--limit=10 : Number of recent runs per source to show}')]
#[Description('Show import and enrichment status')]
class ShowImportStatus extends Command
{
    /**
     * Display recent runs from each import source.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $this->displayRuns('Last.fm', LastFmImportRun::query()->latest()->limit($limit)->get()->all(), true);
        $this->displayRuns('Spotify library', SpotifyImportRun::query()->latest()->limit($limit)->get()->all(), true);
        $this->displayRuns('Spotify playlists', SpotifyPlaylistImportRun::query()->latest()->limit($limit)->get()->all(), true);
        $this->displayEnrichmentRuns(SpotifyAlbumEnrichmentRun::query()->latest()->limit($limit)->get()->all());
        $this->displayRuns('MusicBrainz discographies', MusicBrainzDiscographyImportRun::query()->latest()->limit($limit)->get()->all(), false);

        return self::SUCCESS;
    }

    /**
     * Render one source's recent runs in a compact table.
     *
     * @param  string  $label  The source label.
     * @param  array<int, LastFmImportRun|SpotifyImportRun|SpotifyPlaylistImportRun|SpotifyAlbumEnrichmentRun|MusicBrainzDiscographyImportRun>  $runs  The recent runs.
     * @param  bool  $hasOffsets  Whether the source records offset progress.
     */
    private function displayRuns(string $label, array $runs, bool $hasOffsets): void
    {
        if ($runs === []) {
            $this->line($label.': no runs recorded.');

            return;
        }

        $this->newLine();
        $this->info($label);
        $this->table(
            ['ID', 'Status', 'Fetched', 'Imported', 'Updated', 'Offset', 'Error'],
            array_map(fn (LastFmImportRun|SpotifyImportRun|SpotifyPlaylistImportRun|SpotifyAlbumEnrichmentRun|MusicBrainzDiscographyImportRun $run): array => [
                $run->id,
                $run->status,
                $run->fetched_count ?? 0,
                $run->imported_count ?? 0,
                $run->updated_count ?? '-',
                $hasOffsets ? ($run->next_offset ?? 0) : '-',
                is_string($run->error) ? str_replace(["\r", "\n"], ' ', mb_strimwidth($run->error, 0, 60, '…')) : '-',
            ], $runs),
        );
    }

    /**
     * Render Spotify album enrichment progress using its run-specific counters.
     *
     * @param  array<int, SpotifyAlbumEnrichmentRun>  $runs  The recent enrichment runs.
     */
    private function displayEnrichmentRuns(array $runs): void
    {
        if ($runs === []) {
            $this->line('Spotify album enrichment: no runs recorded.');

            return;
        }

        $this->newLine();
        $this->info('Spotify album enrichment');
        $this->table(
            ['ID', 'Status', 'Processed', 'Enriched', 'Failed', 'Cursor', 'Error'],
            array_map(fn (SpotifyAlbumEnrichmentRun $run): array => [
                $run->id,
                $run->status,
                $run->processed_count,
                $run->enriched_count,
                $run->failed_count,
                $run->last_album_id ?? '-',
                is_string($run->error) ? str_replace(["\r", "\n"], ' ', mb_strimwidth($run->error, 0, 60, '…')) : '-',
            ], $runs),
        );
    }
}
