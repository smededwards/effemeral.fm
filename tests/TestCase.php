<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prevent destructive database traits from running against the local application database.
     *
     * @throws RuntimeException When the test environment is not isolated.
     */
    protected function setUp(): void
    {
        $this->guardAgainstNonTestingDatabase();

        parent::setUp();
    }

    /**
     * Fail before RefreshDatabase can reset the wrong database.
     *
     * @throws RuntimeException When configuration is cached or points outside the test database.
     */
    private function guardAgainstNonTestingDatabase(): void
    {
        $environment = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
        $databaseName = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');

        if (is_file(__DIR__.'/../bootstrap/cache/config.php')) {
            throw new RuntimeException(
                'Tests cannot run while Laravel configuration is cached. Run php artisan config:clear first.',
            );
        }

        if ($environment !== 'testing' || $databaseName !== 'testing') {
            throw new RuntimeException(
                "Tests must use APP_ENV=testing and DB_DATABASE=testing; received {$databaseName}.",
            );
        }
    }
}
