# =============================================================================
# CollaboraNexio - Cloudflare cache purge (PowerShell)
#
# Usage:
#   tools\cloudflare_purge.ps1                     # purge default UI assets
#   tools\cloudflare_purge.ps1 -All                # purge entire zone
#   tools\cloudflare_purge.ps1 -DryRun             # show what would purge
#   tools\cloudflare_purge.ps1 -Files @("https://app.nexiosolution.it/...")
#
# Auth (set ONCE per shell, or put in tools\.cloudflare.env):
#   $env:CF_API_TOKEN = "your-zone-cache-purge-token"
#   $env:CF_ZONE_ID   = "nexiosolution-zone-id"
#
# Generate token at https://dash.cloudflare.com/profile/api-tokens with
# permission: Zone -> Cache Purge -> Purge.
# Get zone ID from the zone overview page (right sidebar).
# =============================================================================

[CmdletBinding()]
param(
    [switch]$All,
    [switch]$DryRun,
    [string[]]$Files,
    [string]$EnvFile = (Join-Path $PSScriptRoot ".cloudflare.env")
)

$ErrorActionPreference = "Stop"

# ---- Load .cloudflare.env if present (KEY=VALUE per line, # for comments) ----
if (Test-Path $EnvFile) {
    Write-Host "[cf-purge] Loading $EnvFile" -ForegroundColor DarkGray
    Get-Content $EnvFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
            $idx = $line.IndexOf("=")
            $k = $line.Substring(0, $idx).Trim()
            $v = $line.Substring($idx + 1).Trim().Trim('"').Trim("'")
            if (-not [Environment]::GetEnvironmentVariable($k, "Process")) {
                [Environment]::SetEnvironmentVariable($k, $v, "Process")
            }
        }
    }
}

$token  = $env:CF_API_TOKEN
$zoneId = $env:CF_ZONE_ID

if (-not $token -or -not $zoneId) {
    Write-Host "[cf-purge] ERROR: CF_API_TOKEN and CF_ZONE_ID must be set." -ForegroundColor Red
    Write-Host "          Either export them in your shell or write tools\.cloudflare.env" -ForegroundColor Red
    Write-Host "          (template: tools\.cloudflare.env.example)" -ForegroundColor Red
    exit 1
}

# ---- Default file list (UI redesign assets + entry points hit hardest by CF) ----
$defaultFiles = @(
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/styles.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/components.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/dashboard.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/filemanager.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/filemanager_enhanced.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/calendar.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/shifts.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/workflow.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/sidebar-responsive.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/company_filter.css",
    "https://app.nexiosolution.it/CollaboraNexio/assets/js/app.js",
    "https://app.nexiosolution.it/CollaboraNexio/assets/js/company_filter.js",
    "https://app.nexiosolution.it/CollaboraNexio/dashboard.php",
    "https://app.nexiosolution.it/CollaboraNexio/files.php",
    "https://app.nexiosolution.it/CollaboraNexio/calendar.php",
    "https://app.nexiosolution.it/CollaboraNexio/tasks.php",
    "https://app.nexiosolution.it/CollaboraNexio/ticket.php",
    "https://app.nexiosolution.it/CollaboraNexio/turni.php",
    "https://app.nexiosolution.it/CollaboraNexio/aziende.php",
    "https://app.nexiosolution.it/CollaboraNexio/utenti.php",
    "https://app.nexiosolution.it/CollaboraNexio/audit_log.php",
    "https://app.nexiosolution.it/CollaboraNexio/configurazioni.php"
)

# ---- Build payload ----
if ($All) {
    $payload = @{ purge_everything = $true } | ConvertTo-Json -Compress
    $action  = "PURGE EVERYTHING (entire zone)"
} else {
    if (-not $Files -or $Files.Count -eq 0) { $Files = $defaultFiles }
    $payload = @{ files = $Files } | ConvertTo-Json -Compress
    $action  = "Purge $($Files.Count) URLs"
}

Write-Host "[cf-purge] Zone: $zoneId" -ForegroundColor DarkGray
Write-Host "[cf-purge] Action: $action" -ForegroundColor Cyan

if ($DryRun) {
    Write-Host "[cf-purge] DRY RUN - payload that would be sent:" -ForegroundColor Yellow
    Write-Host $payload
    exit 0
}

# ---- Call Cloudflare API ----
$url = "https://api.cloudflare.com/client/v4/zones/$zoneId/purge_cache"
try {
    $resp = Invoke-RestMethod -Uri $url -Method POST `
        -Headers @{
            "Authorization" = "Bearer $token"
            "Content-Type"  = "application/json"
        } `
        -Body $payload
} catch {
    Write-Host "[cf-purge] HTTP error:" -ForegroundColor Red
    Write-Host $_.Exception.Message
    if ($_.ErrorDetails) { Write-Host $_.ErrorDetails.Message }
    exit 1
}

if ($resp.success) {
    Write-Host "[cf-purge] OK - purge id: $($resp.result.id)" -ForegroundColor Green
    Write-Host "[cf-purge] Edge propagation typically completes in ~30s." -ForegroundColor DarkGray
    exit 0
} else {
    Write-Host "[cf-purge] FAILED:" -ForegroundColor Red
    $resp.errors | ForEach-Object { Write-Host "  - [$($_.code)] $($_.message)" }
    exit 1
}
