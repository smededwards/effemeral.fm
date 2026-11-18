<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
