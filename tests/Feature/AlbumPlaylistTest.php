<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\SpotifyPlaylist;
use App\Services\Spotify\AlbumPlaylistSyncService;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumPlaylistTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_rename_a_persistent_album_playlist(): void
    {
        $this->post(route('album-playlists.store'), ['name' => '  Heavy Rotation  '])
            ->assertRedirect();

        $playlist = AlbumPlaylist::query()->firstOrFail();
        $this->assertSame('Heavy Rotation', $playlist->name);

        $this->patch(route('album-playlists.update', $playlist), ['name' => 'New Releases'])
            ->assertRedirect(route('album-playlists.show', $playlist));

        $this->assertSame('New Releases', $playlist->fresh()->name);
    }

    public function test_unnamed_playlist_creation_generates_a_two_word_draft_name(): void
    {
        $this->post(route('album-playlists.store'), ['name' => '   '])
            ->assertRedirect();

        $playlist = AlbumPlaylist::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+ [A-Z][a-z]+$/', $playlist->name);
    }

    public function test_unnamed_playlist_names_store_deduplicated_words_from_owned_spotify_playlists(): void
    {
        $this->mock(SpotifyClient::class, function ($mock): void {
            $mock->shouldReceive('currentUser')->once()->andReturn([
                'id' => 'owned-user',
                'display_name' => 'Owned User',
            ]);
        });
        SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'owned-one',
            'name' => 'Mourning Earth',
            'owner_id' => 'owned-user',
        ]);
        SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'owned-two',
            'name' => 'Mourning Garden',
            'owner_id' => 'owned-user',
        ]);
        SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'other-one',
            'name' => 'Unrelated Static',
            'owner_id' => 'other-user',
        ]);

        $this->post(route('album-playlists.store'), ['name' => '   '])
            ->assertRedirect();

        $this->assertDatabaseHas('playlist_name_options', [
            'word' => 'mourning',
            'source' => 'spotify_playlist',
        ]);
        $this->assertDatabaseHas('playlist_name_options', ['word' => 'earth']);
        $this->assertDatabaseHas('playlist_name_options', ['word' => 'garden']);
        $this->assertDatabaseMissing('playlist_name_options', ['word' => 'unrelated']);
        $this->assertSame(3, (int) \DB::table('playlist_name_options')->count());
    }

    public function test_user_can_add_remove_and_reorder_albums_independently_of_other_playlists(): void
    {
        $first = $this->createAlbum('First Album');
        $second = $this->createAlbum('Second Album');
        $third = $this->createAlbum('Third Album');
        $playlist = AlbumPlaylist::query()->create(['name' => 'Doom']);
        $otherPlaylist = AlbumPlaylist::query()->create(['name' => 'Post Metal']);

        foreach ([$first, $second, $third] as $album) {
            $this->post(route('album-playlists.albums.store', $playlist), ['album_id' => $album->id])
                ->assertRedirect();
        }
        $this->post(route('album-playlists.albums.store', $otherPlaylist), ['album_id' => $first->id])
            ->assertRedirect();

        $this->post(route('album-playlists.albums.move', [$playlist, $third]), ['direction' => 'up'])
            ->assertRedirect();
        $this->assertSame([$first->id, $third->id, $second->id], $playlist->fresh()->albums->pluck('id')->all());

        $this->delete(route('album-playlists.albums.destroy', [$playlist, $first]))
            ->assertRedirect();
        $this->assertSame([$third->id, $second->id], $playlist->fresh()->albums->pluck('id')->all());
        $this->assertSame([$first->id], $otherPlaylist->fresh()->albums->pluck('id')->all());
        $this->assertNull($playlist->fresh()->spotify_playlist_id);
    }

    public function test_playlist_page_searches_canonical_albums_and_lists_saved_playlists(): void
    {
        $album = $this->createAlbum('Mariner');
        $artist = Artist::query()->create([
            'name' => 'Cult of Luna',
            'normalised_name' => 'cult of luna',
        ]);
        $genre = Genre::query()->create([
            'name' => 'post-metal',
            'normalised_name' => 'post metal',
        ]);
        $artist->genres()->attach($genre->id, ['source' => 'manual', 'suppressed' => false]);
        $album->artists()->attach($artist->id);
        $playlist = AlbumPlaylist::query()->create(['name' => 'Listening Queue']);

        $this->get(route('playlists.index'))
            ->assertOk()
            ->assertSee('Effemeral Album Playlists')
            ->assertSee('Listening Queue')
            ->assertSee(route('album-playlists.show', $playlist, false), false);

        $this->get(route('album-playlists.show', [$playlist, 'q' => 'Mariner']))
            ->assertOk()
            ->assertSee('Mariner')
            ->assertSee('name="album_ids[]"', false)
            ->assertSee(route('album-playlists.albums.bulk-store', [], false), false);

        $this->get(route('album-playlists.show', [$playlist, 'q' => 'post-metal']))
            ->assertOk()
            ->assertSee('Mariner');

        $this->assertDatabaseHas('albums', ['id' => $album->id]);
    }

    public function test_playlist_name_can_be_saved_or_refreshed_from_album_contents(): void
    {
        $album = $this->createAlbum('Mourning Earth');
        $playlist = AlbumPlaylist::query()->create(['name' => 'Old Name']);
        $playlist->albums()->attach($album->id, ['position' => 1]);

        $this->patch(route('album-playlists.update', $playlist), ['name' => 'New Name'])
            ->assertRedirect();
        $this->assertSame('New Name', $playlist->fresh()->name);

        $this->post(route('album-playlists.name.refresh', $playlist))
            ->assertRedirect()
            ->assertSessionHas('success', 'Playlist name refreshed.');
        $this->assertSame('Mourning Earth', $playlist->fresh()->name);
    }

    public function test_bulk_album_add_generates_a_name_for_an_unnamed_new_playlist(): void
    {
        $album = $this->createAlbum('Mariner');

        $this->post(route('album-playlists.albums.bulk-store'), [
            'album_ids' => [$album->id],
            'destination' => 'new',
            'playlist_name' => '   ',
        ])->assertRedirect();

        $playlist = AlbumPlaylist::query()->firstOrFail();
        $this->assertStringContainsString('Mariner', $playlist->name);
        $this->assertSame([$album->id], $playlist->albums->pluck('id')->all());
    }

    public function test_selected_albums_can_be_added_to_an_existing_playlist(): void
    {
        $first = $this->createAlbum('Mariner');
        $second = $this->createAlbum('The Silent Life');
        $playlist = AlbumPlaylist::query()->create(['name' => 'Fossil Gardens']);

        $this->post(route('album-playlists.albums.bulk-store'), [
            'album_ids' => [$first->id, $second->id],
            'destination' => 'existing',
            'playlist_id' => $playlist->id,
        ])->assertRedirect(route('album-playlists.show', $playlist));

        $this->assertSame([$first->id, $second->id], $playlist->fresh()->albums->pluck('id')->all());
    }

    public function test_playlist_can_be_exported_as_csv_without_spotify(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Cult of Luna',
            'normalised_name' => 'cult of luna',
        ]);
        $genre = Genre::query()->create([
            'name' => 'Post-Metal',
            'normalised_name' => 'post metal',
        ]);
        $artist->genres()->attach($genre->id, ['source' => 'manual', 'suppressed' => false]);
        $album = $this->createAlbum('Mariner');
        $album->update([
            'release_date' => '2016-06-08',
            'spotify_url' => 'https://open.spotify.com/album/mariner',
        ]);
        $album->artists()->attach($artist->id);
        $playlist = AlbumPlaylist::query()->create(['name' => 'Fossil Gardens']);
        $playlist->albums()->attach($album->id, ['position' => 1]);

        $response = $this->get(route('album-playlists.export', $playlist));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));

        $this->assertSame(['Position', 'Album', 'Artist', 'Release date', 'Styles', 'Spotify URL'], $rows[0]);
        $this->assertSame([
            '1',
            'Mariner',
            'Cult of Luna',
            '2016-06-08',
            'post-metal',
            'https://open.spotify.com/album/mariner',
        ], $rows[1]);
    }

    public function test_reordering_a_playlist_does_not_remove_its_spotify_tracks(): void
    {
        $first = $this->createAlbumWithTrack('First Album', 'spotify:track:first');
        $second = $this->createAlbumWithTrack('Second Album', 'spotify:track:second');
        $playlist = AlbumPlaylist::query()->create([
            'name' => 'Mourning Earth',
            'spotify_playlist_id' => 'spotify-playlist-id',
            'spotify_managed_track_uris' => ['spotify:track:first', 'spotify:track:second'],
            'spotify_sync_status' => 'synced',
        ]);
        $playlist->albums()->attach($second->id, ['position' => 1]);
        $playlist->albums()->attach($first->id, ['position' => 2]);

        $this->mock(SpotifyClient::class, function ($mock): void {
            $mock->shouldReceive('playlistDetails')
                ->once()
                ->with('spotify-playlist-id')
                ->andReturn([
                    'id' => 'spotify-playlist-id',
                    'name' => 'Mourning Earth',
                    'snapshot_id' => 'snapshot-id',
                    'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/spotify-playlist-id'],
                ]);
            $mock->shouldReceive('playlistItems')
                ->once()
                ->with('spotify-playlist-id')
                ->andReturn([
                    ['track' => ['uri' => 'spotify:track:first', 'name' => 'First track', 'album' => ['name' => 'First Album']]],
                    ['track' => ['uri' => 'spotify:track:second', 'name' => 'Second track', 'album' => ['name' => 'Second Album']]],
                ]);
        });

        $preview = app(AlbumPlaylistSyncService::class)->preview($playlist, 'linked');

        $this->assertSame([], $preview['remove_uris']);
        $this->assertSame([], $preview['add_uris']);
    }

    public function test_spotify_sync_preview_blocks_incomplete_albums(): void
    {
        $album = $this->createAlbumWithTrack('Incomplete Album', 'spotify:track:available');
        $album->update(['total_tracks' => 2]);
        $playlist = AlbumPlaylist::query()->create(['name' => 'Incomplete Draft']);
        $playlist->albums()->attach($album->id, ['position' => 1]);

        $preview = app(AlbumPlaylistSyncService::class)->preview($playlist, 'new');

        $this->assertFalse($preview['sync_allowed']);
        $this->assertSame([['title' => 'Incomplete Album', 'artist' => 'Unknown artist', 'available_tracks' => 1, 'total_tracks' => 2]], $preview['incomplete_albums']);
    }

    private function createAlbum(string $title): Album
    {
        return Album::query()->create([
            'title' => $title,
            'normalised_title' => strtolower($title),
            'total_tracks' => 8,
        ]);
    }

    private function createAlbumWithTrack(string $title, string $spotifyUri): Album
    {
        $album = $this->createAlbum($title);
        $album->tracks()->create([
            'title' => $title.' track',
            'normalised_title' => strtolower($title).' track',
            'spotify_uri' => $spotifyUri,
            'disc_number' => 1,
            'track_number' => 1,
        ]);

        return $album;
    }
}
