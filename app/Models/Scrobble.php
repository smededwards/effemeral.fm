<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scrobble extends Model
{
    protected $fillable = [
        'source', 'source_key', 'artist_name', 'artist_mbid', 'track_name',
        'track_mbid', 'album_name', 'album_mbid', 'source_url', 'listened_at',
        'now_playing',
    ];

    protected function casts(): array
    {
        return [
            'listened_at' => 'immutable_datetime',
            'now_playing' => 'boolean',
        ];
    }
}
