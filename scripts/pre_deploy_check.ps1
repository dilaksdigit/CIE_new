# SOURCE: Production Roadmap P0 — pre-deploy assertions (Windows)
param(
    [string]$EnvFile = (Join-Path $PSScriptRoot "..\backend\php\.env")
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

function Fail($msg) { Write-Error "PRE-DEPLOY FAIL: $msg"; exit 1 }

if (-not (Test-Path $EnvFile)) { Fail "Missing env file: $EnvFile" }

$content = Get-Content $EnvFile -Raw
if ($content -notmatch '(?m)^APP_ENV=production') { Fail "APP_ENV must be production" }
if ($content -notmatch '(?m)^APP_DEBUG=false') { Fail "APP_DEBUG must be false" }
if ($content -match '(?m)^DB_PASSWORD=cie_password') { Fail "Rotate DB password — not cie_password" }
if ($content -match 'shpat_[a-f0-9]{20,}') { Fail "Rotate Shopify token — shpat_ found in env" }
if ($content -match '(?m)^ALLOW_DEMO_TOKEN=true') { Fail "ALLOW_DEMO_TOKEN must be false in production" }
if ($content -notmatch '(?m)^INTERNAL_SERVICE_TOKEN=.+') { Fail "INTERNAL_SERVICE_TOKEN must be set" }
if ($content -notmatch '(?m)^N8N_WEBHOOK_SECRET=.+') { Fail "N8N_WEBHOOK_SECRET must be set" }

if (Test-Path (Join-Path $Root "service_account.json")) {
    Fail "Move service_account.json outside repository"
}

python (Join-Path $Root "scripts\verify_openapi_route_parity.py")
if ($LASTEXITCODE -ne 0) { Fail "OpenAPI route parity" }

python (Join-Path $Root "scripts\scan_tracked_secrets.py")
if ($LASTEXITCODE -ne 0) { Fail "Tracked secret scan" }

Write-Host "OK: Env and security checks passed for $EnvFile"
