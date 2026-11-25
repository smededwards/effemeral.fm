<?php

namespace App\Services\Spotify;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes locally imported Spotify playlists outside the approved owner list.
 */
class SpotifyPlaylistPurgeService
{
    /**
     * Preview the records that would be removed from the local catalogue.
     *
     * @param  string  $ownerId  The connected Spotify user ID.
     * @return array{playlists: int, playlist_items: int, albums: int, tracks: int}
     */
    public function preview(string $ownerId): array
    {
        [$playlistIds, $albumIds] = $this->purgeTargets($ownerId);

        return [
            'playlists' => $playlistIds->count(),
            'playlist_items' => DB::table('spotify_playlist_items')->whereIn('spotify_playlist_id', $playlistIds)->count(),
            'albums' => $albumIds->count(),
            'tracks' => DB::table('tracks')->whereIn('album_id', $albumIds)->count(),
        ];
    }

    /**
     * Purge unapproved playlists and catalogue records unique to those playlists.
     *
     * @param  string  $ownerId  The connected Spotify user ID.
     * @return array{playlists: int, playlist_items: int, albums: int, tracks: int}
     */
    public function purge(string $ownerId): array
    {
        return DB::transaction(function () use ($ownerId): array {
            [$playlistIds, $albumIds] = $this->purgeTargets($ownerId);
            $summary = [
                'playlists' => $playlistIds->count(),
                'playlist_items' => DB::table('spotify_playlist_items')->whereIn('spotify_playlist_id', $playlistIds)->count(),
                'albums' => $albumIds->count(),
                'tracks' => DB::table('tracks')->whereIn('album_id', $albumIds)->count(),
            ];

            DB::table('spotify_playlists')->whereIn('id', $playlistIds)->delete();
            DB::table('albums')->whereIn('id', $albumIds)->delete();

            return $summary;
        });
    }

    /**
     * Identify unapproved playlists and albums that are not referenced elsewhere.
     *
     * @param  string  $ownerId  The connected Spotify user ID.
     * @return array{0: Collection<int, int>, 1: Collection<int, int>}
     */
    private function purgeTargets(string $ownerId): array
    {
        $allowedOwners = array_merge([$ownerId], config('services.spotify.import_allowed_owners', []));
        $playlistIds = DB::table('spotify_playlists')
            ->where(function ($query) use ($allowedOwners): void {
                $query->whereNull('owner_id')->orWhereNotIn('owner_id', $allowedOwners);
            })
            ->pluck('id');
        $externalAlbumIds = DB::table('spotify_playlist_items')
            ->whereIn('spotify_playlist_id', $playlistIds)
            ->whereNotNull('album_id')
            ->pluck('album_id')
            ->unique();
        $sharedAlbumIds = DB::table('spotify_playlist_items as items')
            ->join('spotify_playlists as playlists', 'playlists.id', '=', 'items.spotify_playlist_id')
            ->whereIn('playlists.owner_id', $allowedOwners)
            ->whereNotNull('items.album_id')
            ->pluck('items.album_id')
            ->unique();
        $protectedAlbumIds = $this->protectedAlbumIds($externalAlbumIds);

        return [$playlistIds, $externalAlbumIds->diff($sharedAlbumIds)->diff($protectedAlbumIds)];
    }

    /**
     * Find album records that must survive playlist cleanup.
     *
     * @param  Collection<int, int>  $albumIds  Candidate album IDs.
     * @return Collection<int, int> Protected album IDs.
     */
    private function protectedAlbumIds(Collection $albumIds): Collection
    {
        $protected = collect();
        $protected = $protected->merge(DB::table('scrobbles')->whereIn('album_id', $albumIds)->whereNotNull('album_id')->pluck('album_id'));
        $protected = $protected->merge(DB::table('spotify_album_collections')->whereIn('album_id', $albumIds)->where('collection_type', 'saved')->pluck('album_id'));
        $protected = $protected->merge(DB::table('spotify_liked_tracks')->whereIn('album_id', $albumIds)->pluck('album_id'));
        $protected = $protected->merge(DB::table('album_playlist_items')->whereIn('album_id', $albumIds)->pluck('album_id'));
        $protected = $protected->merge(DB::table('albums')->whereIn('id', $albumIds)->where(function ($query): void {
            $query->whereNotNull('lastfm_mbid')->orWhereNotNull('musicbrainz_release_group_mbid');
        })->pluck('id'));
        $protected = $protected->merge(DB::table('tracks')->whereIn('album_id', $albumIds)->whereNotNull('lastfm_mbid')->pluck('album_id'));

        return $protected->unique();
    }
}
