<?php

namespace App\Models;

use App\Models\Concerns\HasEfmId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Persistent Effemeral playlist containing ordered albums.
 */
class AlbumPlaylist extends Model
{
    use HasEfmId;

    /** EFMID entity type used in generated identifiers. */
    protected const EFM_TYPE = 'PLY';

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

    /**
     * Return the ordered albums assigned to the playlist.
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_playlist_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('album_playlist_items.position');
    }
}
