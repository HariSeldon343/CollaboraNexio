# Responsive audit — ticket.php

## Summary
- Total findings: 11
- Severity: critical=4, major=4, minor=3
- Worst breakpoint: 375px (mobile) — every layout system except the auto-fit stat grid breaks; 768px also has a mid-range break in the filter row.

Screenshots reviewed:
- `tools/ui_redesign/responsive_pre/mobile/ticket.png` (375x812)
- `tools/ui_redesign/responsive_pre/tablet_portrait/ticket.png` (768x1024)

Sources reviewed:
- `ticket.php` (inline `<style>` block, lines 44-446; markup lines 449-594)
- `assets/css/styles.css` (`.main-content` rules, lines 1209-1220 + media at 1551-1574 + 1909-1911)
- `assets/css/components.css` (header normalization 58-146; topbar 16-49)
- `assets/css/dashboard.css` (`.stats-grid` 107-112; mobile media 589-621)
- `assets/css/sidebar-responsive.css` (sidebar collapse rules)

## Findings

### F1 — Filter row clips the search input ("Cerc" cut off on mobile)
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.tickets-filters` (file: `ticket.php:88-93` and the `.filter-group` children at 94-97)
- **Screenshot region:** Bottom of mobile screenshot — four boxes labelled (empty), (empty), (empty), "Cerc" pushed against the right edge.
- **Current behaviour:** The 4 filter `<select>`s + the "Cerca ticket" text input share a single `display: flex; gap: var(--space-4)` row with NO `flex-wrap`. Each `.form-control` keeps its intrinsic width so they refuse to shrink and the last one is clipped by the viewport / scrollbar.
- **Root cause (CSS):** `ticket.php:88` defines `.tickets-filters { display: flex; gap: var(--space-4); margin-bottom: var(--space-6); }` with no wrapping; `.filter-group { display: flex; gap: var(--space-2); }` (line 94) doesn't flex-shrink; the 4 selects also don't have `min-width: 0`, so the row exceeds 375px.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .tickets-filters {
        flex-wrap: wrap;
        gap: 8px;
    }
    .tickets-filters .filter-group {
        flex: 1 1 calc(50% - 8px); /* 2-up at 768px */
        min-width: 0;
    }
    .tickets-filters .filter-group .form-control {
        width: 100%;
        min-width: 0;
    }
}
@media (max-width: 480px) {
    .tickets-filters .filter-group {
        flex: 1 1 100%; /* full-width stack on phones */
    }
}
```
Use tokens: replace `8px` with `var(--space-2)` if available (the inline style relies on `--space-*` already).

### F2 — Stat row crammed into 4 columns at 768px
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.tickets-stats` (file: `ticket.php:59-64`)
- **Screenshot region:** Tablet screenshot — top stat row shows "Ticket Aperti / In Lavorazione / Risolti Oggi" on row 1 and "Tempo Medio Risoluzione" alone on row 2 because `auto-fit minmax(200px, 1fr)` calculates 3-up. At 375px it correctly stacks.
- **Current behaviour:** `auto-fit, minmax(200px, 1fr)` produces 3 columns at 768px (768 - sidebar 0 - padding 48 = ~720 / 200 ≈ 3.6 → 3 cols), leaving the 4th card orphaned. At 375px, only 1 column fits (correct stack).
- **Root cause (CSS):** `ticket.php:60-63` `grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));`. The `200px` minmax is the problem — at tablet width it cannot snap to 2 nor 4.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .tickets-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: var(--space-3);
    }
}
@media (max-width: 480px) {
    .tickets-stats {
        grid-template-columns: 1fr !important;
    }
}
```
The `!important` is needed only because the desktop rule uses an inline `<style>` block with the same specificity.

### F3 — Tickets table never reflows; horizontal overflow at 375px
- **Severity:** critical
- **Breakpoint:** 375px (and 768px keeps 8 columns visible only because viewport just barely fits)
- **Selector / element:** `.tickets-table .table-wrapper > table` (file: `ticket.php:524-589`, CSS at `ticket.php:99-133`)
- **Screenshot region:** Mobile — the ticket table is below the fold; once scrolled into view it would force horizontal scroll because columns ID/Oggetto/Categoria/Priorità/Stato/Assegnato a/Creato/Azioni all keep their default widths.
- **Current behaviour:** 8 columns with `padding: var(--space-4)` per cell on a 375px viewport produces ~700-800px of intrinsic table width. `.table-wrapper { overflow-x: auto }` (line 106) makes it scrollable but UX is poor: thumb-scrolling a deeply nested area + losing row context.
- **Root cause (CSS):** `ticket.php:110-112` sets `table { width: 100%; border-collapse: collapse; }` and there's no responsive reflow strategy. On mobile the user sees the same desktop table, just shrunk via overflow.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** ideally (add `data-label` attributes to `<td>` for label-stacked card style), but a CSS-only mitigation is to hide low-priority columns and use a scroll hint:
```css
@media (max-width: 768px) {
    /* Hide secondary columns on tablet & below */
    .tickets-table th:nth-child(3),  /* Categoria */
    .tickets-table td:nth-child(3),
    .tickets-table th:nth-child(6),  /* Assegnato a */
    .tickets-table td:nth-child(6),
    .tickets-table th:nth-child(7),  /* Creato */
    .tickets-table td:nth-child(7) {
        display: none;
    }
    .tickets-table th,
    .tickets-table td {
        padding: var(--space-2) var(--space-3);
        font-size: var(--cnx-text-small-size);
    }
}
@media (max-width: 480px) {
    /* Mobile: keep only ID/Oggetto/Stato/Azioni */
    .tickets-table th:nth-child(4),  /* Priorità */
    .tickets-table td:nth-child(4) {
        display: none;
    }
    /* Force the wrapper to allow internal scroll without hugging the viewport edge */
    .tickets-table .table-wrapper {
        margin: 0 calc(var(--space-4) * -1);
        border-radius: 0;
    }
}
```

### F4 — Page header (`Sistema Ticket` + Company filter + greeting) overlaps and overflows on mobile
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.main-content > .header` containing `.flex.items-center.gap-4` (file: `ticket.php:449-457`)
- **Screenshot region:** Mobile — the greeting text "Benvenuto, Antonio Amodeo" wraps onto two lines AND sits next to the company-filter pill creating visual noise; at 375 the company filter occupies most of the row leaving the user's name with very little space.
- **Current behaviour:** components.css already wraps the header (rule at lines 58-92) but the inner `.flex.items-center.gap-4` (an inline tailwind-style utility on the `<div>`) does NOT itself wrap — it uses `gap: var(--space-4)` and no `flex-wrap`. Result: nesting forces the single inner flex row to overflow.
- **Root cause (CSS):** `.flex` from `styles.css` uses `display: flex` with no wrap; `gap-4` adds 16px gap. There is no per-page mobile rule that wraps the inner row.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .main-content > .header > .flex.items-center {
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-start;
        width: 100%;
    }
    .main-content > .header > .flex.items-center > .text-sm.text-muted {
        order: 2;
        flex-basis: 100%;
        font-size: 12px;
        color: var(--cnx-text-muted);
    }
}
```
Ensures greeting drops to its own line and the company-filter is a single free pill.

### F5 — `.tickets-header` (h2 + "Nuovo Ticket" button) does not wrap; title pushes button off-axis
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.tickets-header` (file: `ticket.php:52-57`)
- **Screenshot region:** Mobile — "Gestione Ticket" wraps onto 2 lines ("Gestione" / "Ticket"), and the "+ Nuovo Ticket" pill sits beside it cramped against the right edge.
- **Current behaviour:** `display: flex; justify-content: space-between; align-items: center` with no wrap forces title + button on the same row. Title becomes 2 lines, vertically off-centre relative to the button.
- **Root cause (CSS):** `ticket.php:52-57` no `flex-wrap`.
- **Recommended fix:**
```css
@media (max-width: 480px) {
    .tickets-header {
        flex-wrap: wrap;
        gap: 12px;
        align-items: stretch;
    }
    .tickets-header h2 {
        flex: 1 1 100%;
        font-size: 24px;
        line-height: 1.2;
    }
    .tickets-header .btn,
    .tickets-header .btn--primary {
        align-self: flex-start;
    }
}
```

### F6 — `.tickets-container` keeps 24px (var(--space-6)) padding even on mobile, eating ~13% of viewport width
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.tickets-container` (file: `ticket.php:48-50`)
- **Screenshot region:** Mobile — visible empty gutters on both sides.
- **Current behaviour:** `padding: var(--space-6)` = 24px both sides → 48px stolen from a 375px viewport (~12.8%). Combined with `.page-content` padding (already `var(--space-6) var(--space-4)` mobile from `styles.css:1571`), there's double-padding.
- **Root cause (CSS):** No mobile override.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .tickets-container {
        padding: var(--space-4);
    }
}
@media (max-width: 480px) {
    .tickets-container {
        padding: var(--space-3);
    }
}
```

### F7 — Touch target size: `.action-btn` (eye/edit/assign icons in last column) below 44x44 minimum
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `.action-btn` (file: `ticket.php:165-178`)
- **Screenshot region:** Tablet — last "Azioni" column on each table row has 3 small icon buttons with `padding: var(--space-1) var(--space-2)` (4px / 8px) → ~28x24px touch target.
- **Current behaviour:** Buttons are too small for thumb taps; iOS HIG / Material recommend ≥44x44px.
- **Root cause (CSS):** Hardcoded narrow padding, no min-width/min-height.
- **Recommended fix:**
```css
@media (max-width: 1024px) {
    .ticket-actions {
        gap: var(--space-1);
    }
    .action-btn {
        min-width: 36px;
        min-height: 36px;
        padding: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
}
@media (max-width: 480px) {
    .action-btn {
        min-width: 44px;
        min-height: 44px;
    }
}
```

### F8 — Modal does not constrain on tablet; left-column 35% / right-column 65% layout breaks below 900px
- **Severity:** major
- **Breakpoint:** 768px (when ticket detail modal is opened)
- **Selector / element:** `.modal-content` with inline `style="max-width: 1200px; width: 95%"` and child columns `style="width: 35%"` / `"width: 65%"` (file: `ticket.php:671-829`)
- **Screenshot region:** Not visible in baseline (modal closed) but inspection shows the design.
- **Current behaviour:** `max-width: 1200px; width: 95%` → at 768px the modal is ~730px wide. The fixed inline percentages put left col at 255px (too narrow for `width: 80px` label cells with their content) and right col at 475px. Below 600px the dual-column conversation breaks.
- **Root cause (CSS):** Inline styles on lines 671-785 hardcode percentages; no media query.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to remove `style="width: 35%"` and `style="width: 65%"` so a CSS rule can reflow:
```css
@media (max-width: 900px) {
    #ticket-detail-modal .modal-body {
        flex-direction: column !important;
        overflow-y: auto !important;
    }
    #ticket-detail-modal .modal-body > div:first-child {
        width: 100% !important;
        border-right: none !important;
        border-bottom: 2px solid var(--cnx-border) !important;
    }
    #ticket-detail-modal .modal-body > div:last-child {
        width: 100% !important;
    }
}
```
The `!important` is required to defeat the inline-style specificity. Without markup change, every override needs `!important`.

### F9 — Status / Priority badges use horizontal padding that wraps "In Attesa di Risposta" and "Critica" awkwardly
- **Severity:** minor
- **Breakpoint:** 375px (and 768px partially — see "In Attesa di / Risposta" wrap in tablet screenshot)
- **Selector / element:** `.priority-badge`, `.status-badge` (file: `ticket.php:135-158`)
- **Screenshot region:** Tablet screenshot — row 4 "In Attesa di Risposta" wraps onto 2 lines inside the cell, breaking row height alignment.
- **Current behaviour:** Default badge `padding: var(--space-1) var(--space-2)` is fine but text wraps because the cell is too narrow once 8 columns share 720px.
- **Root cause (CSS):** No `white-space: nowrap` on badges; combined with narrow column widths.
- **Recommended fix:**
```css
.priority-badge,
.status-badge {
    white-space: nowrap;
}
@media (max-width: 768px) {
    .priority-badge,
    .status-badge {
        font-size: 10px;
        padding: 2px 6px;
    }
}
```

### F10 — Modal create form inputs cap at full width but `<input type="file">` (allegato) leaks padding
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `#ticket-attachment` with inline style `padding: 8px; border: 1px dashed; background: #f9fafb` (file: `ticket.php:644-646`)
- **Screenshot region:** Not visible in baseline (modal closed).
- **Current behaviour:** Native `<input type="file">` on iOS Safari shows "Choose File" + filename label that easily overflows on 375px. There is no `box-sizing: border-box` enforcement on the inline padding.
- **Root cause (CSS):** Inline styles, no responsive sizing.
- **Recommended fix:**
```css
@media (max-width: 480px) {
    #ticket-attachment {
        font-size: 12px;
        padding: 6px !important;
        max-width: 100%;
        box-sizing: border-box;
    }
}
```

### F11 — Sidebar/topbar interaction: hamburger toggle shows at <=1024px but topbar still reserves `left: var(--sidebar-width)` until 768px breakpoint
- **Severity:** minor (cosmetic only — not visible in screenshot but a 769-1024px window shows a "ghost gap" where the closed sidebar used to be)
- **Breakpoint:** between 769px and 1024px
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-49`) vs `.sidebar-toggle` (file: `assets/css/styles.css:1258-1283`) vs `.main-content` margin (`styles.css:1209-1220`)
- **Screenshot region:** N/A in current screenshots (768 exactly hits the boundary).
- **Current behaviour:** At 1024px the sidebar-toggle appears but the sidebar itself is still visible AND `.main-content { margin-left: var(--sidebar-width) }` still applies. At 768px exactly, components.css `.cnx-app-topbar { left: 0 }` kicks in but `.main-content { margin-left: 0 }` also kicks in — they align. The bug is in the 769-1024 window where sidebar is open but a "Toggle" button suggests it should be closeable.
- **Root cause (CSS):** Three different breakpoints govern related layout: sidebar-toggle visibility (1024px), topbar offset (768px), main-content margin (768px).
- **Recommended fix:** Unify at 1024px:
```css
@media (max-width: 1024px) {
    .cnx-app-topbar { left: 0; }
    .main-content { margin-left: 0; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
}
```
This requires care because `assets/css/styles.css:1551` already uses `max-width: 768px` for sidebar collapse — moving it to 1024px is a global behaviour change. Flag for design review before applying.
