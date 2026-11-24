<?php

namespace App\Services\Library;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifySavedAlbum;
use Illuminate\Http\Request;

/**
 * Builds the imported Spotify library data shown in the library page.
 */
class LibraryPageService
{
    /**
     * @return array<string, mixed> The library page data.
     */
    public function data(Request $request): array
    {
        $librarySearch = trim((string) $request->query('library_search'));
        $savedAlbums = SpotifySavedAlbum::query()->latest('saved_at');

        if ($librarySearch !== '') {
            $savedAlbums->where(function ($query) use ($librarySearch): void {
                $query->where('name', 'like', '%'.$librarySearch.'%')
                    ->orWhere('primary_artist_name', 'like', '%'.$librarySearch.'%');
            });
        }

        $spotifySavedAlbums = $savedAlbums->limit(24)->get();
        $canonicalAlbums = Album::query()
            ->whereIn('spotify_id', $spotifySavedAlbums->pluck('spotify_album_id'))
            ->get(['id', 'spotify_id'])
            ->keyBy('spotify_id');
        $canonicalArtists = Artist::query()
            ->whereIn('spotify_id', $spotifySavedAlbums->pluck('primary_artist_id')->filter())
            ->get(['id', 'spotify_id'])
            ->keyBy('spotify_id');

        foreach ($spotifySavedAlbums as $savedAlbum) {
            $savedAlbum->setAttribute('canonical_album_id', $canonicalAlbums->get($savedAlbum->spotify_album_id)?->id);
            $savedAlbum->setAttribute('canonical_artist_id', $canonicalArtists->get($savedAlbum->primary_artist_id)?->id);
        }

        return [
            'librarySearch' => $librarySearch,
            'spotifyPlaylists' => SpotifyPlaylist::query()->with('items.album')->latest('last_synced_at')->limit(24)->get(),
            'spotifySavedAlbums' => $spotifySavedAlbums,
            'spotifySavedAlbumCount' => SpotifySavedAlbum::query()->count(),
        ];
    }
}
