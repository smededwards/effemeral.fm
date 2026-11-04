<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Track extends Model
{
    protected $fillable = [
        'album_id', 'title', 'normalised_title', 'spotify_id', 'lastfm_mbid',
        'spotify_uri', 'disc_number', 'track_number', 'duration_ms',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
