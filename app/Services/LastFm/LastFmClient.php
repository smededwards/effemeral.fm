<?php

namespace App\Services\LastFm;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads Last.fm history and tags with bounded retries and request pacing.
 */
class LastFmClient
{
    private float $lastRequestAt = 0.0;

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Fetch recent tracks in one bounded result set.
     *
     * @param  CarbonImmutable  $from  Start of the requested range.
     * @param  CarbonImmutable  $to  End of the requested range.
     * @param  int|null  $limit  Maximum number of scrobbles to return.
     * @return list<array<string, mixed>> The Last.fm track payloads.
     */
    public function recentTracks(CarbonImmutable $from, CarbonImmutable $to, ?int $limit = null): array
    {
        $tracks = [];

        $this->eachRecentTrack($from, $to, $limit, function (array $track) use (&$tracks): void {
            $tracks[] = $track;
        });

        return $tracks;
    }

    /**
     * Stream recent tracks page by page so full imports stay memory-safe.
     *
     * @param  CarbonImmutable  $from  Start of the requested range.
     * @param  CarbonImmutable  $to  End of the requested range.
     * @param  int|null  $limit  Maximum number of scrobbles to yield.
     * @param  Closure(array<string, mixed>): void  $trackHandler  Handles each track.
     * @param  Closure(int): void|null  $pageHandler  Handles each completed page.
     */
    public function eachRecentTrack(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $limit,
        Closure $trackHandler,
        ?Closure $pageHandler = null,
    ): void {
        $fetched = 0;
        $page = 1;

        do {
            $pageLimit = $limit === null ? 200 : min(200, $limit - $fetched);
            if ($pageLimit < 1) {
                break;
            }

            $response = $this->getWithRateLimit([
                'method' => 'user.getrecenttracks',
                'user' => config('services.lastfm.username'),
                'api_key' => config('services.lastfm.api_key'),
                'format' => 'json',
                'limit' => $pageLimit,
                'page' => $page,
                'from' => $from->timestamp,
                'to' => $to->timestamp,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Last.fm returned HTTP '.$response->status());
            }

            $payload = $response->json();

            if (isset($payload['error'])) {
                throw new RuntimeException('Last.fm error '.$payload['error'].': '.($payload['message'] ?? 'Unknown error'));
            }

            $pageTracks = data_get($payload, 'recenttracks.track', []);
            $pageTracks = $pageTracks === []
                ? []
                : (isset($pageTracks[0]) ? $pageTracks : [$pageTracks]);
            $pageTracks = array_values(array_filter($pageTracks, fn (mixed $track): bool => is_array($track)));

            foreach ($pageTracks as $track) {
                $trackHandler($track);
                $fetched++;
            }

            if ($pageHandler !== null) {
                $pageHandler($fetched);
            }
            $totalPages = (int) data_get($payload, 'recenttracks.@attr.totalPages', $page);
            $page++;
        } while (($limit === null || $fetched < $limit) && $page <= $totalPages && $pageTracks !== []);
    }

    /**
     * Fetch an artist's tags for genre enrichment.
     *
     * @param  string  $artist  The Last.fm artist name.
     * @param  string|null  $mbid  The optional MusicBrainz artist ID.
     * @return list<string> The Last.fm tag names.
     */
    public function artistTags(string $artist, ?string $mbid = null): array
    {
        $response = $this->getWithRateLimit(array_filter([
            'method' => 'artist.getInfo',
            'artist' => $artist,
            'mbid' => $mbid,
            'api_key' => config('services.lastfm.api_key'),
            'format' => 'json',
        ]));

        if ($response->failed()) {
            throw new RuntimeException('Last.fm returned HTTP '.$response->status());
        }

        $tags = data_get($response->json(), 'artist.tags.tag', []);
        $tags = isset($tags['name']) ? [$tags] : $tags;

        return collect($tags)
            ->map(fn (mixed $tag): mixed => is_array($tag) ? ($tag['name'] ?? null) : null)
            ->filter(fn (mixed $tag): bool => is_string($tag) && $tag !== '')
            ->values()
            ->all();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.lastfm.endpoint'))
            ->withHeaders([
                'User-Agent' => config('services.lastfm.user_agent'),
                'Accept' => 'application/json',
            ])->timeout(15)->retry(3, 1000, throw: false);
    }

    /**
     * @param  array<string, mixed>  $query  The Last.fm request parameters.
     */
    private function getWithRateLimit(array $query): Response
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->throttle();
            $response = $this->request()->get('', $query);
            $payload = $response->json();
            $rateLimited = $response->status() === 429 || (int) data_get($payload, 'error') === 29;

            if (! $rateLimited || $attempt === 3) {
                return $response;
            }

            $this->waitForRateLimit($response);
        }

        return $response;
    }

    private function throttle(): void
    {
        $minimumInterval = max(0, (int) config('services.lastfm.min_request_interval_ms', 250)) / 1000;
        $wait = $minimumInterval - (microtime(true) - $this->lastRequestAt);

        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }

        $this->lastRequestAt = microtime(true);
    }

    private function waitForRateLimit(Response $response): void
    {
        $headers = array_change_key_case($response->headers());

        sleep(max(0, (int) ($headers['retry-after'] ?? 1)));
    }
}
