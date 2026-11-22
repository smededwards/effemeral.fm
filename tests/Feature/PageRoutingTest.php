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
            ->assertSee(route('imports.lastfm', [], false), false);

        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('Saved Albums')
            ->assertSee('Spotify Playlists');

        $this->get(route('playlists.index'))
            ->assertOk()
            ->assertSee('Spotify Playlist Organisation')
            ->assertSee('Import all playlists');

        $this->get(route('listening.index'))
            ->assertOk()
            ->assertSee('Listening Archive')
            ->assertSee('aria-label="Listening Views"', false);

        $this->get(route('recommendations.index'))
            ->assertOk()
            ->assertSee('To Complete')
            ->assertSee(route('recommendations.generate', [], false), false);

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('text-ef-accent-strong uppercase', false)
            ->assertSee('Catalogue / 05');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-hidden="true">♪</span>', false)
            ->assertSee('<link rel="manifest" href="/site.webmanifest">', false)
            ->assertSee('href="/favicon-32x32.png"', false);
    }
}
