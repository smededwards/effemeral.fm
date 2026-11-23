<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Imported Spotify playlist metadata used for audit and sync selection.
 */
class SpotifyPlaylist extends Model
{
    protected $fillable = [
        'spotify_playlist_id', 'name', 'description', 'spotify_url', 'owner_id',
        'public', 'collaborative', 'tracks_total', 'snapshot_id', 'last_synced_at',
        'last_import_error', 'last_import_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'public' => 'boolean',
            'collaborative' => 'boolean',
            'last_synced_at' => 'immutable_datetime',
            'last_import_attempted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Return imported items associated with the playlist.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SpotifyPlaylistItem::class);
    }
}
