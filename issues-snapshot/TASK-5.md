# TASK-5: Scheduled publishing

**Status:** Done

## Description

## Problem

Staff need to prepare documents in advance and have them become visible to recipients only at a specific date and time. Today there is no way to embargo content — any share link immediately exposes the document body.

## Acceptance criteria

* Staff can set a `publish_at` datetime on a document (when they share a link, it will not be editable in the admin UI).
* `view.php` checks `publish_at` against the current time (America/Chicago). If the current time is before `publish_at`, the recipient sees a clear "not yet available" message instead of the document body.
* Scheduling a change is written to `audit_log`.
* A migration file under `migrations/` adds the `publish_at` column (schema.sql is not edited directly).
* At least one test in `tests/test.php` covers the before/after behaviour.

## Comments

**Stephen** — 2026-05-11T17:38:35Z
Planning PR opened: https://github.com/rguerra712/pinnacle-whimsical-echo/pull/1. Review the approach and open questions before implementation starts.

**Stephen** — 2026-05-11T21:56:22Z
Implementation complete: https://github.com/rguerra712/pinnacle-whimsical-echo/pull/1. PLAN.md deleted. Ready for code review.

**Stephen** — 2026-05-12T17:02:08Z
Planning PR opened: https://github.com/rguerra712/humbling-barbequed-originations/pull/2. Review the approach and open questions before implementation starts.

**Stephen** — 2026-05-12T17:20:43Z
Plan updated based on clarifications: `publish_at` now lives on the `shares` table (not `documents`) — set from `share.php` at link-creation time. "Not yet available" page shows the document title but withholds the body. No open questions remain. PR: https://github.com/rguerra712/humbling-barbequed-originations/pull/2

**Stephen** — 2026-05-12T17:27:39Z
Implementation complete: https://github.com/rguerra712/humbling-barbequed-originations/pull/2. PLAN.md deleted. Ready for code review.
