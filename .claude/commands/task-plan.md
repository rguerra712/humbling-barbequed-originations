Look up the Linear issue $ARGUMENTS and create a PLAN.md on a new branch, then open a PR for planning review.

## Steps

### 1. Validate the issue key

The argument is a Linear issue key (e.g. `PIN-12`). If `$ARGUMENTS` is empty, tell the developer: "Usage: /task-plan <issue-key> (e.g. /task-plan PIN-12)" and stop.

Use `mcp__linear-server__get_issue` with the key `$ARGUMENTS`. If the issue does not exist or the tool returns an error, tell the developer: "Issue $ARGUMENTS not found in Linear. Check the key and try again." and stop.

### 2. Gather context

- Read the issue title, description, and acceptance criteria from the Linear response.
- Use `mcp__linear-server__get_project` (via the project ID on the issue) to read the project name and description for broader context. If no project is linked, skip this step.
- Read `CLAUDE.md` from the repo root to understand project conventions, architecture, and constraints.
- Run `git log --oneline -10` to understand recent history.
- Read the key source files relevant to the issue (e.g. `public/*.php`, `lib/bootstrap.php`, `schema.sql`) to understand what exists and what will change.

### 3. Create the planning branch and worktree

Determine the branch name from the issue key and title:
- Format: `TASK-#/short_description` where `#` is the numeric part of the issue key and `short_description` is a 2–4 word snake_case summary of the title.
- Example: issue `PIN-3` titled "Share by name" → branch `TASK-3/share_by_name`

Run:
```bash
git fetch origin
git pull origin main
git worktree add -b TASK-#/short_description .worktrees/$ARGUMENTS main
```

All subsequent file reads and writes happen inside `.worktrees/$ARGUMENTS/`. Do not switch branches in the main checkout.

### 4. Write PLAN.md

Create `PLAN.md` in the repo root with this structure:

```markdown
# Plan: [Issue Title] ([Issue Key])

## Linear issue
[Link or key + one-sentence summary of what the user wants]

## Approach
[2–4 paragraphs explaining the implementation strategy. Be concrete: name specific files, functions, schema columns, and code paths you will touch. Explain why you chose this approach over alternatives.]

## Files to change
| File | Change |
|------|--------|
| path/to/file.php | [what changes and why] |
| schema.sql / migrations/XXX.sql | [if schema changes needed] |
| tests/test.php | [new test blocks to add] |

## Schema changes
[If any — show the exact SQL. If none, write "None."]

## Tradeoffs considered
- **[Option A vs Option B]**: Chose A because [reason]. Downside: [known limitation].
- [Repeat for each meaningful tradeoff]

## Open questions
- [Any ambiguity in the requirements that needs product/stakeholder input]
- [Any technical uncertainty that could affect the approach]

## Out of scope
[Anything the issue mentions or implies but this plan deliberately excludes, and why]
```

Fill every section from what you learned in step 2. Do not leave placeholder text.

### 5. Commit, push, and clean up the worktree

From inside `.worktrees/$ARGUMENTS/`:
```bash
git add PLAN.md
git commit -m "plan: $ARGUMENTS [issue title]"
git push -u origin TASK-#/short_description
```

Then remove the worktree:
```bash
git worktree remove .worktrees/$ARGUMENTS
```

### 6. Open the pull request

Use `gh pr create` with:
- Title: `[PLAN] [Issue Key]: [Issue Title]`
- Body: paste the full contents of PLAN.md, then append:

```
---
> **This PR contains only a plan for review — no implementation yet.**
> Comment here or on the Linear issue with feedback before work begins.
```

- Base branch: `main`

### 7. Update Linear

Use `mcp__linear-server__save_comment` to post on the Linear issue:
> "Planning PR opened: [PR URL]. Review the approach and open questions before implementation starts."

Use `mcp__linear-server__save_issue` to move the issue status to `In Progress` if it is currently `Todo` or `Backlog`.

### 8. Report back

Tell the developer:
- The PR URL
- A one-paragraph plain-English summary of the plan
- Any open questions from the plan that need answers before coding starts
