# Dark mode audit — turni.php

## Summary
- Total findings: 16
- Severity breakdown: critical=4, major=8, minor=4

## Findings

### F1 — Top toolbar buttons have zero hierarchy (all transparent + mint border)
- **Severity:** critical
- **Selector / element:** `.btn.btn-secondary` (Guida rapida) at `turni.php:126`, plus 4 × `.btn.btn-outline` (Riepilogo, Tipi Turno, Wizard Turni, Bilanciamento) at `turni.php:127, 130, 137, 146`.
- **Screenshot region:** Top header row beneath "Gestione Turni" — the strip of 5 pill-shaped buttons.
- **Current behaviour (dark mode):** All 5 buttons render with *identical* visual weight: transparent bg, mint-coloured text and a faint outline. There is no distinction between the secondary-utility action ("Guida rapida") and the primary feature actions ("Tipi Turno", "Wizard Turni"), and the user cannot tell at a glance which is the destructive option.
- **Root cause (CSS):**
  1. `assets/css/components.css:225-230` overrides `.btn-outline` to `color: var(--cnx-accent) !important` for ALL outline buttons — turning them all into mint-text outline pills.
  2. `assets/css/shifts.css:1363-1372` and `assets/css/styles.css:437-446` set `.btn-secondary` to `bg: var(--color-white)` + `color: var(--color-gray-700)` + `border: var(--color-gray-300)`. After the dark legacy-grey override (`styles.css:239-249`), `--color-white` = `#15262A` and `--color-gray-300` = `#2E464C` — which makes `.btn-secondary` look almost identical to `.btn-outline` in dark.
  3. `.btn-secondary` is not visually differentiated by the redesign at all.
- **Recommended fix:** Differentiate by giving `.btn-secondary` a real surface fill in dark mode and reserving the mint outline ONLY for `.btn-outline`:
```css
[data-theme="dark"] .btn-secondary {
    background: var(--cnx-bg-subtle) !important;
    color: var(--cnx-text-primary) !important;
    border: 1px solid var(--cnx-border-strong) !important;
}
[data-theme="dark"] .btn-secondary:hover {
    background: var(--cnx-bg-surface) !important;
    border-color: var(--cnx-text-muted) !important;
}
[data-theme="dark"] .btn-outline {
    background: transparent !important;
    border: 1px solid rgba(95, 202, 211, 0.32) !important;
    /* color already set to --cnx-accent via components.css:226 */
}
[data-theme="dark"] .btn-outline:hover {
    background: var(--cnx-accent-soft) !important;
    border-color: var(--cnx-accent) !important;
}
```

### F2 — "Maria · Assenza" chip uses wrong red palette in dark mode
- **Severity:** major
- **Selector / element:** `.turni-shift` chip on Thu May 1 (`assets/css/shifts.css:286-304`) — a shift type with `--shift-color: red` set inline (probably from data: shift_types.color column).
- **Screenshot region:** "GIO" column, row 1 — the single chip "Maria · Assenza" with a saturated red dashed border.
- **Current behaviour (dark mode):** Bright saturated red border on a near-black bg. The CSS variable `--shift-color` (set inline by JS from `shift_types.color` data) is rendered raw — there is no dark-theme tonal adaptation.
- **Root cause (CSS):** `assets/css/shifts.css:295-296`:
  ```
  border: 1px dashed var(--shift-color, #6B7280);
  background-color: rgba(107, 114, 128, 0.1);
  ```
  The bg is a fixed grey overlay. The shift-color (data-driven hex from DB, here likely `#EF4444` or `#DC2626`) shows raw. There's also no dark-mode variant of the bg (the `0.1` alpha grey looks washed out on `--cnx-bg-app`).
- **Recommended fix:** Use the chip's own `--shift-color` for both border and a soft tint background, with a slightly higher alpha in dark mode:
```css
.turni-shift {
    /* keep existing rules but replace bg-color */
    background-color: color-mix(in srgb, var(--shift-color, #6B7280) 12%, transparent);
}
[data-theme="dark"] .turni-shift {
    background-color: color-mix(in srgb, var(--shift-color, #6B7280) 18%, transparent);
    color: var(--cnx-text-primary);
}
```
(Modern browsers support `color-mix`. If older browser support needed, add a fallback `rgba()`.) **Note:** the actual red hue is data-driven (admin-configured shift type colour). If users find red still feels wrong, the fix is in admin UX (palette guidance) — outside CSS scope.

### F3 — "Today" indicator uses cobalt blue, not mint accent
- **Severity:** critical
- **Selector / element:** `.shifts-month-cell.today .month-cell-date` (`assets/css/shifts.css:494-503`) and `.shifts-day-header.today .day-number` (line 221-228).
- **Screenshot region:** "DOM" column, row 1 — the bright blue circle around the number "3".
- **Current behaviour (dark mode):** The today-marker is a saturated cobalt-blue circle (`#2563EB`), clashing visibly with the mint-everywhere palette (sidebar active item, theme toggle, all primary CTAs).
- **Root cause (CSS):** `shifts.css:500-501`:
  ```
  background: var(--color-primary);
  color: var(--color-white);
  ```
  `--color-primary` is the legacy `#2563EB`, never remapped for dark mode.
- **Recommended fix:** Override in `assets/css/components.css` (or directly in shifts.css):
```css
.shifts-month-cell.today .month-cell-date,
.shifts-day-header.today .day-number {
    background: var(--cnx-accent) !important;
    color: var(--cnx-accent-ink) !important;
}
.shifts-day-header.today .day-name,
.shifts-month-cell.today,
.shifts-cell.today {
    color: var(--cnx-accent);
}
[data-theme="dark"] .shifts-month-cell.today,
[data-theme="dark"] .shifts-cell.today {
    background: rgba(95, 202, 211, 0.06) !important;  /* was rgba(37, 99, 235, 0.03) */
}
```

### F4 — `.shifts-month-cell.today` and `.shifts-cell.today` use cobalt-blue tint
- **Severity:** major
- **Selector / element:** `.shifts-month-cell.today` (`assets/css/shifts.css:469-471`), `.shifts-cell.today` (line 275-277)
- **Screenshot region:** Today (DOM 3) cell — has a barely-visible cobalt blue tint background.
- **Current behaviour (dark mode):** `background: rgba(37, 99, 235, 0.03)` — 3% cobalt blue tint over `--cnx-bg-surface`. Tint is wrong colour (blue, not mint) and barely perceptible.
- **Root cause (CSS):** Hardcoded `rgba(37, 99, 235, ...)` literal — pre-redesign blue.
- **Recommended fix:** Migrate to mint accent (combined with F3 fix):
```css
.shifts-month-cell.today,
.shifts-cell.today {
    background: rgba(95, 202, 211, 0.05);
}
[data-theme="dark"] .shifts-month-cell.today,
[data-theme="dark"] .shifts-cell.today {
    background: rgba(95, 202, 211, 0.08);
}
```

### F5 — Calendar grid lines invisible (border-color = bg-color in dark)
- **Severity:** critical
- **Selector / element:** `.shifts-month-cell` (`assets/css/shifts.css:451-459`), `.shifts-month-grid` (line 442-449), `.shifts-month-day-header` (line 427-436), `.shifts-month-header` (line 418-425).
- **Screenshot region:** Entire month-view grid — the cell separators are extremely faint.
- **Current behaviour (dark mode):** Cells have `border-right: 1px solid var(--color-gray-100)` and `border-bottom: 1px solid var(--color-gray-100)`. After the dark legacy-grey override, `--color-gray-100` = `#1B2F33` — basically identical to `--cnx-bg-subtle` (= `#1B2F33`) and very close to `--color-white` (= `#15262A`). The grid is essentially borderless.
- **Root cause (CSS):**
  - `shifts.css:454-455` uses `--color-gray-100` for cell borders.
  - `shifts.css:444-445` outer grid uses `--color-gray-200`.
  - In dark, both gray-100 and gray-200 collapse to near-bg colours.
- **Recommended fix:**
```css
[data-theme="dark"] .shifts-month-cell {
    border-right-color: var(--cnx-border) !important;
    border-bottom-color: var(--cnx-border) !important;
}
[data-theme="dark"] .shifts-month-grid,
[data-theme="dark"] .shifts-month-header {
    border-color: var(--cnx-border-strong) !important;
}
[data-theme="dark"] .shifts-month-day-header {
    border-right-color: var(--cnx-border) !important;
    color: var(--cnx-text-secondary) !important;
}
```

### F6 — Past-month days too dim (27, 28, 29, 30) — almost invisible
- **Severity:** major
- **Selector / element:** `.shifts-month-cell.other-month .month-cell-date` (`assets/css/shifts.css:477-479`)
- **Screenshot region:** Top row LUN/MAR/MER/GIO — numbers 27, 28, 29, 30 (April leftover days) and bottom row 1, 2, 3 etc. (June first days).
- **Current behaviour (dark mode):** `color: var(--color-gray-400)` which after dark override = `#4A5560`. On `--color-gray-50` (= `#1B2F33`) bg — hex distance is < 50 in luminance, gives < 3:1 contrast. Numbers are nearly invisible.
- **Root cause (CSS):** `shifts.css:478` `color: var(--color-gray-400);` — too low contrast in dark.
- **Recommended fix:**
```css
[data-theme="dark"] .shifts-month-cell.other-month {
    background: rgba(0, 0, 0, 0.16) !important;  /* slightly darker than bg-surface */
}
[data-theme="dark"] .shifts-month-cell.other-month .month-cell-date {
    color: var(--cnx-text-muted) !important;     /* #6B7A82 = ~3.5:1 */
    opacity: 0.7;
}
```

### F7 — Calendar header band lighter than cell rows (inverted hierarchy)
- **Severity:** major
- **Selector / element:** `.shifts-month-header` (`assets/css/shifts.css:418-425`), `.shifts-calendar-header` (line 83-91)
- **Screenshot region:** Weekday header strip ("LUN MAR MER GIO VEN SAB DOM") — it's lighter than the cells below.
- **Current behaviour (dark mode):** Header uses `--color-gray-50` (= `#1B2F33`) while body cells use `--color-white` (= `#15262A`). In light mode this is correct (subtle grey header on white body), but in dark mode the convention should invert: cells on `--cnx-bg-surface`, header on a slightly DARKER strip OR a mint-tinted accent strip.
- **Root cause (CSS):** Token mapping: in light theme `--color-gray-50` is lighter than `--color-white`; in dark theme `--color-gray-50` is `#1B2F33` which is LIGHTER than `--color-white` = `#15262A`. The tokens flip but their luminance ordering inverts — light-mode design assumptions break.
- **Recommended fix:** In dark mode, give the header a mint-tinted soft band:
```css
[data-theme="dark"] .shifts-month-header,
[data-theme="dark"] .shifts-grid-header,
[data-theme="dark"] .shifts-calendar-header {
    background: rgba(95, 202, 211, 0.04) !important;
    border-bottom-color: var(--cnx-border-strong) !important;
}
[data-theme="dark"] .shifts-month-day-header,
[data-theme="dark"] .shifts-employee-header,
[data-theme="dark"] .shifts-day-header .day-name {
    color: var(--cnx-text-secondary) !important;
}
```

### F8 — Calendar wrapper (`.shifts-main`) card is invisible (no border)
- **Severity:** major
- **Selector / element:** `.shifts-main` (`assets/css/shifts.css:23-32`)
- **Screenshot region:** The whole calendar area — should appear as one elevated "card".
- **Current behaviour (dark mode):** `bg: var(--color-white)` (= `#15262A`), `border: 1px solid var(--color-gray-200)` (= `#22363B`), `box-shadow: var(--shadow-sm)` (rgba black, invisible on dark). The 1px border between `#15262A` and `--cnx-bg-app: #0E1A1D` provides ~15% luminance delta — adequate but barely; the box-shadow contributes nothing.
- **Root cause (CSS):** Same legacy-shadow problem as ticket F2. `var(--shadow-sm)` is rgba black, useless on dark.
- **Recommended fix:**
```css
[data-theme="dark"] .shifts-main {
    border-color: var(--cnx-border-strong) !important;
    box-shadow: var(--cnx-shadow-md) !important;
}
[data-theme="dark"] .shifts-requests-sidebar {
    border-color: var(--cnx-border-strong) !important;
    box-shadow: var(--cnx-shadow-md) !important;
}
```

### F9 — Empty-state illustration in "Richieste" sidebar uses muted color (acceptable but improvable)
- **Severity:** minor
- **Selector / element:** `.requests-empty svg` (`assets/css/shifts.css:614-627`), illustration is the "smiley face" SVG inline at `turni.php:227-232`.
- **Screenshot region:** "Richieste" sidebar — the smiley illustration with text "Nessuna richiesta in sospeso".
- **Current behaviour (dark mode):** The SVG inherits `stroke: currentColor` and `color: var(--color-gray-400)` from `.requests-empty`. After dark override = `#4A5560`. Result: very dim icon, hard to read at thumbnail.
- **Root cause (CSS):** `shifts.css:621` `color: var(--color-gray-400);`
- **Recommended fix:**
```css
[data-theme="dark"] .requests-empty {
    color: var(--cnx-text-secondary);
}
[data-theme="dark"] .requests-empty svg {
    opacity: 0.6;  /* was 0.5 */
}
```

### F10 — `.shifts-view-selector` active-pill background is DARKER than container (inverted)
- **Severity:** major
- **Selector / element:** `.shifts-view-btn.active` (`assets/css/shifts.css:70-74`)
- **Screenshot region:** "Settimana / Mese" pill switcher in the top toolbar — both pills look similar.
- **Current behaviour (dark mode):** Container `.shifts-view-selector` uses `bg: var(--color-gray-100)` (= `#1B2F33`). Active button uses `bg: var(--color-white)` (= `#15262A`) — DARKER than the container. The active-state visual cue is reversed.
- **Root cause (CSS):**
  - `shifts.css:40` `background: var(--color-gray-100)` (container)
  - `shifts.css:71` `background: var(--color-white)` (active pill)
  - In dark mode: gray-100 = `#1B2F33`, white = `#15262A` → container is the lighter of the two.
- **Recommended fix:** Force the active-state to be mint-tinted in dark mode:
```css
[data-theme="dark"] .shifts-view-selector {
    background: var(--cnx-bg-subtle) !important;
}
[data-theme="dark"] .shifts-view-btn {
    color: var(--cnx-text-secondary);
}
[data-theme="dark"] .shifts-view-btn.active {
    background: var(--cnx-accent-soft) !important;
    color: var(--cnx-accent) !important;
    box-shadow: none !important;
}
```

### F11 — Active pill icon stroke colour leaks legacy default
- **Severity:** minor
- **Selector / element:** `.shifts-view-btn svg` (`turni.php:99-119`) — inline SVG with `stroke="currentColor"`.
- **Screenshot region:** Icons in the Settimana/Mese pills.
- **Current behaviour (dark mode):** SVGs inherit `currentColor` from the button text colour — fine in light mode, but in dark mode after F10 fix the active button colour is mint, the inactive is muted text — verify both look balanced.
- **Root cause (CSS):** Inheritance — no specific issue if F10 is fixed.
- **Recommended fix:** No change needed beyond F10.

### F12 — `.btn-warning` ("Richiedi Modifica") inside detail modal uses harsh `#F59E0B` orange
- **Severity:** minor
- **Selector / element:** `.btn-warning` (`assets/css/shifts.css:1392-1399`) — used at `turni.php:668` for the user's "Richiedi Modifica" button.
- **Screenshot region:** Not visible in static screenshot but appears in shift-detail modal for non-manager users.
- **Current behaviour (dark mode):** Saturated orange `#F59E0B` on white text, jarring against the mint+teal-only redesign.
- **Root cause (CSS):** `shifts.css:1393` `background: var(--color-warning);` — straight legacy orange. Not adapted to dark theme.
- **Recommended fix:** Soften and align hue (orange is semantically OK for "warning request", but tone it down):
```css
[data-theme="dark"] .btn-warning {
    background: rgba(245, 158, 11, 0.18) !important;
    color: #FBBF24 !important;
    border: 1px solid rgba(245, 158, 11, 0.36) !important;
}
[data-theme="dark"] .btn-warning:hover {
    background: rgba(245, 158, 11, 0.28) !important;
}
```

### F13 — `.btn-success` (approve request) and `.btn-danger` (reject) use saturated dark-mode-unfriendly colours
- **Severity:** minor
- **Selector / element:** `.btn-success` (`assets/css/shifts.css:1374-1381`), `.btn-danger` (line 1383-1390)
- **Screenshot region:** Not visible in static screenshot but shown in `requestDetailModal` (`turni.php:702-703`).
- **Current behaviour (dark mode):** Saturated `#10B981` green and `#EF4444` red, no dark adaptation.
- **Root cause (CSS):** `shifts.css:1375, 1384` direct `var(--color-success)` / `var(--color-error)`.
- **Recommended fix:**
```css
[data-theme="dark"] .btn-success {
    background: rgba(16, 185, 129, 0.85) !important;
    color: #FFFFFF !important;
}
[data-theme="dark"] .btn-danger {
    background: rgba(239, 68, 68, 0.85) !important;
    color: #FFFFFF !important;
}
```

### F14 — `.shifts-spinner` border colour stuck on cobalt
- **Severity:** minor
- **Selector / element:** `.shifts-spinner` (`assets/css/shifts.css:719-727`)
- **Screenshot region:** Not in screenshot (post-load), but flashes on initial calendar load.
- **Current behaviour (dark mode):** Spinner ring `border-top-color: var(--color-primary)` = cobalt blue.
- **Root cause (CSS):** `shifts.css:723` literal `--color-primary`.
- **Recommended fix:**
```css
.shifts-spinner {
    border-top-color: var(--cnx-accent);
}
[data-theme="dark"] .shifts-spinner {
    border-color: var(--cnx-border) !important;
    border-top-color: var(--cnx-accent) !important;
}
```

### F15 — `.add-shift-cell` and `.month-cell-add` hover colour cobalt blue
- **Severity:** minor
- **Selector / element:** `.add-shift-cell:hover` (`assets/css/shifts.css:385-389`), `.month-cell-add:hover` (line 554-558)
- **Screenshot region:** Not in screenshot (only on hover over an empty cell).
- **Current behaviour (dark mode):** Hover state is cobalt-blue tinted (`rgba(37, 99, 235, 0.05)`).
- **Root cause (CSS):** Hardcoded blue rgba.
- **Recommended fix:**
```css
.add-shift-cell:hover,
.month-cell-add:hover {
    border-color: var(--cnx-accent);
    color: var(--cnx-accent);
    background: rgba(95, 202, 211, 0.06);
}
```

### F16 — `requests-badge` uses saturated red on a tile with no requests (visual noise risk)
- **Severity:** minor
- **Selector / element:** `.requests-badge` (`assets/css/shifts.css:594-606`)
- **Screenshot region:** "Richieste" header pill — currently shows "0" in screenshot.
- **Current behaviour (dark mode):** `bg: var(--color-error)` = `#EF4444` saturated red, applied even when count is 0. The badge says "0" in red — implies an error/alarm where there is none.
- **Root cause (CSS):** `shifts.css:601-602` `bg: var(--color-error); color: white;` always-red. Plus there is no zero-count visual treatment.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to add a `is-zero` class when count == 0. CSS-only stopgap (works because the badge contains literal "0"):
```css
/* Default state: keep red for non-zero counts */
.requests-badge {
    background: var(--cnx-danger);
}
/* Zero state via :has (modern browsers) */
.requests-sidebar-header:has(.requests-badge:empty),
.requests-badge:empty {
    background: var(--cnx-bg-subtle);
    color: var(--cnx-text-muted);
}
[data-theme="dark"] .requests-badge {
    background: rgba(239, 68, 68, 0.85);
    color: #FFFFFF;
}
```
Cleaner: have JS toggle a `.requests-badge--zero` class. Mark for follow-up.
