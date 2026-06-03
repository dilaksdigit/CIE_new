# SOURCE: Production Roadmap P3.1 — prove batch fixes + optional live staging E2E (Windows)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

Write-Host "=== 1/4 PHPUnit batch-fix regression suite ==="
Set-Location "$Root\backend\php"
$env:APP_KEY = if ($env:APP_KEY) { $env:APP_KEY } else { "base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGs=" }
& vendor\bin\phpunit --bootstrap "$Root\tests\php\bootstrap-app.php" `
  "$Root\tests\php\Feature\PublishAuthorizationTest.php" `
  "$Root\tests\php\Feature\DemoTokenGateTest.php" `
  "$Root\tests\php\Feature\SemrushMigrationTest.php" `
  "$Root\tests\php\Feature\PythonWorkerConfigTest.php" `
  "$Root\tests\php\Feature\BusinessRulesRouteTest.php" -q

Write-Host "=== 2/4 Frontend production build ==="
Set-Location "$Root\frontend"
if (Test-Path package-lock.json) {
  npm ci 2>$null; if ($LASTEXITCODE -ne 0) { npm install }
} else {
  npm install
}
npm run build

Write-Host "=== 3/4 Python gate tests ==="
Set-Location "$Root\backend\python"
python -m pytest tests/ -q

Write-Host "=== 4/4 Optional live staging E2E (P3.1 publish flow) ==="
if ($env:APP_URL -and $env:TEST_WRITER_TOKEN) {
  python "$Root\scripts\e2e_publish_flow_check.py"
} else {
  Write-Host "SKIP: Set APP_URL and TEST_WRITER_TOKEN to run live publish E2E"
}

Write-Host ""
Write-Host "OK: Staging readiness checks passed (static + build)."
