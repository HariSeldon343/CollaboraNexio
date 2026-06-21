<#
.SYNOPSIS
    Installer idempotente per il tuning OPcache di CollaboraNexio (XAMPP/Windows).

.DESCRIPTION
    Applica i settings OPcache raccomandati al file php.ini di XAMPP.
    Crea un backup timestamped prima di ogni modifica e mostra un diff
    before/after dei valori toccati. Lo script puo' essere eseguito piu'
    volte senza creare duplicati: i settings vengono aggiornati in place
    se gia' presenti, oppure aggiunti in coda se mancanti.

.PARAMETER PhpIni
    Percorso al file php.ini da modificare. Default: C:\xampp\php\php.ini

.PARAMETER RestartApache
    Se specificato, riavvia il servizio Apache2.4 al termine
    (richiede privilegi amministrativi).

.PARAMETER DryRun
    Se specificato, mostra le modifiche che verrebbero applicate senza
    scrivere nulla su disco.

.EXAMPLE
    .\tools\opcache_install.ps1
    .\tools\opcache_install.ps1 -DryRun
    .\tools\opcache_install.ps1 -RestartApache

.NOTES
    Author:  CollaboraNexio perf-batch
    Target:  PHP 8.x + XAMPP su Windows (dev) — settings adatti anche a prod
             previo opcache.validate_timestamps=1 e revalidate_freq=2 in produzione
             se non si gestisce manualmente il reset post-deploy.
#>

[CmdletBinding()]
param(
    [string]$PhpIni = 'C:\xampp\php\php.ini',
    [switch]$RestartApache,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

# Settings target. Ordine = ordine di scrittura nel blocco append.
$TargetSettings = [ordered]@{
    'opcache.enable'                  = '1'
    'opcache.enable_cli'              = '0'
    'opcache.memory_consumption'      = '256'
    'opcache.interned_strings_buffer' = '16'
    'opcache.max_accelerated_files'   = '20000'
    'opcache.validate_timestamps'     = '0'
    'opcache.revalidate_freq'         = '0'
    'opcache.fast_shutdown'           = '1'
    'opcache.save_comments'           = '1'
}

function Write-Section {
    param([string]$Title)
    Write-Host ''
    Write-Host ('=' * 70) -ForegroundColor Cyan
    Write-Host $Title -ForegroundColor Cyan
    Write-Host ('=' * 70) -ForegroundColor Cyan
}

function Get-IniValue {
    <#
        Ritorna il valore di una chiave INI (anche se commentata con ';').
        $null se assente.
    #>
    param(
        [string[]]$Lines,
        [string]$Key
    )
    $escapedKey = [regex]::Escape($Key)
    $pattern = "^\s*;?\s*$escapedKey\s*="
    foreach ($line in $Lines) {
        if ($line -match $pattern) {
            $parts = $line -split '=', 2
            if ($parts.Length -eq 2) {
                $val = $parts[1].Trim()
                # rimuovi commenti inline ; ...
                $semicolon = $val.IndexOf(';')
                if ($semicolon -ge 0) {
                    $val = $val.Substring(0, $semicolon).Trim()
                }
                return $val
            }
        }
    }
    return $null
}

function Test-KeyPresent {
    param(
        [string[]]$Lines,
        [string]$Key
    )
    $escapedKey = [regex]::Escape($Key)
    $pattern = "^\s*;?\s*$escapedKey\s*="
    foreach ($line in $Lines) {
        if ($line -match $pattern) {
            return $true
        }
    }
    return $false
}

function Update-IniLines {
    <#
        Sostituisce in place ogni occorrenza di chiavi presenti
        (decommentandole se necessario) e aggiunge in coda quelle mancanti.
        Ritorna il nuovo array di linee.
    #>
    param(
        [string[]]$Lines,
        [System.Collections.Specialized.OrderedDictionary]$Settings
    )

    $newLines = New-Object System.Collections.Generic.List[string]
    $handled  = @{}

    foreach ($line in $Lines) {
        $replaced = $false
        foreach ($key in $Settings.Keys) {
            $escapedKey = [regex]::Escape($key)
            $pattern = "^\s*;?\s*$escapedKey\s*="
            if ($line -match $pattern) {
                $newLines.Add("$key=$($Settings[$key])") | Out-Null
                $handled[$key] = $true
                $replaced = $true
                break
            }
        }
        if (-not $replaced) {
            $newLines.Add($line) | Out-Null
        }
    }

    # Aggiungi le chiavi non trovate
    $missing = @()
    foreach ($key in $Settings.Keys) {
        if (-not $handled.ContainsKey($key)) {
            $missing += $key
        }
    }

    if ($missing.Count -gt 0) {
        # Trova la sezione [opcache]: se esiste, inserisci subito dopo;
        # altrimenti append blocco con header.
        $opcacheIndex = -1
        for ($i = 0; $i -lt $newLines.Count; $i++) {
            if ($newLines[$i] -match '^\s*\[opcache\]\s*$') {
                $opcacheIndex = $i
                break
            }
        }

        if ($opcacheIndex -ge 0) {
            $insertAt = $opcacheIndex + 1
            foreach ($key in $missing) {
                $newLines.Insert($insertAt, "$key=$($Settings[$key])")
                $insertAt++
            }
        }
        else {
            # Append nuovo blocco
            $newLines.Add('') | Out-Null
            $newLines.Add('[opcache]') | Out-Null
            $newLines.Add('; Aggiunto da tools/opcache_install.ps1 (CollaboraNexio perf-batch)') | Out-Null
            foreach ($key in $missing) {
                $newLines.Add("$key=$($Settings[$key])") | Out-Null
            }
        }
    }

    # TODO opt-in: opcache.preload (richiede opcache.preload_user e file PHP dedicato)
    # opcache.preload=C:\xampp\htdocs\CollaboraNexio\preload.php
    # opcache.preload_user=apache

    return ,$newLines.ToArray()
}

# ----------------------------------------------------------------------
# Validazioni preliminari
# ----------------------------------------------------------------------
Write-Section 'CollaboraNexio - OPcache tuning installer'

if (-not (Test-Path -LiteralPath $PhpIni)) {
    Write-Error "php.ini non trovato in: $PhpIni"
    exit 1
}

Write-Host "Target php.ini : $PhpIni"
Write-Host "Dry run        : $($DryRun.IsPresent)"
Write-Host "Restart Apache : $($RestartApache.IsPresent)"

$originalLines = Get-Content -LiteralPath $PhpIni -Encoding UTF8

# ----------------------------------------------------------------------
# Diff before
# ----------------------------------------------------------------------
Write-Section 'Valori correnti (before)'
$beforeValues = [ordered]@{}
foreach ($key in $TargetSettings.Keys) {
    $present = Test-KeyPresent -Lines $originalLines -Key $key
    $current = Get-IniValue   -Lines $originalLines -Key $key
    $display = if (-not $present) { '<assente>' }
               elseif ($null -eq $current -or $current -eq '') { '<vuoto>' }
               else { $current }
    $beforeValues[$key] = $display
    '{0,-38} = {1}' -f $key, $display | Write-Host
}

# ----------------------------------------------------------------------
# Calcola nuovo contenuto
# ----------------------------------------------------------------------
$newLines = Update-IniLines -Lines $originalLines -Settings $TargetSettings

Write-Section 'Valori target (after)'
foreach ($key in $TargetSettings.Keys) {
    $newVal = Get-IniValue -Lines $newLines -Key $key
    $marker = if ($beforeValues[$key] -ne $newVal) { ' <-- CHANGED' } else { '' }
    '{0,-38} = {1}{2}' -f $key, $newVal, $marker | Write-Host
}

# ----------------------------------------------------------------------
# Scrittura (con backup) o dry-run
# ----------------------------------------------------------------------
if ($DryRun) {
    Write-Section 'DRY RUN attivo: nessuna modifica scritta su disco.'
    exit 0
}

$timestamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupPath = "$PhpIni.bak.$timestamp"

Write-Section 'Backup e scrittura'
Copy-Item -LiteralPath $PhpIni -Destination $backupPath -Force
Write-Host "Backup creato: $backupPath"

# Scrive con encoding ASCII per compatibilita' php.ini (no BOM)
[System.IO.File]::WriteAllLines($PhpIni, $newLines, [System.Text.Encoding]::ASCII)
Write-Host "php.ini aggiornato: $PhpIni"

# ----------------------------------------------------------------------
# Restart Apache (opzionale)
# ----------------------------------------------------------------------
if ($RestartApache) {
    Write-Section 'Restart Apache2.4'
    $svc = Get-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue
    if ($null -eq $svc) {
        Write-Warning 'Servizio "Apache2.4" non installato. Riavvia Apache manualmente da XAMPP Control Panel.'
    }
    else {
        try {
            if ($svc.Status -eq 'Running') {
                Stop-Service  -Name 'Apache2.4' -Force -ErrorAction Stop
                Write-Host 'Apache fermato.'
            }
            Start-Service -Name 'Apache2.4' -ErrorAction Stop
            Write-Host 'Apache avviato.'
        }
        catch {
            Write-Warning "Impossibile riavviare Apache automaticamente: $($_.Exception.Message)"
            Write-Warning 'Riavvia Apache manualmente da XAMPP Control Panel.'
        }
    }
}

Write-Section 'Done'
Write-Host 'Verifica con: php -r "var_dump(opcache_get_status(false));"'
Write-Host 'Reset cache (post-deploy): riavvia Apache oppure chiama opcache_reset() via PHP.'
