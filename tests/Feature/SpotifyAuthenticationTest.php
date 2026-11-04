<?php

namespace Tests\Feature;

use App\Models\SpotifyConnection;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpotifyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spotify_authentication_redirect_contains_a_state_token(): void
    {
        config()->set('services.spotify.client_id', 'client-id');

        $response = $this->get(route('auth.spotify'));

        $response->assertRedirectContains('https://accounts.spotify.com/authorize');
        $response->assertRedirectContains('playlist-modify-private');
        $this->assertNotEmpty(session('spotify_oauth_state'));
    }

    public function test_spotify_callback_stores_tokens_encrypted(): void
    {
        config()->set([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);
        Http::fake([
            '*' => Http::response(['access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600]),
        ]);

        $response = $this->withSession(['spotify_oauth_state' => 'state-token'])->get(route('auth.spotify.callback', [
            'code' => 'auth-code',
            'state' => 'state-token',
        ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Spotify account connected.');
        $connection = SpotifyConnection::query()->firstOrFail();
        $this->assertSame('access-token', $connection->access_token);
        $this->assertNotSame('access-token', $connection->getRawOriginal('access_token'));
        Http::assertSent(fn ($request): bool => $request->url() === config('services.spotify.token_endpoint') && $request['code'] === 'auth-code');
    }

    public function test_expired_spotify_access_tokens_are_refreshed_before_an_import_request(): void
    {
        config()->set([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);
        SpotifyConnection::query()->create([
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->subMinute(),
        ]);
        Http::fake([
            config('services.spotify.token_endpoint') => Http::response(['access_token' => 'new-token', 'expires_in' => 3600]),
            '*' => Http::response(['next' => null, 'items' => []]),
        ]);

        app(SpotifyClient::class)->savedAlbums(1);

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), config('services.spotify.api_endpoint').'/me/albums') && $request->header('Authorization') === ['Bearer new-token']);
        $this->assertSame('new-token', SpotifyConnection::query()->firstOrFail()->access_token);
    }
}
