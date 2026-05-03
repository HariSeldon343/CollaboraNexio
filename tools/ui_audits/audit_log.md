# Dark mode audit — audit_log.php

## Summary
- Total findings: 18
- Severity breakdown: critical=5, major=8, minor=5

The page uses an inline `<style>` block (lines 59–1106 of `audit_log.php`) whose class names (`.filters-container`, `.table-container`, `.audit-table`, `.btn-secondary`, `.btn-icon`, `.modal-content`, `.json-view`, `.audit-section`, `.export-dropdown`, `.export-option`, `.audit-detail-modal`, `.pagination-container`, `.pagination-btn`, `.skeleton`, `.ip-address`, `.empty-icon`, `.audit-warning`, etc.) all hardcode `background: white`, `background: #F9FAFB`, `color: #1F2937`, `color: #6B7280`, etc. and **do NOT match** the dark fallback selectors that live in `assets/css/components.css:238-273` (those target `.filters-card` and `.audit-table-container`, the wrong names). The result is two giant white panels (Filtri + Log di Audit) sitting on a dark page, plus invisible white-on-white buttons.

The screenshot also shows the topbar (search row) is **darker teal than the page bg**, which suggests the topbar uses `--cnx-bg-sidebar` (`#0A1517` dark) rather than `--cnx-bg-app` (`#0E1A1D`) — visually OK but worth noting in F18.

## Findings

### F1 — `.filters-container` is pure white in dark mode
- **Severity:** critical
- **Selector / element:** `.filters-container` (file: `audit_log.php:168`)
- **Screenshot region:** the entire "Filtri" card (mid-page, contains date pickers + selects + Reset/Applica Filtri)
- **Current behaviour (dark mode):** card renders fully white (`#FFFFFF`) instead of dark surface; light-grey label text (`#4B5563`) is barely readable on white; "Reset" button white-on-white is invisible.
- **Root cause (CSS):** `audit_log.php:170` declares `background: white;` literal; the dark override in `components.css:238` targets `.filters-card` (wrong class name), so this rule has no dark-mode counterpart.
- **Recommended fix:** add `.filters-container` to the dark fallback list in `components.css:238`:
  ```css
  [data-theme="dark"] .stat-card,
  [data-theme="dark"] .filters-container,           /* NEW */
  [data-theme="dark"] .table-container,             /* NEW (see F2) */
  [data-theme="dark"] .audit-section,               /* NEW (see F11) */
  [data-theme="dark"] .modal-content,
  ...
  {
      background: var(--cnx-bg-surface) !important;
      color: var(--cnx-text-primary) !important;
      border-color: var(--cnx-border) !important;
  }
  ```

### F2 — `.table-container` ("Log di Audit" panel) is pure white in dark mode
- **Severity:** critical
- **Selector / element:** `.table-container` (file: `audit_log.php:236`)
- **Screenshot region:** lower card "Log di Audit" with the Esporta button + table head row
- **Current behaviour (dark mode):** white card on dark background. Table header strip ("DATA/ORA / UTENTE / AZIONE...") is visible because `--color-gray-50` is overridden, but the surrounding container stays white, producing a glaring white slab.
- **Root cause (CSS):** `audit_log.php:238` `background: white;` literal, no dark override matches.
- **Recommended fix:** include `.table-container` in the dark fallback selector list (see F1). Also override the inner `.table-header` and `.pagination-container` since they hardcode `background: #F9FAFB`:
  ```css
  [data-theme="dark"] .table-container,
  [data-theme="dark"] .table-header,
  [data-theme="dark"] .pagination-container {
      background: var(--cnx-bg-surface) !important;
      border-color: var(--cnx-border) !important;
  }
  ```

### F3 — "Reset" secondary button invisible (white-on-white)
- **Severity:** critical
- **Selector / element:** `.btn-secondary` inside `.filters-actions` (file: `audit_log.php:391-399, 1221-1226`)
- **Screenshot region:** lower-right corner of the Filtri card, left of "Applica Filtri"
- **Current behaviour (dark mode):** background `white`, color `#4B5563`, border `#D1D5DB` — completely indistinguishable from the white card behind it; only the icon stroke gives a faint outline.
- **Root cause (CSS):** `audit_log.php:390-399` hardcodes `background: white; color: #4B5563; border: 1px solid #D1D5DB`.
- **Recommended fix:** add a dark fallback that flips secondary buttons inside the audit page:
  ```css
  [data-theme="dark"] .audit-container .btn-secondary,
  [data-theme="dark"] .audit-container .btn-icon,
  [data-theme="dark"] .audit-container .pagination-btn {
      background: transparent !important;
      color: var(--cnx-text-primary) !important;
      border-color: var(--cnx-border-strong) !important;
  }
  [data-theme="dark"] .audit-container .btn-secondary:hover,
  [data-theme="dark"] .audit-container .btn-icon:hover,
  [data-theme="dark"] .audit-container .pagination-btn:hover:not(:disabled) {
      background: var(--cnx-bg-subtle) !important;
      border-color: var(--cnx-accent) !important;
  }
  ```

### F4 — "Esporta" button invisible (white-on-white inside table header)
- **Severity:** critical
- **Selector / element:** `.export-menu .btn-secondary` (file: `audit_log.php:1242`)
- **Screenshot region:** top-right of the "Log di Audit" card
- **Current behaviour (dark mode):** white button on white card; only the icon and the "Esporta" text barely visible because the text color is `#4B5563` on `#FFFFFF`.
- **Root cause (CSS):** same `.btn-secondary` rule + `.table-header` `background: #F9FAFB` (which IS overridden, so the strip becomes dark) — but `.btn-secondary` background stays white because hardcoded.
- **Recommended fix:** the dark override from F3 also fixes this. No further action required once F3 is applied.

### F5 — "Applica Filtri" primary button OK but loses focus ring style on dark
- **Severity:** minor
- **Selector / element:** `.btn-primary` (file: `audit_log.php:380-388`)
- **Screenshot region:** lower-right of Filtri card
- **Current behaviour (dark mode):** background renders mint via `components.css:206-211` (`!important` override) — visually correct. **However** focus state still shows `box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25)` (legacy blue glow) which clashes with the new mint accent.
- **Root cause (CSS):** `audit_log.php:387` `box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25)` hardcoded blue glow.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .audit-container .btn-primary:hover,
  .audit-container .btn-primary:hover {
      box-shadow: 0 4px 12px rgba(95, 202, 211, 0.35) !important; /* mint glow */
  }
  ```

### F6 — Date input borders too pale in dark mode
- **Severity:** major
- **Selector / element:** `.filter-input, .filter-select` (file: `audit_log.php:211-219`)
- **Screenshot region:** "gg / mm / aaaa" date placeholders inside Filtri card
- **Current behaviour (dark mode):** inputs flip to dark via the catch-all in `components.css:262-273` (good). However the inline rule still applies `border: 1px solid #D1D5DB` which is overridden in dark by `--cnx-border` — once F1 lands and the parent card is dark, the inputs need slightly stronger borders to be visible.
- **Root cause (CSS):** `audit_log.php:213-218` hardcodes `border: 1px solid #D1D5DB; background: white;` — the dark fallback already exists in `components.css:262-273` but uses `--cnx-border` (`#22363B`), which against `--cnx-bg-subtle` (`#1B2F33`) is barely visible.
- **Recommended fix:** bump dark border for inputs *inside the audit page only*:
  ```css
  [data-theme="dark"] .audit-container .filter-input,
  [data-theme="dark"] .audit-container .filter-select {
      border-color: var(--cnx-border-strong) !important;
  }
  ```

### F7 — Filter labels ("Data Dal", "Data Al", "Utente"...) very low contrast on dark
- **Severity:** major
- **Selector / element:** `.filter-label` (file: `audit_log.php:205-209`)
- **Screenshot region:** above each input in the Filtri card (currently visible because the card behind is still white in screenshot)
- **Current behaviour (dark mode):** rule is `color: #4B5563`. Once F1 lands and the card flips dark, this gray on dark teal will fail WCAG AA.
- **Root cause (CSS):** `audit_log.php:208` `color: #4B5563` literal.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .audit-container .filter-label,
  [data-theme="dark"] .audit-container .filters-title,
  [data-theme="dark"] .audit-container .table-title {
      color: var(--cnx-text-secondary) !important;
  }
  ```

### F8 — "Filtri" title icon and text mid-grey on white (will be wrong color when card flips)
- **Severity:** major
- **Selector / element:** `.filters-title` (file: `audit_log.php:183-190`)
- **Screenshot region:** top-left of Filtri card, "Filtri" with funnel icon
- **Current behaviour (dark mode):** color `#1F2937` literal — currently OK on white card but once F1 lands the card becomes dark and this dark text disappears.
- **Root cause (CSS):** `audit_log.php:186` `color: #1F2937` literal.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .audit-container .filters-title,
  [data-theme="dark"] .audit-container .table-title {
      color: var(--cnx-text-primary) !important;
  }
  ```

### F9 — "Log di Audit" title text becomes invisible after card flip
- **Severity:** major
- **Selector / element:** `.table-title` (file: `audit_log.php:252-256`)
- **Screenshot region:** top-left of "Log di Audit" card
- **Current behaviour (dark mode):** color `#1F2937` on white now (visible). After F1+F2 fix, title becomes black-on-dark and disappears.
- **Root cause (CSS):** `audit_log.php:255` `color: #1F2937;` literal.
- **Recommended fix:** see F8 fix snippet (single combined selector).

### F10 — Pagination strip (`.pagination-container`) hardcodes light gray bg
- **Severity:** major
- **Selector / element:** `.pagination-container` (file: `audit_log.php:455-463`)
- **Screenshot region:** off-screen below table (visible when results exist)
- **Current behaviour (dark mode):** `background: #F9FAFB` flips dark via gray-50 token override (`#1B2F33`), but `border-top: 1px solid #E5E7EB` does not. Light border line on dark bg is jarring.
- **Root cause (CSS):** `audit_log.php:461` `border-top: 1px solid #E5E7EB`.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .audit-container .pagination-container {
      border-top-color: var(--cnx-border) !important;
  }
  [data-theme="dark"] .audit-container .pagination-info {
      color: var(--cnx-text-muted) !important;
  }
  ```

### F11 — Modal "Dettagli Audit Log" body white in dark mode
- **Severity:** critical
- **Selector / element:** `.modal-content`, `.audit-section`, `.json-view` (file: `audit_log.php:520-531, 599-604, 758-769`)
- **Screenshot region:** N/A in current screenshot (modal closed) but reproducible by clicking any "Dettagli" button.
- **Current behaviour (dark mode):** `.modal-content` IS covered by `components.css:241` (good) so it flips dark; however nested `.audit-section` (`background: #FFFFFF`), `.json-view` (`background: #F9FAFB`), `.audit-warning` (`background: #FEF3C7`) and `.audit-pill` (`background: #F9FAFB`) do not, producing a stack of white inner cards inside a dark modal.
- **Root cause (CSS):** literal hex backgrounds in inline `<style>` block.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .audit-section {
      background: var(--cnx-bg-subtle) !important;
      border-color: var(--cnx-border) !important;
  }
  [data-theme="dark"] .audit-section-title,
  [data-theme="dark"] .audit-detail-row strong,
  [data-theme="dark"] .audit-kv dd,
  [data-theme="dark"] .audit-detail-row { color: var(--cnx-text-primary) !important; }
  [data-theme="dark"] .audit-kv dt,
  [data-theme="dark"] .audit-subtitle { color: var(--cnx-text-muted) !important; }
  [data-theme="dark"] .audit-pill,
  [data-theme="dark"] .audit-toggle {
      background: var(--cnx-bg-subtle) !important;
      color: var(--cnx-text-primary) !important;
      border-color: var(--cnx-border) !important;
  }
  [data-theme="dark"] .json-view {
      background: #0B1417 !important;             /* propose new token --cnx-bg-code */
      color: var(--cnx-text-primary) !important;
      border-color: var(--cnx-border) !important;
  }
  [data-theme="dark"] .audit-warning {
      background: rgba(245, 158, 11, 0.12) !important;
      color: #FCD34D !important;
  }
  ```
  Suggested NEW token in `:root` and `[data-theme="dark"]`:
  - `--cnx-bg-code: #F9FAFB` (light), `--cnx-bg-code: #0B1417` (dark) — for `<pre>`/JSON viewers.

### F12 — Severity badges (.severity-badge.info / .warning / .error) bright pastel on dark
- **Severity:** minor
- **Selector / element:** `.severity-badge.info|.warning|.error|.critical` (file: `audit_log.php:346-364`)
- **Screenshot region:** "Severità" column in the table (not visible in current screenshot but exists when log rows render)
- **Current behaviour (dark mode):** badges keep light pastel backgrounds (`#E0F2FE`, `#FEF3C7`, `#FEE2E2`) which look like pure white blobs on a dark table. `.severity-badge.critical` red is fine.
- **Root cause (CSS):** literal hex pastel colors in inline `<style>`.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .severity-badge.info {
      background: rgba(95, 202, 211, 0.18) !important;
      color: #5FCAD3 !important;
  }
  [data-theme="dark"] .severity-badge.warning {
      background: rgba(245, 158, 11, 0.18) !important;
      color: #FCD34D !important;
  }
  [data-theme="dark"] .severity-badge.error {
      background: rgba(239, 68, 68, 0.18) !important;
      color: #FCA5A5 !important;
  }
  /* .severity-badge.critical (solid red) is fine as-is */
  ```

### F13 — Action badges (`.action-badge.create|update|delete|login|access`) light pastel on dark
- **Severity:** minor
- **Selector / element:** `.action-badge.*` (file: `audit_log.php:311-334`)
- **Screenshot region:** "Azione" column in the table (not visible in current empty-state screenshot)
- **Current behaviour (dark mode):** light blue/yellow/red/violet pastels on dark rows look harsh and white-ish.
- **Root cause (CSS):** literal pastel hex.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .action-badge.create {
      background: rgba(59, 130, 246, 0.18) !important;
      color: #93C5FD !important;
  }
  [data-theme="dark"] .action-badge.update {
      background: rgba(245, 158, 11, 0.18) !important;
      color: #FCD34D !important;
  }
  [data-theme="dark"] .action-badge.delete {
      background: rgba(239, 68, 68, 0.18) !important;
      color: #FCA5A5 !important;
  }
  [data-theme="dark"] .action-badge.login {
      background: rgba(139, 92, 246, 0.18) !important;
      color: #C4B5FD !important;
  }
  [data-theme="dark"] .action-badge.access {
      background: rgba(99, 102, 241, 0.18) !important;
      color: #A5B4FC !important;
  }
  ```

### F14 — `.ip-address` chip bg `#F3F4F6` flips dark via gray-100 override but border missing
- **Severity:** minor
- **Selector / element:** `.ip-address` (file: `audit_log.php:813-821`)
- **Screenshot region:** "IP Address" column (not visible in current screenshot — empty table)
- **Current behaviour (dark mode):** `background: #F3F4F6` literal — NOT covered by gray-100 token alias, stays light gray on dark rows.
- **Root cause (CSS):** literal hex (not via `var(--color-gray-100)`).
- **Recommended fix:**
  ```css
  [data-theme="dark"] .ip-address {
      background: var(--cnx-bg-subtle) !important;
      color: var(--cnx-text-muted) !important;
  }
  ```

### F15 — `.empty-icon` circle stays light grey #F3F4F6 in dark mode
- **Severity:** minor
- **Selector / element:** `.empty-icon` (file: `audit_log.php:431-441`)
- **Screenshot region:** shown only when zero log results (not in current screenshot)
- **Current behaviour (dark mode):** literal `#F3F4F6` background; once container goes dark this circle becomes a bright light-grey orb.
- **Root cause (CSS):** literal hex.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .empty-icon {
      background: var(--cnx-bg-subtle) !important;
      color: var(--cnx-text-muted) !important;
  }
  ```

### F16 — Skeleton loader gradient too bright in dark mode
- **Severity:** minor
- **Selector / element:** `.skeleton` (file: `audit_log.php:772-777`)
- **Screenshot region:** appears under the 5 stat-cards if data still loading (not visible in screenshot — already loaded)
- **Current behaviour (dark mode):** `linear-gradient(90deg, #F3F4F6 25%, #E5E7EB 50%, #F3F4F6 75%)` produces bright pulsing strips against dark surface.
- **Root cause (CSS):** literal hex stops.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .skeleton {
      background: linear-gradient(90deg, var(--cnx-bg-subtle) 25%, var(--cnx-border) 50%, var(--cnx-bg-subtle) 75%) !important;
      background-size: 200% 100% !important;
  }
  ```

### F17 — Export dropdown menu white in dark mode
- **Severity:** major
- **Selector / element:** `.export-dropdown`, `.export-option` (file: `audit_log.php:1066-1097`)
- **Screenshot region:** appears when clicking "Esporta" (not visible in screenshot but reproducible)
- **Current behaviour (dark mode):** `background: white; border: 1px solid #E5E7EB; box-shadow: 0 4px 12px rgba(0,0,0,0.1);` — pops as a fully white menu on dark.
- **Root cause (CSS):** literal `background: white` and hex border.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .export-dropdown {
      background: var(--cnx-bg-surface) !important;
      border-color: var(--cnx-border) !important;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45) !important;
  }
  [data-theme="dark"] .export-option {
      color: var(--cnx-text-primary) !important;
  }
  [data-theme="dark"] .export-option:hover {
      background: var(--cnx-bg-subtle) !important;
  }
  ```

### F18 — Topbar (search bar row) appears darker than page background — looks like a "shelf"
- **Severity:** minor
- **Selector / element:** `.cnx-app-topbar` (background pulled from `--cnx-bg-sidebar` `#0A1517` while page uses `--cnx-bg-app` `#0E1A1D`)
- **Screenshot region:** top horizontal strip with the "Cerca file, persone, ticket..." search field
- **Current behaviour (dark mode):** topbar visibly darker than the page bg by ~3 lightness steps; reads as a separate panel rather than a unified shell. Acceptable per direction.md but worth noting; the same effect on light mode is intentional. In dark, the contrast is large enough to feel like a step.
- **Root cause (CSS):** topbar uses `--cnx-bg-sidebar` (which is also overridden in dark to `#0A1517`) while page uses `--cnx-bg-app`.
- **Recommended fix (optional — design call):** in dark mode unify topbar with app bg:
  ```css
  [data-theme="dark"] .cnx-app-topbar {
      background: var(--cnx-bg-app) !important;
      border-bottom: 1px solid var(--cnx-border) !important;
  }
  ```
  Flag as design decision — leaving as-is is also defensible. Do not apply if `cnx-ui-shell-engineer` already declared the topbar uses sidebar bg by spec.
