<?php

namespace Tests\Unit;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Scrobble;
use App\Models\SpotifyAlbumCollection;
use App\Models\SpotifySavedAlbum;
use App\Models\Track;
use App\Services\Recommendations\AlbumCompletionService;
use App\Services\Recommendations\RecommendationListeningWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecommendationListeningWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_windows_include_a_default_year_and_all_time_option(): void
    {
        $windows = app(RecommendationListeningWindow::class)->options();

        $this->assertSame('Last 365 days', $windows['1_year']);
        $this->assertSame('This calendar year', $windows['this_year']);
        $this->assertSame('All time', $windows['all_time']);
    }

    public function test_this_year_uses_the_current_calendar_year(): void
    {
        Carbon::setTestNow('2026-11-24 18:00:00');

        $cutoff = app(RecommendationListeningWindow::class)->cutoff('this_year');

        $this->assertSame('2026-01-01 00:00:00', $cutoff?->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_old_listening_is_eligible_in_the_default_window_but_not_all_time(): void
    {
        $artist = Artist::query()->create([
            'name' => 'An Artist',
            'normalised_name' => 'an artist',
        ]);
        $album = Album::query()->create([
            'title' => 'An Album',
            'normalised_title' => 'an album',
            'spotify_id' => 'album-id',
            'total_tracks' => 1,
        ]);
        $album->artists()->attach($artist->id);
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'A Track',
            'normalised_title' => 'a track',
        ]);
        SpotifyAlbumCollection::query()->create([
            'album_id' => $album->id,
            'collection_type' => 'saved',
        ]);
        SpotifySavedAlbum::query()->create([
            'spotify_album_id' => 'album-id',
            'spotify_uri' => 'spotify:album:album-id',
            'name' => 'An Album',
            'album_type' => 'album',
            'total_tracks' => 1,
            'primary_artist_name' => 'An Artist',
        ]);
        Scrobble::query()->create([
            'source_key' => 'old-listen',
            'artist_name' => 'An Artist',
            'track_name' => 'A Track',
            'album_name' => 'An Album',
            'track_id' => $track->id,
            'listened_at' => now()->subDays(400),
        ]);

        $service = app(AlbumCompletionService::class);

        $this->assertSame([$album->id], $service->incompleteAlbums()->pluck('canonical_id')->all());
        $this->assertSame([], $service->incompleteAlbums('all_time')->pluck('canonical_id')->all());
    }
}
