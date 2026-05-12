# Plan: Share by name (document title search) (TASK-7)

## Linear issue

TASK-7 — Staff must currently scroll the full document list on `admin.php` to find the document they want to share. As document count grows this becomes impractical. The fix is a title filter directly on the document list in `admin.php`.

## Approach

Add a search text input above the documents table in `admin.php`. Submitting the form reloads the page with `?q=<term>` in the query string. When `?q` is present, the document query adds a `WHERE lower(title) LIKE lower(?)` clause with the term wrapped in `%` wildcards. The "Create share →" link per row remains unchanged — the filter just narrows which rows are visible, so the existing share flow is entirely unaffected.

**Case insensitivity:** SQLite's `LIKE` is case-insensitive for ASCII letters by default, but we normalise both sides explicitly — `WHERE lower(title) LIKE lower(?)` — to make the intent clear and avoid any surprise with future SQLite builds or collation changes.

**Index:** A migration adds an expression index `CREATE INDEX IF NOT EXISTS idx_documents_title_lower ON documents (lower(title))`. For the substring pattern `lower(title) LIKE lower('%q%')`, the leading wildcard means SQLite cannot use the B-tree index. However the index pays off immediately for any prefix-anchored search and leaves the door open for switching to prefix-only matching later. At this app's document volumes even an unindexed scan is instant, but the index shows intent and is essentially free.

No table structure changes — only a new index via a migration file.

## Files to change

| File | Change |
|------|--------|
| `public/admin.php` | Add a GET search form (input `name="q"`) above the documents table; when `?q` is set, apply `WHERE lower(title) LIKE lower(?)` with `%`-wrapped term and `LIMIT 50`; re-populate the input with the current `?q` value so staff see their active filter. |
| `migrations/002_add_title_index.sql` | Expression index on `lower(title)` for fast prefix searches. |
| `tests/test.php` | Add four test blocks: exact/full-title match, partial/substring match, no-match (empty result set), case-insensitive match (search lowercase against mixed-case title). |

## Schema changes

```sql
-- migrations/002_add_title_index.sql
CREATE INDEX IF NOT EXISTS idx_documents_title_lower ON documents (lower(title));
```

No table structure changes.

## Tradeoffs considered

- **Filter on `admin.php` vs search phase on `share.php`**: Filtering the existing document list in `admin.php` is the right UX — staff already use `admin.php` as their home base; a search box there requires no new mental model. `share.php` stays single-purpose (generating a link for a known document).
- **Substring (`LIKE '%q%'`) vs prefix (`LIKE 'q%'`)**: Substring is more forgiving — staff can type any word from the title. The expression index only activates for prefix patterns, but at this scale the full scan is negligible. Tradeoff documented so a future maintainer can switch to prefix if scale demands it.
- **`lower()` normalisation vs `COLLATE NOCASE`**: Both achieve ASCII case-insensitivity. `lower()` is explicit, pairs naturally with the expression index, and avoids changing column-level collation for all comparisons on the column.
- **Server-side form submit vs JS live filter**: The app has no client-side JS for data operations. Server-side GET form is consistent with the stack, produces a bookmarkable/shareable URL, and is straightforward to test.
- **Result cap at 50**: Agreed in review — no pagination needed at this scale.

## Open questions

None — all questions from the first draft are resolved.

## Out of scope

- Changes to `share.php` — the share flow is unchanged.
- Fuzzy/phonetic matching — substring LIKE covers the stated need.
- Real-time/live search (JS autocomplete) — consistent with no-framework stack choice.
- Searching by document body, creator, or date — title only per the issue.
- Pagination of search results beyond the 50-row cap.
