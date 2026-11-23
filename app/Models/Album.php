<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Album extends Model
{
    protected $fillable = [
        'title', 'normalised_title', 'album_type', 'spotify_id', 'lastfm_mbid', 'musicbrainz_release_group_mbid',
        'spotify_uri', 'spotify_url', 'total_tracks', 'release_date',
        'release_date_precision', 'cover_image_url',
    ];

    public function displayTitle(): string
    {
        return Str::title($this->title);
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('artist_position');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function spotifyCollections(): HasMany
    {
        return $this->hasMany(SpotifyAlbumCollection::class);
    }

    public function spotifyPlaylistItems(): HasMany
    {
        return $this->hasMany(SpotifyPlaylistItem::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(AlbumPlaylist::class, 'album_playlist_items')
            ->withPivot('position')
            ->withTimestamps();
    }
}
