<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    protected $fillable = [
        'title', 'normalised_title', 'album_type', 'spotify_id', 'lastfm_mbid',
        'spotify_uri', 'spotify_url', 'total_tracks', 'release_date',
        'release_date_precision', 'cover_image_url',
    ];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('artist_position');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
