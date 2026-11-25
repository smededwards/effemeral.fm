<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It keeps saved location controls collapsed until the user chooses to edit.
     */
    public function test_saved_artist_location_is_collapsed(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Mastodon',
            'normalised_name' => 'mastodon',
            'country_code' => 'US',
            'location' => 'Atlanta',
        ]);

        $this->get(route('artists.show', $artist))
            ->assertOk()
            ->assertSee('class="mt-4 max-w-xl"', false)
            ->assertSee('Edit location')
            ->assertSee('Save location');
    }

    /**
     * It exposes the existing manual genre override on the artist page.
     */
    public function test_artist_page_exposes_genre_editor(): void
    {
        $artist = Artist::query()->create([
            'name' => 'Mastodon',
            'normalised_name' => 'mastodon',
        ]);
        $genre = Genre::query()->create([
            'name' => 'sludge metal',
            'normalised_name' => 'sludge metal',
        ]);
        $artist->genres()->attach($genre->id, ['source' => 'manual', 'suppressed' => false]);

        $this->get(route('artists.show', $artist))
            ->assertOk()
            ->assertSee('Edit genres')
            ->assertSee('Remove sludge metal')
            ->assertSee('sludge metal')
            ->assertDontSee('Save genres');
    }
}
