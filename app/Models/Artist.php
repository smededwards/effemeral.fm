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
}
