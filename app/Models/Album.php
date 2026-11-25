<?php

namespace App\Models;

use App\Models\Concerns\HasEfmId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Canonical album record shared by Last.fm, Spotify, and MusicBrainz.
 */
class Album extends Model
{
    use HasEfmId;

    /** EFMID entity type used in generated identifiers. */
    protected const EFM_TYPE = 'ALB';

    protected $fillable = [
        'title', 'normalised_title', 'album_type', 'spotify_id', 'lastfm_mbid', 'musicbrainz_release_group_mbid',
        'spotify_uri', 'spotify_url', 'total_tracks', 'release_date',
        'release_date_precision', 'cover_image_url',
    ];

    /**
     * Return the presentation title without changing the stored source value.
     *
     * @return string The display-ready album title.
     */
    public function displayTitle(): string
    {
        return Str::title($this->title);
    }

    /**
     * Return the artists credited on the album.
     */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('artist_position');
    }

    /**
     * Return the canonical tracks belonging to the album.
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    /**
     * Return every canonical track released on this album, including tracks shared with another release.
     */
    public function releaseTracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'album_track_releases')
            ->withPivot('disc_number', 'track_number')
            ->withTimestamps();
    }

    /**
     * Return saved-album and playlist collection memberships.
     */
    public function spotifyCollections(): HasMany
    {
        return $this->hasMany(SpotifyAlbumCollection::class);
    }

    /**
     * Return imported Spotify playlist item references.
     */
    public function spotifyPlaylistItems(): HasMany
    {
        return $this->hasMany(SpotifyPlaylistItem::class);
    }

    /**
     * Return Effemeral playlists containing the album.
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(AlbumPlaylist::class, 'album_playlist_items')
            ->withPivot('position')
            ->withTimestamps();
    }
}
