# TASK-11: E2E Test Suite — Core Document & Share Flows

**Status:** Backlog

## Description

## Goal

Create an end-to-end test suite that exercises the app through real HTTP requests against the live Docker container, verifying the core document-and-share flows work together end to end. This is separate from the existing `tests/test.php` unit tests, which call PHP functions directly.

## Acceptance criteria

* A new `tests/e2e.php` file exists and is runnable with:

  ```
  docker compose exec app php tests/e2e.php
  ```
* The suite re-seeds the DB at the start of each run (or documents clearly that a fresh seed is assumed) so results are deterministic.
* All five flows below have at least one assertion each:
  1. **Seed document in admin list** — a GET to `http://localhost/admin.php` returns a 200 response whose body includes the title of the seed document (e.g. "hello" or whatever `seed.php` inserts).
  2. **Create share link** — a POST to `http://localhost/share.php` with the seed document's ID returns a response that contains a URL with a 32-character lowercase hex token (matching `/[0-9a-f]{32}/`).
  3. **View shared document** — a GET to `http://localhost/view.php?token=<token-from-step-2>` returns a 200 response whose body includes the document body content (not "not yet available", not a PHP error, not a blank page).
  4. **Invalid token rejected** — a GET to `http://localhost/view.php?token=000000000000000000000000deadbeef` returns a response whose body contains a human-readable error message (e.g. "not found", "invalid", or similar) and does not contain a PHP fatal error or an empty body.
  5. **Admin redirect** — a GET to `http://localhost/index.php` (without following redirects) returns a 3xx status code with a `Location` header pointing to `admin.php`.
* The suite exits with a non-zero status code if any assertion fails, and prints a clear failure message identifying which assertion failed and what was received.
* No new Composer packages or external dependencies are introduced. Use only PHP built-ins (`file_get_contents`, stream contexts, `curl_*`, etc.) or tools already present in the container.
* At least one `test(...)` block per flow (matching the style in `tests/test.php`) if the helper is reused, or an equivalent self-contained test runner.

## Out of scope

* Testing the scheduled-publishing embargo logic (`publish_at`) — that is covered by the unit tests for TASK-5.
* Testing human-readable IDs (TASK-6) or share-by-name search (TASK-7).
* Browser-level JS or CSS rendering checks.
* Load or performance testing.

## Dependencies

None. The suite should work against the baseline app (no feature branches required), though it must not break when TASK-5, TASK-6, or TASK-7 are merged.

## Comments

_No comments._
