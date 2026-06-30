#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Smoke tests"
php scripts/smoke_test.php

echo "==> Integration tests"
php scripts/integration_test.php

BASE_URL="${MECHINNO_TEST_URL:-http://127.0.0.1:8765}"
if ! curl -sf "$BASE_URL/login.php" >/dev/null 2>&1; then
  echo "==> Starting PHP dev server on 127.0.0.1:8765"
  php -S 127.0.0.1:8765 -t . >/tmp/mechinno-dev-server.log 2>&1 &
  SERVER_PID=$!
  trap 'kill "$SERVER_PID" 2>/dev/null || true' EXIT
  sleep 1
fi

if [[ -f config.php ]]; then
  echo "==> HTTP tests"
  MECHINNO_TEST_URL="$BASE_URL" php scripts/http_test.php
else
  echo "==> Skipping HTTP tests (config.php not found — copy config.sample.php)"
fi

echo "==> All tests passed"
