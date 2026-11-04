# Effemeral

Effemeral is a private, single-user Laravel application for turning listening history into album-level discovery and playlist drafts.

The prototype is intentionally album-first and manual-first:

- Last.fm is the authoritative source for listening history and completion evidence.
- Spotify provides saved albums, liked songs, playlists, catalogue metadata, and the eventual playlist export.
- Effemeral stores a canonical catalogue of artists, albums, and tracks, with source identifiers and Spotify collection membership.
- Recommendations remain drafts until they are reviewed, reordered, and explicitly synced.
- Discogs, genre enrichment, full-history import, liked-song import, and playlist-content import are follow-up slices.

## Current import foundation

The current import layer supports:

- Last.fm scrobbles linked to canonical artists, albums, and tracks.
- Spotify saved albums linked to canonical artists, albums, tracks, and saved-collection membership.
- Idempotent retries and cross-source matching using provider IDs first, then normalized identity.
- Existing compatibility storage for the current dashboard while the canonical model becomes the source for future features.

The model is deliberately additive. Existing imported data is not removed by the catalogue migrations.

## Local development

The application runs in Docker with MySQL, Redis, a queue worker, and the shared Traefik proxy used by the other local projects.

The local URL is https://app.effemeral.local.

Start the shared proxy once:

    docker compose -f /Users/smededwards/Developer/effemeral/.docker/traefik/compose.yaml up -d

Then start Effemeral:

    cd /Users/smededwards/Developer/effemeral/effemeral.fm
    docker compose up -d

Do not commit .env or provider credentials. Copy .env.example to .env and configure the Last.fm and Spotify credentials locally.

## Verification

Run the standard checks from the application container:

    docker compose exec laravel.test composer test
    docker compose exec laravel.test composer lint
    docker compose exec laravel.test composer analyse

## Development workflow

- Work from the latest dev branch using a feature branch.
- Open pull requests into dev and review them before merging.
- Use Conventional Commits.
- Every pull request needs a description covering purpose, user-facing changes, validation, migration or setup notes, and follow-up work.
- Keep manual review and override paths for recommendations and completion decisions.

## Roadmap direction

1. Complete the canonical import foundation.
2. Import Spotify liked songs and playlist contents into the canonical model.
3. Rebuild completion and recommendation queries on canonical records.
4. Add album-level Effemeral playlist editing and Spotify export.
5. Add normalized genre enrichment and manual genre corrections.
6. Add Discogs metadata and full discography support.
7. Expand from the prototype's limited imports to the complete Last.fm and Spotify datasets.
