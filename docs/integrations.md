# Integrations guide

## Source responsibilities

Effemeral stores a canonical catalogue of artists, albums, tracks, genres, playlists, and source identifiers. Provider data is imported into that catalogue with provenance rather than replacing it.

| Source | Used for | Not used as |
| --- | --- | --- |
| Last.fm | Scrobbles, listening dates, completion evidence, and filtered tags. | A Spotify catalogue or playlist destination. |
| Spotify | Saved albums, liked songs, imported playlists, Spotify IDs, track availability, and final playlist sync. | The authority for whether an album has been heard. |
| MusicBrainz | Artist identity, country, location, and existing-catalogue enrichment. | A reason to import every release variant or full external discography. |

## Last.fm

Configure these values in `.env`:

```dotenv
LASTFM_API_KEY=...
LASTFM_USERNAME=...
LASTFM_IMPORT_START=2010-11-10
LASTFM_API_ENDPOINT=https://ws.audioscrobbler.com/2.0/
LASTFM_USER_AGENT="Effemeral/0.1 (+https://github.com/smededwards/effemeral.fm)"
LASTFM_GENRE_ALLOWLIST=
LASTFM_MIN_REQUEST_INTERVAL_MS=250
```

Last.fm is authoritative for listening history. Imports are idempotent and retain source URLs and timestamps where available. Resume a failed import instead of starting a second competing history import.

Unknown dates represented as 1970 should not be treated as modern listening evidence. The known first scrobble date can be used as the lower bound for a complete history import.

## Spotify

Configure these values in `.env`:

```dotenv
SPOTIFY_CLIENT_ID=...
SPOTIFY_CLIENT_SECRET=...
SPOTIFY_REDIRECT_URI=https://app.effemeral.local/auth/spotify/callback
SPOTIFY_API_ENDPOINT=https://api.spotify.com/v1
SPOTIFY_TOKEN_ENDPOINT=https://accounts.spotify.com/api/token
SPOTIFY_RATE_LIMIT_COOLDOWN_SECONDS=60
SPOTIFY_QUOTA_COOLDOWN_SECONDS=300
SPOTIFY_SCOPES="user-library-read playlist-read-private playlist-read-collaborative playlist-modify-private playlist-modify-public"
```

The redirect URI must match the Spotify developer application exactly, including scheme, host, path, and local HTTPS. The local callback is:

```text
https://app.effemeral.local/auth/spotify/callback
```

Spotify imports are limited to saved albums, liked songs, and playlists. Album track enrichment is separate so it can use the shared lock, persistent cursor, pauses, and quota handling in the [CLI guide](cli.md).

Avoid using `--enrich` on large library or playlist imports. Import the collection first, then run targeted enrichment in protected batches.

### Spotify rate-limit rules

- Only one Spotify album enrichment command runs at a time.
- Each album is written before the cursor advances.
- A rate-limit or quota response pauses the run.
- The album that triggered the response remains eligible for retry.
- `--resume` continues the recorded run instead of starting from the beginning.
- The Redis lock prevents overlapping enrichment processes.

After a quota error, do not repeatedly launch new runs. Check status, wait for the provider reset window, and resume the paused run.

## MusicBrainz

MusicBrainz enrichment is deliberately limited to context for records already in Effemeral:

1. `artists:import-musicbrainz` adds or refreshes artist IDs, country, location, aliases and relationships for existing artists.
2. `artists:enrich-musicbrainz` matches MusicBrainz release groups to existing catalogue albums and adds release metadata.

MusicBrainz enrichment does not create artists, albums or tracks. An artist must already have Last.fm or Spotify source evidence before a MusicBrainz ID can be added or refreshed. A MusicBrainz ID alone is never treated as proof that an artist belongs in the catalogue. Album enrichment also requires an existing album with Last.fm or Spotify evidence, so MusicBrainz release groups cannot create empty album shells. Spotify and Last.fm remain the sources for catalogue track data and track credits. A release group that has no existing Effemeral album is reported as unmatched and is not imported.

The discography process excludes release types that do not belong in the canonical album catalogue, including live recordings, compilations, demos, bootlegs, b-sides, and special releases. It is not a general-purpose import of every MusicBrainz release.

Useful examples:

```bash
docker compose exec laravel.test php artisan artists:import-musicbrainz --known-only --limit=50
docker compose exec laravel.test php artisan artists:enrich-musicbrainz --limit=10
```

MusicBrainz is the preferred source for artist country and location. Manual edits take precedence over later provider refreshes. Artist aliases and relationships are stored as provider context and never merge canonical artists automatically.

## Matching and identity

Matching prefers provider IDs, then normalized artist and album identity. The catalogue also stores internal EFM identifiers for artists, albums, tracks, genres, and playlists. Internal IDs are stable application identifiers; provider IDs remain useful for cross-source matching and external links.

Album reconciliation is conservative. Empty duplicate album shells can be merged
after a dry run, but records with provider IDs, tracks or listening references
remain separate until they are reviewed. Retired album EFMIDs are retained as
aliases when a safe merge is applied.

Artists with similar names must not be merged solely because their display names match. Provider IDs, MusicBrainz IDs, Spotify IDs, and album relationships keep distinct artists separate.

## Credentials and privacy

Keep `.env` and provider credentials local. Do not paste client secrets, access tokens, refresh tokens, or local certificates into issues, pull requests, documentation, or screenshots. The application is intended to remain private while it is a single-user prototype.
