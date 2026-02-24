# Delete sample SKUs from the database.
# Option A: Run SQL file (set $DbName, $DbUser, $DbPass or use defaults).
# Option B: Run from MySQL client: mysql -u root -p cie_v232 < database/scripts/delete_sample_skus.sql

param(
    [string]$DbName = "cie_v232",
    [string]$DbUser = "root",
    [string]$DbPass = "root1234",
    [string]$DbHost = "127.0.0.1"
)

$sqlPath = Join-Path $PSScriptRoot "..\database\scripts\delete_sample_skus.sql"
if (-not (Test-Path $sqlPath)) {
    Write-Error "SQL file not found: $sqlPath"
    exit 1
}

$mysql = Get-Command mysql -ErrorAction SilentlyContinue
if (-not $mysql) {
    Write-Host "mysql CLI not in PATH. Run the SQL manually:"
    Write-Host "  mysql -u $DbUser -p $DbName < $sqlPath"
    Get-Content $sqlPath
    exit 0
}

$env:MYSQL_PWD = $DbPass
Get-Content $sqlPath -Raw | & mysql -h $DbHost -u $DbUser $DbName 2>&1
$env:MYSQL_PWD = $null
if ($LASTEXITCODE -eq 0) {
    Write-Host "Sample SKUs deleted."
} else {
    Write-Host "If credentials differ, edit this script or run: mysql -u user -p dbname < database/scripts/delete_sample_skus.sql"
}
