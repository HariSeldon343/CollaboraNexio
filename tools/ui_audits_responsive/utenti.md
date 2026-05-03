# Responsive audit — utenti.php

## Summary
- Total findings: 12
- Severity: critical=4, major=6, minor=2
- Worst breakpoint: 375px

Screenshots audited:
- `tools/ui_redesign/responsive_pre/mobile/utenti.png` (375x812)
- `tools/ui_redesign/responsive_pre/tablet_portrait/utenti.png` (768x1024)

## Findings

### F1 — Company filter dropdown + page header subtitle stack incorrectly at 375px
- **Severity:** critical
- **Breakpoint:** 375px (also tablet portrait 768px to a lesser degree)
- **Selector / element:** `.main-content > .header > .flex.items-center.gap-4` (file: `utenti.php:617-622`); `.company-filter-dropdown` (rendered by `CompanyFilter::renderDropdown()`)
- **Screenshot region:** top "Gestione Utenti" header bar
- **Current behaviour:** At 375px the header places the H1 on row 1, then the dropdown "Azienda: Tutte le aziende ▼" wraps below; "Gestisci utenti e permessi" wraps onto a third line, half clipped on the right. At 768px portrait the dropdown sits between H1 and subtitle but visually crowds it.
- **Root cause (CSS):** `.main-content > .header` (`components.css:58-70`) flex-wraps but the company-filter element is wider than the remaining row width, and the `<span class="text-sm text-muted">` has no min-width:0, so content overflows. No mobile-specific stacking rules.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .main-content > .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .main-content > .header > .flex.items-center.gap-4 {
        flex-wrap: wrap;
        gap: 8px;
        width: 100%;
    }
    .main-content > .header .company-filter-dropdown {
        flex: 1 1 100%;
        min-width: 0;
    }
    .main-content > .header .text-sm.text-muted {
        font-size: 12px;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .main-content > .header h1.page-title {
        font-size: 20px;
        line-height: 28px;
    }
}
```

### F2 — Users table 8-column layout forces horizontal scroll on mobile (badges hidden)
- **Severity:** critical
- **Breakpoint:** 375px (and 768px partial)
- **Selector / element:** `.users-table table` and `<th>/<td>` (file: `utenti.php:221-258, 638-655`)
- **Screenshot region:** main table
- **Current behaviour:** At 375px only NOME + EMAIL columns are visible; "TIPO UTENTE" badge (UTENTE / MANAGER / ADMIN), "RUOLO AZIENDALE", "AZIENDA", "STATO", "DATA CREAZIONE", "AZIONI" all hide off-screen until horizontal scroll. The user-info-cell wraps awkwardly (avatar + name on 2 rows). At 768px portrait, "AZIONI" column is fully cut off and AZIENDA column truncates ("FONDAZ GIGLIO" instead of "FONDAZIONE GIGLIO"). EMAIL truncates at "lorenzo.guarrera@hs..." which is acceptable but only because the column is narrowed.
- **Root cause (CSS):** Plain `width: 100%` table with no responsive transform (`utenti.php:229-233`). 8 columns is too many for <1024px screens.
- **Recommended fix:** Card-reflow pattern:
```css
@media (max-width: 768px) {
    .users-table { box-shadow: none; background: transparent; }
    .users-table table,
    .users-table thead,
    .users-table tbody,
    .users-table tr,
    .users-table td {
        display: block;
        width: 100%;
    }
    .users-table thead { display: none; }
    .users-table tbody tr {
        background: var(--cnx-bg-surface);
        border: 1px solid var(--cnx-border);
        border-radius: var(--cnx-radius-lg);
        margin-bottom: 12px;
        padding: 12px 16px;
    }
    .users-table td {
        border-bottom: 1px solid var(--cnx-border-soft, rgba(255,255,255,0.06));
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        text-align: right;
    }
    .users-table td:last-child { border-bottom: none; }
    .users-table td::before {
        content: attr(data-label);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--cnx-text-muted);
        flex: 0 0 40%;
        text-align: left;
    }
    /* First cell (avatar + name) takes full row, no label */
    .users-table td:first-child {
        justify-content: flex-start;
        text-align: left;
    }
    .users-table td:first-child::before { content: ""; flex: 0; }
}
```
**REQUIRES MARKUP CHANGE** — JS row template at `utenti.php:1484-1517` must add `data-label="Email"`, `data-label="Tipo Utente"`, `data-label="Ruolo Aziendale"`, `data-label="Azienda"`, `data-label="Stato"`, `data-label="Data Creazione"`, `data-label="Azioni"` on each `<td>`.

### F3 — TIPO UTENTE / RUOLO AZIENDALE badges hidden until horizontal scroll on mobile
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.role-badge`, `.tenant-role-badge` (file: `utenti.php:295-344`)
- **Screenshot region:** TIPO UTENTE column
- **Current behaviour:** These badges are key signals (admin/manager/user discrimination) but are completely off-screen at 375px because they sit in the 3rd and 4th columns. User must scroll right to see them.
- **Root cause (CSS):** Same as F2 — table has no card reflow and the badges live in narrow columns.
- **Recommended fix:** Covered by F2 card-reflow. Additionally ensure badges don't wrap inside their cell:
```css
@media (max-width: 768px) {
    .users-table .role-badge,
    .users-table .tenant-role-badge {
        white-space: nowrap;
        font-size: 11px;
    }
}
```

### F4 — Avatar circle + name should stack vertically OR remain inline cleanly on mobile
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.user-info-cell` (file: `utenti.php:259-263, 1487-1492`)
- **Screenshot region:** NOME column
- **Current behaviour:** Avatar (LG/EC/GP/MP) is inline next to the name, but at 375px the names are forced into 2 lines ("Lorenzo / Guarrera", "Massimo / Parisi") with the avatar tied to row 1; visually messy and creates uneven row heights.
- **Root cause (CSS):** `.user-info-cell { display: flex; align-items: center; gap: var(--space-3); }` (`utenti.php:259-263`) — the inner `.user-name-table` has no `min-width: 0` so it wraps awkwardly inside the constrained mobile column. After F2 reflow, this still presents because the name+avatar combo lives in `td:first-child` which spans full width.
- **Recommended fix:** Keep inline but allow the name to wrap normally (single block) and shrink avatar slightly:
```css
@media (max-width: 768px) {
    .users-table .user-info-cell {
        align-items: center;
        gap: 12px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .users-table .user-avatar-table {
        width: 32px;
        height: 32px;
        font-size: 12px;
        flex-shrink: 0;
    }
    .users-table .user-name-table {
        font-size: 14px;
        font-weight: 600;
        line-height: 1.3;
        word-break: break-word;
    }
}
```

### F5 — "+ Nuovo Utente" CTA + search bar lay out in same row, search collapses oddly at 375px
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.users-header > .search-bar` + `.btn.btn-primary` (file: `utenti.php:627-635`); search-bar styles at `utenti.php:192-219`
- **Screenshot region:** action toolbar (Cerca utenti + Nuovo Utente)
- **Current behaviour:** At 375px the search-bar (fixed `width: 300px`) and the "+ Nuovo Utente" button collide, with the search input squashed below 300px while still nominally that wide; "+ Nuovo Utente" stays right but feels cramped.
- **Root cause (CSS):** `.users-header { display: flex; justify-content: space-between }` (`utenti.php:59-64`) plus `.search-bar { width: 300px }` (`utenti.php:194`) and no media-query.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .users-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .users-header .search-bar {
        width: 100%;
    }
    .users-header .btn-primary {
        width: 100%;
        min-height: 44px;
    }
}
```

### F6 — AZIONI column emoji buttons fail 44x44 touch-target rule
- **Severity:** major
- **Breakpoint:** 375px and 768px
- **Selector / element:** `.action-buttons .btn-icon` (file: `utenti.php:373-401`); rendered at `utenti.php:1508-1514` (5 buttons: edit, toggle, resend, docs, delete)
- **Screenshot region:** AZIONI column (off-screen on mobile)
- **Current behaviour:** Each button has `padding: var(--space-2)` ≈ 8px; rendered size ~32x32. Below WCAG 2.5.5 / Apple HIG 44pt min. Especially problematic because there are FIVE buttons in a row (edit, toggle, resend, docs, delete), so total width is ~160-180px which doesn't fit a card-reflow row cleanly.
- **Root cause (CSS):** Generic `.btn-icon` rule with no responsive override. Five buttons require either bigger touch targets (which forces them to wrap) or a "more" overflow menu.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .users-table .action-buttons {
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .users-table .action-buttons .btn-icon {
        min-width: 44px;
        min-height: 44px;
        padding: 10px;
        font-size: 18px;
        line-height: 1;
        flex: 0 0 auto;
    }
}
```
**Future enhancement:** Consider collapsing the 5 buttons into a single overflow `…` menu on mobile (`**REQUIRES MARKUP CHANGE**`).

### F7 — `--sidebar-width` topbar offset coherence at 769-1024px (same as aziende F7)
- **Severity:** major
- **Breakpoint:** 769-1024px
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-49`); `.main-content` (`assets/css/styles.css:1209-1220, 1551-1574`)
- **Screenshot region:** top fixed search bar
- **Current behaviour:** Topbar uses `left: var(--sidebar-width)` and only flips to `left: 0` at <=768px. Sidebar transform-translate also at <=768px. But sidebar-toggle is shown at <=1024px, implying intent to hide sidebar at 769-1024 too — coherence gap.
- **Root cause (CSS):** Misaligned breakpoints between sidebar-toggle (1024) and sidebar `transform: translateX(-100%)` (768).
- **Recommended fix:** (identical to aziende F7)
```css
@media (max-width: 1024px) {
    .cnx-app-topbar { left: 0; }
    .main-content { margin-left: 0; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
}
```

### F8 — EMAIL column hits viewport edge with no padding/ellipsis safeguard
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.users-table td:nth-child(2)` (email column) (file: `utenti.php:246-249, 1494`)
- **Screenshot region:** EMAIL column
- **Current behaviour:** "lorenzo.guarrera@hs..." truncates with ellipsis (good) but truncation happens because column overflow hidden, not because of explicit `text-overflow: ellipsis` rule. After card-reflow (F2), this becomes a label/value pair where long email may overflow horizontally if `word-break` not set.
- **Root cause (CSS):** No `overflow-wrap`/`word-break` on the email cell. Default browser behaviour clips long URL-like strings.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .users-table td {
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .users-table td[data-label="Email"] {
        font-size: 13px;
        color: var(--cnx-text-muted);
    }
}
```

### F9 — AZIENDA column truncates company name "FONDAZ GIGLIO" / "Agrumi s.n.c" at 768px
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** AZIENDA column cell (file: `utenti.php:1499`); `getTenantDisplay()` at `utenti.php:~2390`
- **Screenshot region:** AZIENDA column at tablet portrait
- **Current behaviour:** Long names ("FONDAZIONE GIGLIO", "Agrumi Gel s.n.c", "Lab. Anal. Bioclinic Check-Up Dott.ssa Fornarot S.R.L.") get clipped to "FONDAZ / GIGLIO" or wrapped into 6+ lines (Concetta Laquatra row). No max-height, no hover tooltip.
- **Root cause (CSS):** Column has fixed-ish width imposed by table layout; no whitespace policy on cell content.
- **Recommended fix:**
```css
@media (max-width: 1024px) {
    .users-table td:nth-child(5) { /* Azienda column */
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
@media (max-width: 768px) {
    /* In card-reflow, allow full text */
    .users-table td:nth-child(5) {
        max-width: none;
        white-space: normal;
        word-break: break-word;
    }
}
```

### F10 — Search-bar fixed width 300px causes overflow at 375px
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.search-bar` (file: `utenti.php:192-195`)
- **Screenshot region:** action toolbar
- **Current behaviour:** Same issue as aziende — hard-coded 300px doesn't fit at 375px viewport with 16px page padding.
- **Root cause (CSS):** `width: 300px` literal at line 194, no media-query override.
- **Recommended fix:** Covered by F5. Standalone:
```css
@media (max-width: 768px) {
    .search-bar { width: 100%; }
}
```

### F11 — `.flex.items-center.gap-4` (header inner wrapper) lacks min-width:0
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.header > .flex.items-center.gap-4` (file: `utenti.php:617`)
- **Screenshot region:** header subtitle / dropdown wrapper
- **Current behaviour:** Same root issue as aziende F11 — utility classes lack `min-width: 0`, so the company-filter-dropdown + subtitle don't shrink/wrap correctly inside the flex parent.
- **Root cause (CSS):** Tailwind-style utility classes from `styles.css:1439-1457` don't set min-width.
- **Recommended fix:** (covered by F1) — explicit:
```css
.main-content > .header > .flex {
    min-width: 0;
    flex: 1 1 auto;
}
```

### F12 — Page-content padding insufficient on mobile
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.page-content` (file: `assets/css/styles.css:1373-1377, 1570-1573`)
- **Screenshot region:** entire page-content
- **Current behaviour:** Mobile uses `padding: var(--space-6) var(--space-4)` = `24px 16px`. Acceptable but cramped.
- **Root cause (CSS):** Single mobile breakpoint with no narrower tier.
- **Recommended fix:**
```css
@media (max-width: 480px) {
    .page-content {
        padding: 16px 12px;
    }
}
```
