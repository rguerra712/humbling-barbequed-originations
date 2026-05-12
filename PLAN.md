# Plan: Scheduled publishing (TASK-5)

## Linear issue

TASK-5 — Staff need to embargo documents so recipients only see content at or after a specific date/time. The embargo is set per share link at the moment the link is created; `view.php` shows the document title plus a "not yet available" message before the scheduled time instead of the document body.

## Approach

`publish_at` is a nullable `TEXT` column added to the `shares` table via `migrations/001_add_publish_at.sql`. Putting it on `shares` (rather than `documents`) is the correct fit because the UI lives in `share.php` — the embargo is a property of a specific share link, set at creation time. This also naturally allows different share links for the same document to have different embargo times. NULL means "immediately available", so all existing shares remain unaffected.

In `share.php`, the form gains an optional `datetime-local` input labeled "Available from (optional)". On POST, the handler reads the value, stores it in the `shares` INSERT (NULL if blank), and calls `audit_log('schedule', 'share', $shareId, ['publish_at' => $publishAt])` when a value is provided. The existing `audit_log('create', 'share', ...)` call is kept for all share creations regardless.

In `view.php`, the existing SELECT already joins `shares` and `documents`. After the row is fetched, a guard checks whether `$doc['publish_at']` is set and `time() < strtotime($doc['publish_at'])`. If so, the page renders the document **title** (confirming the link is valid) plus a "not yet available" message showing the scheduled datetime — but exits before rendering the body. HTTP response stays 200.

`seed.php` applies the migration after the base schema load, keeping `docker compose up` working from a fresh clone.

## Files to change

| File | Change |
|------|--------|
| `migrations/001_add_publish_at.sql` | New file — `ALTER TABLE shares ADD COLUMN publish_at TEXT;` |
| `seed.php` | After `$pdo->exec(file_get_contents(...schema.sql...))`, execute the migration SQL |
| `public/share.php` | Add optional `publish_at` datetime-local input; include column in INSERT; call `audit_log('schedule', 'share', $shareId, ...)` when non-empty |
| `public/view.php` | After fetching the row, check `s.publish_at` vs `time()`; if embargoed render title + "not yet available" message and exit before the body |
| `tests/test.php` | Two new test blocks: (1) share with past `publish_at` — body visible; (2) share with future `publish_at` — body withheld, title still present |

## Schema changes

```sql
-- migrations/001_add_publish_at.sql
ALTER TABLE shares ADD COLUMN publish_at TEXT;
```

Nullable, no default — all existing shares remain immediately visible.

## Tradeoffs considered

- **Column on `shares` vs. `documents`**: `shares` is correct because the UI is on `share.php` and the embargo is a property of a specific link. A column on `documents` would force all share links for a document to share one embargo time and would require the UI on `admin.php`, neither of which matches the requirements.
- **Store as TEXT vs. INTEGER (Unix timestamp)**: TEXT is consistent with existing `created_at` columns across the schema. PHP's `strtotime()` parses ISO-8601 correctly under the globally-set America/Chicago timezone with no manual offset math.
- **Enforce embargo in PHP vs. SQL**: PHP guard (after fetch) lets `view.php` show the title and a helpful message. Filtering in SQL would make embargoed links indistinguishable from invalid tokens — worse UX and harder to test.
- **Show title on the "not yet available" page**: Confirms to the recipient that the link is valid and they have the right document. The body remains withheld until the embargo lifts.
- **HTTP 200 for embargoed content**: Consistent with the rest of the app. 425 ("Too Early") is semantically apt but obscure; 200 is fine here.
- **No edit UI for `publish_at`**: Set once at share-creation time per the acceptance criteria.

## Open questions

None — all ambiguities resolved by updated criteria and product clarifications.

## Out of scope

- Editing `publish_at` after a share is created.
- Setting a `publish_at` from the admin document-creation form.
- Push notifications when the embargo lifts — `view.php` is a pull model.
- Timezone selection by staff — America/Chicago is used system-wide.
