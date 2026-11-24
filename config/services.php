<?php

return [

    'effemeral' => [
        'access_username' => env('EFFEMERAL_ACCESS_USERNAME', 'effemeral'),
        'access_password' => env('EFFEMERAL_ACCESS_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'lastfm' => [
        'api_key' => env('LASTFM_API_KEY'),
        'username' => env('LASTFM_USERNAME'),
        'import_start' => env('LASTFM_IMPORT_START', '2010-11-10'),
        'min_request_interval_ms' => (int) env('LASTFM_MIN_REQUEST_INTERVAL_MS', 250),
        'endpoint' => env('LASTFM_API_ENDPOINT', 'https://ws.audioscrobbler.com/2.0/'),
        'user_agent' => env('LASTFM_USER_AGENT', 'Effemeral/0.1 (+https://github.com/smededwards/effemeral.fm)'),
        'genre_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('LASTFM_GENRE_ALLOWLIST', ''))))),
    ],

    'musicbrainz' => [
        'endpoint' => env('MUSICBRAINZ_API_ENDPOINT', 'https://musicbrainz.org/ws/2'),
        'user_agent' => env('MUSICBRAINZ_USER_AGENT', 'Effemeral/0.1 (https://github.com/smededwards/effemeral.fm)'),
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        'redirect_uri' => env('SPOTIFY_REDIRECT_URI', 'https://app.effemeral.local/auth/spotify/callback'),
        'access_token' => env('SPOTIFY_ACCESS_TOKEN'),
        'refresh_token' => env('SPOTIFY_REFRESH_TOKEN'),
        'api_endpoint' => env('SPOTIFY_API_ENDPOINT', 'https://api.spotify.com/v1'),
        'token_endpoint' => env('SPOTIFY_TOKEN_ENDPOINT', 'https://accounts.spotify.com/api/token'),
        'rate_limit_cooldown_seconds' => (int) env('SPOTIFY_RATE_LIMIT_COOLDOWN_SECONDS', 60),
        'quota_cooldown_seconds' => (int) env('SPOTIFY_QUOTA_COOLDOWN_SECONDS', 300),
        'scopes' => env('SPOTIFY_SCOPES', 'user-library-read playlist-read-private playlist-read-collaborative playlist-modify-private playlist-modify-public'),
    ],

];
