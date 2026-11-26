<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
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
        $response->assertSee('Search by artist or album');
        $response->assertSee('Cult of Luna');
        $response->assertSee('Mariner');

        $this->get(route('catalogue.index', ['q' => 'Mariner.']))
            ->assertOk()
            ->assertSee('Mariner');
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

    public function test_catalogue_filters_show_only_matching_albums_instead_of_the_default_artist_list(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Matching Artist',
            'normalised_name' => 'matching artist',
            'country_code' => 'GB',
        ]);
        $genre = Genre::query()->create([
            'name' => 'Black Metal',
            'normalised_name' => 'black metal',
        ]);
        $artist->genres()->attach($genre->id, ['source' => 'manual', 'suppressed' => false]);

        $matchingAlbum = Album::query()->create([
            'title' => 'Matching Album',
            'normalised_title' => 'matching album',
            'album_type' => 'album',
            'release_date' => '2024-06-01',
        ]);
        $matchingAlbum->artists()->attach($artist->id);
        $matchingAlbum->tracks()->create([
            'title' => 'Matching track',
            'normalised_title' => 'matching track',
            'spotify_uri' => 'spotify:track:matching',
        ]);

        $unmatchedArtist = Artist::query()->create([
            'name' => 'Unfiltered Artist',
            'normalised_name' => 'unfiltered artist',
        ]);
        $unmatchedAlbum = Album::query()->create([
            'title' => 'Unmatched Album',
            'normalised_title' => 'unmatched album',
            'album_type' => 'album',
            'release_date' => '2023-06-01',
        ]);
        $unmatchedAlbum->artists()->attach($unmatchedArtist->id);

        $this->get(route('catalogue.index', [
            'filter' => 1,
            'genre' => $genre->id,
            'country' => 'GB',
            'year' => 2024,
            'completion' => 'incomplete',
        ]))
            ->assertOk()
            ->assertSee('Matching albums')
            ->assertSee('Matching Album')
            ->assertDontSee('Unmatched Album')
            ->assertDontSee('Unfiltered Artist')
            ->assertSee('Clear filters')
            ->assertSee('catalogue-select-all');
    }

    public function test_catalogue_styles_page_browses_accepted_genres(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Mastodon',
            'normalised_name' => 'mastodon',
        ]);
        $genre = Genre::query()->create([
            'name' => 'Progressive Metal',
            'normalised_name' => 'progressive metal',
        ]);
        $artist->genres()->attach($genre->id, ['source' => 'manual', 'suppressed' => false]);

        $this->get(route('catalogue.styles'))
            ->assertOk()
            ->assertSee('All styles')
            ->assertSee('progressive metal')
            ->assertSee(route('genres.show', $genre, false), false)
            ->assertSee('1');

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('Browse styles')
            ->assertDontSee('Browse by genre');
    }
}
