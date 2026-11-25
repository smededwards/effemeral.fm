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
     * Resolve an artist's origin metadata without guessing ambiguous matches.
     *
     * @param  Artist  $artist  The existing artist to enrich.
     * @return array{
     *     musicbrainz_mbid: string,
     *     location: ?string,
     *     country_code: ?string,
     *     aliases: list<array{name: string, locale: ?string, type: ?string}>,
     *     relationships: list<array{target_id: string, target_name: string, type: string, attributes: list<string>}>
     * }|null
     */
    public function artistMetadata(Artist $artist): ?array
    {
        $musicbrainzId = $artist->musicbrainz_mbid ?: $artist->lastfm_mbid;
        $payload = $musicbrainzId !== null
            ? $this->get('artist/'.rawurlencode($musicbrainzId), ['fmt' => 'json', 'inc' => 'aliases+artist-rels'])
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
            'aliases' => collect($payload['aliases'] ?? [])
                ->filter(fn (mixed $alias): bool => is_array($alias) && is_string($alias['name'] ?? null))
                ->map(fn (array $alias): array => [
                    'name' => trim((string) $alias['name']),
                    'locale' => is_string($alias['locale'] ?? null) ? $alias['locale'] : null,
                    'type' => is_string($alias['type'] ?? null) ? $alias['type'] : null,
                ])
                ->filter(fn (array $alias): bool => $this->normalise($alias['name']) !== $this->normalise($artist->name))
                ->unique(fn (array $alias): string => $this->normalise($alias['name']))
                ->values()
                ->all(),
            'relationships' => collect($payload['relations'] ?? [])
                ->filter(fn (mixed $relation): bool => is_array($relation)
                    && is_string(data_get($relation, 'artist.id'))
                    && is_string(data_get($relation, 'artist.name'))
                    && is_string($relation['type'] ?? null))
                ->map(fn (array $relation): array => [
                    'target_id' => (string) data_get($relation, 'artist.id'),
                    'target_name' => (string) data_get($relation, 'artist.name'),
                    'type' => (string) $relation['type'],
                    'attributes' => collect($relation['attributes'] ?? [])
                        ->filter(fn (mixed $attribute): bool => is_string($attribute))
                        ->values()
                        ->all(),
                ])
                ->unique(fn (array $relation): string => $relation['target_id'].'|'.$relation['type'])
                ->values()
                ->all(),
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
                fn (mixed $group): bool => is_array($group) && $this->isEligibleReleaseGroup($group),
            )));
            $offset += count($page);
        } while ($offset < $total);

        return $groups;
    }

    /**
     * Keep standard albums and EPs while excluding non-canonical release groups.
     *
     * @param  array<string, mixed>  $group  The MusicBrainz release group.
     */
    private function isEligibleReleaseGroup(array $group): bool
    {
        $primaryType = mb_strtolower((string) ($group['primary-type'] ?? ''));
        if (! in_array($primaryType, ['album', 'ep'], true)) {
            return false;
        }

        $excludedTypes = [
            'audio drama',
            'audiobook',
            'bootleg',
            'broadcast',
            'box set',
            'compilation',
            'concert film',
            'demo',
            'dj-mix',
            'field recording',
            'holiday',
            'interview',
            'live',
            'mixtape',
            'other',
            'promo',
            'remix',
            'soundtrack',
            'spokenword',
            'video',
        ];

        return collect($group['secondary-types'] ?? [])
            ->filter(fn (mixed $type): bool => is_string($type))
            ->map(fn (string $type): string => mb_strtolower(trim($type)))
            ->intersect($excludedTypes)
            ->isEmpty();
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
                // MusicBrainz requires requests to be spaced even when the previous response failed.
                Sleep::for((int) config('services.musicbrainz.min_request_interval_ms', 1500))->milliseconds();
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
