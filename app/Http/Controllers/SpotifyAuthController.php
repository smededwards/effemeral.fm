<?php

namespace App\Http\Controllers;

use App\Models\SpotifyConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Manages the single-user Spotify OAuth connection.
 */
class SpotifyAuthController extends Controller
{
    /**
     * Redirect the user to Spotify's authorization screen.
     *
     * @return RedirectResponse The Spotify authorization redirect.
     */
    public function redirect(): RedirectResponse
    {
        $state = Str::random(40);
        session(['spotify_oauth_state' => $state]);

        return redirect('https://accounts.spotify.com/authorize?'.http_build_query([
            'client_id' => config('services.spotify.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.spotify.redirect_uri'),
            'scope' => config('services.spotify.scopes'),
            'state' => $state,
        ]));
    }

    /**
     * Validate Spotify's callback state and store the encrypted tokens.
     *
     * @param  Request  $request  The OAuth callback request.
     * @return RedirectResponse The connection status redirect.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! session()->has('spotify_oauth_state') || ! $request->filled('state') || ! hash_equals((string) session('spotify_oauth_state'), (string) $request->input('state'))) {
            return redirect()->route('dashboard')->with('error', 'Spotify authentication state did not match.');
        }

        session()->forget('spotify_oauth_state');

        if ($request->filled('error')) {
            return redirect()->route('dashboard')->with('error', 'Spotify authentication was cancelled.');
        }

        $response = Http::asForm()->acceptJson()->post(config('services.spotify.token_endpoint'), [
            'grant_type' => 'authorization_code',
            'code' => $request->string('code')->toString(),
            'redirect_uri' => config('services.spotify.redirect_uri'),
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
        ]);

        if ($response->failed()) {
            return redirect()->route('dashboard')->with('error', 'Spotify token exchange failed.');
        }

        $token = $response->json();
        $accessToken = $token['access_token'] ?? null;
        $refreshToken = $token['refresh_token'] ?? null;

        if (! is_string($accessToken) || ! is_string($refreshToken)) {
            return redirect()->route('dashboard')->with('error', 'Spotify returned an incomplete token response.');
        }

        SpotifyConnection::query()->delete();
        SpotifyConnection::query()->create([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ]);

        return redirect()->route('dashboard')->with('success', 'Spotify account connected.');
    }

    /**
     * Remove the local Spotify connection and its stored tokens.
     *
     * @return RedirectResponse The connection status redirect.
     */
    public function disconnect(): RedirectResponse
    {
        SpotifyConnection::query()->delete();

        return redirect()->route('dashboard')->with('success', 'Spotify account disconnected.');
    }
}
