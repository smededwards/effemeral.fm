<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Assign an immutable, human-readable Effemeral identifier after creation.
 */
trait HasEfmId
{
    /**
     * Register the EFMID assignment hook.
     */
    protected static function bootHasEfmId(): void
    {
        static::created(function (Model $model): void {
            if (is_string($model->getAttribute('efm_id')) && $model->getAttribute('efm_id') !== '') {
                return;
            }

            $model->forceFill(['efm_id' => self::makeEfmId($model)])->saveQuietly();
        });
    }

    /**
     * Build an EFMID from the entity type, allocation block, initial bucket and sequence.
     *
     * @param  Model  $model  The newly-created catalogue record.
     * @return string The immutable Effemeral identifier.
     */
    private static function makeEfmId(Model $model): string
    {
        $name = (string) ($model->getAttribute('name') ?? $model->getAttribute('title') ?? '');
        $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
        // Non-Latin and non-letter names use the reserved zero bucket.
        $category = preg_match('/^[A-Z]$/', $initial) === 1 ? ord($initial) - 64 : 0;
        $id = (int) $model->getKey();
        $zeroBasedId = max(0, $id - 1);
        // The database primary key provides a stable allocation without another sequence table.
        $block = intdiv($zeroBasedId, 999) + 1;
        $sequence = ($zeroBasedId % 999) + 1;

        return sprintf('EFM-%s-%03d-%03d-%03d', static::EFM_TYPE, $block, $category, $sequence);
    }
}
