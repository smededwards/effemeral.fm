<?php

namespace App\Console\Commands;

use App\Jobs\ImportMusicBrainzArtistDiscography;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Enriches existing catalogue albums with MusicBrainz release metadata.
 */
#[Signature('artists:enrich-musicbrainz {--artist-id= : Only process one artist ID} {--after-id=0 : Start after this artist ID} {--limit=10 : Stop after this many artists} {--refresh : Include artists with a completed discography run} {--dry-run : List eligible artists without requesting provider data}')]
#[Description('Match existing albums with MusicBrainz release metadata')]
class EnrichMusicBrainzDiscographies extends Command
{
    /**
     * Run a bounded, resumable MusicBrainz discography batch.
     *
     * @return int The command exit status.
     */
    public function handle(): int
    {
        $lock = Cache::lock('musicbrainz-discography-enrichment', 3600);
        if (! $lock->get()) {
            $this->error('A MusicBrainz discography enrichment is already in progress.');

            return self::FAILURE;
        }

        try {
            $artists = $this->eligibleArtists();
            $limit = max(1, (int) $this->option('limit'));
            $artists = $artists->limit($limit)->get();

            if ($artists->isEmpty()) {
                $this->info('No artists need MusicBrainz discography enrichment.');

                return self::SUCCESS;
            }

            $this->info('MusicBrainz enrichment batch: '.$artists->count().' artist(s).');
            $this->line('Each artist will record its own resumable import run.');

            if ($this->option('dry-run')) {
                $artists->each(fn (Artist $artist) => $this->line($artist->id.' '.$artist->name));
                $this->info('Listed '.$artists->count().' eligible artists.');

                return self::SUCCESS;
            }

            $processed = 0;
            $failed = 0;

            foreach ($artists as $artist) {
                $run = MusicBrainzDiscographyImportRun::query()->create([
                    'artist_id' => $artist->id,
                    'status' => 'queued',
                ]);

                try {
                    $this->line('['.($processed + $failed + 1).'/'.$artists->count().'] Fetching '
                        .$artist->name.' (ID '.$artist->id.').');
                    ImportMusicBrainzArtistDiscography::dispatchSync($run->id);
                    $result = $run->fresh();
                    $processed++;
                    $this->info('Completed '.$artist->name.': '.$result->fetched_count.' release(s), '
                        .$result->matched_count.' matched, '.$result->unmatched_count.' unmatched.');
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn('Failed '.$artist->id.' '.$artist->name.': '.$exception->getMessage());
                }
            }

            $this->info("Enriched {$processed} artists; failed {$failed}.");

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * Build the query for artists with a MusicBrainz identity.
     *
     * @return Builder<Artist> The eligible artist query.
     */
    private function eligibleArtists(): Builder
    {
        return Artist::query()
            ->whereNotNull('musicbrainz_mbid')
            ->when($this->option('artist-id'), fn ($query, $artistId) => $query->whereKey((int) $artistId))
            ->when((int) $this->option('after-id') > 0, fn ($query) => $query->where('id', '>', (int) $this->option('after-id')))
            ->when(! $this->option('refresh'), fn ($query) => $query->whereDoesntHave(
                'musicBrainzDiscographyImportRuns',
                fn ($runQuery) => $runQuery->where('status', 'completed'),
            ))
            ->whereDoesntHave(
                'musicBrainzDiscographyImportRuns',
                fn ($runQuery) => $runQuery->whereIn('status', ['queued', 'running']),
            )
            ->orderBy('id');
    }
}
