# Folio

A small document-sharing app.

## Setup

Requires Docker (with Compose). That's it — PHP, SQLite, and everything else ship inside the container.

### Environment variables

| Variable | Required | Purpose |
|---|---|---|
| `GEMINI_API_KEY` | Recommended | Gemini API key used to generate human-readable document slugs. If absent, slugs fall back to a title-derived format. |

Set the variable in your shell before starting the app:

```bash
export GEMINI_API_KEY=your_key_here
```

> **Security note:** Never commit your API key. The key is read from your shell environment at `docker compose up` time via the `${GEMINI_API_KEY}` placeholder in `docker-compose.yml`. A `.env.example` file lists all required variables; copy it to `.env` if you prefer a file-based approach — `.env` is listed in `.gitignore` and will not be committed.

```
docker compose up
```

Open http://localhost:8000. The first run builds the image (~30 seconds); subsequent runs start instantly.

Each `docker compose up` re-seeds `db.sqlite` from scratch, so you always start with a known state. Stop with `Ctrl+C`.

To run the tests:

```
docker compose exec app php tests/test.php
```

You edit files on your host machine in your normal editor — the container has them mounted, so changes show up immediately on browser refresh.

## Background

Folio is a small tool that lets staff create documents and share them with recipients via one-time links. This repo contains a staff admin page, document creation, share-link generation, and a recipient view. The schema (`schema.sql`) and helpers (`lib/bootstrap.php`) are meant to feel representative of a real internal tool.

