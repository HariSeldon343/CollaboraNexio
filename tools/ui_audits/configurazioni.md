# Dark mode audit — configurazioni.php

## Summary
- Total findings: 16
- Severity breakdown: critical=2, major=8, minor=6

The page is structurally healthier than `audit_log.php`: most surfaces use `var(--color-white)` / `var(--color-gray-*)` which **are** flipped via the dark-mode token alias overrides at `assets/css/styles.css:239-249`. The big remaining issues come from a small set of tokens that are **NOT** flipped: `--color-primary` (legacy blue `#2563EB`) and `--color-warning-50/200/700` / `--color-primary-50/200/700` (alert boxes). These manifest as:
1. Active tab "Generale" rendering with the **legacy blue** underline + text (should be mint).
2. The "Registrazione Utenti" toggle ON state rendering legacy blue (should be mint).
3. The legacy `.btn-primary` "Salva Modifiche" / "Esegui Backup Manuale" buttons render mint correctly thanks to `components.css:206-211` `!important` override (good).
4. The `.btn--primary` BEM-style class (used by configurazioni's actual buttons, e.g. `cfg_general_save_btn`) needs verification — see F11.

Several toggle switches and form panels look correct.

## Findings

### F1 — Active tab "Generale" rendered with legacy blue (`#2563EB`) instead of mint
- **Severity:** critical
- **Selector / element:** `.tab-btn.active`, `.tab-btn.active::after` (file: `configurazioni.php:100-112`)
- **Screenshot region:** top of content area, "Generale | Sicurezza | Email | Backup | Integrazioni | Aspetto | Visibilità Pagine" — "Generale" text + the underline beneath it
- **Current behaviour (dark mode):** text and underline use `var(--color-primary)` which resolves to `#2563EB` (legacy blue). The rest of the redesign is teal/mint, so this tab pops as a foreign blue accent.
- **Root cause (CSS):** `--color-primary` is defined in `:root` (`styles.css:19`) but **NOT overridden** in `[data-theme="dark"]` (lines 220-250) → stays blue regardless of theme. configurazioni.php:101 uses `color: var(--color-primary)` for the active tab text and `:112` `background: var(--color-primary)` for the underline.
- **Recommended fix:** scope a dark-mode override (or a global one once the design system migrates):
  ```css
  [data-theme="dark"] .tab-btn.active {
      color: var(--cnx-accent) !important;
  }
  [data-theme="dark"] .tab-btn.active::after {
      background: var(--cnx-accent) !important;
  }
  ```
  Better long-term fix (out of scope for this audit but worth flagging): override `--color-primary` inside `[data-theme="dark"]` block at `styles.css:220-250` to point at the mint accent:
  ```css
  [data-theme="dark"] {
      --color-primary: var(--cnx-accent);
      --color-primary-dark: var(--cnx-accent-hover);
      --color-primary-light: var(--cnx-accent);
  }
  ```
  This single change cascades to F2, F11, and many other future pages.

### F2 — "Registrazione Utenti" toggle ON state legacy blue
- **Severity:** critical
- **Selector / element:** `input:checked + .toggle-slider` (file: `configurazioni.php:207-209`)
- **Screenshot region:** lower-right of the screenshot, the rounded pill toggle on the "Registrazione Utenti" row
- **Current behaviour (dark mode):** background `var(--color-primary)` (blue `#2563EB`) — clashes with the rest of the mint accents.
- **Root cause (CSS):** `configurazioni.php:208` uses `background: var(--color-primary)` which is not overridden in dark mode.
- **Recommended fix:**
  ```css
  [data-theme="dark"] input:checked + .toggle-slider {
      background: var(--cnx-accent) !important;
  }
  ```
  Same root cause as F1 — addressing it via the global `--color-primary` override (see F1 long-term fix) auto-fixes both.

### F3 — Inactive tab buttons low contrast
- **Severity:** major
- **Selector / element:** `.tab-btn` (file: `configurazioni.php:85-94`)
- **Screenshot region:** "Sicurezza | Email | Backup | Integrazioni | Aspetto | Visibilità Pagine" labels
- **Current behaviour (dark mode):** `color: var(--color-gray-600)` resolves to `#A8B5BB` (gray-600 in dark). Against the dark surface that's borderline-OK, but the hover state `color: var(--color-gray-900)` resolves to `#E6EEF0` — tabs are barely distinguishable from each other.
- **Root cause (CSS):** dark gray-tokens flip OK but the difference between `--color-gray-600` (`#A8B5BB`) and `--color-gray-900` (`#E6EEF0`) is large; the tabs default state may feel washed-out next to the bright active.
- **Recommended fix:** raise default contrast slightly:
  ```css
  [data-theme="dark"] .tab-btn {
      color: var(--cnx-text-secondary) !important;
  }
  [data-theme="dark"] .tab-btn:hover {
      color: var(--cnx-text-primary) !important;
  }
  ```

### F4 — Tabs underline track (`.config-tabs` border-bottom) too dark
- **Severity:** minor
- **Selector / element:** `.config-tabs` (file: `configurazioni.php:78-83`)
- **Screenshot region:** thin horizontal line under the tabs
- **Current behaviour (dark mode):** `border-bottom: 2px solid var(--color-gray-200)` resolves to `#22363B` in dark — almost invisible against `--cnx-bg-app` `#0E1A1D` (depth ~3 steps). The active mint underline floats with no track behind it.
- **Root cause (CSS):** gray-200 alias points at the very dark border value.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .config-tabs {
      border-bottom-color: var(--cnx-border-strong) !important;
  }
  ```

### F5 — `.config-section` cards: borders effectively invisible
- **Severity:** major
- **Selector / element:** `.config-section` (file: `configurazioni.php:114-120`)
- **Screenshot region:** the "Impostazioni Generali" container box
- **Current behaviour (dark mode):** `background: var(--color-white)` → flips to `#15262A` ✓; `box-shadow: var(--shadow-sm)` is hardcoded for light mode (`rgba(0,0,0,0.05)`) → on dark surface the shadow is invisible. Card edge has no clear delineation from page bg (`#0E1A1D` page vs `#15262A` card — a ~2-step difference).
- **Root cause (CSS):** legacy `--shadow-sm` token doesn't have a dark-mode equivalent; no border declared on `.config-section`.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .config-section {
      border: 1px solid var(--cnx-border) !important;
      box-shadow: none !important;
  }
  ```

### F6 — Form input borders barely visible (`.form-control`)
- **Severity:** major
- **Selector / element:** `.form-control` (used by Nome Piattaforma, URL Base, Fuso Orario, Lingua, etc.) — defined in `assets/css/styles.css` global rules
- **Screenshot region:** all four input/select fields in the "Impostazioni Generali" section
- **Current behaviour (dark mode):** inputs flip to dark via the catch-all in `components.css:262-273`. Border resolves to `--cnx-border` `#22363B` against card bg `#15262A` → ~1 lightness step, almost invisible.
- **Root cause (CSS):** dark-mode input border token is too close to the card surface tone.
- **Recommended fix:** scope a stronger border on configurazioni form inputs:
  ```css
  [data-theme="dark"] .config-section .form-control,
  [data-theme="dark"] .config-section input[type="text"],
  [data-theme="dark"] .config-section input[type="email"],
  [data-theme="dark"] .config-section input[type="password"],
  [data-theme="dark"] .config-section input[type="number"],
  [data-theme="dark"] .config-section input[type="time"],
  [data-theme="dark"] .config-section select,
  [data-theme="dark"] .config-section textarea {
      border-color: var(--cnx-border-strong) !important;
  }
  ```

### F7 — Toggle group backgrounds blend into card (`.toggle-group`)
- **Severity:** major
- **Selector / element:** `.toggle-group` (file: `configurazioni.php:215-222`)
- **Screenshot region:** the two horizontal bars containing "Modalità Manutenzione" and "Registrazione Utenti"
- **Current behaviour (dark mode):** `background: var(--color-gray-50)` → resolves to `#1B2F33` in dark, while the parent `.config-section` is `#15262A` — the difference is so subtle the toggle bars look "unboxed". Currently OK in screenshot, but tight.
- **Root cause (CSS):** light-mode design used a 4% darker neutral for the bar; in dark the relationship inverts and the same alias resolves only ~6 lightness from card.
- **Recommended fix:** add a 1px subtle border to lift the bars:
  ```css
  [data-theme="dark"] .toggle-group {
      background: var(--cnx-bg-subtle) !important;
      border: 1px solid var(--cnx-border) !important;
  }
  ```

### F8 — Toggle OFF state knob extremely dark
- **Severity:** minor
- **Selector / element:** `.toggle-slider` (file: `configurazioni.php:183-205`)
- **Screenshot region:** "Modalità Manutenzione" toggle (OFF state, knob on the left)
- **Current behaviour (dark mode):** `.toggle-slider:before` has `background: white` literal at line 202 — flips to `#15262A` via the dark `--color-white` override. So the knob (the round dot) becomes nearly invisible against the slightly-lighter slider track (`var(--color-gray-300)` resolves to `#2E464C` in dark). Screenshot shows the OFF knob as a near-black blob — barely readable.
- **Root cause (CSS):** the `before` pseudo uses bare `background: white` (line 202), not a token; in light mode that's white-on-gray (visible). In dark, white→`#15262A`, on track `#2E464C` (dark border) → low contrast.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .toggle-slider:before {
      background: var(--cnx-text-primary) !important;  /* light pebble in dark mode */
  }
  ```
  This produces a light grey knob on a dark track for OFF state — readable.

### F9 — Section description / form-help text low contrast
- **Severity:** minor
- **Selector / element:** `.section-description`, `.form-help`, `.toggle-description` (file: `configurazioni.php:129-133, 164-168, 234-237`)
- **Screenshot region:** "Configura le impostazioni generali..." subtitle, "Il nome visualizzato in tutta la piattaforma" helps under each input, "Mostra un messaggio di manutenzione..." under toggle
- **Current behaviour (dark mode):** `color: var(--color-gray-600)` `#A8B5BB` and `--color-gray-500` `#8893A0` — readable, but lighter than what the design tokens recommend. `--cnx-text-muted` (`#6B7A82`) might actually be too dim; `--cnx-text-secondary` (`#A8B5BB`) is what we want for help text.
- **Root cause (CSS):** uses gray-* aliases which in dark map to similar values — small adjustment to use cnx tokens for clearer hierarchy.
- **Recommended fix:** optional improvement — use specific cnx tokens:
  ```css
  [data-theme="dark"] .section-description,
  [data-theme="dark"] .toggle-description {
      color: var(--cnx-text-secondary) !important;
  }
  [data-theme="dark"] .form-help {
      color: var(--cnx-text-muted) !important;
  }
  ```

### F10 — `.alert-box.warning` and `.alert-box.info` reference undefined CSS variables
- **Severity:** major
- **Selector / element:** `.alert-box.warning`, `.alert-box.info` (file: `configurazioni.php:256-266`)
- **Screenshot region:** appears in Sicurezza tab ("Attenzione: Modificare queste impostazioni..."), Backup tab ("Ultimo backup completato...") and Visibilità Pagine tab ("Nota: queste impostazioni..."). Not visible on the current Generale screenshot.
- **Current behaviour (dark mode):** rules reference `--color-warning-50`, `--color-warning-200`, `--color-warning-700`, `--color-primary-50`, `--color-primary-200`, `--color-primary-700`. These tokens are **NOT defined** in `:root` (only `--color-warning` is defined at `styles.css:35`) → CSS resolves to invalid value, browser falls back to inherit / default. Effect: alert box has no background, no border, no special text color — the warning text just blends with the surrounding card.
- **Root cause (CSS):** missing token chain. Pre-existing bug independent of dark mode but exposed by it.
- **Recommended fix:** define the missing tokens in `:root` and `[data-theme="dark"]`. Suggested values:
  ```css
  :root {
      /* Warning palette */
      --color-warning-50:  #FEF3C7;
      --color-warning-200: #FDE68A;
      --color-warning-700: #92400E;
      /* Info / primary palette */
      --color-primary-50:  #DBEAFE;
      --color-primary-200: #BFDBFE;
      --color-primary-700: #1D4ED8;
  }
  [data-theme="dark"] {
      --color-warning-50:  rgba(245, 158, 11, 0.12);
      --color-warning-200: rgba(245, 158, 11, 0.32);
      --color-warning-700: #FCD34D;
      --color-primary-50:  rgba(95, 202, 211, 0.12);
      --color-primary-200: rgba(95, 202, 211, 0.32);
      --color-primary-700: var(--cnx-accent);
  }
  ```
  **REQUIRES MARKUP CHANGE — NO**, CSS-only.

### F11 — `.btn--primary` / `.btn--secondary` (BEM) — verify mint coverage
- **Severity:** major
- **Selector / element:** `.btn.btn--primary`, `.btn.btn--secondary` (used by `configurazioni.php` save/cancel buttons: lines 442-443, 510-511, 574-575, 624-625, 832-833 etc.)
- **Screenshot region:** out of view in current screenshot (need to scroll); affects "Salva Modifiche", "Annulla", "Test Connessione", "Download", "Ripristina", etc.
- **Current behaviour (dark mode):** `components.css:206-211` overrides `.btn-primary, .btn.btn-primary` (single dash) with mint. The BEM variant `.btn--primary` (double dash, used here) is **not** matched by that selector. Result: these buttons fall back to the legacy `.btn-primary` CSS rule in `styles.css:1086-1093` which uses `var(--color-primary)` → still legacy blue in dark mode.
- **Root cause (CSS):** override selector doesn't include the BEM variant.
- **Recommended fix:** extend `components.css:206-217`:
  ```css
  .btn-primary,
  .btn.btn-primary,
  .btn--primary,
  .btn.btn--primary {
      background-color: var(--cnx-accent) !important;
      border-color: var(--cnx-accent) !important;
      color: var(--cnx-accent-ink) !important;
  }
  .btn-primary:hover,
  .btn.btn-primary:hover,
  .btn--primary:hover,
  .btn.btn--primary:hover {
      background-color: var(--cnx-accent-hover) !important;
      border-color: var(--cnx-accent-hover) !important;
  }
  ```

### F12 — Color preview swatches in "Aspetto" tab show legacy hex values (`#3b82f6`, `#8b5cf6`)
- **Severity:** minor
- **Selector / element:** inline `style="background: #3b82f6"` (file: `configurazioni.php:748, 755`)
- **Screenshot region:** out-of-view; in "Aspetto" tab, "Colori Tema" section
- **Current behaviour (dark mode):** the two color preview tiles are bright blue and purple — clash with the mint design system.
- **Root cause (CSS):** values are stored in DB and rendered via inline `style="background: …"` — they're literal data, not tokens. The defaults (`#3b82f6` / `#8b5cf6`) are the historical seed values that the user can change.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to update the seed default in DB. Out of CSS scope. Flag for product decision: should the default theme primary be migrated to `#5FCAD3` (mint)? If yes, update the row in `system_settings` via migration. CSS audit cannot fix this.

### F13 — Status indicators (`.status-indicator.active|inactive`) use legacy semantic colors (OK but worth noting)
- **Severity:** minor
- **Selector / element:** `.status-indicator.active`, `.status-indicator.inactive` (file: `configurazioni.php:319-333`)
- **Screenshot region:** out-of-view; in Integrazioni tab — green dots for "Google Calendar", "OnlyOffice"; gray for "Jitsi Meet", "Slack"
- **Current behaviour (dark mode):** `--color-success` `#10B981` not overridden → bright green dot OK on dark; `--color-gray-400` `#4A5560` in dark — a barely visible dim dot for inactive.
- **Root cause (CSS):** inactive indicator uses gray-400 which becomes too dim against card.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .status-indicator.inactive {
      background: var(--cnx-text-muted) !important;
  }
  ```

### F14 — Backup-list row backgrounds (`.backup-item`) too close to card
- **Severity:** minor
- **Selector / element:** `.backup-item` (file: `configurazioni.php:288-296`)
- **Screenshot region:** out-of-view; Backup tab list of recent backups, also re-used in Integrazioni tab
- **Current behaviour (dark mode):** `background: var(--color-gray-50)` → `#1B2F33`, parent card `#15262A`, very subtle separation.
- **Root cause (CSS):** dark gray-50 maps very close to card surface.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .backup-item {
      background: var(--cnx-bg-subtle) !important;
      border: 1px solid var(--cnx-border) !important;
  }
  ```

### F15 — `.color-preview` border too dark
- **Severity:** minor
- **Selector / element:** `.color-preview` (file: `configurazioni.php:280-286`)
- **Screenshot region:** out-of-view; "Colori Tema" section
- **Current behaviour (dark mode):** `border: 2px solid var(--color-gray-300)` → `#2E464C` — practically merges with the dark page bg around the swatches.
- **Root cause (CSS):** dark gray-300 too dim.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .color-preview {
      border-color: var(--cnx-border-strong) !important;
  }
  ```

### F16 — Header user-name "Antonio Amodeo" muted text low contrast
- **Severity:** minor
- **Selector / element:** `.text-muted` inside `.header` (file: `configurazioni.php:354`)
- **Screenshot region:** top-right of content area, "Antonio Amodeo" label
- **Current behaviour (dark mode):** `color: var(--color-gray-500)` `#8893A0` — readable but a bit dim against the dark teal topbar (`--cnx-bg-sidebar` `#0A1517`).
- **Root cause (CSS):** legacy utility class `.text-muted` uses gray-500 alias.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .header .text-muted {
      color: var(--cnx-text-on-sidebar-muted) !important;
  }
  ```
