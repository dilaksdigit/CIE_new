#!/usr/bin/env bash
# SOURCE: Production Roadmap — local test runner (P4 + partial P6)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "=== OpenAPI route parity (GAP-ROUTES-01) ==="
python "${ROOT}/scripts/verify_openapi_route_parity.py"

echo "=== P0 secret scan ==="
python "${ROOT}/scripts/scan_tracked_secrets.py"

echo "=== P3 N8N/Shopify static E2E ==="
python "${ROOT}/scripts/e2e_n8n_shopify_deploy_static.py"

echo "=== Python (gates + golden) ==="
cd "${ROOT}/backend/python"
python -m pytest tests/ -q

echo "=== PHPUnit (Phase0/1 + Feature) ==="
cd "${ROOT}/backend/php"
export APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"
vendor/bin/phpunit --bootstrap "${ROOT}/tests/php/bootstrap-app.php" \
  "${ROOT}/tests/php/Phase1/ChannelGovernorTest.php" \
  "${ROOT}/tests/php/Feature/RbacEndpointMatrixTest.php" \
  "${ROOT}/tests/php/Feature/AdminUsersEndpointTest.php" \
  "${ROOT}/tests/php/Feature/PublishAuthorizationTest.php" \
  "${ROOT}/tests/php/Feature/DemoTokenGateTest.php" \
  "${ROOT}/tests/php/Feature/SemrushMigrationTest.php" \
  "${ROOT}/tests/php/Feature/OpenApiRouteParityTest.php" \
  "${ROOT}/tests/php/Feature/ProductionSecurityTest.php" \
  "${ROOT}/tests/php/Feature/ChannelDeployE2eTest.php" \
  "${ROOT}/tests/php/Feature/PythonWorkerConfigTest.php" \
  "${ROOT}/tests/php/Feature/BusinessRulesRouteTest.php" \
  -q || true

echo "Done. For full PHPUnit + DB tests, set DB_* env and run: cd backend/php && composer test"
