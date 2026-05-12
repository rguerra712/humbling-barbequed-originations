# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start the app (re-seeds db.sqlite from scratch each run)
docker compose up

# Run PHP unit tests (inside the container)
docker compose exec app php tests/test.php

# Seed the DB manually (destructive — drops and recreates db.sqlite)
docker compose exec app php seed.php
```

## Architecture

Single-process PHP 8.3 app using PHP's built-in web server, SQLite via PDO, no framework. `public/index.php` just redirects to `admin.php`.

- `lib/bootstrap.php` — loaded by every page. Provides `db()` (PDO singleton), `current_staff()` (always returns staff id=1 — no real auth), `audit_log()`, `random_token()`, and `h()` (XSS-safe output).
- `lib/layout.php` — `render_header(string $title, ?array $staff)` and `render_footer()`. All pages call these to wrap HTML.
- `public/*.php` — one file per page, no router. Each file requires bootstrap + layout, handles its own POST, then renders.
- `schema.sql` — source of truth for the initial schema, applied by `seed.php` on startup.
- `tests/test.php` — re-seeds then runs assertions using `test(name, fn)` + `assert_true(cond, msg)`.

### Data flow

`admin.php` creates documents and links to `share.php` → `share.php` generates a `shares` row with a random hex token → `view.php?token=X` looks up the share and renders the document to the recipient.

### Key conventions

- **All HTML output** must go through `h()`. EJS-style auto-escape does not exist here.
- **Every write** (create document, create share, schedule change) gets an `audit_log()` call. Pattern: `audit_log('create', 'document', $docId, ['title' => $title])`.
- **Timezone** is `America/Chicago` (set globally in `bootstrap.php`). Any `publish_at` or scheduled time logic must account for this.
- **Tokens** are `random_token()` = `bin2hex(random_bytes(16))` = 32-char lowercase hex. Tokens live in query strings (they appear in server logs and referrer headers — a known tradeoff).

## Schema migrations

`schema.sql` is **not edited directly** after initial setup. Add new `.sql` migration files (e.g. `migrations/001_add_publish_at.sql`) and apply them in `seed.php` after the initial schema load. The `docker compose up` flow must still work from a fresh clone.

## Tests

Add each new PHP test as a `test('description', function() { ... })` block in `tests/test.php`. The file re-seeds the DB at the top, so tests always run against a known state. Use `assert_true($cond, $message)` for assertions. At least one test per feature built.

## Agent Workflow

When working on any code task, agents must operate in an isolated git worktree so that multiple tasks can run in parallel without conflicts. Do so in the .worktrees folder found in this project.

### For the orchestrating agent (spawning subagents)

Use `isolation: "worktree"` on every Agent tool call that involves writing code:

```
Agent({
  subagent_type: "...",
  isolation: "worktree",
  prompt: "..."
})
```

The worktree is created automatically on a fresh branch. Tell the subagent the branch name it should use (see naming below).

### For subagents doing the work

1. **Branch name** — use the format `TASK-#/description_with_underscores`, e.g. `TASK-1/scheduled_publishing` or `TASK-3/share_by_name`. The task number maps to the feature list below.
2. **Stay in the worktree** — all edits, tests, and shell commands run inside the worktree directory provided at start.
3. **On completion** — stage relevant files, commit with a short imperative message (≤72 chars), then push:
   ```bash
   git add <files>
   git commit -m "short description of what was done"
   git push -u origin TASK-#/description_with_underscores
   ```
4. **Do not merge** — leave merging to the orchestrator or the human reviewer.

## Features to build

Three customer-requested features (implement in any order; scope as fits the time budget):

1. **Scheduled publishing** — staff set a `publish_at` datetime on a document. Before that time, `view.php` shows "not yet available" instead of the document body.
2. **Human-readable document IDs** — each document gets a short, speakable slug or code (e.g. `welcome-2026`, `FOLIO-7QX4`). Consider collisions, guessability, and interaction with the existing token mechanism.
3. **Share by name** — staff can search for a document by title on `share.php` rather than navigating from the full list. Choose and justify the match strategy (exact, prefix, fuzzy, etc.).
