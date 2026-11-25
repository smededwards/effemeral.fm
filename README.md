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

    docker compose -f ../.docker/traefik/compose.yaml up -d

Then start Effemeral:

    cd /Users/smededwards/Developer/effemeral/effemeral.fm
    docker compose up -d

Do not commit .env or provider credentials. Copy .env.example to .env and configure the Last.fm and Spotify credentials locally.

For a private deployment, set `EFFEMERAL_ACCESS_USERNAME` and `EFFEMERAL_ACCESS_PASSWORD`. Local development remains open when the password is blank; production fails closed until a password is configured.

## Command-line enrichment

See the complete [CLI guide](docs/cli.md) for safe import, enrichment, status, retry, and playlist-sync workflows.

See the [documentation index](docs/README.md) for the [application guide](docs/application.md), [integrations guide](docs/integrations.md), and [development guide](docs/development.md).

Enrichment can be run in small, resumable batches from the application container:

    docker compose exec laravel.test php artisan albums:enrich-spotify --scope=priority --limit=10 --pause-every=3 --pause=10
    docker compose exec laravel.test php artisan artists:enrich-musicbrainz --limit=10

Use `--after-id=ID` to continue after a known record, `--artist-id=ID` to target one artist, and `--dry-run` to list eligible records without creating a run or making provider requests. Spotify album enrichment defaults to priority ordering, persists progress, supports `--resume`, and can run continuously with `--continuous`. Its `priority` and `canonical` scopes exclude obvious live, demo, compilation, bootleg, remix, soundtrack, sampler, session, radio, unplugged, box-set, and special-release titles. MusicBrainz discography enrichment also accepts `--refresh` to include artists with a completed run. Spotify enrichment stops when its shared rate-limit or quota protection is active.

Import operations are also available from the CLI:

    docker compose exec laravel.test php artisan imports:status
    docker compose exec laravel.test php artisan imports:clear-stale --dry-run
    docker compose exec laravel.test php artisan lastfm:import --from=2024-01-01 --to=2024-12-31 --limit=100
    docker compose exec laravel.test php artisan spotify:import-albums --limit=100
    docker compose exec laravel.test php artisan spotify:import-playlists --limit=10 --only-new
    docker compose exec laravel.test php artisan spotify:import-liked-tracks --limit=100
    docker compose exec laravel.test php artisan playlists:sync 12
    docker compose exec laravel.test php artisan playlists:sync 12 --destination=existing --spotify-playlist-id=spotify-id --confirm

Use `--resume` for failed resumable Last.fm, saved-album, or playlist imports. Import commands run synchronously so progress and provider errors remain visible in the terminal.

Playlist syncing previews changes by default. It only writes to Spotify when `--confirm` is supplied, and it refuses to sync incomplete albums.

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
- Apply DRY, KISS, and YAGNI; use SOLID, separation of concerns, composition over inheritance, fail-fast checks, and guard clauses as practical design guidance. Write comparisons in natural order (avoid Yoda conditions), and prefer ternaries for concise expression choices while keeping multi-step branches as `if` statements.
- Run Ponytail on the complete proposed changes before opening a pull request, address actionable findings, and summarize the outcome in the PR handoff.

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
