<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use App\Models\SpotifyPlaylistItem;
use App\Services\Spotify\SpotifyPlaylistPurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify that Spotify purge keeps approved and shared catalogue records.
 */
class SpotifyPlaylistPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_removes_unapproved_playlists_and_unique_albums(): void
    {
        config()->set('services.spotify.import_allowed_owners', ['trusted-user']);
        $artist = Artist::query()->create([
            'name' => 'Test artist',
            'normalised_name' => 'test artist',
        ]);
        $uniqueAlbum = $this->createAlbum('Unique album', $artist);
        $sharedAlbum = $this->createAlbum('Shared album', $artist);
        $ownedPlaylist = $this->createPlaylist('Owned playlist', 'smededwards');
        $trustedPlaylist = $this->createPlaylist('Trusted playlist', 'trusted-user');
        $foreignPlaylist = $this->createPlaylist('Foreign playlist', 'other-user');

        SpotifyPlaylistItem::query()->create([
            'spotify_playlist_id' => $ownedPlaylist->id,
            'album_id' => $sharedAlbum->id,
            'spotify_track_id' => 'shared-owned-track',
            'position' => 0,
        ]);
        SpotifyPlaylistItem::query()->create([
            'spotify_playlist_id' => $trustedPlaylist->id,
            'album_id' => $sharedAlbum->id,
            'spotify_track_id' => 'shared-trusted-track',
            'position' => 0,
        ]);
        SpotifyPlaylistItem::query()->create([
            'spotify_playlist_id' => $foreignPlaylist->id,
            'album_id' => $uniqueAlbum->id,
            'spotify_track_id' => 'unique-track',
            'position' => 0,
        ]);

        $summary = app(SpotifyPlaylistPurgeService::class)->purge('smededwards');

        $this->assertSame([
            'playlists' => 1,
            'playlist_items' => 1,
            'albums' => 1,
            'tracks' => 0,
        ], $summary);
        $this->assertDatabaseHas('spotify_playlists', ['id' => $ownedPlaylist->id]);
        $this->assertDatabaseHas('spotify_playlists', ['id' => $trustedPlaylist->id]);
        $this->assertDatabaseMissing('spotify_playlists', ['id' => $foreignPlaylist->id]);
        $this->assertDatabaseMissing('albums', ['id' => $uniqueAlbum->id]);
        $this->assertDatabaseHas('albums', ['id' => $sharedAlbum->id]);
    }

    /**
     * Create an album connected to one artist for the purge fixture.
     */
    private function createAlbum(string $title, Artist $artist): Album
    {
        $album = Album::query()->create([
            'title' => $title,
            'normalised_title' => mb_strtolower($title),
        ]);
        $album->artists()->attach($artist);

        return $album;
    }

    /**
     * Create a Spotify playlist fixture.
     */
    private function createPlaylist(string $name, string $ownerId): SpotifyPlaylist
    {
        return SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => mb_strtolower(str_replace(' ', '-', $name)),
            'name' => $name,
            'owner_id' => $ownerId,
        ]);
    }
}
