# Responsive audit — aziende.php

## Summary
- Total findings: 11
- Severity: critical=4, major=5, minor=2
- Worst breakpoint: 375px

Screenshots audited:
- `tools/ui_redesign/responsive_pre/mobile/aziende.png` (375x812)
- `tools/ui_redesign/responsive_pre/tablet_portrait/aziende.png` (768x1024)

## Findings

### F1 — Page header subtitle "Gestisci aziende e piani" overflows / cut off at 375px
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.main-content > .header > .flex.items-center.gap-4 > .text-sm.text-muted` (file: `aziende.php:733`); base header rule in `assets/css/components.css:58-70`
- **Screenshot region:** top "Gestione Aziende" header bar
- **Current behaviour:** "Gestione Aziende" h1 sits on the left and "Gestisci aziende e piani" subtitle is pushed by flexbox to the right edge and partially cut off the viewport. At 375px the row is too narrow for both items side by side but `flex-wrap` is allowed so the subtitle visually drops/clips in dark gradient zone.
- **Root cause (CSS):** The header normalisation in `components.css:58-70` uses `flex-wrap: wrap` but the inline wrapper `<div class="flex items-center gap-4">` (line 732 in `aziende.php`) is `display: flex; align-items: center; gap: 16px` (utility classes) and does NOT wrap; combined with the long subtitle text + lack of stacking rules at <768px the subtitle is forced into horizontal overflow. There is also no `max-width` on the subtitle.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .main-content > .header {
        align-items: flex-start;
        gap: 8px 12px;
    }
    .main-content > .header h1.page-title {
        font-size: 20px;
        line-height: 28px;
        flex: 1 1 100%;
    }
    .main-content > .header .flex.items-center.gap-4 {
        flex: 1 1 100%;
        flex-wrap: wrap;
        gap: 8px;
    }
    .main-content > .header .flex.items-center.gap-4 .text-muted {
        font-size: 12px;
        color: var(--cnx-text-muted);
        white-space: normal;
        overflow-wrap: anywhere;
    }
}
```

### F2 — "+ Nuova Azienda" CTA truncated to "+ Nuova Az" at 375px
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.companies-header > div[style*="display:flex"] > .btn.btn-primary` (file: `aziende.php:750-752`); container in `aziende.php:177-182`
- **Screenshot region:** filter / action toolbar row
- **Current behaviour:** The action button is clipped inside the `.companies-header` row — only "+ Nuova Az" is visible because it sits in a fixed `width: 300px` search-bar + `display:flex; gap:10px` + 2 buttons that exceed 375px viewport.
- **Root cause (CSS):** `aziende.php:177-187` defines `.companies-header` as `display: flex; justify-content: space-between` and `.search-bar { width: 300px }` with no media query. At 375px a fixed 300px search bar plus a 2-button group cannot fit; `white-space: nowrap` from `.btn` (`styles.css:465`) prevents wrap and overflow is hidden by the parent's `overflow-x: hidden`.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .companies-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .companies-header .search-bar {
        width: 100%;
    }
    .companies-header > div[style*="display:flex"] {
        flex-wrap: wrap;
        gap: 8px;
    }
    .companies-header .btn {
        flex: 1 1 auto;
        min-height: 44px; /* a11y touch target */
        padding: 10px 12px;
        font-size: 13px;
    }
    .companies-header #bulkDeleteBtn { order: 2; }
    .companies-header .btn-primary { order: 1; }
}
```

### F3 — Companies table forces horizontal scroll on mobile (8 columns visible only via scroll)
- **Severity:** critical
- **Breakpoint:** 375px (also impacts 768px portrait)
- **Selector / element:** `.companies-table table` and its `<th>/<td>` (file: `aziende.php:213-249`)
- **Screenshot region:** main table grid
- **Current behaviour:** Mobile viewport shows only "ID + DENOMINAZIONE + (cut COMUNE/FIS/PA)"; CODICE FISCALE column wraps awkwardly into 3-line slabs ("CC / FIS / PA"), MANAGER / STATO / AZIONI columns are hidden until horizontal scroll. Tablet portrait at 768px barely fits all columns and AZIONI is fully off-screen.
- **Root cause (CSS):** Table is plain `width: 100%` with no responsive transformation (`aziende.php:221-223`). Column count (super_admin sees 8 columns: checkbox, ID, Denominazione, Codice Fiscale, Comune, Manager, Stato, Azioni — `aziende.php:760-771`) is incompatible with widths < ~1024px.
- **Recommended fix:** Card-style reflow using `data-label` attributes plus CSS:
```css
@media (max-width: 768px) {
    .companies-table { box-shadow: none; background: transparent; }
    .companies-table table,
    .companies-table thead,
    .companies-table tbody,
    .companies-table tr,
    .companies-table td {
        display: block;
        width: 100%;
    }
    .companies-table thead { display: none; }
    .companies-table tbody tr {
        background: var(--cnx-bg-surface);
        border: 1px solid var(--cnx-border);
        border-radius: var(--cnx-radius-lg);
        margin-bottom: 12px;
        padding: 12px 16px;
    }
    .companies-table td {
        border-bottom: 1px solid var(--cnx-border-soft, rgba(255,255,255,0.06));
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }
    .companies-table td:last-child { border-bottom: none; }
    .companies-table td::before {
        content: attr(data-label);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--cnx-text-muted);
        flex: 0 0 40%;
    }
    .companies-table td:first-child {
        justify-content: flex-end; /* checkbox */
    }
    .companies-table td:first-child::before { content: ""; }
}
```
**REQUIRES MARKUP CHANGE** in JS row template `aziende.php:1860-1916` — each `<td>` needs `data-label="ID"`, `data-label="Denominazione"`, etc. for the pseudo-element labels to render.

### F4 — AZIONI emoji buttons fail 44x44 touch-target rule on mobile
- **Severity:** major
- **Breakpoint:** 375px and 768px
- **Selector / element:** `.action-buttons .btn-icon` (file: `aziende.php:349-369`); rendered at `aziende.php:1905-1915`
- **Screenshot region:** rightmost AZIONI column buttons (✏️ 👥 🗑️)
- **Current behaviour:** Each button has `padding: var(--space-2)` ≈ 8px; visual size is roughly 32x32 — below the WCAG 2.5.5 / Apple HIG 44pt minimum.
- **Root cause (CSS):** `aziende.php:354-365` `.btn-icon { padding: var(--space-2); ... }` no min size and no responsive bump.
- **Recommended fix:**
```css
@media (max-width: 768px) {
    .companies-table .action-buttons {
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .companies-table .action-buttons .btn-icon {
        min-width: 44px;
        min-height: 44px;
        padding: 10px;
        font-size: 18px;
        line-height: 1;
    }
}
```

### F5 — Search-bar fixed width 300px causes overflow at 375px
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.search-bar` (file: `aziende.php:184-187`)
- **Screenshot region:** action toolbar
- **Current behaviour:** Hard-coded 300px doesn't fit cleanly inside 375px viewport once 16px page padding and the gap to the next button is accounted for; the input wraps but stays at 300px wide.
- **Root cause (CSS):** `width: 300px` literal at line 186, no media-query override.
- **Recommended fix:** (also covered by F2) — explicit:
```css
@media (max-width: 768px) {
    .search-bar { width: 100%; }
}
```

### F6 — Bulk-delete button label "Elimina selezionate" wraps awkwardly at 375px
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `#bulkDeleteBtn` (file: `aziende.php:746-749`)
- **Screenshot region:** action toolbar (left of "+ Nuova Az")
- **Current behaviour:** Button icon plus "Elimina selezionate" text plus dynamic count exceed available width; renders on a single line forcing the next button off the viewport.
- **Root cause (CSS):** `.btn { white-space: nowrap }` (`styles.css:465`) keeps full label on one line; no shorter mobile label exists.
- **Recommended fix:**
```css
@media (max-width: 480px) {
    #bulkDeleteBtn {
        font-size: 12px;
        padding: 10px 8px;
    }
    /* Hide the verbose label, keep emoji + count for context */
    #bulkDeleteBtn { font-size: 0; }
    #bulkDeleteBtn::before {
        content: "🗑️";
        font-size: 16px;
    }
    #bulkDeleteBtn #bulkDeleteCount {
        font-size: 12px;
        margin-left: 4px;
    }
}
```
**Note:** Alternative is `**REQUIRES MARKUP CHANGE**` to wrap the verbose label in a `.label` span and hide it via CSS — cleaner than the `font-size:0` trick.

### F7 — Topbar `--sidebar-width` offset is correctly removed at <=768px but not at 769-1024px
- **Severity:** major
- **Breakpoint:** 768-1024px gap (tablet landscape & small laptops)
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-49`)
- **Screenshot region:** top fixed search bar
- **Current behaviour:** At tablet portrait 768px (max-width: 768px applies, includes 768) the topbar correctly fills full width (`left: 0`). However at 769-1023px the sidebar is still hidden by the toggle rule (sidebar-toggle visible at <=1024px per `styles.css:1279-1283`) but the topbar still shows `left: var(--sidebar-width)` = 260px gap from left, leaving an empty band. **Note:** the actual sidebar `transform: translateX(-100%)` only kicks in at <=768px (`styles.css:1551-1554`), so 769-1024px still has the sidebar visible — this finding is about coherence; visual screenshots at 768/375 don't fail but tablet-landscape would.
- **Root cause (CSS):** Two different breakpoints in `styles.css` — sidebar hidden at <=768, sidebar-toggle visible at <=1024. The topbar `left: var(--sidebar-width)` only overridden at <=768.
- **Recommended fix:** Align breakpoints; expand the topbar `left:0` rule (and main-content margin/padding) to track sidebar visibility:
```css
@media (max-width: 1024px) {
    .cnx-app-topbar { left: 0; }
}
@media (max-width: 768px) {
    /* existing rule kept */
}
```
Also add corresponding margin-left:0 to `.main-content` at the same width to prevent dead-space:
```css
@media (max-width: 1024px) {
    .main-content { margin-left: 0; }
}
```
(Currently `.main-content` only loses the margin at <=768 in `styles.css:1560-1564`.)

### F8 — DENOMINAZIONE column wraps the avatar circle into a 2-row card on mobile
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.company-info-cell` (file: `aziende.php:251-285`)
- **Screenshot region:** "DENOMINAZIONE" cells
- **Current behaviour:** Avatar (AG/AC/AP) sits horizontally next to wrapped 2-line names ("Agrumi Gel s.n.c", "Analisi Cliniche Amenta"); when card-style reflow (F3) is applied, this becomes a tight 2-line block which is acceptable but the avatar should remain inline.
- **Root cause (CSS):** Inside the card-reflow `td:flex; justify-content: space-between`, the `.company-info-cell` (already `display: flex; align-items: center; gap: var(--space-3)`) collapses correctly; needs an explicit override to stay inline.
- **Recommended fix:** When applying F3, ensure:
```css
@media (max-width: 768px) {
    .companies-table td .company-info-cell {
        flex: 1 1 auto;
        gap: 12px;
    }
    .companies-table td .company-avatar-table {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
}
```

### F9 — STATO badge competes for space with AZIONI in single AZIONI cell on tablet
- **Severity:** minor
- **Breakpoint:** 768px
- **Selector / element:** `.status-badge` + `.action-buttons` (file: `aziende.php:317-377`)
- **Screenshot region:** rightmost columns at tablet portrait
- **Current behaviour:** At 768px portrait, the AZIONI column is fully cut off-screen because STATO column ("• Attivo" badges) consumes too much horizontal real estate. Card reflow (F3) resolves this — flagged for completeness.
- **Root cause (CSS):** Column count (8) > available width / viewport at 768px. Already covered by F3.
- **Recommended fix:** F3 fix is sufficient. No additional CSS needed.

### F10 — Page-content padding insufficient on mobile (content hugs viewport edges)
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.page-content` (file: `assets/css/styles.css:1373-1377`, override at `1570-1573`)
- **Screenshot region:** entire page-content
- **Current behaviour:** `@media (max-width: 768px) { .page-content { padding: var(--space-6) var(--space-4); } }` is `24px 16px`. Acceptable but feels tight on 375px.
- **Root cause (CSS):** Single media-query with no narrower step.
- **Recommended fix:**
```css
@media (max-width: 480px) {
    .page-content {
        padding: 16px 12px;
    }
}
```

### F11 — `.flex.items-center.gap-4` (header subtitle wrapper) lacks min-width:0 — risks subtitle clipping not text-wrapping
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.header > .flex.items-center.gap-4` (file: `aziende.php:732`)
- **Screenshot region:** header subtitle
- **Current behaviour:** The flex utility classes don't include `min-width: 0`, so the inner `.text-muted` span behaves as a non-shrinkable atom (text in `<span>` defaults to `min-width: auto` = content width), forcing it to overflow rather than wrap.
- **Root cause (CSS):** Tailwind-style utility classes `.flex` `.items-center` `.gap-4` from `styles.css:1439-1457` don't set `min-width`. The header normalisation in `components.css:78` only adds `min-width: 0` to `.header-left` (which is not used in aziende.php — it uses `<h1>` directly without `.header-left` wrapper).
- **Recommended fix:**
```css
.main-content > .header > .flex,
.main-content > header.header > .flex {
    min-width: 0;
    flex: 1 1 auto;
}
@media (max-width: 768px) {
    .main-content > .header > .flex.items-center.gap-4 {
        flex-wrap: wrap;
        flex: 1 1 100%;
    }
    .main-content > .header > .flex.items-center.gap-4 > .text-muted {
        white-space: normal;
        overflow-wrap: anywhere;
    }
}
```
