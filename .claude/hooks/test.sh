#!/bin/bash
TEST_OUT=$(docker compose exec app php tests/test.php 2>&1)
TEST_CODE=$?

if [ "$TEST_CODE" -ne 0 ]; then
  jq -nc --arg ctx "PHP tests failed after edit:\n$TEST_OUT" \
    '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":$ctx}}'
fi
