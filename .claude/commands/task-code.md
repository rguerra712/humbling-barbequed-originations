Look up the Linear issue $ARGUMENTS, find its planning PR, implement the plan, and delete PLAN.md.

## Steps

### 1. Validate the issue key

The argument is a Linear issue key (e.g. `PIN-12`). If `$ARGUMENTS` is empty, tell the developer: "Usage: /task-code <issue-key> (e.g. /task-code PIN-12)" and stop.

Use `mcp__linear-server__get_issue` with the key `$ARGUMENTS`. If the issue does not exist or the tool returns an error, tell the developer: "Issue $ARGUMENTS not found in Linear. Check the key and try again." and stop.

Record the issue title, description, and acceptance criteria.

### 2. Find the planning branch and PR

Determine the numeric task number from the issue key (e.g. `PIN-3` → `3`).

Run:
```bash
gh pr list --state open --search "TASK-#" --json number,title,headRefName,url
```

Also try:
```bash
git branch -r | grep "TASK-#/"
```

**If no PR is found:** Tell the developer: "No open PR found for $ARGUMENTS. Run `/task-plan $ARGUMENTS` first to create a planning PR, then come back." Stop.

**If exactly one PR is found:** Proceed with that PR.

**If multiple PRs are found:** List them with their titles and URLs, then ask: "Found multiple PRs for $ARGUMENTS — which one should I implement? Please reply with the PR number." Stop and wait for the developer to clarify before continuing.

### 3. Create a worktree for the branch and read the plan

```bash
git fetch origin
git worktree add .worktrees/$ARGUMENTS <branch-name-from-PR>
```

All subsequent file reads, writes, and shell commands happen inside `.worktrees/$ARGUMENTS/`. Do not switch branches in the main checkout.

Read `PLAN.md` from the worktree root. This is the authoritative specification for what to build. If `PLAN.md` does not exist on this branch, tell the developer and stop — implementation cannot proceed without a plan.

Also read `CLAUDE.md` and all source files listed in the plan's "Files to change" table before writing any code.

### 4. Implement the plan

Work through the plan section by section:

**Schema changes first** (if any):
- Create a new migration file `migrations/NNN_description.sql` (next number in sequence).
- Add the migration to `seed.php` so `docker compose up` applies it from a fresh clone.
- Do not edit `schema.sql` directly — per project conventions.

**Application changes**:
- Edit only the files listed in the plan's "Files to change" table, plus any others the plan's approach clearly requires.
- Follow all conventions from `CLAUDE.md`:
  - All HTML output through `h()`
  - Every write operation gets an `audit_log()` call
  - Use `random_token()` for tokens
  - Use PDO prepared statements — no raw SQL interpolation
  - Timezone is `America/Chicago`

**Tests**:
- Add at least one `test('description', function() { ... })` block to `tests/test.php` for each acceptance criterion.
- Each test must be specific enough that a bug in the implementation would cause it to fail.

Do not implement anything outside the plan's scope. If an open question in the plan would block correct implementation, stop and ask the developer for guidance before writing that part.

### 5. Delete PLAN.md

```bash
rm PLAN.md
git add -A
```

### 6. Commit, push, and clean up the worktree

From inside `.worktrees/$ARGUMENTS/`:
```bash
git commit -m "implement: $ARGUMENTS [issue title]"
git push origin <branch-name>
```

Then remove the worktree:
```bash
git worktree remove .worktrees/$ARGUMENTS
```

### 7. Update the PR

Fetch the current PR title with `gh pr view <PR-number> --json title`. If it starts with `[PLAN]`, strip that prefix before setting the new title.

Use `gh pr edit <PR-number> --title "[issue key]: [issue title]"` — never include `[PLAN]` in the title since the branch now contains an implementation, not just a plan.

Update the PR body with `gh pr edit <PR-number> --body "$(cat <<'EOF'
## Summary
[2–4 bullets describing what was implemented, matching the acceptance criteria]

## Test plan
- [ ] `docker compose exec app php tests/test.php` — all tests pass
- [ ] Manual: [describe the golden path to verify in a browser]
- [ ] Edge cases: [list any edge cases the tests or manual steps cover]

## Linear issue
$ARGUMENTS

🤖 Generated with [Claude Code](https://claude.ai/claude-code)
EOF
)"` — fill in the actual content, don't use placeholders.

### 8. Update Linear

Use `mcp__linear-server__save_comment` to post on the Linear issue:
> "Implementation complete: [PR URL]. PLAN.md deleted. Ready for code review."

Use `mcp__linear-server__save_issue` to move the issue status to `In Review`.

### 9. Report back

Tell the developer:
- The PR URL
- A bullet list of what was implemented, keyed to the acceptance criteria
- Any deviations from the plan (if you had to adjust the approach, explain why)
- How to manually verify the feature (`docker compose up`, then what to click/visit)
