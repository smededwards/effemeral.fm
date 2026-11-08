# Effemeral

Effemeral is a private, single-user Laravel application for turning listening history into album-level discovery and playlist drafts.

The prototype is intentionally album-first and manual-first:

- Last.fm is the authoritative source for listening history and completion evidence.
- Spotify provides saved albums, liked songs, playlists, catalogue metadata, and the eventual playlist export.
- Effemeral stores a canonical catalogue of artists, albums, and tracks, with source identifiers and Spotify collection membership.
- Recommendations remain drafts until they are reviewed, reordered, and explicitly synced.
- The next discovery workflows are selected-album similarity and artist discography playlist drafts.
- Discogs metadata, full-history import, liked-song import, and richer playlist-content analysis remain follow-up slices.

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

The target prototype supports two related album-first workflows:

1. Select an album or artist from the canonical catalogue.
2. Suggest similar albums using normalized genre, artist relationships, release year, and Last.fm completion evidence.
3. Generate an album-level discography draft for a selected artist.
4. Review, reorder, include, exclude, and manually override the draft.
5. Sync the approved draft to a private Spotify playlist.

The implementation order is:

1. Complete the canonical catalogue and import foundation.
2. Import and normalize Spotify artist genres and Last.fm tags, with manual corrections.
3. Add catalogue search and selected album/artist context.
4. Add same-genre, same-year album suggestions that exclude completed albums by default.
5. Add artist discography discovery and album-level playlist drafts.
6. Keep the existing review, audit, and Spotify sync flow as the shared delivery path.
7. Add Discogs as the preferred metadata and genre authority when ready.
8. Expand to the complete Last.fm history, Spotify liked songs, and deeper playlist-content analysis.

The current prototype has steps 1 and 6 in place for saved-album recommendations. It does not yet provide steps 2–5 for arbitrary catalogue selections.
