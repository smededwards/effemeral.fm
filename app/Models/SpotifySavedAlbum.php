<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpotifySavedAlbum extends Model
{
    protected $fillable = [
        'spotify_album_id', 'spotify_uri', 'spotify_url', 'name', 'album_type',
        'total_tracks', 'release_date', 'release_date_precision', 'primary_artist_id',
        'primary_artist_name', 'artists', 'tracks', 'cover_image_url', 'saved_at',
        'last_synced_at', 'completion_override',
    ];

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

    public function recommendations(): HasMany
    {
        return $this->hasMany(AlbumRecommendation::class);
    }
}
