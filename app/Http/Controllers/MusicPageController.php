<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\SpotifyPlaylist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Renders linked artist, album, genre, and imported playlist pages.
 */
class MusicPageController extends Controller
{
    /**
     * Save a manually reviewed country and locality for an artist.
     *
     * @param  Request  $request  The submitted location values.
     * @param  Artist  $artist  The artist being edited.
     * @return RedirectResponse The artist page redirect.
     */
    public function updateArtistLocation(Request $request, Artist $artist): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:150'],
            'country_code' => ['nullable', 'string', Rule::in(array_keys(config('countries')))],
        ]);
        $updates = [];

        foreach (['location', 'country_code'] as $field) {
            if ($request->exists($field)) {
                $updates[$field] = trim((string) ($validated[$field] ?? '')) ?: null;
                $updates[$field === 'country_code' ? 'country_manual' : 'location_manual'] = true;
            }
        }

        $artist->update($updates);

        return back()->with('success', 'Artist location updated.');
    }

    /**
     * Show the artists associated with a genre.
     *
     * @param  Genre  $genre  The genre being viewed.
     * @return View The genre page.
     */
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

    /**
     * Show an artist with its albums and accepted genres.
     *
     * @param  Artist  $artist  The artist being viewed.
     * @return View The artist page.
     */
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

    /**
     * Show an album with its tracks, genres, and playlists.
     *
     * @param  Album  $album  The album being viewed.
     * @return View The album page.
     */
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

    /**
     * Show an imported Spotify playlist and its catalogue content.
     *
     * @param  SpotifyPlaylist  $spotifyPlaylist  The playlist being viewed.
     * @return View The imported playlist page.
     */
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
