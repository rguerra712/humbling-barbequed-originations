# TASK-8: Add incremental DB migration system (migrate.php + Docker auto-run)

**Status:** Done

## Description

## Problem

`seed.php` is destructive — it drops and recreates the database on every run. There is no way to apply incremental schema changes to an existing database without losing data.

## Solution

Implement a Flyway-style migration system in plain PHP:

### Option 1 — `migrate.php` with a `schema_migrations` tracking table

* Creates `schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT)` if it doesn't exist
* Reads all `migrations/*.sql` files in lexicographic order
* Skips any filename already recorded in `schema_migrations`
* Applies new files and records them — idempotent, safe to run repeatedly

### Option 2 — Auto-run on `docker compose up`

* Call `php migrate.php` in the Docker startup command before the PHP built-in web server starts
* Since SQLite is file-backed and the DB persists across container restarts, migrations only run once per file

## Acceptance Criteria

- [X] `migrate.php` exists and applies `migrations/*.sql` files in order, skipping already-applied ones
- [X] A `schema_migrations` table tracks applied filenames + timestamps
- [X] `docker-compose.yml` runs `php migrate.php` before starting the web server
- [X] `seed.php` still works as-is for tests (destructive reset)
- [X] At least one PHP test covers the migration runner behaviour

## Comments

**Stephen** — 2026-05-11T21:34:28Z
Another option mentioned by Claude, but I chose against it due to complexity:

Phinx is the PHP equivalent of Flyway — versioned migration files, migrate/rollback

commands, a phinxlog table. Overkill for SQLite/no-framework here, and it requires

Composer. Not worth it unless you're already using Composer.
