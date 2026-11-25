<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify that album reconciliation preserves safe references and retired EFMIDs.
 */
class AlbumReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_dry_run_does_not_change_duplicate_album_shells(): void
    {
        [$canonical, $duplicate] = $this->duplicateAlbums();

        $this->artisan('catalog:reconcile-albums')
            ->expectsOutputToContain('SAFE the bamboo parachute (2 records); no changes made.')
            ->assertSuccessful();

        $this->assertDatabaseHas('albums', ['id' => $canonical->id]);
        $this->assertDatabaseHas('albums', ['id' => $duplicate->id]);
        $this->assertDatabaseCount('catalog_efm_id_aliases', 0);
    }

    public function test_reconciliation_moves_safe_references_and_retains_retired_efmid(): void
    {
        [$canonical, $duplicate] = $this->duplicateAlbums();

        $this->artisan('catalog:reconcile-albums --apply')
            ->expectsOutputToContain('retired 1 duplicate record(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('albums', ['id' => $canonical->id]);
        $this->assertDatabaseMissing('albums', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('catalog_efm_id_aliases', [
            'retired_efm_id' => $duplicate->efm_id,
            'canonical_entity_id' => $canonical->id,
            'canonical_efm_id' => $canonical->efm_id,
        ]);
        $this->assertDatabaseHas('spotify_album_collections', [
            'album_id' => $canonical->id,
            'collection_type' => 'playlist',
            'spotify_playlist_id' => 'playlist-id',
        ]);
        $this->assertDatabaseHas('spotify_playlist_items', [
            'album_id' => $canonical->id,
            'spotify_track_id' => 'track-id',
        ]);
    }

    public function test_reconciliation_keeps_the_provider_backed_album_as_canonical(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Acid Mammoth',
            'normalised_name' => 'acid mammoth',
        ]);
        $shell = Album::query()->create([
            'title' => 'Doom Sessions, Vol. 2',
            'normalised_title' => 'doom sessions, vol. 2',
        ]);
        $providerAlbum = Album::query()->create([
            'title' => 'Doom Sessions, Vol. 2',
            'normalised_title' => 'doom sessions, vol. 2',
            'spotify_id' => 'spotify-album-id',
        ]);
        $shell->artists()->attach($artist);
        $providerAlbum->artists()->attach($artist);

        $this->artisan('catalog:reconcile-albums --apply')->assertSuccessful();

        $this->assertDatabaseHas('albums', ['id' => $providerAlbum->id, 'spotify_id' => 'spotify-album-id']);
        $this->assertDatabaseMissing('albums', ['id' => $shell->id]);
        $this->assertDatabaseHas('catalog_efm_id_aliases', [
            'retired_efm_id' => $shell->efm_id,
            'canonical_entity_id' => $providerAlbum->id,
        ]);
    }

    /**
     * Create two empty album shells with references that can be safely deduplicated.
     *
     * @return array{0: Album, 1: Album}
     */
    private function duplicateAlbums(): array
    {
        $artist = Artist::query()->create([
            'name' => 'Chi Cheng',
            'normalised_name' => 'chi cheng',
        ]);
        $canonical = Album::query()->create([
            'title' => 'The Bamboo Parachute',
            'normalised_title' => 'the bamboo parachute',
        ]);
        $duplicate = Album::query()->create([
            'title' => 'The Bamboo Parachute',
            'normalised_title' => 'the bamboo parachute',
        ]);
        $canonical->artists()->attach($artist);
        $duplicate->artists()->attach($artist);
        $playlist = SpotifyPlaylist::query()->create([
            'spotify_playlist_id' => 'playlist-id',
            'name' => 'Imported playlist',
        ]);
        $duplicate->spotifyCollections()->create([
            'collection_type' => 'playlist',
            'spotify_playlist_id' => 'playlist-id',
        ]);
        $duplicate->spotifyPlaylistItems()->create([
            'spotify_playlist_id' => $playlist->id,
            'spotify_track_id' => 'track-id',
            'spotify_uri' => 'spotify:track:track-id',
            'track_name' => 'Track',
            'position' => 0,
        ]);

        return [$canonical, $duplicate];
    }
}
