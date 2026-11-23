<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spotify liked-track membership linked to canonical catalogue records.
 */
class SpotifyLikedTrack extends Model
{
    protected $fillable = [
        'spotify_track_id', 'spotify_uri', 'track_name', 'album_id', 'track_id', 'saved_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'saved_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
        ];
    }

    /**
     * Return the canonical album represented by the liked track.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Return the canonical track represented by the liked item.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
