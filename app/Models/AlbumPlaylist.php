<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AlbumPlaylist extends Model
{
    protected $fillable = ['name', 'spotify_playlist_id', 'spotify_playlist_url'];

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_playlist_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('album_playlist_items.position');
    }
}
