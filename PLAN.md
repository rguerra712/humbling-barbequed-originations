# Plan: Scheduled publishing (TASK-5)

## Linear issue

TASK-5 — Staff need to embargo documents so recipients only see content at or after a specific date/time. Any document can have an optional `publish_at` timestamp; before that moment `view.php` shows a "not yet available" message instead of the document body.

## Approach

The `publish_at` column is added to the `documents` table via a migration file (`migrations/001_add_publish_at.sql`). The column is nullable — NULL means "always published", matching all existing documents with no behaviour change. The value is stored as an ISO-8601 string in SQLite (consistent with `created_at` in the same table), and all comparisons happen in PHP after converting to Unix timestamps so the America/Chicago timezone set in `bootstrap.php` is honoured automatically.

The admin creation form (`admin.php`) gets an optional `publish_at` datetime-local `<input>`. The value is an empty string when the staff member leaves it blank (no embargo), or a local datetime string (`YYYY-MM-DDTHH:MM`) when set. On POST, the value is stored as-is (SQLite TEXT) after basic validation; because PHP's timezone is already set to America/Chicago, `strtotime()` and `date()` will interpret the value correctly without any manual offset arithmetic.

In `view.php`, after the document row is fetched, a single guard checks whether `publish_at` is set and whether `time() < strtotime($doc['publish_at'])`. If so, the page renders a "not yet available" message with the scheduled time — and critically the document `title` and `body` are **not** sent to the browser. The HTTP response code stays 200 (the link is valid), but the content is withheld.

The `audit_log` call for the `schedule` action is added in `admin.php` whenever `publish_at` is non-empty at document creation time. No separate edit UI is required by the acceptance criteria; scheduling is set once at creation. If a future edit UI is added, a new audit entry should be written then too, but that is out of scope here.

`seed.php` is updated to apply the migration after the base schema, keeping `docker compose up` working from a fresh clone.

## Files to change

| File | Change |
|------|--------|
| `migrations/001_add_publish_at.sql` | New file — `ALTER TABLE documents ADD COLUMN publish_at TEXT;` |
| `seed.php` | Apply the migration file after loading `schema.sql` |
| `public/admin.php` | Add optional `publish_at` datetime-local input to the form; include the column in the INSERT; call `audit_log('schedule', 'document', $docId, ['publish_at' => $publishAt])` when a value is provided |
| `public/view.php` | After fetching the document row, check `publish_at` against `time()`; if before `publish_at`, render "not yet available" message and exit before rendering body |
| `tests/test.php` | Add two test blocks: one for a document whose `publish_at` is in the past (should be visible), one for a future `publish_at` (should return the "not yet available" flag) |

## Schema changes

```sql
-- migrations/001_add_publish_at.sql
ALTER TABLE documents ADD COLUMN publish_at TEXT;
```

The column is nullable with no default, so all existing rows remain unaffected (NULL = always published).

## Tradeoffs considered

- **Nullable column vs. separate `embargoes` table**: A nullable column is simpler and sufficient for a one-time schedule per document. A join table would be needed if multiple embargo windows or per-share scheduling were ever required, but that is not in scope. Downside: removing or editing a scheduled time would require an UPDATE rather than a DELETE, which is a trivial difference.
- **Store datetime as TEXT vs. INTEGER (Unix timestamp)**: The existing `created_at` column uses SQLite's `datetime('now')` TEXT format, so TEXT is consistent with the rest of the schema. PHP's `strtotime()` parses ISO-8601 reliably, and the timezone is already set globally. Downside: string comparisons in SQL would require `datetime()` wrappers if we ever wanted to query by publish date in SQLite directly.
- **Enforce embargo in PHP vs. in the SQL query**: Enforcing in PHP (after fetch) is clearer and lets us return a helpful message. Enforcing in SQL (adding `AND (publish_at IS NULL OR publish_at <= datetime('now'))` to the query) would return no row before the embargo lifts, which would be indistinguishable from an invalid token — a worse UX and harder to test. PHP check is the right call.
- **HTTP 200 vs. 403/425 for embargoed content**: Returning 200 with a "not yet available" message is consistent with the existing 404 pattern (which uses `http_response_code(404)` explicitly) and avoids leaking whether a token is valid. A 425 ("Too Early") would be more semantically correct but is obscure and not widely supported. 200 is fine.
- **No edit UI for `publish_at`**: The acceptance criteria only require setting the time on creation. Adding an edit path would mean a second form, another audit log entry, and more test coverage. Deferred to keep scope tight.

## Open questions

- Should the "not yet available" page reveal the document title? Showing the title confirms the share link is valid; hiding it is more conservative. The current plan shows **neither** title nor body before the embargo lifts — staff should confirm which behaviour is preferred.
- Should `publish_at` be editable after creation (e.g. to push the embargo date)? Not in scope per the acceptance criteria, but worth a quick product confirmation so the data model is not later changed in a breaking way.

## Out of scope

- Editing `publish_at` after a document is created — no edit UI is planned; scheduling is set once at creation time.
- Per-share scheduling — all share links for a document share the same `publish_at`. A per-share embargo would require a column on the `shares` table instead.
- Automatic "publish" notifications or webhooks when the embargo lifts — `view.php` is a pull model; there is no push mechanism.
- Timezone selection by the staff member — the system timezone (America/Chicago) is used for all scheduling as specified in `bootstrap.php`.
