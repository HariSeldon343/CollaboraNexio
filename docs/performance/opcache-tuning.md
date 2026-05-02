# OPcache Tuning — CollaboraNexio

> Guida operativa al tuning OPcache per CollaboraNexio. Aggiornato: 2026-05-02.

OPcache memorizza in RAM il bytecode PHP precompilato evitando il parse + compile di ogni richiesta. Per CollaboraNexio (PHP vanilla, ~migliaia di file inclusi tra `includes/`, `api/`, `vendor/`-like) un OPcache ben dimensionato e' la singola ottimizzazione piu' efficace lato runtime PHP.

Questo documento descrive i settings raccomandati, il razionale di ogni scelta, come applicarli con lo script idempotente `tools/opcache_install.ps1` e come verificare l'effetto.

---

## 1) Settings raccomandati

| Direttiva | Valore | Razionale |
|---|---|---|
| `opcache.enable` | `1` | Abilita OPcache per il SAPI web (Apache). |
| `opcache.enable_cli` | `0` | Disabilita per CLI: utility e cron sono short-lived, il cache non si riusa tra processi. |
| `opcache.memory_consumption` | `256` (MB) | Sufficiente a tenere in cache l'intero codebase + margine. Default 128 MB e' al limite. |
| `opcache.interned_strings_buffer` | `16` (MB) | Riduce duplicazione stringhe ricorrenti (nomi tabelle, chiavi array, attributi). |
| `opcache.max_accelerated_files` | `20000` | Headroom abbondante: il codebase + dipendenze stanno sotto, ma cresce nel tempo. |
| `opcache.validate_timestamps` | `0` | **Scelta consapevole**: niente `stat()` per ogni file ad ogni request. Reset richiesto post-deploy. |
| `opcache.revalidate_freq` | `0` | Coerente con `validate_timestamps=0` (ignorato), valore di sicurezza se in futuro si riattiva validazione. |
| `opcache.fast_shutdown` | `1` | Shutdown sequence ottimizzato. |
| `opcache.save_comments` | `1` | Necessario se si introducono librerie che leggono attributi/PHPDoc (es. PHPUnit attribute readers, Doctrine). |

---

## 2) Perche' `validate_timestamps=0`

Con `validate_timestamps=1` (default) PHP fa una `stat()` per ogni file in cache ad **ogni** request per verificare se il file e' cambiato su disco. Su Windows + filesystem locale e' un'operazione non gratuita, e su filesystem di rete o container puo' incidere sensibilmente.

Con `validate_timestamps=0` la cache e' "frozen": modifiche ai file PHP non vengono rilevate. **Dopo ogni deploy** e' necessario:

- riavviare Apache, **oppure**
- chiamare `opcache_reset()` via PHP (es. uno script protetto richiamato dal flusso di deploy).

> In dev XAMPP locale chi modifica un file e non vede l'effetto ricordi: riavviare Apache (XAMPP Control Panel → Apache → Stop/Start). Per developer flow piu' fluido in locale e' accettabile mettere `validate_timestamps=1` e `revalidate_freq=2`: scelta personale, lo script non lo impone.

---

## 3) Preload (NON abilitato)

`opcache.preload` permette di pre-caricare un set di file all'avvio di Apache, accessibili da ogni request senza require. Beneficio reale, ma:

- richiede un file PHP dedicato (`preload.php`) con `opcache_compile_file(...)`,
- richiede `opcache.preload_user` (su Windows e' tipicamente `SYSTEM` o l'utente di servizio Apache),
- ogni errore in preload **impedisce l'avvio** di Apache.

Decisione: **non abilitato in questa fase**. Lo script lo lascia commentato come TODO opt-in.

---

## 4) Come applicare i settings

Lo script PowerShell `tools/opcache_install.ps1` applica i settings in modo idempotente:

- esegue un backup `php.ini.bak.YYYYMMDD-HHmmss`,
- aggiorna in place le righe gia' presenti (anche se commentate),
- aggiunge in coda le chiavi mancanti, sotto la sezione `[opcache]`,
- stampa un diff before/after,
- opzionalmente riavvia Apache.

### Esecuzione tipica (dev XAMPP)

```powershell
# Dry run: mostra solo cosa cambierebbe
.\tools\opcache_install.ps1 -DryRun

# Applica modifiche al php.ini di default (C:\xampp\php\php.ini)
.\tools\opcache_install.ps1

# Applica e riavvia Apache (richiede privilegi amministrativi)
.\tools\opcache_install.ps1 -RestartApache
```

### Path php.ini diverso

```powershell
.\tools\opcache_install.ps1 -PhpIni 'D:\php\php.ini'
```

### Identificare il php.ini caricato

```powershell
php --ini
# Loaded Configuration File: C:\xampp\php\php.ini
```

> Nota: in produzione lo script va eseguito **manualmente** dopo aver letto questa guida e verificato il valore di `opcache.memory_consumption` rispetto alla RAM disponibile. Niente esecuzione automatica.

---

## 5) Verifica post-installazione

### Via PHP CLI

```powershell
php -r "print_r(opcache_get_configuration());"
php -r "var_dump(opcache_get_status(false));"
```

> `opcache_get_status(false)` evita di stampare il dump completo dei file in cache (rumoroso). Per avere la lista completa: `opcache_get_status(true)`.

### Via web (script ad-hoc protetto)

Esempio di endpoint diagnostico (super_admin only, tenant-aware) — non incluso in repo, da creare al bisogno:

```php
<?php
// Esempio, NON commitato. Proteggere con auth super_admin.
require_once __DIR__ . '/includes/api_auth.php';
initializeApiEnvironment();
verifyApiAuthentication();
$user = getApiUserInfo();
if (($user['role'] ?? '') !== 'super_admin') {
    api_error('Forbidden', 403);
}
api_success([
    'config' => opcache_get_configuration(),
    'status' => opcache_get_status(false),
], 'OK');
```

Metriche chiave da osservare:

- `memory_usage.used_memory` vs `free_memory`: se `free_memory` cala sotto ~10% di `memory_consumption`, alzare il valore.
- `opcache_statistics.opcache_hit_rate`: target > 99% in regime steady-state.
- `opcache_statistics.num_cached_keys` vs `max_cached_keys`: se vicino al limite, alzare `max_accelerated_files`.

---

## 6) Reset post-deploy

Con `validate_timestamps=0` il deploy **deve** invalidare la cache. Opzioni:

1. **Restart Apache** (il piu' affidabile):
   ```powershell
   Restart-Service -Name 'Apache2.4'
   ```
2. **`opcache_reset()` via PHP**: utile se non si vuole interrompere altri processi web. Esempio di script CLI:
   ```powershell
   php -r "opcache_reset(); echo 'OPcache resettato', PHP_EOL;"
   ```
   > Attenzione: il reset CLI agisce sul SAPI CLI, non su Apache. Per resettare la cache di Apache bisogna invocare `opcache_reset()` da una request HTTP (es. endpoint protetto richiamato in coda al deploy).
3. **Touch di `php.ini`**: alcune configurazioni reagiscono al cambio di mtime di `php.ini`. Non affidabile, sconsigliato.

L'opzione 1 e' la default raccomandata per CollaboraNexio.

---

## 7) Rollback

Se qualcosa va storto:

```powershell
# Identifica il backup piu' recente
Get-ChildItem 'C:\xampp\php\php.ini.bak.*' | Sort-Object LastWriteTime -Descending | Select-Object -First 1

# Ripristina (sostituisci il timestamp con quello reale)
Copy-Item 'C:\xampp\php\php.ini.bak.YYYYMMDD-HHmmss' 'C:\xampp\php\php.ini' -Force
Restart-Service -Name 'Apache2.4'
```

I backup vengono creati automaticamente dallo script ad ogni esecuzione non-dry-run.

---

## 8) Riferimenti

- [PHP manual — OPcache configuration](https://www.php.net/manual/en/opcache.configuration.php)
- [PHP manual — opcache_get_status()](https://www.php.net/manual/en/function.opcache-get-status.php)
- `tools/opcache_install.ps1` — installer idempotente
