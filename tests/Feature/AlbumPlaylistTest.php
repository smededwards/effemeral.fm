<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumPlaylist;
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
        $playlist = AlbumPlaylist::query()->create(['name' => 'Listening Queue']);

        $this->get(route('playlists.index'))
            ->assertOk()
            ->assertSee('Effemeral Album Playlists')
            ->assertSee('Listening Queue');

        $this->get(route('album-playlists.show', [$playlist, 'q' => 'Mariner']))
            ->assertOk()
            ->assertSee('Mariner')
            ->assertSee(route('album-playlists.albums.store', $playlist, false), false);

        $this->assertDatabaseHas('albums', ['id' => $album->id]);
    }

    private function createAlbum(string $title): Album
    {
        return Album::query()->create([
            'title' => $title,
            'normalised_title' => strtolower($title),
            'total_tracks' => 8,
        ]);
    }
}
