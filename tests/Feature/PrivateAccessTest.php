<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_private_access_protects_the_application(): void
    {
        config()->set('services.effemeral.access_username', 'michael');
        config()->set('services.effemeral.access_password', 'secret');

        $this->get(route('dashboard'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="Effemeral"');

        $this->withBasicAuth('michael', 'secret')
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_production_fails_closed_without_a_password(): void
    {
        config()->set('app.env', 'production');
        config()->set('services.effemeral.access_password', null);

        $this->get(route('dashboard'))
            ->assertStatus(503)
            ->assertSee('Private access is not configured.');
    }
}
