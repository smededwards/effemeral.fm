<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotifyAlbumCollection extends Model
{
    protected $fillable = ['album_id', 'collection_type', 'spotify_playlist_id', 'saved_at'];

    protected function casts(): array
    {
        return ['saved_at' => 'immutable_datetime'];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
