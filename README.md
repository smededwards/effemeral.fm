# Effemeral

Effemeral is a private, single-user Laravel application for discovering full albums and curating them into playlists.

The primary workflow is album-first and manual-first:

- Last.fm is the authoritative source for listening history and completion evidence.
- Browse albums by genre, artist, country or region, release year, and listening completion.
- Build and manage named album playlists in Effemeral, then review their order and contents.
- Spotify supplies supporting catalogue data and receives reviewed playlists on explicit sync.
- Effemeral stores a canonical catalogue of artists, albums, and tracks, with source identifiers and Spotify collection membership.
- Recommendations remain drafts until they are reviewed, reordered, and explicitly synced.
- Selected-album similarity and artist discography playlist drafts are now available through the shared review workflow.
- Imports support discovery and completion; they are not the main destination of the app.
- Discogs metadata, bidirectional playlist sync, richer playlist-content analysis, deployment hardening, and multi-user support remain later work.

## Supporting data foundation

The current data layer supports:

- Last.fm scrobbles linked to canonical artists, albums, and tracks.
- Full Last.fm history can be queued from the account's earliest available scrobble.
- Spotify saved albums linked to canonical artists, albums, tracks, and saved-collection membership.
- Spotify liked songs linked to canonical artists, albums, tracks, and liked-track collection membership.
- Spotify artist genres and filtered Last.fm tags with normalized manual corrections.
- Idempotent retries and cross-source matching using provider IDs first, then normalized identity.
- Existing compatibility storage for the current dashboard while the canonical model becomes the source for future features.

The model is deliberately additive. Existing imported data is not removed by the catalogue migrations. Imports are manual and retryable so the user can refresh supporting data when needed.

## Local development

The application runs in Docker with MySQL, Redis, a queue worker, and the shared Traefik proxy used by the other local projects.

The local URL is https://app.effemeral.local.

Start the shared proxy once:

    docker compose -f /Users/smededwards/Developer/effemeral/.docker/traefik/compose.yaml up -d

Then start Effemeral:

    cd /Users/smededwards/Developer/effemeral/effemeral.fm
    docker compose up -d

Do not commit .env or provider credentials. Copy .env.example to .env and configure the Last.fm and Spotify credentials locally.

For a private deployment, set `EFFEMERAL_ACCESS_USERNAME` and `EFFEMERAL_ACCESS_PASSWORD`. Local development remains open when the password is blank; production fails closed until a password is configured.

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

## Current priorities

The selected-album discovery slice already supports similar-album suggestions and artist discography drafts. The next milestone focuses on making playlist curation a complete, everyday workflow:

1. Make Effemeral playlists persistent and first-class: create, name, reopen, and manage multiple album-level drafts.
2. Add albums from a filterable catalogue using genre, artist, country or region, release year, and completion status.
3. Review album inclusion and order, save changes, and show which Spotify playlist each Effemeral playlist syncs with.
4. Create a new Spotify playlist or update a chosen destination only after explicit review; show sync outcomes and errors.
5. Make the searchable imported Spotify playlist overview available as supporting context and audit access.
6. Keep imports and status details on the Imports page and secondary on the overview.

The existing [imported Spotify playlist overview](https://github.com/smededwards/effemeral.fm/issues/68) is in review as a supporting slice of this milestone.

Bidirectional editing between Spotify and Effemeral is deferred until this reviewed Effemeral-to-Spotify workflow has been validated. Discogs remains a later catalogue enrichment source; current discovery operates on records already present in the canonical catalogue.

The active `v0.3.0` Candlemass milestone is being realigned around this playlist-first workflow. GitHub tasks must include acceptance criteria before they are marked ready.
