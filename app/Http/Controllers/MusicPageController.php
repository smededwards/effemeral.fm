<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use Illuminate\View\View;

class MusicPageController extends Controller
{
    public function artist(Artist $artist): View
    {
        return view('artists.show', [
            'artist' => $artist->load([
                'acceptedGenres',
                'albums' => fn ($query) => $query->with('artists')->withCount('tracks')->orderBy('title'),
            ]),
        ]);
    }

    public function album(Album $album): View
    {
        return view('albums.show', [
            'album' => $album->load([
                'artists.acceptedGenres',
                'tracks' => fn ($query) => $query->orderBy('disc_number')->orderBy('track_number')->orderBy('id'),
                'playlists',
                'spotifyPlaylistItems.playlist',
            ]),
        ]);
    }

    public function spotifyPlaylist(SpotifyPlaylist $spotifyPlaylist): View
    {
        $playlistItems = $spotifyPlaylist->items()
            ->with(['album.artists', 'track'])
            ->orderBy('position')
            ->paginate(100)
            ->withQueryString();

        return view('spotify-playlists.show', [
            'spotifyPlaylist' => $spotifyPlaylist,
            'playlistItems' => $playlistItems,
            'matchedAlbumCount' => $spotifyPlaylist->items()
                ->whereNotNull('album_id')
                ->select('album_id')
                ->distinct()
                ->count('album_id'),
        ]);
    }
}
