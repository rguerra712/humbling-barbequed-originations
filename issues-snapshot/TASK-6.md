# TASK-6: Human-readable document IDs

**Status:** Done

## Description

## Problem

Documents are currently identified only by auto-increment integers and share links use opaque hex tokens. Customers want each document to have a short, memorable, speakable ID they can say aloud, type into a URL, or paste into an email.

## Acceptance criteria

* Each new document receives a unique human-readable slug or code at creation time
* To get the slug, use the gemini API and an API key that will be set in an environment variable on the container called GEMINI_API_KEY
* Ensure that I can store the gemini api key as a local environment variable on my machine and the docker-compose is updated to read in that api key as an api key on the container
  * Ensure that the API key storage is secure and offer any alternatives if you see any
  * Ensure that the API key is never committed to the repo
* How it will work for the slug
  * If the title looks to contain PII, ask gemini for three random words three different times
  * If the title does not look to contain PII, pass the title of the shared document as an input and ask for gemini to generate three suggestions for the share link
  * Ensure uniqueness per user for the title and ensure that the title is indexed such that it can be searched. For any option that already exists, ask gemini for a different option.
  * Then let the user choose which title they want
    * Not in scope: Generating different options
* The readable ID complements or replaces the existing hex-token mechanism — tradeoffs (privacy, guessability, link permanence) are explicitly considered.
* A migration file under `migrations/` adds the slug column and ensures it is indexed for searchability.
* Tests are generated per the criteria above.

## Comments

**Stephen** — 2026-05-12T17:53:04Z
Planning PR opened: https://github.com/rguerra712/humbling-barbequed-originations/pull/4. Review the approach and open questions before implementation starts.

**Stephen** — 2026-05-12T18:29:12Z
Implementation complete: https://github.com/rguerra712/humbling-barbequed-originations/pull/4. PLAN.md deleted. Ready for code review.
