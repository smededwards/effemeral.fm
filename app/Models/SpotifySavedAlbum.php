<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Spotify saved-album membership and manual completion state.
 */
class SpotifySavedAlbum extends Model
{
    protected $fillable = [
        'spotify_album_id', 'spotify_uri', 'spotify_url', 'name', 'album_type',
        'total_tracks', 'release_date', 'release_date_precision', 'primary_artist_id',
        'primary_artist_name', 'artists', 'tracks', 'cover_image_url', 'saved_at',
        'last_synced_at', 'completion_override',
    ];

    /**
     * Return the saved album title in the application presentation style.
     *
     * @return string The display-ready album title.
     */
    public function displayTitle(): string
    {
        return Str::title($this->name);
    }

    protected function casts(): array
    {
        return [
            'artists' => 'array',
            'tracks' => 'array',
            'saved_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'completion_override' => 'boolean',
        ];
    }

    /**
     * Return recommendation entries sourced from this saved album.
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(AlbumRecommendation::class);
    }
}
