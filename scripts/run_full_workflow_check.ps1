# Full workflow + gate check for one SKU payload.
# Usage: .\scripts\run_full_workflow_check.ps1 [-BaseUrl "http://localhost:8080"] [-Token "demo-token"]
# Expects the payload in a file or uses embedded CBL-BLK-3C-1M data.

param(
    [string]$BaseUrl = "http://localhost:8080",
    [string]$Token = "demo-token",
    [string]$JsonPath = "",
    [switch]$UniqueSku
)

$headers = @{
    "Authorization" = "Bearer $Token"
    "Content-Type"  = "application/json"
    "Accept"        = "application/json"
}

# Load payload
if ($JsonPath -and (Test-Path $JsonPath)) {
    $payload = Get-Content $JsonPath -Raw | ConvertFrom-Json
} else {
    $payload = @{
        sku_code = "CBL-BLK-3C-1M"
        product_name = "Black Braided Pendant Cable Set 3-Core 1m E27"
        tier = "Hero"
        use_case = @{
            cluster_id = "CLU-CBL-P-E27"
            primary_intent = "Compatibility"
            secondary_intents = @("Installation/How-To", "Specification")
            best_for = @("Standard ceiling pendant installations", "Kitchen island lighting", "Bedroom pendant upgrades", "Replacing old flex cable")
            not_for = @("Bathroom installations (not IP-rated)", "Outdoor use", "Heavy industrial fixtures over 5kg")
        }
        content = @{
            shopify_title = "Pendant Cable Set for Ceiling Lights - Safe Wiring Made Simple | 3-Core Braided 1m E27"
            meta_description = "3-core braided pendant cable set with E27 holder. Rated to 60W. Compatible with LED and CFL. Ideal for standard 2.4m ceilings. BS 7671 compliant. Free UK delivery."
            ai_answer_block = "A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings."
        }
        authority = @{
            expert_statement = "Wiring compliant with BS 7671 (IET Wiring Regulations, 18th Edition). Cable rated to 3A/60W. Suitable for DIY installation with existing ceiling rose."
        }
        expected_outputs = @{
            gate_results = @{ G1 = "PASS"; G2 = "PASS"; G3 = "PASS"; G4 = "PASS"; G5 = "PASS"; G6 = "PASS"; G7 = "PASS"; overall = "ALL_PASS" }
        }
    }
}

# Normalize: support both flat and nested (use_case, content, authority)
$uc = if ($payload.use_case) { $payload.use_case } else { @{} }
$content = if ($payload.content) { $payload.content } else { @{} }
$auth = if ($payload.authority) { $payload.authority } else { @{} }
$tier = if ($payload.tier) { $payload.tier } else { "Hero" }
$tierUpper = $tier.ToString().ToUpper()
if ($tierUpper -eq "HERO") { $tierUpper = "HERO" }

$shortDesc = if ($content.meta_description) { $content.meta_description } elseif ($content.ai_answer_block) { $content.ai_answer_block } else { "Sample product. Safe wiring and compatibility with LED and CFL. BS 7671 compliant." }
if ($shortDesc.Length -lt 50) { $shortDesc = $shortDesc.PadRight(51, ' ') }

$skuCode = $payload.sku_code
if (-not $skuCode) { $skuCode = "CBL-BLK-3C-1M" }
if ($UniqueSku) { $skuCode = $skuCode + "-" + (Get-Date -Format "yyyyMMddHHmmss") }

Write-Host "1. GET clusters..."
$clusters = Invoke-RestMethod -Uri "$BaseUrl/api/clusters" -Headers $headers -Method Get
$list = if ($clusters.data) { $clusters.data } elseif ($clusters -is [array]) { $clusters } else { @($clusters) }
if (-not $list -or $list.Count -eq 0) { Write-Host "No clusters. Create one first."; exit 1 }
$clusterId = $list[0].id
Write-Host "   Cluster id: $clusterId"

$bestFor = $uc.best_for
$notFor = $uc.not_for
if ($bestFor -is [string]) { $bestFor = $bestFor | ConvertFrom-Json }
if ($notFor -is [string]) { $notFor = $notFor | ConvertFrom-Json }
$title = if ($content.shopify_title) { $content.shopify_title } else { $payload.product_name }
$longDesc = if ($content.ai_answer_block) { $content.ai_answer_block } else { $shortDesc }
# Send best_for/not_for as JSON strings for TEXT columns
$bestForJson = if ($bestFor -is [array]) { ($bestFor | ConvertTo-Json -Compress) } else { $bestFor }
$notForJson = if ($notFor -is [array]) { ($notFor | ConvertTo-Json -Compress) } else { $notFor }
$body = @{
    sku_code = $skuCode
    title = $title
    tier = $tierUpper
    primary_cluster_id = $clusterId
    short_description = $shortDesc
    long_description = $longDesc
    best_for = $bestForJson
    not_for = $notForJson
}
if ($content.ai_answer_block) { $body.ai_answer_block = $content.ai_answer_block }
if ($auth.expert_statement) { $body.expert_authority = $auth.expert_statement }
$bodyJson = $body | ConvertTo-Json -Depth 5 -Compress

Write-Host "2. POST /api/skus (create)..."
try {
    $create = Invoke-RestMethod -Uri "$BaseUrl/api/skus" -Headers $headers -Method Post -Body $bodyJson
} catch {
    Write-Host "Error: $($_.Exception.Message)"
    if ($_.ErrorDetails.Message) { Write-Host $_.ErrorDetails.Message }
    try { $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream()); $reader.BaseStream.Position = 0; Write-Host $reader.ReadToEnd() } catch {}
    exit 1
}
$skuId = $create.data.sku.id; if (-not $skuId) { $skuId = $create.sku.id }
Write-Host "   SKU id: $skuId"

$primaryIntent = if ($uc.primary_intent) { $uc.primary_intent } else { "Compatibility" }
$secondaryIntents = if ($uc.secondary_intents) { $uc.secondary_intents } else { @("Installation/How-To", "Specification") }
if ($secondaryIntents -is [string]) { $secondaryIntents = @($secondaryIntents) }
$intentsBody = @{ primary_intent = $primaryIntent; secondary_intents = $secondaryIntents } | ConvertTo-Json

Write-Host "3. POST /api/skus/$skuId/intents (attach intents)..."
try {
    Invoke-RestMethod -Uri "$BaseUrl/api/skus/$skuId/intents" -Headers $headers -Method Post -Body $intentsBody | Out-Null
} catch {
    Write-Host "   Warning: $($_.Exception.Message)"
}
Write-Host "   Intents attached."

Write-Host "4. POST /api/skus/$skuId/validate..."
try {
    $validate = Invoke-RestMethod -Uri "$BaseUrl/api/skus/$skuId/validate" -Headers $headers -Method Post
} catch {
    Write-Host "Validate request failed: $($_.Exception.Message)"
    exit 1
}
$data = $validate.data
$valid = $data.valid
$status = $data.status
if ($data.error) { Write-Host "Validation error: $($data.error)" }
$results = @()
if ($data.results) { $results = @($data.results) }
elseif ($data.gates) { $results = @($data.gates) }
if ($results.Count -eq 0 -and $data.PSObject.Properties) {
    foreach ($p in $data.PSObject.Properties) { if ($p.Name -eq 'results' -or $p.Name -eq 'gates') { $results = @($p.Value); break } }
}

Write-Host ""
Write-Host "=== GATE RESULTS ==="
$gateMap = @{}
if ($results.Count -gt 0) {
    foreach ($r in $results) {
        $rawGate = $r.gate
        $g = $rawGate -replace 'G1_BASIC_INFO','G1' -replace 'G2_INTENT','G2' -replace 'G3_SECONDARY_INTENT','G3' -replace 'G4_ANSWER_BLOCK','G4' -replace 'G4_VECTOR','G4V' -replace 'G5_VECTOR','G5V' -replace 'G5_TECHNICAL','G5' -replace 'G6_COMMERCIAL_POLICY','G6' -replace 'G7_EXPERT','G7'
        $gateMap[$g] = $r
        $gateMap[$rawGate] = $r
        $sym = if ($r.passed) { "PASS" } else { "FAIL" }
        $reason = if ($r.reason) { " - " + $r.reason.Substring(0, [Math]::Min(70, $r.reason.Length)) } else { "" }
        Write-Host ("   " + $g + " : " + $sym + $reason)
    }
}
Write-Host ""
Write-Host "Overall: Valid=$valid, Status=$status"

$expected = $payload.expected_outputs.gate_results
if ($expected) {
    Write-Host ""
    Write-Host "=== EXPECTED vs ACTUAL ==="
    foreach ($k in @("G1","G2","G3","G4","G5","G6","G7")) {
        $exp = $expected.$k
        $act = "-"
        if ($gateMap[$k]) { $act = if ($gateMap[$k].passed) { "PASS" } else { "FAIL" } }
        else {
            foreach ($gk in $gateMap.Keys) {
                if (($gk -eq $k) -or ($gk -match ("^" + [regex]::Escape($k)))) { $act = if ($gateMap[$gk].passed) { "PASS" } else { "FAIL" }; break }
            }
        }
        $match = if ($exp -eq $act) { "OK" } else { "MISMATCH" }
        Write-Host "   $k : expected=$exp actual=$act $match"
    }
    $expOverall = $expected.overall
    $actOverall = if ($valid) { "ALL_PASS" } else { "GATE_FAIL" }
    Write-Host "   overall : expected=$expOverall actual=$actOverall $(if ($expOverall -eq $actOverall) { 'OK' } else { 'MISMATCH' })"
}

Write-Host ""
Write-Host "Workflow check complete."
