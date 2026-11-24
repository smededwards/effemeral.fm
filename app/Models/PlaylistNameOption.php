<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable word captured from an owned Spotify playlist name.
 */
class PlaylistNameOption extends Model
{
    protected $fillable = [
        'word',
        'source',
    ];
}
