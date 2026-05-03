# Dark mode audit — utenti.php

## Summary
- Total findings: 17
- Severity breakdown: critical=4, major=8, minor=5

Visual reference: `tools/ui_redesign/dark_round3/utenti.png`
Token reference: `assets/css/styles.css` `:root` (light) and `[data-theme="dark"]` (lines 220-250).

---

## Findings

### F1 — Search input "Cerca utenti..." has no visible border in dark mode
- **Severity:** critical
- **Selector / element:** `.search-bar input` (file: `utenti.php:197-204`)
- **Screenshot region:** top of the page-content, search box at left of the toolbar row
- **Current behaviour (dark mode):** Same problem as `aziende.php` F1: hardcoded `border: 1px solid var(--color-gray-300)` resolves to `#2E464C`, then global override in `components.css:262-273` slams `background: var(--cnx-bg-subtle) !important; border-color: var(--cnx-border) !important;` => bg `#1B2F33` border `#22363B` (≈3pt delta — invisible).
- **Root cause (CSS):** Inline `utenti.php:200` declares the legacy `--color-gray-300` border, then `components.css:262-273` overrides border to `--cnx-border`. Neither contrasts against `--cnx-bg-subtle`.
- **Recommended fix:** Same as aziende.md F1 — extend the dark-mode input override in `components.css`:
  ```css
  [data-theme="dark"] .search-bar input,
  [data-theme="dark"] input[type="text"],
  [data-theme="dark"] input[type="email"],
  [data-theme="dark"] input[type="search"] {
      background: var(--cnx-bg-surface) !important;
      border-color: var(--cnx-border-strong) !important;
      color: var(--cnx-text-primary) !important;
  }
  [data-theme="dark"] .search-bar input::placeholder {
      color: var(--cnx-text-muted) !important;
  }
  ```

### F2 — Search-icon emoji (🔍) renders as full-color emoji on a teal UI
- **Severity:** major
- **Selector / element:** `.search-icon` (file: `utenti.php:212-219`, emoji at `utenti.php:630`)
- **Screenshot region:** right edge of the "Cerca utenti..." input
- **Current behaviour (dark mode):** Same as aziende F2 — the `🔍` glyph is rendered with OS emoji color and the `color: var(--color-gray-400)` rule has no effect on emoji.
- **Root cause (CSS):** Emoji glyph cannot be recolored via CSS `color`.
- **Recommended fix:** Same SVG-mask approach as aziende.md F2 — replace the emoji visually with a CSS mask icon (no markup change).

### F3 — Tipo Utente role-badges use bright pastel hex literals (UTENTE/MANAGER/ADMIN)
- **Severity:** critical
- **Selector / element:** `.role-badge.super-admin/.admin/.user/.manager` (file: `utenti.php:295-323`)
- **Screenshot region:** "TIPO UTENTE" column — every row has either UTENTE (lilac), MANAGER (light purple), ADMIN (light blue) pill
- **Current behaviour (dark mode):** Hardcoded pastels:
  - `super-admin`: `#FEF3C7 / #92400E` (cream / brown)
  - `admin`: `#DBEAFE / #1E3A8A` (sky / navy)
  - `user`: `#E0E7FF / #3730A3` (lilac / indigo)
  - `manager`: `#F3E8FF / #6B21A8` (light purple / dark purple)
  All four badges remain bright pastel rectangles on the dark page — they do **not** flip for dark mode. They contrast loud against everything else and create a "rainbow" effect that the design direction explicitly avoids (single-accent mint policy).
- **Root cause (CSS):** Hex literals at `utenti.php:306-322` bypass the `--cnx-*` token system entirely.
- **Recommended fix:** Add token pairs to `assets/css/styles.css` and remap each role to use them. In `:root`:
  ```css
  --cnx-badge-amber-bg: #FEF3C7; --cnx-badge-amber-fg: #92400E;
  --cnx-badge-blue-bg:  #DBEAFE; --cnx-badge-blue-fg:  #1E3A8A;
  --cnx-badge-indigo-bg:#E0E7FF; --cnx-badge-indigo-fg:#3730A3;
  --cnx-badge-purple-bg:#F3E8FF; --cnx-badge-purple-fg:#6B21A8;
  ```
  In `[data-theme="dark"]`:
  ```css
  --cnx-badge-amber-bg:  rgba(245, 158, 11, 0.16); --cnx-badge-amber-fg:  #FBBF24;
  --cnx-badge-blue-bg:   rgba(59, 130, 246, 0.16); --cnx-badge-blue-fg:   #93C5FD;
  --cnx-badge-indigo-bg: rgba(99, 102, 241, 0.18); --cnx-badge-indigo-fg: #A5B4FC;
  --cnx-badge-purple-bg: rgba(168, 85, 247, 0.18); --cnx-badge-purple-fg: #D8B4FE;
  ```
  Then in `utenti.php`:
  ```css
  .role-badge.super-admin { background: var(--cnx-badge-amber-bg);  color: var(--cnx-badge-amber-fg); }
  .role-badge.admin       { background: var(--cnx-badge-blue-bg);   color: var(--cnx-badge-blue-fg); }
  .role-badge.user        { background: var(--cnx-badge-indigo-bg); color: var(--cnx-badge-indigo-fg); }
  .role-badge.manager     { background: var(--cnx-badge-purple-bg); color: var(--cnx-badge-purple-fg); }
  ```
  Optional stricter alternative (per direction.md "single accent"): collapse all 4 to a single neutral pill with role spelled out — but that is a UX call, not a CSS-only fix.

### F4 — "Nessuno" tenant-role chip is light gray on dark — low contrast & bright island
- **Severity:** major
- **Selector / element:** `.tenant-role-badge.no-role` (file: `utenti.php:340-344`)
- **Screenshot region:** "RUOLO AZIENDALE" column — most rows show "Nessuno" italic pill
- **Current behaviour (dark mode):** `background: #F3F4F6; color: #6B7280; font-style: italic;` — **light gray rectangle on dark page**, the brightest element after the avatars. Hex literals don't flip; visually screams "missing data" but in a too-loud way.
- **Root cause (CSS):** Hex literals at `utenti.php:341-342`.
- **Recommended fix:**
  ```css
  .tenant-role-badge.no-role {
      background: transparent;
      border: 1px dashed var(--cnx-border-strong);
      color: var(--cnx-text-muted);
      font-style: italic;
  }
  ```
  Dashed border + transparent bg = "empty/placeholder" semantic without the bright island. Works in both light and dark.

### F5 — Default `.tenant-role-badge` (assigned role) uses lilac hex literal
- **Severity:** minor
- **Selector / element:** `.tenant-role-badge` (file: `utenti.php:326-338`)
- **Screenshot region:** "RUOLO AZIENDALE" column — would display assigned roles like "Direttore", "Tecnico", etc.; not visible in current screenshot but applies whenever a tenant role is assigned
- **Current behaviour (dark mode):** `background: #E0E7FF; color: #3730A3` — same hex pastel that ignores dark theme.
- **Root cause (CSS):** Hex literals at `utenti.php:336-337`.
- **Recommended fix:** Reuse the indigo token pair from F3:
  ```css
  .tenant-role-badge { background: var(--cnx-badge-indigo-bg); color: var(--cnx-badge-indigo-fg); }
  ```

### F6 — Action-button row icons render as full-color emoji (pencil orange, pause/play blue, key yellow, trash silver)
- **Severity:** critical
- **Selector / element:** `.btn-icon` with emoji content `✏️ ⏸️ ▶️ 🔑 📄 🗑️` (file: `utenti.php:1455-1482`, base style `utenti.php:378-401`)
- **Screenshot region:** "AZIONI" column, every row — three or four icon-buttons depending on permissions
- **Current behaviour (dark mode):** Each row shows colored emoji that ignore the CSS `color` rule. Pencil emoji is yellow/orange, pause emoji is blue/white, key emoji is yellow, trash emoji is silver. The buttons have `border: 1px solid var(--color-gray-300)` (=> `#2E464C` in dark) — barely visible — so emoji float on the page.
- **Root cause (CSS):** Emoji used as icon content; CSS `color` cannot recolor emoji. `.btn-icon.edit { color: var(--color-primary); }` and `.btn-icon.delete { color: var(--color-error); }` (lines 395-401) only affect text color, not emoji.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to fully fix (replace emoji with SVG icons). CSS-only mitigation:
  ```css
  [data-theme="dark"] .btn-icon {
      background: var(--cnx-bg-surface);
      border-color: var(--cnx-border-strong);
      filter: grayscale(0.85) brightness(1.1);
  }
  [data-theme="dark"] .btn-icon:hover {
      background: var(--cnx-accent-soft);
      border-color: var(--cnx-accent);
      filter: none;
  }
  [data-theme="dark"] .btn-icon.edit { color: var(--cnx-accent); }
  [data-theme="dark"] .btn-icon.delete { color: var(--cnx-danger); }
  ```
  Long-term: swap emoji for SVG masks following the `.cnx-input-group__icon` pattern in `components.css:501-518`.

### F7 — Page subtitle "Gestisci utenti e permessi" too dim
- **Severity:** minor
- **Selector / element:** `.text-sm.text-muted` (file: `utenti.php:621`, rule: `assets/css/styles.css:1443`)
- **Screenshot region:** top-right of page header next to "Gestione Utenti"
- **Current behaviour (dark mode):** `.text-muted { color: var(--color-gray-500); }` => `#8893A0` in dark. Same washed-out feeling as aziende.md F5.
- **Root cause (CSS):** `.text-muted` resolves to `--color-gray-500`.
- **Recommended fix:** Same as aziende F5:
  ```css
  [data-theme="dark"] .text-muted { color: var(--cnx-text-secondary); }   /* #A8B5BB */
  ```

### F8 — User avatar circle is hardcoded blue (legacy primary), clashes with mint accent
- **Severity:** major
- **Selector / element:** `.user-avatar-table` (file: `utenti.php:265-277`)
- **Screenshot region:** "NOME" column — every row has an LG/EC/GP/MP/FS/RM/GI initials circle
- **Current behaviour (dark mode):** `background: var(--color-primary)` => the legacy blue `#2563EB` (un-touched by dark override). All circles are bright royal blue and visually dominate the table while clashing with the mint single-accent palette.
- **Root cause (CSS):** `--color-primary` token still points to legacy blue (defined `:root` line 19, not overridden in dark).
- **Recommended fix:**
  ```css
  .user-avatar-table {
      background: var(--cnx-accent);
      color: var(--cnx-accent-ink);
  }
  ```
  Place this either inline in `utenti.php:265` or in `components.css` after the other dark-mode patches. Mint avatar matches the mint "+ Nuovo Utente" button.

### F9 — Table header bar invisible (no contrast vs page bg)
- **Severity:** major
- **Selector / element:** `.users-table th` (file: `utenti.php:234-244`)
- **Screenshot region:** "NOME / EMAIL / TIPO UTENTE / RUOLO AZIENDALE / AZIENDA / STATO / DATA CREAZIONE / AZIONI" header row
- **Current behaviour (dark mode):** Same problem as aziende F6: header `background: var(--color-gray-50)` => `#1B2F33`, body card `background: var(--color-white)` => `#15262A`. Header is **darker** than the body — inverted hierarchy in dark mode.
- **Root cause (CSS):** Token order inversion when `--color-gray-50` is remapped to `#1B2F33` and `--color-white` to `#15262A`.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .users-table th {
      background: var(--cnx-bg-subtle);
      color: var(--cnx-text-secondary);
      border-bottom: 1px solid var(--cnx-border-strong);
  }
  [data-theme="dark"] .users-table {
      background: var(--cnx-bg-surface);
      border: 1px solid var(--cnx-border);
      box-shadow: none;
  }
  ```

### F10 — Table row hover bg is darker than the row itself
- **Severity:** minor
- **Selector / element:** `.users-table tbody tr:hover` (file: `utenti.php:255-257`)
- **Screenshot region:** every row on hover
- **Current behaviour (dark mode):** `background: var(--color-gray-50)` => `#1B2F33`. Body `--color-white` => `#15262A`. Hover is darker, not lighter — counter-intuitive.
- **Root cause (CSS):** Same as F9.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .users-table tbody tr:hover {
      background: var(--cnx-accent-soft);  /* rgba(95,202,211,0.12) — readable hover */
  }
  ```

### F11 — Status-badge "Attivo" uses light pastel hex literals
- **Severity:** major
- **Selector / element:** `.status-badge.active` (file: `utenti.php:356-359`)
- **Screenshot region:** "STATO" column — every row shows "Attivo" green pill
- **Current behaviour (dark mode):** `background: #D1FAE5; color: #065F46;` — bright mint pastel that doesn't dim for dark. Same problem applies to `.status-badge.inactive { background: #FEE2E2; color: #991B1B; }` (lines 361-364) when an account is suspended.
- **Root cause (CSS):** Hex literals at `utenti.php:357-358, 362-363`.
- **Recommended fix:** Add success/danger badge tokens (same as aziende F8):
  ```css
  /* :root */
  --cnx-success-bg: #D1FAE5; --cnx-success-fg: #065F46;
  --cnx-danger-bg:  #FEE2E2; --cnx-danger-fg:  #991B1B;
  /* [data-theme="dark"] */
  --cnx-success-bg: rgba(34, 197, 94, 0.18);  --cnx-success-fg: #6BE5A0;
  --cnx-danger-bg:  rgba(239, 68, 68, 0.16);  --cnx-danger-fg:  #FCA5A5;
  ```
  ```css
  .status-badge.active   { background: var(--cnx-success-bg); color: var(--cnx-success-fg); }
  .status-badge.inactive { background: var(--cnx-danger-bg);  color: var(--cnx-danger-fg); }
  ```

### F12 — Topbar appears as teal-tinted band that doesn't match app bg
- **Severity:** minor
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-33`)
- **Screenshot region:** top of page (global "Cerca file, persone, ticket..." search row)
- **Current behaviour (dark mode):** `background: var(--cnx-accent-soft)` => `rgba(95,202,211,0.12)` over the dark page reads as a subtle teal band, distinct from `--cnx-bg-app`. Same finding as aziende F12.
- **Root cause (CSS):** `--cnx-accent-soft` used as topbar bg in both themes.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .cnx-app-topbar {
      background: var(--cnx-bg-app);
      border-bottom: 1px solid var(--cnx-border);
      backdrop-filter: none;
      -webkit-backdrop-filter: none;
  }
  ```

### F13 — Global search pill ("Cerca file, persone, ticket...") looks like a light island in the topbar
- **Severity:** major
- **Selector / element:** `.cnx-app-topbar__search .cnx-input` (file: `components.css:173-180`)
- **Screenshot region:** centered pill in the global topbar
- **Current behaviour (dark mode):** Same as aziende F13 — the `--cnx-bg-surface` pill is *lighter* than the surrounding `--cnx-accent-soft` band, popping forward harder than the page H1.
- **Root cause (CSS):** `--cnx-bg-surface` shared token between light and dark.
- **Recommended fix:**
  ```css
  [data-theme="dark"] .cnx-app-topbar__search .cnx-input {
      background: var(--cnx-bg-subtle);
      border: 1px solid var(--cnx-border);
      box-shadow: none;
  }
  [data-theme="dark"] .cnx-app-topbar__search .cnx-input:focus {
      background: var(--cnx-bg-surface);
      border-color: var(--cnx-accent);
  }
  ```

### F14 — `.users-table` outer card has no visible border in dark
- **Severity:** minor
- **Selector / element:** `.users-table` (file: `utenti.php:221-227`)
- **Screenshot region:** outer edge of the user table
- **Current behaviour (dark mode):** `background: var(--color-white)` => `#15262A`, no border, `box-shadow: var(--shadow-sm)` invisible on dark. Card edge isn't perceptible.
- **Root cause (CSS):** Box-shadow tuned for light mode (low-alpha black on white).
- **Recommended fix:**
  ```css
  [data-theme="dark"] .users-table {
      border: 1px solid var(--cnx-border);
      box-shadow: none;
  }
  ```

### F15 — Modal form inputs use light gray bg / borders bypassing dark overrides
- **Severity:** major
- **Selector / element:** `.modal-content`, `.tenant-checkbox-list`, `.tenant-checkbox-item` and their hover/active states (file: `utenti.php:67-180`)
- **Screenshot region:** not in static screenshot, but every Edit/Add/Delete modal in the page
- **Current behaviour (dark mode):** Multi-select tenant pickers use `--color-gray-50/100/200/400/500` for bg, borders, and scrollbar — all of which DO flip via the `[data-theme="dark"]` override in styles.css:239-249. However:
  - `.tenant-checkbox-list { background: var(--color-gray-50); }` (line 74) becomes `#1B2F33` — sits on modal `var(--color-white)` (=> `#15262A`) — list area is **darker** than the modal (same inversion as F9).
  - `.tenant-checkbox-item.checked { background: rgba(37, 99, 235, 0.05); border-color: var(--color-primary); }` — the legacy blue accent leaks through; should use mint.
  - Various sub-elements use `--color-primary` for selected indicator → blue island in mint design.
- **Root cause (CSS):** Multiple uses of `--color-primary` (blue legacy) instead of `--cnx-accent` (mint). Token-flip ordering between gray-50/gray-100/white in dark.
- **Recommended fix:** In `components.css` (after the existing `[data-theme="dark"]` block):
  ```css
  [data-theme="dark"] .tenant-checkbox-list {
      background: var(--cnx-bg-app);
      border-color: var(--cnx-border);
  }
  [data-theme="dark"] .tenant-checkbox-item:hover {
      background: var(--cnx-bg-subtle);
      border-color: var(--cnx-border-strong);
  }
  /* Mint accent on selected state, both themes */
  .tenant-checkbox-item.checked {
      background: var(--cnx-accent-soft);
      border-color: var(--cnx-accent);
  }
  ```

### F16 — Manager "ADMIN" target row visually identical to manageable rows even though buttons are hidden
- **Severity:** minor
- **Selector / element:** rows where `managerBlockedTarget = isManager && (user.role === 'admin' || ...)` (file: `utenti.php:1467`)
- **Screenshot region:** for the screenshot user (super_admin), N/A — but a manager would see rows with no action buttons and no visual cue that they are "locked"
- **Current behaviour (dark mode):** When a manager logs in, ADMIN/SUPER_ADMIN rows lose their action buttons (correct per BUG-104 logic) but the row keeps the same bg / opacity as manageable rows. There is no `:disabled-row` state.
- **Root cause (CSS):** No CSS class added to "blocked" rows; visual affordance missing.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** — add a class like `data-row-locked="true"` on the `<tr>` when the user is a manager-blocked target, then style:
  ```css
  [data-theme="dark"] tr[data-row-locked="true"] {
      opacity: 0.65;
      background: var(--cnx-bg-app);
  }
  tr[data-row-locked="true"]::before {
      content: "🔒";
      /* or use mask icon */
  }
  ```
  Flagged as markup change for the lead's review.

### F17 — Toast notification (`.toast`) uses `--color-gray-900` bg => light pill in dark mode (inversion bug)
- **Severity:** major
- **Selector / element:** `.toast` (file: `utenti.php:582-602` — also defined in aziende.php:665-700)
- **Screenshot region:** not in static screenshot — appears bottom-right after CRUD actions
- **Current behaviour (dark mode):** `background: var(--color-gray-900); color: var(--color-white);` — in dark mode `--color-gray-900` is overridden to `#E6EEF0` (per styles.css:248) and `--color-white` is overridden to `#15262A` (per line 249). Result: toast becomes **light gray pill with dark gray text** — the inverse of intent. Same defect for `.toast.success` (`var(--color-success)` stays green which is fine) and `.toast.error` (`var(--color-error)` stays red, also fine).
- **Root cause (CSS):** The `--color-gray-900 → light` and `--color-white → dark` swap (styles.css:248-249) is correct for *text colors* but inverts the meaning of "toast bg = gray-900" (which the design assumed = always-dark for both themes).
- **Recommended fix:**
  ```css
  [data-theme="dark"] .toast {
      background: #0A1517;            /* dark slab even in dark theme */
      color: var(--cnx-text-primary);
      border: 1px solid var(--cnx-border-strong);
  }
  ```
  Or, more idiomatic, decouple toast bg from gray-900:
  ```css
  .toast {
      background: var(--cnx-text-primary);   /* always the inverse of body text */
      color: var(--cnx-bg-app);
  }
  ```
