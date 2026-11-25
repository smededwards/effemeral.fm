<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumRecommendation;
use App\Models\RecommendationRun;
use App\Models\SpotifyPlaylist;
use App\Services\Recommendations\PlaylistNameSuggester;
use App\Services\Recommendations\RecommendationTrackResolver;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

/**
 * Reviews and syncs recommendation drafts as album-level Spotify playlists.
 */
class RecommendationPlaylistController extends Controller
{
    public function __construct(
        private RecommendationTrackResolver $trackResolver,
        private PlaylistNameSuggester $playlistNameSuggester,
    ) {}

    /**
     * Show the included albums, tracks, and Spotify sync choices for a draft.
     *
     * @param  RecommendationRun  $recommendationRun  The draft being reviewed.
     * @return View The recommendation review page.
     */
    public function review(RecommendationRun $recommendationRun): View
    {
        /**
         * @var Collection<int, AlbumRecommendation> $recommendations
         */
        $recommendations = $recommendationRun->albumRecommendations()
            ->with('album.releaseTracks', 'album.artists', 'spotifySavedAlbum')
            ->get();
        $reviewAlbums = $recommendations->map(function (AlbumRecommendation $recommendation): array {
            return [
                'recommendation' => $recommendation,
                'track_count' => count($this->trackResolver->resolve($recommendation)),
            ];
        });

        return view('recommendations.review', [
            'recommendationRun' => $recommendationRun,
            'reviewAlbums' => $reviewAlbums,
            'includedAlbums' => $reviewAlbums->where('recommendation.included', true)->count(),
            'excludedAlbums' => $reviewAlbums->where('recommendation.included', false)->count(),
            'includedTracks' => $reviewAlbums
                ->where('recommendation.included', true)
                ->sum('track_count'),
            'playlists' => SpotifyPlaylist::query()->latest('name')->get(),
            'playlistNameSuggestion' => $this->playlistNameSuggester->suggest($reviewAlbums),
        ]);
    }

    /**
     * Compare an Effemeral draft against an imported Spotify playlist.
     *
     * @param  Request  $request  The selected draft and playlist.
     * @return View The audit page.
     */
    public function audit(Request $request): View
    {
        $recommendationRuns = RecommendationRun::query()->latest('generated_at')->get();
        $selectedRun = $recommendationRuns->firstWhere('id', (int) request('run'));
        $selectedPlaylist = SpotifyPlaylist::query()->find($request->integer('playlist'));
        $auditAlbums = collect();

        if ($selectedRun !== null) {
            /**
             * @var Collection<int, AlbumRecommendation> $recommendations
             */
            $recommendations = $selectedRun->albumRecommendations()
                ->with('album.releaseTracks', 'album.artists', 'spotifySavedAlbum')
                ->get();
            $playlists = SpotifyPlaylist::query()
                ->when($selectedPlaylist !== null, fn ($query) => $query->whereKey($selectedPlaylist->id))
                ->with('items')
                ->latest('last_synced_at')
                ->get();
            $auditAlbums = $recommendations->map(function (AlbumRecommendation $recommendation) use ($playlists): array {
                $expectedUris = $this->trackResolver->resolve($recommendation);
                $coveredUris = collect();
                $playlistMatches = collect();

                foreach ($playlists as $playlist) {
                    $playlistUris = $playlist->items
                        ->pluck('spotify_uri')
                        ->filter(fn (mixed $uri): bool => is_string($uri))
                        ->unique();
                    $matches = $playlistUris->intersect($expectedUris)->values();

                    if ($matches->isNotEmpty()) {
                        $coveredUris = $coveredUris->merge($matches);
                        $playlistMatches->push([
                            'name' => $playlist->name,
                            'url' => $playlist->spotify_url,
                            'tracks' => $matches->count(),
                        ]);
                    }
                }

                $coveredCount = $coveredUris->unique()->count();
                $status = $expectedUris === [] ? 'unavailable' : match (true) {
                    $coveredCount === count($expectedUris) => 'complete',
                    $coveredCount > 0 => 'partial',
                    default => 'missing',
                };

                return [
                    'recommendation' => $recommendation,
                    'status' => $status,
                    'expected_tracks' => count($expectedUris),
                    'covered_tracks' => $coveredCount,
                    'missing_tracks' => max(0, count($expectedUris) - $coveredCount),
                    'playlists' => $playlistMatches,
                ];
            });
        }

        return view('recommendations.audit', [
            'recommendationRuns' => $recommendationRuns,
            'selectedRun' => $selectedRun,
            'auditAlbums' => $auditAlbums,
            'completeAlbums' => $auditAlbums->where('status', 'complete')->count(),
            'partialAlbums' => $auditAlbums->where('status', 'partial')->count(),
            'missingAlbums' => $auditAlbums->where('status', 'missing')->count(),
            'playlists' => SpotifyPlaylist::query()->latest('name')->get(),
            'selectedPlaylist' => $selectedPlaylist,
        ]);
    }

    /**
     * Create or update the selected Spotify playlist after review.
     *
     * @param  Request  $request  The submitted sync choice.
     * @param  RecommendationRun  $recommendationRun  The reviewed draft.
     * @param  SpotifyClient  $client  The Spotify API client.
     * @return RedirectResponse The post-sync redirect.
     */
    public function sync(Request $request, RecommendationRun $recommendationRun, SpotifyClient $client): RedirectResponse
    {
        if ($recommendationRun->status === 'synced') {
            return back()->with('error', 'This recommendation draft has already been synced to Spotify.');
        }

        if ($recommendationRun->status === 'syncing') {
            return back()->with('error', 'This recommendation draft is already syncing to Spotify.');
        }

        if ($request->boolean('confirmed') !== true) {
            return back()->with('error', 'Confirm the reviewed albums before syncing to Spotify.');
        }

        $validated = $request->validate([
            'playlist_target' => ['nullable', 'in:new,existing'],
            'playlist_name' => ['nullable', 'string', 'max:100'],
            'spotify_playlist_id' => ['nullable', 'string', 'max:255'],
        ]);
        $targetPlaylist = null;

        if (($validated['playlist_target'] ?? null) === 'existing') {
            $targetPlaylist = SpotifyPlaylist::query()
                ->where('spotify_playlist_id', $validated['spotify_playlist_id'] ?? '')
                ->first();

            if ($targetPlaylist === null) {
                return back()->with('error', 'Select an imported Spotify playlist before syncing.');
            }
        }

        /**
         * @var Collection<int, AlbumRecommendation> $recommendations
         */
        $recommendations = $recommendationRun->albumRecommendations()
            ->with('album.releaseTracks', 'spotifySavedAlbum')
            ->where('included', true)
            ->get();
        $trackUris = [];

        foreach ($recommendations as $recommendation) {
            $trackUris = [...$trackUris, ...$this->trackResolver->resolve($recommendation)];
        }
        $trackUris = array_values(array_unique($trackUris));

        if ($trackUris === []) {
            return back()->with('error', 'No Spotify tracks are available in the included albums.');
        }

        $recommendationRun->update(['status' => 'syncing', 'sync_error' => null]);

        try {
            $playlistId = $recommendationRun->spotify_playlist_id;

            if ($targetPlaylist !== null) {
                $playlistId = $targetPlaylist->spotify_playlist_id;
                $recommendationRun->update([
                    'spotify_playlist_id' => $playlistId,
                    'spotify_playlist_url' => $targetPlaylist->spotify_url,
                ]);
            }

            if ($playlistId === null) {
                $playlist = $client->createPrivatePlaylist(
                    trim((string) ($validated['playlist_name'] ?? '')) ?: $this->playlistNameSuggester->suggest($recommendations->map(fn (AlbumRecommendation $recommendation): array => ['recommendation' => $recommendation])),
                    'Reviewed album recommendations generated by Effemeral.',
                );
                $playlistId = $playlist['id'];
                $recommendationRun->update([
                    'spotify_playlist_id' => $playlistId,
                    'spotify_playlist_url' => $playlist['external_urls']['spotify'] ?? null,
                ]);
            }

            $existingTrackUris = collect($client->playlistItems($playlistId))
                ->map(fn (array $item): mixed => data_get($item, 'track.uri'))
                ->filter(fn (mixed $uri): bool => is_string($uri))
                ->all();
            $trackUrisToAdd = array_values(array_diff($trackUris, $existingTrackUris));

            if ($trackUrisToAdd !== []) {
                $client->addPlaylistItems($playlistId, $trackUrisToAdd);
            }
            $recommendationRun->update(['status' => 'synced', 'synced_at' => now()]);
        } catch (Throwable $exception) {
            $recommendationRun->update(['status' => 'sync_failed', 'sync_error' => $exception->getMessage()]);

            return back()->with('error', 'Spotify playlist sync failed. Check the recommendation status.');
        }

        return back()->with('success', 'Recommendation draft synced to Spotify.');
    }
}
