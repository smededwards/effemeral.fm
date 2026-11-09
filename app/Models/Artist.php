<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    protected $fillable = ['name', 'normalised_name', 'spotify_id', 'lastfm_mbid'];

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->withPivot('source', 'suppressed')->withTimestamps();
    }

    public function acceptedGenres(): BelongsToMany
    {
        return $this->genres()->wherePivot('suppressed', false)->select('genres.*')->distinct();
    }
}
