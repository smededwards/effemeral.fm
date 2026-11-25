<?php

namespace Tests\Unit;

use App\Services\Search\SearchTermNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchTermNormalizerTest extends TestCase
{
    /**
     * @param  string  $input  The user-entered search term.
     * @param  string  $expected  The normalised search term.
     */
    #[DataProvider('searchTerms')]
    public function test_boundary_punctuation_does_not_change_a_search_term(string $input, string $expected): void
    {
        $this->assertSame($expected, app(SearchTermNormalizer::class)->normalize($input));
    }

    /**
     * @return array<string, array{string, string}> The search term examples.
     */
    public static function searchTerms(): array
    {
        return [
            'trailing full stop' => ['Mariner.', 'mariner'],
            'quoted search' => ['"Mariner"', 'mariner'],
            'extra whitespace' => ['  Cult of Luna  ', 'cult of luna'],
        ];
    }
}
