<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_playlist_overview_searches_and_describes_album_content(): void
    {
        $album = Album::query()->create([
            'title' => 'Complete Album',
            'normalised_title' => 'complete album',
            'total_tracks' => 1,
        ]);
        $completePlaylist = SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'complete-playlist-id',
            'name' => 'Complete Playlist',
            'tracks_total' => 1,
            'last_synced_at' => now(),
        ]);
        SpotifyPlaylistItem::query()->create([
            'spotify_playlist_id' => $completePlaylist->id,
            'album_id' => $album->id,
            'spotify_track_id' => 'complete-track-id',
            'position' => 0,
        ]);
        SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'failed-playlist-id',
            'name' => 'Failed Playlist',
            'last_import_error' => 'Spotify returned HTTP 500.',
        ]);

        $this->get(route('playlists.index', ['playlist_search' => 'Complete']))
            ->assertOk()
            ->assertSee('Complete Playlist')
            ->assertSee('Imported')
            ->assertSee('1 complete album');
    }

    public function test_playlist_overview_paginates_imported_playlists(): void
    {
        foreach (range(1, 26) as $number) {
            SpotifyPlaylist::query()->create([
                'spotify_playlist_id' => 'playlist-'.$number,
                'name' => 'Playlist '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->get(route('playlists.index'))
            ->assertOk()
            ->assertSee('Playlist 01')
            ->assertDontSee('Playlist 26');

        $this->get(route('playlists.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Playlist 26');
    }
}
