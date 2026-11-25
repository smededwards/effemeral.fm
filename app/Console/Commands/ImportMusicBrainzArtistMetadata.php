<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\MusicBrainz\MusicBrainzArtistMetadataWriter;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Enriches existing artists with MusicBrainz identity, origin and cross-reference metadata.
 */
#[Signature('artists:import-musicbrainz {--refresh : Check all artists again, including previously checked artists} {--known-only : Process only artists that already have a MusicBrainz ID} {--after-id=0 : Start after this artist ID} {--limit=0 : Stop after this many artists (0 means all)}')]
#[Description('Enrich existing artists with MusicBrainz metadata')]
class ImportMusicBrainzArtistMetadata extends Command
{
    /**
     * Run the metadata enrichment command.
     *
     * @param  MusicBrainzClient  $client  The MusicBrainz API client.
     * @return int The command exit status.
     */
    public function handle(
        MusicBrainzClient $client,
        MusicBrainzArtistMetadataWriter $metadataWriter,
    ): int {
        $lock = Cache::lock('musicbrainz-artist-metadata-import', 86400);
        if (! $lock->get()) {
            $this->error('A MusicBrainz artist import is already in progress.');

            return self::FAILURE;
        }

        try {
            $artists = Artist::query()
                ->withSourceEvidence()
                ->when(! $this->option('refresh'), fn ($query) => $query->whereNull('musicbrainz_checked_at'))
                ->when($this->option('known-only'), fn ($query) => $query->whereNotNull('musicbrainz_mbid'))
                ->when((int) $this->option('after-id') > 0, fn ($query) => $query->where('id', '>', (int) $this->option('after-id')))
                ->orderBy('id');
            $limit = max(0, (int) $this->option('limit'));
            $total = $limit > 0 ? min($limit, $artists->count()) : $artists->count();
            $matched = 0;
            $failed = 0;

            if ($total === 0) {
                $this->info('No artists need MusicBrainz metadata.');

                return self::SUCCESS;
            }

            $artistsToProcess = $limit > 0
                ? $artists->limit($limit)->get()
                : $artists->lazyById();

            $this->withProgressBar($artistsToProcess, function (Artist $artist) use ($client, $metadataWriter, &$failed, &$matched): void {
                try {
                    $metadata = $client->artistMetadata($artist);
                } catch (\RuntimeException) {
                    // Leave the timestamp empty so the artist remains eligible for a later retry.
                    $failed++;

                    return;
                }

                $artist->fill([
                    'musicbrainz_mbid' => $metadata['musicbrainz_mbid'] ?? $artist->musicbrainz_mbid,
                    'location' => $artist->location_manual ? $artist->location : ($metadata['location'] ?? $artist->location),
                    'country_code' => $artist->country_manual ? $artist->country_code : ($metadata['country_code'] ?? $artist->country_code),
                    'musicbrainz_checked_at' => now(),
                ])->save();
                $metadataWriter->write(
                    $artist,
                    $metadata['aliases'] ?? [],
                    $metadata['relationships'] ?? [],
                );

                if ($metadata !== null) {
                    $matched++;
                }
            });

            $this->newLine();
            $this->info("Checked {$total} artists; matched {$matched}.");
            if ($failed > 0) {
                $this->warn("Skipped {$failed} artists after provider errors; they remain eligible for a later retry.");
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
