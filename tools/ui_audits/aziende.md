# Dark mode audit — aziende.php

## Summary
- Total findings: 18
- Severity breakdown: critical=4, major=8, minor=6

Visual reference: `tools/ui_redesign/dark_round3/aziende.png`
Token reference: `assets/css/styles.css` `:root` (light) and `[data-theme="dark"]` (lines 220-250).

---

## Findings

### F1 — Search input "Cerca aziende..." has no visible border in dark mode
- **Severity:** critical
- **Selector / element:** `.search-bar input` (file: `aziende.php:189-196`)
- **Screenshot region:** top of the page-content, search box on the left of the toolbar row
- **Current behaviour (dark mode):** The input is a flat dark rectangle with no perceptible edge. The hardcoded `border: 1px solid var(--color-gray-300)` resolves to `#2E464C` in dark, and the global override in `components.css:262-273` forces `background: var(--cnx-bg-subtle) !important; border-color: var(--cnx-border) !important;` => bg `#1B2F33`, border `#22363B`. The 3-point delta between bg and border is invisible.
- **Root cause (CSS):** Inline `aziende.php:192` declares the legacy `--color-gray-300` border, then `components.css:262-273` slams `border-color: var(--cnx-border) !important` (#22363B). Neither value contrasts against `--cnx-bg-subtle` (#1B2F33).
- **Recommended fix:** In `components.css` extend the dark-mode input override to use `--cnx-border-strong` and add a soft inner shadow:
  ```css
  [data-theme="dark"] .search-bar input,
  [data-theme="dark"] input[type="text"],
  [data-theme="dark"] input[type="email"],
  [data-theme="dark"] input[type="search"] {
      background: var(--cnx-bg-surface) !important;   /* #15262A — sits on top of #0E1A1D page bg */
      border-color: var(--cnx-border-strong) !important; /* #2E464C contrasts vs #15262A */
      color: var(--cnx-text-primary) !important;
  }
  [data-theme="dark"] .search-bar input::placeholder {
      color: var(--cnx-text-muted) !important;
  }
  ```

### F2 — Search-icon emoji renders as full-color emoji (orange/red) on a teal UI
- **Severity:** major
- **Selector / element:** `.search-icon` (file: `aziende.php:204-211`, emoji `🔍` at `aziende.php:743`)
- **Screenshot region:** right edge of the "Cerca aziende..." input
- **Current behaviour (dark mode):** The unicode magnifier emoji renders with its OS-supplied color palette (red handle, blue glass) and visually pops against the muted teal palette. The `color: var(--color-gray-400)` rule has no effect because emoji are colored glyphs, not monochrome text.
- **Root cause (CSS):** Emoji glyphs ignore the CSS `color` property. The `<span class="search-icon">🔍</span>` markup forces the OS emoji set.
- **Recommended fix:** Hide the emoji span in dark (and light) and rely on the SVG mask icon already used by `.cnx-input-group__icon` (see `components.css:501-518`). CSS-only solution:
  ```css
  .search-icon {
      width: 16px;
      height: 16px;
      font-size: 0;                                          /* swallow the emoji glyph */
      background-color: var(--cnx-text-muted);
      mask-size: contain; mask-repeat: no-repeat; mask-position: center;
      -webkit-mask-size: contain; -webkit-mask-repeat: no-repeat; -webkit-mask-position: center;
      mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
      -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
  }
  ```
  This nukes the emoji visually while keeping markup intact.

### F3 — Action-button row icons render as full-color emoji (pencil orange, users purple, trash silver)
- **Severity:** critical
- **Selector / element:** `.btn-icon` with emoji content `✏️ 👥 🗑️` (file: `aziende.php:1856-1914`, base style `aziende.php:354-377`)
- **Screenshot region:** "Azioni" column, every row of the table
- **Current behaviour (dark mode):** Each row shows three colored emoji that clash with the mint/teal palette: pencil is orange-yellow, users is purple, trash is gray. The `color: var(--color-primary)` / `color: var(--color-error)` overrides on `.btn-icon.edit` / `.btn-icon.delete` are ignored by emoji glyphs. Result is "neon-bright" and visually noisy.
- **Root cause (CSS):** Emoji used as icon content; CSS `color` cannot recolor emoji. Buttons inherit transparent bg + `border: 1px solid var(--color-gray-300)` (=> `#2E464C` in dark) which is barely visible — making each emoji float without container affordance.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to fully fix (replace emoji with SVG/icon font). CSS-only mitigation: tint the button frame so the emoji reads as a "pill" and harmonizes:
  ```css
  [data-theme="dark"] .btn-icon {
      background: var(--cnx-bg-surface);
      border-color: var(--cnx-border-strong);
      filter: grayscale(0.85) brightness(1.1);   /* desaturate the emoji */
  }
  [data-theme="dark"] .btn-icon:hover {
      background: var(--cnx-accent-soft);
      border-color: var(--cnx-accent);
      filter: none;                               /* full color on hover for affordance */
  }
  [data-theme="dark"] .btn-icon.edit { color: var(--cnx-accent); }
  [data-theme="dark"] .btn-icon.delete { color: var(--cnx-danger); }
  ```
  The `filter: grayscale(0.85)` is the key trick: it reduces emoji saturation drastically without altering markup. Long-term: replace emoji with SVG masks (see F2 pattern) — flag for markup PR.

### F4 — "Elimina selezionate" button has no danger tint and no defined idle/hover state
- **Severity:** major
- **Selector / element:** `.btn.btn-danger#bulkDeleteBtn` (file: `aziende.php:746-749`)
- **Screenshot region:** toolbar, between search bar and "+ Nuova Azienda"
- **Current behaviour (dark mode):** The button shows only the disabled state (opacity 0.5) — text "Elimina selezionate" with the trash emoji renders as low-contrast gray-on-dark. There is **no global `.btn-danger` rule in `assets/css/styles.css` or `components.css`** (a stray rule exists in `shifts.css:1383` and `workflow.css:530` but those are page-scoped). When the button becomes enabled (after row-selection), it will fall back to bare `.btn` (transparent bg, no color) and look identical to a ghost button — a destructive CTA that doesn't read as destructive.
- **Root cause (CSS):** Missing `.btn-danger` definition in the global stylesheet. Page-scoped definitions in `shifts.css` / `workflow.css` don't apply to `aziende.php`.
- **Recommended fix:** Add a global `.btn-danger` rule to `components.css` (after line 230, near the `.btn-primary` mint override):
  ```css
  .btn-danger,
  .btn.btn-danger {
      background-color: var(--cnx-danger);
      border-color: var(--cnx-danger);
      color: #FFFFFF;
  }
  .btn-danger:hover:not(:disabled),
  .btn.btn-danger:hover:not(:disabled) {
      background-color: #DC2626;
      border-color: #DC2626;
  }
  [data-theme="dark"] .btn-danger:disabled,
  [data-theme="dark"] .btn.btn-danger:disabled {
      background-color: var(--cnx-bg-subtle);
      border-color: var(--cnx-border-strong);
      color: var(--cnx-text-muted);
      opacity: 0.7;                              /* override the global 0.5 to keep label legible */
  }
  ```

### F5 — Page subtitle "Gestisci aziende e piani" too dim
- **Severity:** minor
- **Selector / element:** `.text-sm.text-muted` (file: `aziende.php:733`, rule: `assets/css/styles.css:1443`)
- **Screenshot region:** top-right of the page header, opposite "Gestione Aziende"
- **Current behaviour (dark mode):** Uses `.text-muted { color: var(--color-gray-500); }`. In dark mode `--color-gray-500` is overridden to `#8893A0` — middle gray on `#0E1A1D` page bg gives a contrast ratio of ~4.5:1 which *just* passes AA but feels washed out and visually competes with the page title.
- **Root cause (CSS):** `assets/css/styles.css:1443` resolves `.text-muted` to `--color-gray-500` (=> `#8893A0` in dark theme).
- **Recommended fix:** In dark mode promote `.text-muted` to `--cnx-text-secondary` for better legibility:
  ```css
  [data-theme="dark"] .text-muted { color: var(--cnx-text-secondary); }   /* #A8B5BB */
  ```
  (Light mode keeps the legacy gray.)

### F6 — Table header bar invisible (no contrast vs page bg)
- **Severity:** major
- **Selector / element:** `.companies-table th` (file: `aziende.php:226-236`)
- **Screenshot region:** entire "ID / DENOMINAZIONE / CODICE FISCALE / ..." header row
- **Current behaviour (dark mode):** `background: var(--color-gray-50)` => in dark theme `#1B2F33`, sitting inside `.companies-table` which has `background: var(--color-white)` => `#15262A`. Header is *darker* than the body; reads as a sunken bar instead of a top-of-table affordance. Border-bottom `var(--color-gray-200)` => `#22363B` is also barely visible.
- **Root cause (CSS):** Color tokens flipped one-for-one in dark mode without re-checking the relative ordering: gray-50 (lighter than gray-100 in light) becomes darker than white in dark.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .companies-table th {
      background: var(--cnx-bg-subtle);                  /* #1B2F33 — distinct from card */
      color: var(--cnx-text-secondary);                  /* #A8B5BB */
      border-bottom: 1px solid var(--cnx-border-strong); /* #2E464C */
  }
  [data-theme="dark"] .companies-table {
      background: var(--cnx-bg-surface);                 /* #15262A confirmed */
      border: 1px solid var(--cnx-border);
  }
  ```
  Or, more cleanly, keep table bg = surface and header bg = bg-app to invert the relation (page bg `#0E1A1D` < surface `#15262A`).

### F7 — Table row hover bg is darker than the row itself
- **Severity:** minor
- **Selector / element:** `.companies-table tbody tr:hover` (file: `aziende.php:247-249`)
- **Screenshot region:** every row on hover (not visible in static screenshot but verifiable in browser)
- **Current behaviour (dark mode):** `background: var(--color-gray-50)` => `#1B2F33`. Body cards bg is `var(--color-white)` => `#15262A`. Hover state is *darker* (counter-intuitive — hovers should be lighter or accent-tinted in dark mode).
- **Root cause (CSS):** Same token-flip issue as F6.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .companies-table tbody tr:hover {
      background: var(--cnx-accent-soft);   /* rgba(95,202,211,0.12) — readable hover */
  }
  ```

### F8 — Status-badge "Attivo" pill uses light pastel that breaks dark theme
- **Severity:** major
- **Selector / element:** `.status-badge.active` (file: `aziende.php:327-330`)
- **Screenshot region:** "STATO" column, every row
- **Current behaviour (dark mode):** `background: #D1FAE5; color: #065F46;` — bright mint pastel pill with dark green text. Hex literals don't react to `[data-theme="dark"]`. The pill is **lighter than the page** — looks like a sticker pasted on dark paper; not part of the design language.
- **Root cause (CSS):** Hardcoded hex literals at `aziende.php:328-329` bypass the `--cnx-*` token system.
- **Recommended fix:** Replace literals with tokens and add a dark-aware variant. Add new tokens to `assets/css/styles.css` `:root` and `[data-theme="dark"]`:
  ```css
  /* :root (light) */
  --cnx-success-bg: #D1FAE5;
  --cnx-success-fg: #065F46;
  /* [data-theme="dark"] */
  --cnx-success-bg: rgba(34, 197, 94, 0.18);
  --cnx-success-fg: #6BE5A0;
  ```
  Then in `aziende.php` (or override in `components.css`):
  ```css
  .status-badge.active {
      background: var(--cnx-success-bg);
      color: var(--cnx-success-fg);
  }
  ```

### F9 — Status-badge "suspended" / "pending" use light pastel literals (same issue as F8)
- **Severity:** minor
- **Selector / element:** `.status-badge.suspended`, `.status-badge.pending` (file: `aziende.php:332-340`)
- **Screenshot region:** not visible in current screenshot (all rows are "Attivo") but will surface as soon as a tenant is suspended
- **Current behaviour (dark mode):** Same problem as F8 — `#FEE2E2/#991B1B` and `#FEF3C7/#92400E` are bright pastels that don't dim for dark.
- **Root cause (CSS):** Hex literals in `aziende.php:332-340`.
- **Recommended fix:** Mirror the F8 token approach with `--cnx-danger-bg/-fg` and `--cnx-warning-bg/-fg` tokens, then reference them in the `.status-badge.suspended/.pending` rules.

### F10 — Plan badges (.enterprise / .professional / .starter / .trial) are light pastels
- **Severity:** minor
- **Selector / element:** `.plan-badge.enterprise/.professional/.starter/.trial` (file: `aziende.php:297-315`)
- **Screenshot region:** not visible in current screenshot (no plan column rendered) but defined and would surface if shown
- **Current behaviour (dark mode):** Same hex-literal problem as F8/F9; the four plans render as bright pastel pills incompatible with dark UI.
- **Root cause (CSS):** Hardcoded hex at `aziende.php:298-314`.
- **Recommended fix:** Replace each pair with a `--cnx-badge-*` token pair that flips for dark. E.g.
  ```css
  .plan-badge.enterprise { background: var(--cnx-badge-amber-bg); color: var(--cnx-badge-amber-fg); }
  /* :root: --cnx-badge-amber-bg:#FEF3C7; --cnx-badge-amber-fg:#92400E; */
  /* [data-theme="dark"]: --cnx-badge-amber-bg:rgba(245,158,11,.16); --cnx-badge-amber-fg:#FBBF24; */
  ```

### F11 — Company avatar circle is hardcoded blue, clashes with mint accent
- **Severity:** major
- **Selector / element:** `.company-avatar-table` (file: `aziende.php:257-269`)
- **Screenshot region:** "DENOMINAZIONE" column avatar (e.g., "AG", "AC", "AP", "AD"...)
- **Current behaviour (dark mode):** `background: var(--color-primary)` => the *legacy* blue `#2563EB`. The whole new design system uses mint `#5FCAD3` as the single accent. Blue avatars stand out as visual debt.
- **Root cause (CSS):** `--color-primary` is the pre-redesign blue token, never re-pointed to mint.
- **Recommended fix:** In `components.css` (after the dark-mode override block) add:
  ```css
  .company-avatar-table {
      background: var(--cnx-accent);
      color: var(--cnx-accent-ink);
  }
  ```
  (Same change should apply to `.user-avatar-table` — see utenti.md F11.)

### F12 — Topbar appears as a teal-tinted band that doesn't fully match app bg
- **Severity:** minor
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-33`)
- **Screenshot region:** very top, full width, hosts global "Cerca file, persone, ticket..." search and theme toggle
- **Current behaviour (dark mode):** `background: var(--cnx-accent-soft)` => `rgba(95,202,211,0.12)` over `--cnx-bg-app` `#0E1A1D` produces a faintly teal band, distinct from the page bg. Per the reference (very dark teal/charcoal for everything in the content area), the topbar should sit visually *flush* with the app bg or use `--cnx-bg-surface` for a card-like float.
- **Root cause (CSS):** `--cnx-accent-soft` token is used, which is intentional but produces a tinted band in dark.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .cnx-app-topbar {
      background: var(--cnx-bg-app);                /* flush with page */
      border-bottom: 1px solid var(--cnx-border);
      backdrop-filter: none;                        /* dark mode doesn't need the blur */
      -webkit-backdrop-filter: none;
  }
  ```

### F13 — Global search ("Cerca file, persone, ticket...") inside topbar uses surface bg in dark — looks like a light island
- **Severity:** major
- **Selector / element:** `.cnx-app-topbar__search .cnx-input` (file: `components.css:173-180`)
- **Screenshot region:** centered pill in the global topbar
- **Current behaviour (dark mode):** `background: var(--cnx-bg-surface)` => `#15262A` is *lighter* than the page bg. The pill ends up looking like a bright pill on a dark band. Combined with F12, the global search pops harder than the page title.
- **Root cause (CSS):** Same token (`--cnx-bg-surface`) used for both light (white pill on gray app) and dark (gray pill on darker app), without inverting.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .cnx-app-topbar__search .cnx-input {
      background: var(--cnx-bg-subtle);             /* #1B2F33 — slight separation */
      border: 1px solid var(--cnx-border);
      box-shadow: none;
  }
  [data-theme="dark"] .cnx-app-topbar__search .cnx-input:focus {
      background: var(--cnx-bg-surface);
      border-color: var(--cnx-accent);
  }
  ```

### F14 — `.companies-table` outer card has no visible border in dark
- **Severity:** minor
- **Selector / element:** `.companies-table` (file: `aziende.php:213-219`)
- **Screenshot region:** entire table outer edge
- **Current behaviour (dark mode):** `background: var(--color-white)` => `#15262A`, but no border. `box-shadow: var(--shadow-sm)` (= `0 1px 3px rgba(0,0,0,0.08)...`) is invisible against `#0E1A1D` bg. The table card has no perceptible edge.
- **Root cause (CSS):** Box-shadow values calibrated for light backgrounds (low-alpha black on white), invisible on dark.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .companies-table {
      border: 1px solid var(--cnx-border);
      box-shadow: none;
  }
  ```

### F15 — Modal `.modal-content` keeps light card on dark backdrop (legacy white bg)
- **Severity:** critical
- **Selector / element:** `.modal-content` (file: `aziende.php:404-414`)
- **Screenshot region:** not visible in static screenshot but every modal in the page (Edit, Delete, Roles, Permessi, etc.)
- **Current behaviour (dark mode):** `background: var(--color-white)` => after the dark theme override that's `#15262A` (correct bg). But several inner rules still reference `--color-gray-50/100/200/300` for borders, hover, scrollbars and they appear as too-similar steps of teal-gray. The form-section divider `border-top: 1px solid var(--color-gray-200)` (=> `#22363B`) is barely visible. Modal title `color: var(--color-gray-900)` (=> `#E6EEF0`) is fine.
- **Root cause (CSS):** Modal uses many `--color-gray-*` tokens (line 437, 444, 458, 474, 481, etc.). The dark remap is technically correct but border-200 is too close to the modal bg.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .modal-content { border: 1px solid var(--cnx-border); }
  [data-theme="dark"] .form-section  { border-top-color: var(--cnx-border-strong); }
  [data-theme="dark"] .form-group input,
  [data-theme="dark"] .form-group select,
  [data-theme="dark"] .form-group textarea {
      background: var(--cnx-bg-subtle);
      border-color: var(--cnx-border-strong);
      color: var(--cnx-text-primary);
  }
  ```

### F16 — `.sede-card` (Sedi Operative) uses hardcoded light gray bg
- **Severity:** minor
- **Selector / element:** `.sede-card` (file: `aziende.php:94-101`)
- **Screenshot region:** not visible in main screenshot (only inside the Edit modal) — affects the sub-section listing operational locations
- **Current behaviour (dark mode):** `background: #f8f9fa; border: 1px solid #dee2e6;` — both literal hex values that **do not flip** for dark theme. In dark mode the card stays bright white. Same issue for `.btn-remove { background: #dc3545; ... }` red-pill (line 117-129).
- **Root cause (CSS):** Hardcoded literals at `aziende.php:95-97` and `aziende.php:117-128`.
- **Recommended fix:**
  ```css
  .sede-card {
      background: var(--cnx-bg-subtle);
      border: 1px solid var(--cnx-border);
  }
  .sede-card-header h4 { color: var(--cnx-text-primary); }
  .btn-remove {
      background: var(--cnx-danger);
      color: #FFFFFF;
  }
  .btn-remove:hover { background: #DC2626; }
  ```

### F17 — Autocomplete dropdown (Comune) has white bg + light gray hover
- **Severity:** minor
- **Selector / element:** `.municipality-autocomplete-results`, `.autocomplete-item`, `.autocomplete-item.selected`, `.mun-name`, `.mun-province`, `.autocomplete-item strong` (file: `aziende.php:557-617`)
- **Screenshot region:** not in static screenshot (only when typing in Comune field) — affects the comune typeahead
- **Current behaviour (dark mode):** All hex literals (`background: white`, `background-color: #f8f9fa`, `background-color: #e7f3ff`, `color: #007bff`, etc.) stay bright in dark. The dropdown is unusable: white panel jumping out of dark page.
- **Root cause (CSS):** Hex literals at `aziende.php:560, 562, 588, 592, 597, 602, 608`.
- **Recommended fix:**
  ```css
  .municipality-autocomplete-results {
      background: var(--cnx-bg-surface);
      border: 1px solid var(--cnx-border);
      box-shadow: var(--cnx-shadow-md);
  }
  .autocomplete-item { border-bottom-color: var(--cnx-border); }
  .autocomplete-item:hover,
  .autocomplete-item.selected { background-color: var(--cnx-accent-soft); }
  .mun-name { color: var(--cnx-text-primary); }
  .mun-province { color: var(--cnx-text-muted); }
  .autocomplete-item strong { color: var(--cnx-accent); }
  .autocomplete-loading,
  .autocomplete-no-results { color: var(--cnx-text-muted); }
  ```

### F18 — Alt-tax validation banner uses hardcoded amber on cream
- **Severity:** minor
- **Selector / element:** `.alt-tax-validation-message`, `.alt-tax-asterisk`, `input[data-alt-tax-field].alt-tax-valid`, `input[data-alt-tax-field].alt-tax-invalid` (file: `aziende.php:626-663`)
- **Screenshot region:** not in static screenshot — appears when entering alternative tax codes
- **Current behaviour (dark mode):** `background-color: #fff3cd; color: #856404; border: 1px solid #ffc107;` cream-on-amber banner stays bright on dark. Valid/invalid input borders also use `#28a745` / `#dc3545` literals + light bg `#f8fff9` / `#fff8f8` that ignore dark theme.
- **Root cause (CSS):** Hex literals at `aziende.php:628-651`.
- **Recommended fix:**
  ```css
  .alt-tax-validation-message {
      background-color: rgba(245, 158, 11, 0.12);
      border: 1px solid var(--cnx-warning);
      color: var(--cnx-text-primary);
  }
  input[data-alt-tax-field].alt-tax-valid {
      border-color: var(--cnx-success) !important;
      background-color: rgba(34, 197, 94, 0.06);
  }
  input[data-alt-tax-field].alt-tax-invalid {
      border-color: var(--cnx-danger) !important;
      background-color: rgba(239, 68, 68, 0.06);
  }
  .alt-tax-asterisk { color: var(--cnx-danger); }
  .alt-tax-asterisk.valid { color: var(--cnx-success); }
  ```
