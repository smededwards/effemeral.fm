<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LastFmImportRun extends Model
{
    protected $fillable = [
        'source', 'status', 'from_at', 'to_at', 'requested_limit',
        'fetched_count', 'imported_count', 'skipped_count', 'error',
        'started_at', 'completed_at', 'retry_of_id',
    ];

    protected function casts(): array
    {
        return [
            'from_at' => 'immutable_datetime',
            'to_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }
}
