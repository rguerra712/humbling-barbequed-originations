# TASK-10: CI pipeline — PHP unit tests

**Status:** Backlog

## Description

## Goal

Ensure every push and pull request is automatically validated against the PHP unit test suite, giving developers fast feedback.

## Acceptance criteria

* A `.github/workflows/ci.yml` file exists and triggers on every `push` and `pull_request` event.
* The workflow starts the Docker Compose stack (`docker compose up -d`) and waits until the app is healthy before running tests.
* The test step runs `docker compose exec -T app php tests/test.php`.
* The pipeline exits non-zero and is marked failed when one or more PHP unit tests fail.
* The pipeline exits zero and is marked passed when all PHP unit tests pass.
* No Node, npm, or integration test steps appear in the pipeline.
* Pass/fail status is visible on pull requests via the GitHub Actions status check.
* Uses Ministack (or LocalStack) as a service to emulate any AWS dependencies — no real AWS credentials required.

## Out of scope

* Node integration tests (`tests/integration.test.mjs`) — not yet created.
* Deployment or release steps.
* Linting or static analysis.

## Comments

_No comments._
