<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imported Spotify playlist item and its canonical album or track match.
 */
class SpotifyPlaylistItem extends Model
{
    protected $fillable = [
        'spotify_playlist_id', 'album_id', 'track_id', 'spotify_track_id',
        'spotify_uri', 'track_name', 'position', 'added_at', 'is_local',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'immutable_datetime',
            'is_local' => 'boolean',
        ];
    }

    /**
     * Return the imported Spotify playlist containing the item.
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(SpotifyPlaylist::class, 'spotify_playlist_id');
    }

    /**
     * Return the canonical album matched to the item, when available.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Return the canonical track matched to the item, when available.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
