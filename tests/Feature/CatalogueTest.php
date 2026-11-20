<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_search_finds_artists_and_albums(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Cult of Luna',
            'normalised_name' => 'cult of luna',
        ]);
        $album = Album::query()->create([
            'title' => 'Mariner',
            'normalised_title' => 'mariner',
        ]);
        $album->artists()->attach($artist->id);

        $response = $this->get(route('catalogue.index', ['q' => 'Cult of Luna']));

        $response->assertOk();
        $response->assertSee('Cult of Luna');
        $response->assertSee('Mariner');
    }

    public function test_catalogue_can_select_an_artist_and_album(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Baroness',
            'normalised_name' => 'baroness',
        ]);
        $album = Album::query()->create([
            'title' => 'Blue Record',
            'normalised_title' => 'blue record',
            'release_date' => '2009-10-13',
        ]);
        $album->artists()->attach($artist->id);

        $this->get(route('catalogue.index', ['artist' => $artist->id]))
            ->assertOk()
            ->assertSee('Selected artist')
            ->assertSee('Blue Record');
        $this->get(route('catalogue.index', ['album' => $album->id]))
            ->assertOk()
            ->assertSee('Selected album')
            ->assertSee('2009-10-13');
    }
}
