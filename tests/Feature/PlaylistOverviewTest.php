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
            'last_import_error' => 'Spotify returned HTTP 500',
        ]);

        $this->get(route('spotify-playlists.index', ['playlist_search' => 'Complete']))
            ->assertOk()
            ->assertSee('Complete Playlist')
            ->assertSee(route('spotify-playlists.show', $completePlaylist, false), false)
            ->assertSee('Imported')
            ->assertSee('1 Complete Album');
    }

    public function test_playlist_overview_paginates_imported_playlists(): void
    {
        foreach (range(1, 26) as $number) {
            SpotifyPlaylist::query()->create([
                'spotify_playlist_id' => 'playlist-'.$number,
                'name' => 'Playlist '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->get(route('spotify-playlists.index'))
            ->assertOk()
            ->assertSee('Playlist 01')
            ->assertDontSee('Playlist 26');

        $this->get(route('spotify-playlists.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Playlist 26');
    }

    public function test_playlist_overview_defaults_to_recently_updated_and_supports_other_sorts(): void
    {
        $oldPlaylist = SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'old-playlist-id',
            'name' => 'Old Playlist',
            'last_synced_at' => now()->subDay(),
        ]);
        $recentPlaylist = SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'recent-playlist-id',
            'name' => 'Recent Playlist',
            'last_synced_at' => now(),
        ]);

        $this->get(route('spotify-playlists.index'))
            ->assertOk()
            ->assertSeeInOrder([$recentPlaylist->name, $oldPlaylist->name])
            ->assertSee('Recently Updated');

        $this->get(route('spotify-playlists.index', ['playlist_sort' => 'a_z']))
            ->assertOk()
            ->assertSeeInOrder([$oldPlaylist->name, $recentPlaylist->name])
            ->assertSee('A–Z');
    }
}
