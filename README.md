# Folio

A small document-sharing app.

## Setup

Requires Docker (with Compose). That's it — PHP, SQLite, and everything else ship inside the container.

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

