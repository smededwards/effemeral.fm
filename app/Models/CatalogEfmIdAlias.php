<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Retired EFMID that resolves to a surviving canonical catalogue record.
 */
class CatalogEfmIdAlias extends Model
{
    protected $fillable = [
        'entity_type',
        'retired_efm_id',
        'canonical_entity_id',
        'canonical_efm_id',
        'reason',
    ];
}
