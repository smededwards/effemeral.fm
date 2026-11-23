<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('artists:import-musicbrainz {--refresh : Check all artists again, including previously checked artists} {--limit=0 : Stop after this many artists (0 means all)}')]
#[Description('Import artist country and locality from MusicBrainz')]
/**
 * Imports MusicBrainz origins for artists that already exist locally.
 */
class ImportMusicBrainzArtistMetadata extends Command
{
    /**
     * Run the metadata enrichment command.
     *
     * @param  MusicBrainzClient  $client  The MusicBrainz API client.
     * @return int The command exit status.
     */
    public function handle(MusicBrainzClient $client): int
    {
        $lock = Cache::lock('musicbrainz-artist-metadata-import', 86400);
        if (! $lock->get()) {
            $this->error('A MusicBrainz artist import is already in progress.');

            return self::FAILURE;
        }

        try {
            $artists = Artist::query()
                ->when(! $this->option('refresh'), fn ($query) => $query->whereNull('musicbrainz_checked_at'))
                ->orderBy('id');
            $limit = max(0, (int) $this->option('limit'));
            $total = $limit > 0 ? min($limit, $artists->count()) : $artists->count();
            $matched = 0;

            if ($total === 0) {
                $this->info('No artists need MusicBrainz metadata.');

                return self::SUCCESS;
            }

            $artistsToProcess = $limit > 0
                ? $artists->limit($limit)->get()
                : $artists->lazyById();

            $this->withProgressBar($artistsToProcess, function (Artist $artist) use ($client, &$matched): void {
                $metadata = $client->artistMetadata($artist);
                $artist->fill([
                    'musicbrainz_mbid' => $metadata['musicbrainz_mbid'] ?? $artist->musicbrainz_mbid,
                    'location' => $artist->location_manual ? $artist->location : ($metadata['location'] ?? $artist->location),
                    'country_code' => $artist->country_manual ? $artist->country_code : ($metadata['country_code'] ?? $artist->country_code),
                    'musicbrainz_checked_at' => now(),
                ])->save();

                if ($metadata !== null) {
                    $matched++;
                }
            });

            $this->newLine();
            $this->info("Checked {$total} artists; matched {$matched}.");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
