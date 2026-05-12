# Plan: Share by name (document title search) (TASK-7)

## Linear issue

TASK-7 — Staff need to find a document to share by searching for it by title, rather than scrolling a growing list. The requirement is about discovery-to-share, so the right surface is a purpose-built search results page, not a filter on the admin list.

## Approach

`admin.php` gets a compact search box in the top-right corner of the page header area. Submitting it (GET) navigates to a new `public/results.php?q=<term>`. `results.php` runs a single ranked query and displays matches in three tiers — exact match first, then titles that start with the term, then titles that merely contain it — each with a "Create share →" link identical to the one on `admin.php`. `results.php` also has its own search box at the top so staff can refine without going back.

**Match strategy — three-tier ranking via a single SQL query:**

```sql
SELECT *,
  CASE
    WHEN lower(title) = lower(:q)              THEN 0   -- exact
    WHEN lower(title) LIKE lower(:q) || '%'    THEN 1   -- prefix
    ELSE                                             2   -- contains
  END AS match_rank
FROM documents
WHERE lower(title) LIKE '%' || lower(:q) || '%'
ORDER BY match_rank ASC, title ASC
LIMIT 50
```

All three tiers come back in one round-trip. Exact matches surface first (staff typed the whole title), prefix matches next (staff started typing), contains matches last (staff know a word in the middle). This mirrors familiar search-engine ranking without any fuzzy library or external dependency.

**Case insensitivity:** explicit `lower()` on both sides so the intent is unambiguous, regardless of SQLite build or collation defaults.

**Index:** a migration adds `CREATE INDEX IF NOT EXISTS idx_documents_title_lower ON documents (lower(title))`. The leading `%` in the contains pattern prevents index use for that tier, but the prefix tier (`LIKE lower(:q) || '%'`) can use it once the query planner notices the pattern. At this document volume all tiers are instant regardless; the index is added for correctness of intent and future-proofing.

`admin.php` and `share.php` are otherwise unchanged — the share-creation flow (`share.php?doc=ID`) is the destination, not the subject, of this feature.

## Files to change

| File | Change |
|------|--------|
| `public/admin.php` | Add a compact GET search form (input `name="q"`, action `/results.php`) in the top-right of the page, above or beside the existing heading. No other changes. |
| `public/results.php` | New page. Reads `?q`, runs the ranked query, renders results in three tiers (labelled "Exact match", "Starts with", "Other matches" — or hidden labels if empty), each row with a "Create share →" link. Has its own search box at top. Shows "No results" when nothing matches. |
| `migrations/002_add_title_index.sql` | Expression index `CREATE INDEX IF NOT EXISTS idx_documents_title_lower ON documents (lower(title));` |
| `tests/test.php` | Four test blocks: exact match appears at rank 0, prefix match appears at rank 1, contains match appears at rank 2, case-insensitive match (uppercase query against lowercase-stored title). |

## Schema changes

```sql
-- migrations/002_add_title_index.sql
CREATE INDEX IF NOT EXISTS idx_documents_title_lower ON documents (lower(title));
```

No table structure changes.

## Tradeoffs considered

- **Dedicated `results.php` vs filter on `admin.php`**: A separate page keeps `admin.php` uncluttered and makes the results page bookmarkable/shareable as a URL. The admin list is a management surface; the results page is a search-to-share surface — different jobs, different pages.
- **Three-tier ranking vs flat LIKE list**: A flat `LIKE '%q%'` list buries exact matches under alphabetical ordering. Three tiers make the most likely result (staff typed exactly what they meant) always appear first. Cost: one CASE expression in SQL — negligible.
- **Single ranked query vs two separate queries**: Two queries (one exact, one partial) would require PHP-side merging and deduplication. The single CASE WHEN query is cleaner and less code.
- **`lower()` vs `COLLATE NOCASE`**: `lower()` pairs with the expression index and makes case handling visible at the call site. `COLLATE NOCASE` is a column-level setting that affects all comparisons and isn't wired to the index.
- **No fuzzy/phonetic matching**: Levenshtein or soundex would require PHP-side scoring loops and return surprising results for staff who know the title. Tiered LIKE is predictable and sufficient.
- **Result cap at 50**: Agreed in prior review. No pagination needed at this scale.

## Open questions

None.

## Out of scope

- Changes to `share.php` — untouched; share creation remains `share.php?doc=ID`.
- Fuzzy/phonetic matching.
- Real-time JS autocomplete — consistent with no-framework stack; can layer on later.
- Searching by body, creator, or date — title only per the issue.
- Pagination beyond the 50-row cap.
