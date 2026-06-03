#!/usr/bin/env bash
# SOURCE: Production Roadmap P0.4 / P7.1 — pre-deploy assertions
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${1:-${ROOT}/backend/php/.env}"

fail() { echo "PRE-DEPLOY FAIL: $*" >&2; exit 1; }
ok() { echo "OK: $*"; }

[[ -f "$ENV_FILE" ]] || fail "Missing env file: $ENV_FILE"

grep -qE '^APP_ENV=production' "$ENV_FILE" || fail "APP_ENV must be production"
grep -qE '^APP_DEBUG=false' "$ENV_FILE" || fail "APP_DEBUG must be false"
if grep -qE '^APP_DEBUG=true' "$ENV_FILE" 2>/dev/null; then
  fail "APP_DEBUG=true is forbidden for production deploy"
fi

if grep -qE '^DB_PASSWORD=cie_password' "$ENV_FILE" 2>/dev/null; then
  fail "Rotate DB password — do not deploy with docker-compose default cie_password"
fi

if grep -qE 'shpat_[a-f0-9]{20,}' "$ENV_FILE" 2>/dev/null; then
  fail "Rotate Shopify token — literal shpat_ found in env file"
fi

if [[ -f "${ROOT}/service_account.json" ]]; then
  fail "Move service_account.json outside repository (P0.3)"
fi

if grep -qE '^GOOGLE_SERVICE_ACCOUNT_JSON=.*service_account\.json' "$ENV_FILE" 2>/dev/null; then
  fail "GOOGLE_SERVICE_ACCOUNT_JSON must point outside repo (not service_account.json in tree)"
fi

if grep -qE '^SHOPIFY_ADMIN_ACCESS_TOKEN=$' "$ENV_FILE" 2>/dev/null; then
  fail "SHOPIFY_ADMIN_ACCESS_TOKEN must be set for production channel deploy"
fi

if grep -qE '^CIE_INTERNAL_API_KEY=$' "$ENV_FILE" 2>/dev/null; then
  fail "CIE_INTERNAL_API_KEY must be set for worker→PHP callbacks"
fi

DUPES=$(find "${ROOT}/database/migrations" -maxdepth 1 -type f -name '[0-9]*_*' | sed 's/.*\///;s/_.*//' | sort | uniq -d | wc -l)
[[ "$DUPES" -eq 0 ]] || fail "Duplicate migration prefixes: $DUPES"

grep -qE '^N8N_BASE_URL=.+' "$ENV_FILE" || fail "N8N_BASE_URL must be set (P0 / Week 1 channel deploy)"
grep -qE '^N8N_WEBHOOK_SECRET=.+' "$ENV_FILE" || fail "N8N_WEBHOOK_SECRET must be set"
grep -qE '^INTERNAL_SERVICE_TOKEN=.+' "$ENV_FILE" || fail "INTERNAL_SERVICE_TOKEN must be set (openssl rand -hex 32)"

if grep -qE '^ALLOW_DEMO_TOKEN=true' "$ENV_FILE" 2>/dev/null; then
  fail "ALLOW_DEMO_TOKEN must be false in production (P0 security)"
fi

M166="${ROOT}/database/postgres/migrations/166_ensure_semrush_imports_table.sql"
[[ -f "$M166" ]] || fail "Missing semrush recovery migration: 166_ensure_semrush_imports_table.sql"

CANON_IDX="${ROOT}/database/postgres/canonical/03_spec_indexes.sql"
[[ -f "$CANON_IDX" ]] || fail "Missing spec-native indexes: database/postgres/canonical/03_spec_indexes.sql (run generate_pg_spec_indexes.py)"
[[ -f "${ROOT}/database/postgres/init/03_spec_indexes.sql" ]] || fail "Missing init hook: database/postgres/init/03_spec_indexes.sql"

python3 "${ROOT}/scripts/verify_openapi_route_parity.py" || fail "OpenAPI route parity (GAP-ROUTES-01)"
python3 "${ROOT}/scripts/verify_docs_postgres_alignment.py" || fail "Operator docs PostgreSQL alignment (DECISION-015)"
python3 "${ROOT}/scripts/scan_tracked_secrets.py" || fail "Tracked secret scan (P0)"

if git -C "${ROOT}" ls-files --error-unmatch .env 2>/dev/null; then
  fail ".env is tracked in git — run: git rm --cached .env"
fi
if git -C "${ROOT}" ls-files --error-unmatch service_account.json 2>/dev/null; then
  fail "service_account.json is tracked — run: git rm --cached service_account.json"
fi
TRACKED_DEBUG=$(git -C "${ROOT}" ls-files 'debug-*.log' 2>/dev/null | head -1)
[[ -z "$TRACKED_DEBUG" ]] || fail "Debug logs tracked in git: $TRACKED_DEBUG (git rm --cached)"

ok "Env and migration checks passed for $ENV_FILE"
