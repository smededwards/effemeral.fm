# Development guide

## Local environment

Effemeral uses Docker Compose with a Laravel application container, MySQL 8.4, Redis, a queue worker, and the shared Traefik proxy for local HTTPS.

Start the shared proxy once if it is not already running:

```bash
docker compose -f ../.docker/traefik/compose.yaml up -d
```

Start Effemeral from the project directory:

```bash
docker compose up -d
```

The local application URL is `https://app.effemeral.local`. The host file must point that hostname to `127.0.0.1`, and the local certificate must be trusted by the browser.

Useful commands:

```bash
docker compose ps
docker compose logs -f laravel.test
docker compose logs -f queue
docker compose exec laravel.test php artisan route:list --except-vendor
docker compose exec laravel.test php artisan migrate:status
```

Run Artisan commands inside `laravel.test`, not against the host PHP installation. This keeps PHP, Composer, MySQL, Redis, and application configuration aligned.

## Configuration

Copy `.env.example` to `.env`, generate an application key, and run migrations:

```bash
cp .env.example .env
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --force
```

After changing environment values, clear the configuration cache:

```bash
docker compose exec laravel.test php artisan config:clear
```

Do not commit `.env`, provider credentials, tokens, or local certificates.

## Application structure

- `app/Http/Controllers` handles web requests and redirects or views.
- `app/Http/Requests` contains reusable request validation.
- `app/Jobs` contains queueable provider imports and long-running work.
- `app/Models` contains the canonical catalogue and import-run records.
- `app/Services/Catalog` writes and normalizes canonical catalogue data.
- `app/Services/Spotify`, `app/Services/LastFm`, and `app/Services/MusicBrainz` isolate provider access.
- `app/Services/Recommendations` and `app/Services/Playlists` contain discovery and playlist behavior.
- `resources/views` contains Blade templates.
- `resources/js` and `resources/css` contain custom front-end code loaded through Vite.
- `database/migrations` records additive schema changes.
- `tests/Feature` covers page behavior, imports, enrichment, and playlist workflows.

Keep controllers thin. Put provider calls in clients, catalogue writes in catalog services, reusable validation in Form Requests, and business decisions in focused services or jobs.

## Schema and data changes

Migrations are append-only history. Do not edit an applied migration to change existing data. Create a new migration, test it against the current database, and document any manual cleanup or backfill it requires.

Before adding a table or field:

1. Check whether the canonical model already stores the concept.
2. Check existing migrations and relationships for a compatible structure.
3. Decide whether the value is provider data, canonical data, or a manual override.
4. Add indexes and uniqueness rules where imports may retry the same record.
5. Add feature tests for the normal path and a retry or missing-data path.

## Front-end rules

Blade templates should use semantic HTML and remain readable when nested. Use named routes for links. Keep custom JavaScript in `resources/js` and custom CSS in `resources/css`; do not add inline script or style blocks to Blade templates.

After Blade changes, format and verify the templates:

```bash
npx --yes blade-formatter@1.44.6 --write --indent-inner-html 'resources/views/**/*.blade.php'
npx --yes blade-formatter@1.44.6 --check-formatted --indent-inner-html 'resources/views/**/*.blade.php'
```

## Verification

Run the narrowest relevant test first, then the project checks:

```bash
docker compose exec laravel.test php artisan test tests/Feature/RelevantTest.php --compact
docker compose exec laravel.test composer test
docker compose exec laravel.test composer lint
docker compose exec laravel.test composer analyse
```

Pint enforces PSR-12 formatting. PHPStan checks static types. PHPUnit verifies behavior. A PR should explain any skipped check and any remaining provider or migration risk.

## Git and pull requests

Routine work happens in the primary checkout so it can be tested locally. Use a worktree only when developing separate changes for separate pull requests, and remove it when the PR is merged or closed.

Use a feature branch from the latest `dev` branch. Do not include `codex` in the branch name.

Use Conventional Commits, for example:

```text
feat(spotify): add resumable album enrichment
fix(catalog): preserve existing track associations
docs(cli): document safe import workflows
```

Before opening a PR:

1. Check the working tree and review the complete diff.
2. Run the relevant tests, Pint, and PHPStan.
3. Run Ponytail against the complete proposed change.
4. Add a PR description covering purpose, user-facing changes, validation, migration/setup notes, and follow-up work.
5. Open the PR into `dev` and wait for review.

The user reviews and merges PRs. Do not merge on their behalf.
