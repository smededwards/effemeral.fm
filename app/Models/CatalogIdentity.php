<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Provider identity that can be linked to one or more canonical catalogue records.
 */
class CatalogIdentity extends Model
{
    protected $fillable = [
        'provider', 'entity_type', 'identifier_type', 'identifier', 'provider_url', 'provider_name', 'metadata',
    ];

    /**
     * Cast provider metadata to an array for application use.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
