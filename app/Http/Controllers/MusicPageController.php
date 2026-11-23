<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\SpotifyPlaylist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MusicPageController extends Controller
{
    public function updateArtistRegion(Request $request, Artist $artist): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:150'],
            'region' => ['nullable', 'string', 'max:100'],
        ]);
        $updates = [];

        foreach (['location', 'region'] as $field) {
            if ($request->exists($field)) {
                $updates[$field] = trim((string) ($validated[$field] ?? '')) ?: null;
                $updates[$field.'_manual'] = true;
            }
        }

        $artist->update($updates);

        return back()->with('success', 'Artist location updated.');
    }

    public function genre(Genre $genre): View
    {
        return view('genres.show', [
            'genre' => $genre,
            'artists' => Artist::query()
                ->whereHas('acceptedGenres', fn ($query) => $query->whereKey($genre->id))
                ->withCount('albums')
                ->orderBy('name')
                ->paginate(24, ['*'], 'artists_page'),
            'albums' => Album::query()
                ->whereHas('artists.acceptedGenres', fn ($query) => $query->whereKey($genre->id))
                ->with('artists')
                ->withCount('tracks')
                ->orderBy('title')
                ->paginate(24, ['*'], 'albums_page'),
        ]);
    }

    public function artist(Artist $artist): View
    {
        return view('artists.show', [
            'artist' => $artist->load([
                'acceptedGenres',
                'albums' => fn ($query) => $query->with('artists')->withCount('tracks')->orderBy('title'),
                'musicBrainzDiscographyImportRuns' => fn ($query) => $query->latest()->limit(1),
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
