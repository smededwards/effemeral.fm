<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\MusicBrainzDiscographyImportRun;
use App\Models\SpotifyAlbumEnrichmentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Verifies the bounded enrichment Artisan commands.
 */
class EnrichmentCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Enrich only the missing Spotify tracks for an existing album.
     */
    public function test_spotify_album_enrichment_command_fills_missing_tracks(): void
    {
        config()->set('services.spotify.access_token', 'test-token');
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'spotify_id' => 'artist-id',
        ]);
        $album = Album::query()->create([
            'title' => 'Blue Record',
            'normalised_title' => 'blue record',
            'spotify_id' => 'album-id',
            'album_type' => 'album',
            'total_tracks' => 1,
        ]);
        $album->artists()->attach($artist->id);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/albums/album-id/tracks')) {
                return Http::response(['items' => [[
                    'id' => 'track-id',
                    'name' => 'Track One',
                    'uri' => 'spotify:track:track-id',
                    'track_number' => 1,
                    'disc_number' => 1,
                    'duration_ms' => 180000,
                ]], 'next' => null]);
            }

            return Http::response([], 404);
        });

        $exitCode = Artisan::call('albums:enrich-spotify', ['--limit' => 1]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('tracks', [
            'album_id' => $album->id,
            'spotify_id' => 'track-id',
        ]);
    }

    /**
     * List Spotify candidates without creating a persistent run or contacting Spotify.
     */
    public function test_spotify_album_enrichment_dry_run_is_read_only(): void
    {
        $album = Album::query()->create([
            'title' => 'Blue Record',
            'normalised_title' => 'blue record',
            'spotify_id' => 'album-id',
            'album_type' => 'album',
            'total_tracks' => 1,
        ]);

        $exitCode = Artisan::call('albums:enrich-spotify', [
            '--limit' => 1,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, SpotifyAlbumEnrichmentRun::query()->count());
        $this->assertStringContainsString((string) $album->id, Artisan::output());
    }

    /**
     * Run a selected artist's MusicBrainz discography through the existing job.
     */
    public function test_musicbrainz_discography_command_runs_a_selected_artist(): void
    {
        Sleep::fake();
        config()->set('services.spotify.access_token', 'test-token');
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
            'musicbrainz_mbid' => 'artist-mbid',
        ]);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/release-group?')) {
                return Http::response(['release-group-count' => 0, 'release-groups' => []]);
            }

            return Http::response([], 404);
        });

        $exitCode = Artisan::call('artists:enrich-musicbrainz', ['--artist-id' => $artist->id]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('music_brainz_discography_import_runs', [
            'artist_id' => $artist->id,
            'status' => 'completed',
        ]);
        $this->assertSame(0, MusicBrainzDiscographyImportRun::query()->where('artist_id', $artist->id)->value('fetched_count'));
    }
}
