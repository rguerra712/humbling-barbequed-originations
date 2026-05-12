# Plan: Share by name (document title search) (TASK-7)

## Linear issue

TASK-7 — Staff must currently scroll the full document list on `admin.php` and click through to `share.php?doc=ID`. As the document count grows, finding the right document becomes impractical. This feature adds a title search directly on `share.php` so staff can jump straight to sharing without navigating from the list.

## Approach

`share.php` is currently a two-state page: it either shows a 404 (no `?doc` param or invalid ID) or the share-creation form. The plan extends it to a **three-state page**:

1. **No `?doc` param** → show a search form. If a `?q` query string is also present, run the search and display matching documents below the form.
2. **`?doc` param present and valid** → show the existing share-creation form (no change to this flow).
3. **`?doc` param present but invalid** → existing 404 behaviour (no change).

The search query runs a case-insensitive **substring match** against `documents.title` using SQLite's `LIKE '%query%'`. Each result row links to `share.php?doc=ID` so the staff member lands directly on the share form.

**Match strategy — why substring LIKE:**
Prefix search (`LIKE 'q%'`) would feel surprising when staff type a word from the middle of a long title. Full fuzzy matching (Levenshtein, soundex) requires PHP-side scoring loops that add complexity for no real gain at this app's document scale. Substring LIKE is the sweet spot: it's forgiving, it's SQL-native, SQLite's LIKE is case-insensitive for ASCII by default, and it needs no extra code or tables.

No schema changes are needed — this is a pure query against the existing `documents` table.

## Files to change

| File | Change |
|------|--------|
| `public/share.php` | Add search-phase at top: when no `?doc` param, render a title search form; when `?q` param present, query `documents.title LIKE '%q%'` and render results. Existing `?doc` logic is unchanged. |
| `tests/test.php` | Add three test blocks: full-title match, partial/substring match, no-match (empty results). Tests query the DB directly using the same LIKE pattern — no HTTP. |

## Schema changes

None.

## Tradeoffs considered

- **Substring LIKE vs prefix LIKE**: Chose substring (`LIKE '%q%'`) because staff often know a word from the middle of a title, not necessarily the start. Downside: can't use a B-tree index on `title` for the leading wildcard, but at this document volume SQLite full-table scans are instant.
- **Server-side search vs JavaScript live filter**: The rest of the app has zero client-side JS for data operations. A JS filter would be faster UX but inconsistent with the stack and harder to test. Chose server-side form submission to stay consistent; a JS enhancement can be layered on later without touching the server logic.
- **Modifying `share.php` vs adding a new `search.php`**: A separate page would require duplicating the layout boilerplate and adding a new navigation entry point. Extending `share.php` keeps the code collocated with its purpose and requires no changes to `admin.php` or any links.
- **Showing all documents when `?q` is absent vs requiring a search term**: Requiring a search term (showing blank results until the user types) avoids accidentally dumping a large document list. Staff who want to browse can still use `admin.php`. This keeps `share.php` search-intent-first.

## Open questions

- Should search results be paginated or capped? The plan caps at 50 rows (via `LIMIT 50`) — if document counts can grow much larger, a stricter limit or pagination may be needed.
- Should the search box on `share.php` also be surfaced from `admin.php` as an alternative entry point, or is the existing "Create share →" row link sufficient? No change to `admin.php` is planned here.

## Out of scope

- Fuzzy/phonetic matching — not worth the complexity at this stage; substring LIKE covers the stated need.
- Real-time/live search (JS autocomplete) — consistent with the no-framework stack choice; can be added later.
- Searching by document body, creator, or date — the issue specifies title search only.
- Any changes to `view.php`, `admin.php`, or the share-creation POST logic.
