<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AlbumPlaylist extends Model
{
    protected $fillable = [
        'name', 'spotify_playlist_id', 'spotify_playlist_url', 'spotify_managed_track_uris',
        'spotify_pending_track_uris', 'spotify_sync_status', 'spotify_sync_error', 'spotify_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'spotify_managed_track_uris' => 'array',
            'spotify_pending_track_uris' => 'array',
            'spotify_synced_at' => 'immutable_datetime',
        ];
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_playlist_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('album_playlist_items.position');
    }
}
