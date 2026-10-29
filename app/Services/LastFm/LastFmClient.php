<?php

namespace App\Services\LastFm;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LastFmClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function recentTracks(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $tracks = [];
        $page = 1;

        do {
            $payload = [];

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $response = $this->request()->get('', [
                    'method' => 'user.getrecenttracks',
                    'user' => config('services.lastfm.username'),
                    'api_key' => config('services.lastfm.api_key'),
                    'format' => 'json',
                    'limit' => min(200, $limit - count($tracks)),
                    'page' => $page,
                    'from' => $from->timestamp,
                    'to' => $to->timestamp,
                ]);

                if ($response->failed()) {
                    throw new RuntimeException('Last.fm returned HTTP '.$response->status().'.');
                }

                $payload = $response->json();

                if ((int) data_get($payload, 'error') !== 29 || $attempt === 3) {
                    break;
                }

                sleep(1);
            }

            if (isset($payload['error'])) {
                throw new RuntimeException('Last.fm error '.$payload['error'].': '.($payload['message'] ?? 'Unknown error').'.');
            }

            $pageTracks = data_get($payload, 'recenttracks.track', []);
            $pageTracks = isset($pageTracks[0]) ? $pageTracks : [$pageTracks];
            $pageTracks = array_values(array_filter($pageTracks, fn (mixed $track): bool => is_array($track)));
            $tracks = array_merge($tracks, $pageTracks);
            $totalPages = (int) data_get($payload, 'recenttracks.@attr.totalPages', $page);

            $page++;
        } while (count($tracks) < $limit && $page <= $totalPages && $pageTracks !== []);

        return array_slice($tracks, 0, $limit);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => config('services.lastfm.user_agent'),
            'Accept' => 'application/json',
        ])->timeout(15)->retry(3, 1000, throw: false);
    }
}
