<?php

namespace App\Services\MusicBrainz;

use App\Models\Artist;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Reads MusicBrainz identity and release-group metadata for existing records.
 */
class MusicBrainzClient
{
    private bool $hasRequested = false;

    /**
     * @return array{musicbrainz_mbid: string, location: ?string, country_code: ?string}|null
     */
    /**
     * Resolve an artist's origin metadata without guessing ambiguous matches.
     *
     * @param  Artist  $artist  The existing artist to enrich.
     * @return array<string, mixed>|null The matched metadata, if unambiguous.
     */
    public function artistMetadata(Artist $artist): ?array
    {
        $musicbrainzId = $artist->musicbrainz_mbid ?: $artist->lastfm_mbid;
        $payload = $musicbrainzId !== null
            ? $this->get('artist/'.rawurlencode($musicbrainzId), ['fmt' => 'json'])
            : null;

        if ($payload !== null && $this->normalise((string) ($payload['name'] ?? '')) !== $this->normalise($artist->name)) {
            $payload = null;
        }

        $payload ??= $this->search($artist->name);

        if ($payload === null || $this->normalise((string) ($payload['name'] ?? '')) !== $this->normalise($artist->name)) {
            return null;
        }

        $musicbrainzId = $payload['id'] ?? null;
        if (! is_string($musicbrainzId) || $musicbrainzId === '') {
            return null;
        }

        $countryCode = strtoupper((string) ($payload['country'] ?? ''));

        return [
            'musicbrainz_mbid' => $musicbrainzId,
            'location' => $this->areaName($payload, 'begin-area.name'),
            'country_code' => preg_match('/^[A-Z]{2}$/', $countryCode) === 1 ? $countryCode : null,
        ];
    }

    /**
     * Fetch album release groups for an existing artist identity.
     *
     * @param  Artist  $artist  The existing artist to query.
     * @return list<array<string, mixed>> The matching release groups.
     */
    public function artistReleaseGroups(Artist $artist): array
    {
        if (! is_string($artist->musicbrainz_mbid) || $artist->musicbrainz_mbid === '') {
            return [];
        }

        $groups = [];
        $offset = 0;
        $limit = 100;
        $total = null;

        do {
            $payload = $this->get('release-group', [
                'artist' => $artist->musicbrainz_mbid,
                'type' => 'album|ep',
                'fmt' => 'json',
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if ($payload === null) {
                throw new RuntimeException('MusicBrainz did not find release groups for this artist ID. Confirm the artist link and retry.');
            }

            $page = data_get($payload, 'release-groups', []);
            $total ??= (int) data_get($payload, 'release-group-count', count($page));

            if (! is_array($page) || $page === []) {
                break;
            }

            $groups = array_merge($groups, array_values(array_filter(
                $page,
                fn (mixed $group): bool => is_array($group)
                    && in_array(mb_strtolower((string) ($group['primary-type'] ?? '')), ['album', 'ep'], true),
            )));
            $offset += count($page);
        } while ($offset < $total);

        return $groups;
    }

    /**
     * @return array<string, mixed>|null The decoded provider payload.
     */
    private function search(string $artistName): ?array
    {
        $escapedName = str_replace(['\\', '"'], ['\\\\', '\\"'], $artistName);
        $payload = $this->get('artist', [
            'query' => 'artist:"'.$escapedName.'"',
            'fmt' => 'json',
            'limit' => 10,
        ]);
        $results = data_get($payload, 'artists', []);
        $totalResults = data_get($payload, 'count');

        if (! is_array($results) || ! is_numeric($totalResults) || (int) $totalResults > count($results)) {
            return null;
        }

        $matches = collect($results)
            ->filter(fn (mixed $match): bool => is_array($match)
                && $this->normalise((string) ($match['name'] ?? '')) === $this->normalise($artistName))
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($this->hasRequested) {
                Sleep::for(1)->seconds();
            }
            $this->hasRequested = true;

            $endpoint = rtrim((string) config('services.musicbrainz.endpoint'), '/').'/';
            $response = Http::baseUrl($endpoint)
                ->withHeaders(['User-Agent' => (string) config('services.musicbrainz.user_agent')])
                ->acceptJson()
                ->timeout(15)
                ->get($path, $query);

            if ($response->notFound()) {
                return null;
            }

            if (! in_array($response->status(), [429, 503], true)) {
                if ($response->failed()) {
                    throw new RuntimeException('MusicBrainz returned HTTP '.$response->status().'.');
                }

                return $response->json();
            }

            if ($attempt === 3) {
                throw new RuntimeException('MusicBrainz rate limit persisted after three attempts.');
            }

            $retryAfter = (int) $response->header('Retry-After');
            Sleep::for(max(2, $retryAfter))->seconds();
        }

        return null;
    }

    private function normalise(string $name): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($name))) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload  The MusicBrainz area payload.
     */
    private function areaName(array $payload, string $path): ?string
    {
        $name = data_get($payload, $path);

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }
}
