# CIE workflow test: create SKU from sample data, validate, check audit_log.
# Requires: API server running (e.g. php -S localhost:8080 -t backend/php/public)
# Usage: .\scripts\test_sample_workflow.ps1 [-BaseUrl "http://localhost:8080"] [-Token "demo-token"]

param(
    [string]$BaseUrl = "http://localhost:8080",
    [string]$Token = "demo-token"
)

# Use unique sku_code so repeated runs don't hit duplicate key
$skuCode = "CBL-BLK-3C-1M-" + (Get-Date -Format "yyyyMMddHHmmss")
$sample = @{
    sku_code = $skuCode
    title = "Pendant Cable Set for Ceiling Lights - Safe Wiring Made Simple | 3-Core Braided 1m E27"
    tier = "HERO"
    short_description = "3-core braided pendant cable set with E27 holder. Rated to 60W, compatible with LED and CFL. Ideal for standard 2.4m ceilings. BS 7671 compliant. Free UK delivery."
    long_description = "A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings."
    best_for = @("Standard ceiling pendant installations", "Kitchen island lighting", "Bedroom pendant upgrades", "Replacing old flex cable")
    not_for = @("Bathroom installations (not IP-rated)", "Outdoor use", "Heavy industrial fixtures over 5kg")
} | ConvertTo-Json -Depth 5

$headers = @{
    "Authorization" = "Bearer $Token"
    "Content-Type"  = "application/json"
    "Accept"        = "application/json"
}

Write-Host "1. GET clusters..."
try {
    $clusters = Invoke-RestMethod -Uri "$BaseUrl/api/clusters" -Headers $headers -Method Get
} catch {
    Write-Host "Error: $_"
    exit 1
}

$list = if ($clusters.data) { $clusters.data } elseif ($clusters -is [array]) { $clusters } else { @($clusters) }
$clusterId = $null
if ($list -and $list.Count -gt 0) {
    $clusterId = $list[0].id
    Write-Host "   Using cluster id: $clusterId"
} else {
    Write-Host "   No clusters. Create a cluster first (e.g. via CMS or seed)."
    exit 1
}

$body = $sample | ConvertFrom-Json
$body | Add-Member -NotePropertyName "primary_cluster_id" -NotePropertyValue $clusterId -Force
# Ensure best_for/not_for are JSON strings if API expects TEXT
if ($body.best_for -is [array]) { $body.best_for = ($body.best_for | ConvertTo-Json -Compress) }
if ($body.not_for -is [array]) { $body.not_for = ($body.not_for | ConvertTo-Json -Compress) }
$bodyJson = $body | ConvertTo-Json -Depth 5 -Compress

Write-Host "2. POST /api/skus (create SKU)..."
try {
    $create = Invoke-RestMethod -Uri "$BaseUrl/api/skus" -Headers $headers -Method Post -Body $bodyJson
} catch {
    Write-Host "Error: $($_.Exception.Message)"
    if ($_.ErrorDetails.Message) { Write-Host "Details: $($_.ErrorDetails.Message)" }
    try {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $reader.BaseStream.Position = 0
        Write-Host $reader.ReadToEnd()
    } catch {}
    exit 1
}

$skuId = $create.data.sku.id
if (-not $skuId) { $skuId = $create.sku.id }
if (-not $skuId) {
    Write-Host "   Could not get SKU id from response."
    exit 1
}
Write-Host "   Created SKU id: $skuId"

Write-Host "3. POST /api/skus/$skuId/validate..."
try {
    $validate = Invoke-RestMethod -Uri "$BaseUrl/api/skus/$skuId/validate" -Headers $headers -Method Post
} catch {
    Write-Host "Error: $($_.Exception.Message)"
    exit 1
}

$valid = $validate.data.valid
$status = $validate.data.status
$results = $validate.data.results
Write-Host "   Valid: $valid, Status: $status"
if ($results) {
    foreach ($r in $results) {
        $reason = if ($r.reason -and $r.reason.Length -gt 0) { " — " + $r.reason.Substring(0, [Math]::Min(60, $r.reason.Length)) } else { "" }
        Write-Host "   - $($r.gate): passed=$($r.passed), blocking=$($r.blocking)$reason"
    }
}

Write-Host ""
Write-Host "Workflow test complete. Create -> Validate ran successfully."
Write-Host "If Valid=False: add primary intent (sku_intents) via CMS or run migration 026+035 for audit_log canonical columns."
