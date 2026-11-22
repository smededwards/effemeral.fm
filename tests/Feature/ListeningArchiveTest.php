<?php

namespace Tests\Feature;

use App\Models\Scrobble;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListeningArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_archive_ranks_scrobbles_within_the_selected_period(): void
    {
        $this->travelTo('2026-11-22 12:00:00');

        $this->createScrobble('artist-one-first', 'Artist One', 'First Track', null, '2026-11-20 10:00:00');
        $this->createScrobble('artist-one-second', 'Artist One', 'Second Track', 'Album One', '2026-11-21 10:00:00');
        $this->createScrobble('artist-two', 'Artist Two', 'Third Track', null, '2026-11-19 10:00:00');
        $this->createScrobble('artist-old', 'Old Artist', 'Old Track', null, '2026-09-01 10:00:00');

        $this->get('/listening?type=artists&period=1month')
            ->assertOk()
            ->assertSee('Listening Archive')
            ->assertSee('2 plays')
            ->assertSee('Artist One')
            ->assertSee('Artist Two')
            ->assertDontSee('Old Artist')
            ->assertSeeInOrder(['Artist One', '2 plays', 'Artist Two', '1 play']);
    }

    public function test_album_and_track_views_preserve_artist_identity_and_skip_missing_albums(): void
    {
        $this->travelTo('2026-11-22 12:00:00');

        $this->createScrobble('first-album', 'Artist One', 'Shared Track', 'Shared Album', '2026-11-20 10:00:00');
        $this->createScrobble('second-album', 'Artist Two', 'Shared Track', 'Shared Album', '2026-11-21 10:00:00');
        $this->createScrobble('missing-album', 'Artist Three', 'No Album Track', null, '2026-11-21 11:00:00');

        $this->get('/listening?type=albums&period=overall')
            ->assertOk()
            ->assertSee('Shared Album')
            ->assertSee('Artist One')
            ->assertSee('Artist Two')
            ->assertDontSee('No Album Track');

        $this->get('/listening?type=tracks&period=overall')
            ->assertOk()
            ->assertSee('Shared Track')
            ->assertSee('Artist One')
            ->assertSee('Artist Two')
            ->assertSee('No Album Track')
            ->assertSee('Artist Three');
    }

    public function test_empty_archive_shows_a_helpful_message(): void
    {
        $this->get('/listening')
            ->assertOk()
            ->assertSee('No Last.fm listening history is available for this range yet.');
    }

    public function test_ranked_results_are_paginated_and_keep_the_selected_filters(): void
    {
        for ($position = 1; $position <= 51; $position++) {
            $this->createScrobble(
                'artist-'.$position,
                'Artist '.str_pad((string) $position, 2, '0', STR_PAD_LEFT),
                'Track '.$position,
                null,
                '2026-11-20 10:00:00',
            );
        }

        $this->get('/listening?type=artists&period=overall')
            ->assertOk()
            ->assertSee('51 scrobbles')
            ->assertSee('page=2', false)
            ->assertDontSee('Artist 51');
    }

    private function createScrobble(string $sourceKey, string $artist, string $track, ?string $album, string $listenedAt): void
    {
        Scrobble::query()->create([
            'source_key' => $sourceKey,
            'artist_name' => $artist,
            'track_name' => $track,
            'album_name' => $album,
            'listened_at' => $listenedAt,
        ]);
    }
}
