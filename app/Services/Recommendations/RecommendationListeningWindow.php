<?php

namespace App\Services\Recommendations;

use Carbon\CarbonImmutable;

/**
 * Resolves the listening windows available to recommendation generation.
 */
class RecommendationListeningWindow
{
    public const DEFAULT = '1_year';

    /**
     * Return the supported listening-window labels.
     *
     * @return array<string, string> The available window labels.
     */
    public function options(): array
    {
        return [
            '7_days' => 'Last 7 days',
            '1_month' => 'Last 30 days',
            'this_year' => 'This calendar year',
            '1_year' => 'Last 365 days',
            'all_time' => 'All time',
        ];
    }

    /**
     * Return the inclusive cutoff for a listening window.
     *
     * @param  string  $window  The selected listening window.
     * @return CarbonImmutable|null The cutoff, or null for all-time listening.
     */
    public function cutoff(string $window): ?CarbonImmutable
    {
        return match ($window) {
            '7_days' => now()->subDays(7)->toImmutable(),
            '1_month' => now()->subMonth()->toImmutable(),
            'this_year' => now()->startOfYear()->toImmutable(),
            'all_time' => null,
            default => now()->subYear()->toImmutable(),
        };
    }
}
