#!/usr/bin/env bash
# SOURCE: Production Roadmap P3.1 — prove batch fixes + optional live staging E2E
# Runs static/unit checks always; live HTTP checks when APP_URL + TEST_WRITER_TOKEN are set.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== 1/4 PHPUnit batch-fix regression suite ==="
cd "${ROOT}/backend/php"
export APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32 2>/dev/null || echo dGVzdGtleQ==)}"
vendor/bin/phpunit --bootstrap "${ROOT}/tests/php/bootstrap-app.php" \
  "${ROOT}/tests/php/Feature/PublishAuthorizationTest.php" \
  "${ROOT}/tests/php/Feature/DemoTokenGateTest.php" \
  "${ROOT}/tests/php/Feature/SemrushMigrationTest.php" \
  "${ROOT}/tests/php/Feature/PythonWorkerConfigTest.php" \
  "${ROOT}/tests/php/Feature/BusinessRulesRouteTest.php" \
  -q

echo "=== 2/4 Frontend production build ==="
cd "${ROOT}/frontend"
if [[ -f package-lock.json ]]; then
  npm ci || npm install
else
  npm install
fi
npm run build

echo "=== 3/4 Python gate tests ==="
cd "${ROOT}/backend/python"
python -m pytest tests/ -q

echo "=== 4/4 Optional live staging E2E (P3.1 publish flow) ==="
if [[ -n "${APP_URL:-}" && -n "${TEST_WRITER_TOKEN:-}" ]]; then
  python "${ROOT}/scripts/e2e_publish_flow_check.py"
else
  echo "SKIP: Set APP_URL and TEST_WRITER_TOKEN to run live publish E2E"
fi

echo ""
echo "OK: Staging readiness checks passed (static + build)."
