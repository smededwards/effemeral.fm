<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_focused_pages_are_reachable(): void
    {
        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Queue full import')
            ->assertSee(route('imports.lastfm'), false);

        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('Saved albums')
            ->assertSee('Spotify playlists');

        $this->get(route('recommendations.index'))
            ->assertOk()
            ->assertSee('Recommendations')
            ->assertSee(route('recommendations.generate'), false);
    }
}
