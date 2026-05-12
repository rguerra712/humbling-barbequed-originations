# Folio

Small internal document-sharing tool with scheduled publishing, human-readable IDs, and title search.

## Description

Folio lets staff create documents and share them with recipients via one-time links. The stack is PHP 8.3 + SQLite + Docker — no framework, no build step.

**Active work:** three customer-requested features are being implemented on the `init` branch:

1. **Scheduled publishing** — documents become visible to recipients only after a staff-set `publish_at` datetime.
2. **Human-readable document IDs** — short, speakable slugs or codes per document (e.g. `welcome-2026`, `FOLIO-7QX4`).
3. **Share by name** — staff can search for a document by title on the share page rather than scrolling a full list.
