# Effemeral CLI guide

Use the Artisan commands below from the Effemeral project directory. The commands run inside the Laravel container, so Docker must be running first.

```bash
cd /Users/smededwards/Developer/effemeral/effemeral.fm
docker compose up -d
```

The general command pattern is:

```bash
docker compose exec laravel.test php artisan COMMAND OPTIONS
```

## Check status before doing work

Always check recent runs first:

```bash
docker compose exec laravel.test php artisan imports:status --limit=5
```

This shows Last.fm, Spotify library, Spotify playlist, Spotify album enrichment, and MusicBrainz runs. Enrichment runs show processed, enriched, failed, and cursor values.

If a command appears stuck, inspect stale runs without changing anything:

```bash
docker compose exec laravel.test php artisan imports:clear-stale --dry-run
```

To mark runs older than one hour as failed and make them recoverable:

```bash
docker compose exec laravel.test php artisan imports:clear-stale --hours=1
```

## Spotify album enrichment

This command fills missing Spotify track associations for albums already in Effemeral. It does not import a new Spotify discography.

### Safe first run

List the next candidates without creating a run or contacting Spotify:

```bash
docker compose exec laravel.test php artisan albums:enrich-spotify \
  --scope=priority \
  --limit=10 \
  --dry-run
```

`priority` processes albums that are already in Effemeral playlists or Spotify saved albums first. It then continues with the remaining eligible canonical albums.

The `priority` and `canonical` scopes exclude obvious non-canonical releases, including live recordings, compilations, demos, bootlegs, remixes, soundtracks, samplers, sessions, radio releases, unplugged releases, box sets, and special releases. Use `--scope=all` only when those exclusions are intentional.

### Run one protected batch

The recommended starting command is:

```bash
docker compose exec laravel.test php artisan albums:enrich-spotify \
  --scope=priority \
  --limit=10 \
  --pause-every=3 \
  --pause=10
```

The command:

- stores a persistent run and cursor;
- processes at most 10 albums;
- pauses for 10 seconds after every 3 albums;
- writes progress after each album;
- prints each album, fetched track count, completion, and failure;
- pauses instead of continuing when Spotify reports a rate limit or quota error.

When the batch finishes but more albums remain, the run is marked `paused`. This is expected and allows the next batch to be started deliberately.

### Resume a paused or failed run

```bash
docker compose exec laravel.test php artisan albums:enrich-spotify --resume
```

`--resume` uses the latest paused or failed enrichment run. Failed album IDs are retried first. Successfully retried albums are removed from the failed list. If Spotify is still rate-limited, the run pauses again without advancing past the album that failed.

### Continue automatically

Only use continuous mode when you are happy for the process to keep running:

```bash
docker compose exec laravel.test php artisan albums:enrich-spotify \
  --resume \
  --continuous \
  --pause-every=3 \
  --pause=10
```

Continuous mode still uses the persistent cursor, pauses between batches, and stops on a Spotify rate-limit or quota response. It does not bypass Spotify’s limits.

### Target one artist or resume after an ID

```bash
docker compose exec laravel.test php artisan albums:enrich-spotify \
  --artist-id=123 \
  --limit=10 \
  --pause=10

docker compose exec laravel.test php artisan albums:enrich-spotify \
  --after-id=500 \
  --limit=10 \
  --pause=10
```

Use `--artist-id` for a focused check. Use `--after-id` only when deliberately starting a new run from a known album ID; use `--resume` for normal continuation.

## MusicBrainz artist enrichment

MusicBrainz enriches existing artists with identity, country, location, and related metadata. It does not need to expand the entire MusicBrainz discography for routine enrichment.

Preview candidates:

```bash
docker compose exec laravel.test php artisan artists:enrich-musicbrainz \
  --limit=10 \
  --dry-run
```

Run a small batch:

```bash
docker compose exec laravel.test php artisan artists:enrich-musicbrainz \
  --limit=10
```

Target one artist or include artists with a completed previous run:

```bash
docker compose exec laravel.test php artisan artists:enrich-musicbrainz --artist-id=123
docker compose exec laravel.test php artisan artists:enrich-musicbrainz --limit=10 --refresh
```

## Last.fm import

Last.fm is Effemeral’s authoritative source for listening history and completion evidence.

Import a bounded date range:

```bash
docker compose exec laravel.test php artisan lastfm:import \
  --from=2024-01-01 \
  --to=2024-12-31 \
  --limit=100
```

For the complete known history, use the earliest scrobble date rather than an arbitrary short window:

```bash
docker compose exec laravel.test php artisan lastfm:import \
  --from=2010-11-10 \
  --to=2026-11-25
```

If the import fails after making progress:

```bash
docker compose exec laravel.test php artisan lastfm:import --resume
```

Imports are idempotent, so a retry should update existing records rather than create duplicates.

## Spotify library imports

Import only the Spotify collections currently needed by Effemeral. Avoid the `--enrich` option during large imports; enrichment should be run later through the protected album command so the Spotify quota is controlled centrally.

Saved albums:

```bash
docker compose exec laravel.test php artisan spotify:import-albums \
  --limit=100
```

Resume a failed saved-album import:

```bash
docker compose exec laravel.test php artisan spotify:import-albums --resume
```

Playlists, importing only playlists not already present locally:

```bash
docker compose exec laravel.test php artisan spotify:import-playlists \
  --only-new \
  --limit=10
```

Liked songs are available when needed, but they are supporting catalogue data rather than the primary album-playlist workflow:

```bash
docker compose exec laravel.test php artisan spotify:import-liked-tracks \
  --limit=100
```

## Playlist sync

Playlist sync is preview-first. It does not change Spotify unless `--confirm` is supplied.

Preview an Effemeral playlist by its local ID:

```bash
docker compose exec laravel.test php artisan playlists:sync 12
```

Review the terminal output for albums, track availability, tracks to add, tracks to remove, and incomplete albums. Incomplete albums block sync so that a playlist cannot silently lose part of an album.

Create a new Spotify playlist after reviewing the preview:

```bash
docker compose exec laravel.test php artisan playlists:sync 12 \
  --destination=new \
  --confirm
```

Update an existing imported Spotify playlist:

```bash
docker compose exec laravel.test php artisan playlists:sync 12 \
  --destination=existing \
  --spotify-playlist-id=spotify-playlist-id \
  --confirm
```

Use `--destination=linked` when the Effemeral playlist already has a linked Spotify destination.

## Recommended daily workflow

Use this order when Spotify quota is available:

1. Check status.
2. Run a dry-run for the next Spotify enrichment candidates.
3. Run one small protected enrichment batch.
4. Check status and note the run ID, cursor, and any failures.
5. Resume later rather than starting a new enrichment run.
6. Preview playlist sync and confirm it only after every album is complete.

If Spotify reports a quota or rate-limit error, stop launching new Spotify commands. Check `imports:status`, wait for the provider’s reset window, and use `--resume` on the paused run.

## Verification after code changes

Run the project checks from the application container:

```bash
docker compose exec laravel.test composer test
docker compose exec laravel.test composer lint
docker compose exec laravel.test composer analyse
```
