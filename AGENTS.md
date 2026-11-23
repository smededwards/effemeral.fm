<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

## Effemeral product focus

- Effemeral is a private, single-user album-first playlist curator. The main user journey is discover albums, build and manage named Effemeral playlists, review them, then sync them to Spotify.
- Treat Last.fm and Spotify imports as supporting data maintenance. Keep import controls available, but do not make import volume or import status the primary product experience.
- Last.fm is the canonical source for listening history and completion evidence.
- Spotify is a supporting catalogue and playlist destination: saved albums, liked songs, and imported playlists enrich the catalogue but do not prove that an album was heard.
- A usable playlist workflow needs multiple persistent playlists, album-level add/remove/include/reorder controls, and clear draft and Spotify sync states.
- Prefer catalogue filters for genre, artist, country or region, release year, and listening completion. Completed albums are excluded from discovery by default, with manual inclusion available.
- Keep Spotify sync explicit and review-first. Bidirectional Spotify-to-Effemeral playlist editing is deferred until the one-way create/update workflow is useful and validated.
- The canonical catalogue is the artists, albums, and tracks tables, with source identifiers and collection membership. Do not add new features that depend only on embedded provider JSON when the canonical model can be used.
- Importers must be idempotent, preserve source provenance, tolerate retries, and avoid creating cross-source duplicates. Existing compatibility tables should not be removed without a migration plan.
- Discogs metadata, full discography expansion, deeper playlist-content analysis, deployment hardening, and multi-user support remain later slices. Do not imply they are implemented when they are only represented in the model or roadmap.
- Album-level workflows come before individual-track playlist workflows. Keep manual review, ordering, inclusion, and completion overrides available for consequential automation.
- The selected-album discovery target is implemented on `dev`: choose an album or artist, find similar incomplete albums from a requested genre and year, or generate an album-level artist discography draft.
- Similarity recommendations require normalized genre data, release-year filtering, canonical catalogue records, and Last.fm completion evidence; do not make Spotify playlist membership the recommendation authority.
- Artist discography drafts should reuse the existing recommendation review, ordering, exclusion, audit, and Spotify sync flow rather than creating a second playlist workflow.
- The agreed milestone sequence is `Agalloch`, `Baroness`, `Candlemass`, `Darkthrone`, `Earth`, `Funeral`, `Gallows`, `Hawkwind`, `Interpol`, `Jinjer`, `Kyuss`, `Lucifer`, `Mastodon`, `Neurosis`, `Opeth`, `Pallbearer`, `Quicksand`, `Rammstein`, `Slayer`, `Tombs`, `Ulver`, `Venom`, `Windhand`, `Xasthur`, `Yob`, and `Zao`; then cycle back to `A`.
- The sequence starts with `Agalloch` as the closed `v0.1.0` prototype baseline and `Baroness` as the next `v0.2.0` milestone. Do not pre-create future milestones; create each one as its release work begins.
- A milestone is complete only after its issues are merged into `dev`, CI/Pint/PHPStan/PHPUnit pass, the user reviews the working prototype, and `dev` is merged into `main`.
- Milestone completion creates the matching semantic-version tag, GitHub release, and Conventional Commit changelog summary. Patch releases are reserved for fixes; new feature milestones increment the minor version while the project remains below `v1.0.0`.
- Candlemass is the active `v0.3.0` milestone for the first usable playlist-first workflow. Keep imported Spotify playlist browsing as supporting context; prioritize persistent Effemeral playlists, catalogue discovery, reviewed sync, and a playlist-focused overview.
- After a milestone release, create the next issue and feature branch from the updated `dev`, open a PR into `dev`, and close the linked issue only after the PR is merged.

## Effemeral delivery workflow

- Make routine changes in the primary repository checkout so the user can run and review them locally before merging. Use Git worktrees only to develop separate changes for separate pull requests; when each pull request is merged or closed, remove its clean worktree and return to the primary checkout.
- Work from the latest dev branch using a feature branch without codex in its name.
- Open pull requests into dev; do not merge them without the user's review.
- Every pull request must include a non-empty description covering purpose, user-facing changes, validation, migration or setup notes, and follow-up work.
- Use Conventional Commits and labels limited to Conventional Commit types.
- Enforce PSR-12 across every tracked PHP file: use Laravel Pint in CI with `composer run lint:check`, and treat formatting failures as blocking.
- Before handoff, run the narrowest relevant tests plus Pint and PHPStan. Report any skipped verification and remaining risks.
- Before opening a pull request, run the Ponytail review against the complete proposed change, fix its actionable findings, and include the review outcome in the handoff. The user reviews and merges the PR.
- Add PHPDoc to public classes and methods, and to non-obvious private methods or complex array shapes. PHPDoc must use a multiline block with meaningful `@param`, `@return`, `@throws`, or shape annotations where applicable. Single-line comments must start with `//` and explain why a decision is necessary, not narrate obvious code.
- Before handoff, format Blade templates with `npx --yes blade-formatter@1.44.6 --write --indent-inner-html 'resources/views/**/*.blade.php'` and verify with the same command using `--check-formatted` instead of `--write`. Keep nested HTML and Blade directives indented to show their hierarchy, put nested elements and control directives on separate lines, and avoid compressed multi-element lines. Preserve generated or whitespace-sensitive content when reformatting would alter its output.
- Use semantic HTML in Blade views: choose landmarks for their document role, give each `<section>` an accessible name, use lists for repeated collections, and use headings, labels, descriptions, and status roles according to their meaning. Keep decorative icons hidden from assistive technology; do not add semantic elements solely for styling.

## Development principles

- Apply DRY to meaningful duplication, KISS to keep each solution easy to understand, and YAGNI to avoid speculative features, abstractions, and configuration.
- Use SOLID as design guidance: give classes focused responsibilities, depend on useful boundaries, and keep interfaces small. Do not introduce interfaces or layers without a real second use or a testability need.
- Keep separate concerns in the layer responsible for them; compose small collaborators when behavior needs combining. Prefer composition over inheritance unless an existing framework or domain hierarchy makes inheritance the clearest fit.
- Fail fast at trust boundaries and use guard clauses for invalid, unavailable, or already-completed states. Preserve user-facing error handling where recovery is useful, and never hide failures that could cause data loss.
- Treat these principles as practical design checks, not a requirement to restructure working code without a concrete benefit. Prefer incremental refactors with narrow scope and clear behavior.
- Apply these control-flow rules repo-wide: never use Yoda conditions; put the variable or expression on the left and the literal on the right.
- Use guard clauses and early returns for invalid, unavailable, or already-completed states so the main path stays flat.
- Use ternary operators only for simple, single-line value selection. Use `??` for null defaults, and use `if` or `match` for multi-step or multi-branch logic. Never nest ternaries.
