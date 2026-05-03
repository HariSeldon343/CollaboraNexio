# Install repo git hooks. Run from repo root.
$ErrorActionPreference = 'Stop'
$src = Split-Path -Parent $PSCommandPath
$dst = '.git/hooks'
if (-not (Test-Path $dst)) { Write-Error 'Not a git repo (no .git/hooks)'; exit 1 }
foreach ($hook in @('pre-commit')) {
    Copy-Item -Path (Join-Path $src $hook) -Destination (Join-Path $dst $hook) -Force
    Write-Host "Installed $hook"
}
Write-Host 'Done. To uninstall: Remove-Item .git/hooks/pre-commit'
