<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records how an album entered the Spotify-supported catalogue.
 */
class SpotifyAlbumCollection extends Model
{
    protected $fillable = ['album_id', 'collection_type', 'spotify_playlist_id', 'saved_at'];

    protected function casts(): array
    {
        return ['saved_at' => 'immutable_datetime'];
    }

    /**
     * Return the canonical album represented by this collection record.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
