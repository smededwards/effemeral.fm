<?php

namespace App\Services\Playlists;

use App\Models\Album;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistItem;
use Illuminate\Support\Collection;

/**
 * Calculates imported Spotify playlist completeness and import status.
 */
class SpotifyPlaylistOverviewService
{
    /**
     * @return array{import_status: string, album_status: string, complete_album_count: int} The playlist overview status.
     */
    public function status(SpotifyPlaylist $playlist): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, SpotifyPlaylistItem> $items */
        $items = $playlist->items;
        $albumGroups = $items
            ->filter(fn (SpotifyPlaylistItem $item): bool => $item->album !== null)
            ->groupBy('album_id');
        $completeAlbumCount = $albumGroups->filter(function (Collection $items): bool {
            $firstItem = $items->first();

            if (! $firstItem instanceof SpotifyPlaylistItem) {
                return false;
            }

            /** @var Album|null $album */
            $album = $firstItem->album;

            if ($album === null || $album->total_tracks === null) {
                return false;
            }

            return $items
                ->map(fn (SpotifyPlaylistItem $item): ?string => $item->spotify_track_id ?? ($item->track_id === null ? null : (string) $item->track_id))
                ->filter()
                ->unique()
                ->count() >= $album->total_tracks;
        })->count();

        return [
            'import_status' => $playlist->last_import_error !== null
                ? 'failed'
                : ($playlist->last_synced_at === null
                    ? 'pending'
                    : ($playlist->items_count === 0
                        ? 'empty'
                        : ((int) $playlist->getAttribute('items_count') < $playlist->tracks_total ? 'partial' : 'imported'))),
            'album_status' => $completeAlbumCount > 0
                ? 'complete'
                : ((int) $playlist->getAttribute('album_items_count') > 0 ? 'partial' : 'none'),
            'complete_album_count' => $completeAlbumCount,
        ];
    }
}
