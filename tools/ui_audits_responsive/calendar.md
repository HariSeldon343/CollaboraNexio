# Responsive audit — calendar.php

## Summary
- Total findings: 14
- Severity: critical=5, major=6, minor=3
- Worst breakpoint: 375px

## Findings

### F1 — `.main-content` keeps `margin-left: 260px` ONLY zeroed below 768px → tablet portrait (768px) still pushed off-canvas
- **Severity:** critical
- **Breakpoint:** 768px
- **Selector / element:** `.main-content` (file: `assets/css/styles.css:1209-1220` and `assets/css/styles.css:1551-1564`)
- **Screenshot region:** entire page — visible scroll bar at the right of `tablet_portrait/calendar.png`, content cropped, "Benvenuto, Antonio Amodeo" floats unanchored at the right.
- **Current behaviour:** at exactly 768px the media query `@media (max-width: 768px)` **excludes** 768px (it triggers at <=768), so a 768-wide tablet still has `margin-left: 260px` while the sidebar IS still visible — content available width is only 508px. Worse, the sidebar fixed width 260px + topbar fixed `left: 260px` consume budget BUT the `.cnx-app-topbar` (components.css:46-49) flips `left: 0` already at 768px, creating an 80-100px misalignment between topbar (full width) and main content (still indented). Net effect on tablet: horizontal scrollbar + content truncation on the right.
- **Root cause (CSS):** asymmetric breakpoints — topbar uses `(max-width: 768px)` but sidebar still renders at `<=1024px` per components.css `.sidebar-toggle` rule, while `.main-content` margin reset is also at 768px max. The 768px boundary is INCLUSIVE of the breakpoint but the sidebar is NOT actually hidden at that exact width (no rule does it).
- **Recommended fix:** unify breakpoint to `(max-width: 1024px)` so tablet portrait gets sidebar-as-drawer:
```css
@media (max-width: 1024px) {
  .main-content { margin-left: 0 !important; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .cnx-app-topbar { left: 0; }
}
```

### F2 — Page horizontal overflow on mobile (right-edge scroll bar visible in `mobile/calendar.png`)
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.main-content`, `.page-content`, `#calendar-container` children (file: `assets/css/calendar.css:194` `.calendar-grid-container`)
- **Screenshot region:** entire viewport — vertical scrollbar visible on the FAR RIGHT edge AND content (DOM/LUN/MAR/MER/GIO/VEN/SAB header) extends past the right edge, "SAB" is partly cut off.
- **Current behaviour:** at 375px the SAB column is clipped, scrollbar shows. The `.page-content` rule at styles.css:1570 sets `padding: var(--space-6) var(--space-4)` (=`24px 16px`), but `.calendar-grid` is `grid-template-columns: repeat(7, 1fr)` with `min-height: 600px` and 100px-min cells — combined with 32px horizontal padding the inner width = 343px / 7 = ~49px per cell, but inner event chips have `white-space: nowrap` (calendar.css:362) and overflow forces parent to grow.
- **Root cause (CSS):** `.calendar-event { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }` works only when parent has `min-width: 0`. The grid-track `1fr` does NOT auto-shrink unless track is `minmax(0, 1fr)`.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .calendar-grid,
  .calendar-month-grid,
  .calendar-weekdays {
    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
  }
  .calendar-day { min-width: 0; overflow: hidden; }
}
```

### F3 — Toolbar elements pile vertically into a wasteful 8-row stack consuming ~50% of viewport
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `#calendar-toolbar.calendar-header` (file: `assets/css/calendar.css:32-37` and `:73-87`)
- **Screenshot region:** card area between page header and the 7-day grid — contains "+ Nuovo / Mese / Settimana / Giorno / ◀ Oggi ▶ / maggio 2026 / Calendari (9) / [✓Eventi ☐Turni] / Cerca eventi… / [📁] / [⬆️] [⬇️]" each on its own row.
- **Current behaviour:** `flex-wrap: wrap` + `gap: 12px` + per-section `width: 100%` (line 64-66) collapses every section into a full-width row. That's 11 rows on 375px wasting hundreds of vertical pixels before the grid starts.
- **Root cause (CSS):** `#calendar-toolbar .toolbar-section { width: 100%; }` at calendar.css:65 forces every group to expand. View buttons (Mese/Settimana/Giorno) duplicate the legacy `.calendar-view-selector` already in the page header.
- **Recommended fix:** keep groups inline-wrapping; collapse the duplicate view-selector block inside the toolbar (it already exists in the header per calendar.php:134-138). Hide the inline duplicate on mobile.
```css
@media (max-width: 768px) {
  #calendar-toolbar .toolbar-section { width: auto !important; }
  #calendar-toolbar .view-switcher,           /* already in header */
  #calendar-toolbar .toolbar-section--views { display: none; }
  #calendar-toolbar .search-box { width: 100%; flex: 1 1 100%; }
  #calendar-toolbar .current-date { font-size: 14px; }
  #calendar-toolbar { padding: 12px !important; gap: 8px !important; }
}
```

### F4 — "+ Nuovo" CTA full-width single-row on mobile while next/prev buttons end up 80px wide each
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `#calendar-toolbar .btn` and `#calendar-toolbar .calendar-nav-btn` (file: `assets/css/calendar.css:106-118`)
- **Screenshot region:** "+ Nuovo" mint pill, then "◀ Oggi ▶" trio.
- **Current behaviour:** "+ Nuovo" sits alone on first row at full width; subsequent navigation buttons are 36×36 with 12px gap consuming 3 rows.
- **Root cause (CSS):** no `flex-direction: row` constraint on `.toolbar-section--actions`; flex-wrap separates items.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  #calendar-toolbar .toolbar-section { display: inline-flex; flex-wrap: nowrap; }
  #calendar-toolbar .calendar-nav { display: inline-flex; gap: 6px; }
}
```

### F5 — Search input "Cerca eventi…" stretches full width and the icon button next to it wraps onto its own row
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `#calendar-toolbar .search-box` + adjacent download icon button (file: `assets/css/calendar.css:79-81`)
- **Screenshot region:** input field row + 1 icon button below.
- **Current behaviour:** `width: 100%` on the search box pushes the trailing icon-button onto a new flex row, splitting a logical input-group across two visual rows.
- **Root cause (CSS):** `#calendar-toolbar .search-box { width: 100%; }` ignores the sibling button.
- **Recommended fix:** wrap input and trailing button into one flex row:
```css
@media (max-width: 768px) {
  #calendar-toolbar .toolbar-search-group {
    display: flex; gap: 6px; width: 100%;
  }
  #calendar-toolbar .toolbar-search-group .search-box { flex: 1 1 auto; min-width: 0; }
}
```
**REQUIRES MARKUP CHANGE** if the input + icon are not already wrapped together; if they are sibling toolbar-section children, instead use:
```css
#calendar-toolbar .search-box { width: calc(100% - 60px) !important; }
```

### F6 — "Calendari ( 9 )" chip splits text — extra spaces around 9, no badge styling
- **Severity:** minor
- **Breakpoint:** both (375 + 768)
- **Selector / element:** calendar list toggle chip in toolbar (rendered by `calendar.js`)
- **Screenshot region:** between nav buttons and Eventi/Turni checkboxes
- **Current behaviour:** the count is rendered as plain inline text "Calendari ( 9 )" — text-only with stretched parens, low information hierarchy.
- **Root cause (CSS):** no `.cnx-badge` applied to the count; falls back to default link color (mint) at body size.
- **Recommended fix:** style to `.cnx-badge` and shrink:
```css
#calendar-toolbar .calendars-toggle .calendars-count {
  display: inline-flex; align-items: center; height: 18px; padding: 0 6px;
  border-radius: var(--cnx-radius-pill);
  background: var(--cnx-accent-soft); color: var(--cnx-accent-ink);
  font-size: 11px; margin-left: 4px;
}
```

### F7 — "Eventi" / "Turni" checkboxes — 14×14 default browser checkbox, untappable < 44px touch target
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `#calendar-toolbar .toolbar-checkbox input[type="checkbox"]` (rendered by JS, no dedicated CSS)
- **Screenshot region:** below "Calendari ( 9 )" — "[✓] Eventi  [ ] Turni"
- **Current behaviour:** native checkbox at default 14×14 px is below the 44×44 minimum touch target; labels also too small.
- **Root cause (CSS):** no overrides; native UA styling.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  #calendar-toolbar label.toolbar-checkbox {
    display: inline-flex; align-items: center; gap: 8px;
    min-height: 44px; padding: 0 8px;
    font-size: 14px;
  }
  #calendar-toolbar label.toolbar-checkbox input[type="checkbox"] {
    width: 20px; height: 20px;
  }
}
```

### F8 — Month grid cells ~49px wide → event chips like "07:30 gita Mustafa, Mohamed e Yousef" overflow horizontally
- **Severity:** critical
- **Breakpoint:** 375px (also 768px in cells where day has 3+ events)
- **Selector / element:** `.calendar-grid > .calendar-day > .calendar-day-events > .calendar-event` (file: `assets/css/calendar.css:286-296` and `:352-368`)
- **Screenshot region:** the green/blue/orange chips inside the month cells, `mobile/calendar.png` row 1 ("27 28 29..."): "07:30 gita Mustafa, Mohamed e Yousef" extends WAY past the day-27 cell.
- **Current behaviour:** with `white-space: nowrap` and a fixed-text title, the chip becomes wider than its parent grid track → overflows visibly INTO the neighbouring cells. Title "gita Mustafa, Mohamed e Yousef" is ~250px and a 7-col 343px grid yields 49px tracks.
- **Root cause (CSS):** combination of (a) F2 root cause (no `minmax(0, 1fr)`) and (b) `.calendar-event` lacks `max-width: 100%` and `min-width: 0`.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .calendar-day-events { min-width: 0; }
  .calendar-event {
    max-width: 100%; min-width: 0;
    padding: 2px 4px; font-size: 10px; line-height: 1.2;
  }
  .calendar-event .event-time { display: none; }   /* keep title only */
  .calendar-event .event-icon { display: none; }
}
@media (max-width: 480px) {
  /* Even more aggressive: dot-only mode */
  .calendar-event {
    height: 6px; width: 6px; border-radius: 50%;
    padding: 0; overflow: hidden; text-indent: -9999px;
    display: inline-block; margin: 1px;
  }
  .calendar-day-events { flex-direction: row; flex-wrap: wrap; gap: 2px; }
}
```
**Better UX:** at 375px collapse month-view to an agenda list (next finding F9).

### F9 — Month-view fundamentally unusable at 375px — should fall back to "agenda list"
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.calendar-month-view`, `.calendar-grid` (file: `assets/css/calendar.css:263-267`)
- **Screenshot region:** entire grid area below toolbar.
- **Current behaviour:** even with the F8 dot-mode fix, 7×6 month grid at 375px shows ~49px-wide cells with cramped numbers and unreadable event content; navigation between weeks requires scroll.
- **Root cause (CSS):** no breakpoint-specific view switch; the JS `CalendarApp` always renders month grid regardless of viewport.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** (or JS): force "week" or "agenda" view < 600px. CSS-only mitigation, hide the grid and show an agenda-style fallback message:
```css
@media (max-width: 480px) {
  .calendar-month-view .calendar-grid { display: none; }
  .calendar-month-view::after {
    content: "Vista Mese non disponibile su mobile. Passa a 'Settimana' o 'Giorno'.";
    display: block; padding: 32px 16px; text-align: center;
    color: var(--cnx-text-muted); font-size: 14px;
  }
}
```
Better: add JS in `CalendarApp.init()` to call `changeView('day')` when `matchMedia('(max-width: 480px)').matches`.

### F10 — Week-view scrolls horizontally with min-width 700px — sticky time-column rule references undefined classes
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.calendar-week-view`, `.calendar-time-column*` (file: `assets/css/calendar.css:925-963`)
- **Screenshot region:** N/A in current screenshots (Mese is active) but discoverable via toggling Settimana.
- **Current behaviour:** the rule sets `.calendar-time-column-header / -allday / "" { position: sticky; left: 0 }` but the JS-rendered markup uses `.calendar-week-time-column` / `.calendar-time-label` (per `CalendarApp.renderWeekView()`). The selector doesn't match → no sticky time gutter, time labels scroll out of view.
- **Root cause (CSS):** selector mismatch with actual DOM produced by `assets/js/calendar.js`.
- **Recommended fix:** add aliased selectors:
```css
@media (max-width: 768px) {
  .calendar-week-view .calendar-week-time-column,
  .calendar-week-view .time-label,
  .calendar-week-view .calendar-time-label {
    position: sticky; left: 0; z-index: 50;
    background: var(--cnx-bg-app);
    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
  }
}
```

### F11 — Sidebar visible at 768px tablet (sidebar still anchored, no hamburger toggle button rendered)
- **Severity:** critical
- **Breakpoint:** 768px (and below)
- **Selector / element:** `.sidebar` (no rule hides it < 768px in any non-`@media print` context). The advertised "hamburger toggle" in the prompt does NOT exist in `assets/css/sidebar-responsive.css` (Grep for `hamburger|sidebar-toggle|menu-toggle` returns zero matches in that file).
- **Screenshot region:** in `tablet_portrait/calendar.png` the sidebar is OFFSCREEN-LEFT but `main-content` still has `margin-left: 260px`, leaving an empty 260px gutter on the LEFT and content overflowing on the RIGHT. In `mobile/calendar.png` same: no visible sidebar but no toggle to open it either.
- **Current behaviour:** `styles.css:1551-1564` only sets `transform: translateX(-100%)` on `.sidebar` at <=768px AND zeroes `main-content margin-left: 0`, but at exactly 768.5px (typical tablet) we're in no-man's-land. Plus there's NO `.open` toggle button visible.
- **Root cause (CSS + missing markup):** the only `.sidebar-toggle` rule (`styles.css:1258-1283`) sets `display: none` and only `display: flex` < 1024px, but no `.sidebar-toggle` element appears in `includes/layout_start.php` or `sidebar.php`.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** — add `<button class="sidebar-toggle" aria-label="Apri menu" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>` inside `.cnx-app-topbar` (left side). CSS:
```css
@media (max-width: 1024px) {
  .sidebar-toggle { display: inline-flex !important; }
  .sidebar { transform: translateX(-100%); transition: transform 200ms ease; z-index: 300; }
  .sidebar.open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.5); }
  .main-content { margin-left: 0 !important; }
}
```

### F12 — Page header "Calendario" + view-switcher pill "Mese / Settimana / Giorno" + Company filter overflow horizontally on mobile
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.main-content > .header > .flex` (file: `calendar.php:130-145`, styled by `assets/css/components.css:58-92`)
- **Screenshot region:** in `mobile/calendar.png` the title "Calendario" wraps to row 1, then "Mese / Settimana / Giorno" view-pill is clipped on the right ("Settimana" cut). "Azienda: Tutte le" partly visible on the right edge then sliced.
- **Current behaviour:** `.flex.items-center.gap-4` keeps everything in a flex-row that overflows the header. components.css already wraps `.header` (line 58-70) but doesn't recurse into the inline `.flex` group from calendar.php.
- **Root cause (CSS):** Tailwind-style `.flex.items-center.gap-4` lacks `flex-wrap`.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .main-content > .header .flex.items-center {
    flex-wrap: wrap !important;
    gap: 8px !important;
    width: 100%;
  }
  .calendar-view-selector { flex: 1 1 100%; justify-content: flex-start; }
  .calendar-view-selector .calendar-view-btn {
    flex: 1 1 33%; min-height: 40px;
  }
  .header .text-sm.text-muted { display: none; } /* hide "Benvenuto, Antonio" */
}
```

### F13 — Day-name headers (DOM/LUN/MAR/MER/GIO/VEN/SAB) on mobile use 11px Inter and the SAB column gets clipped
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.calendar-weekday` (file: `assets/css/calendar.css:276-284`)
- **Screenshot region:** weekday strip just above month grid in `mobile/calendar.png`.
- **Current behaviour:** with the grid-overflow bug (F2), the SAB column is the visible casualty.
- **Root cause (CSS):** related to F2; also `padding: var(--space-3) var(--space-2)` (`12px 8px`) is too generous on a 49px column.
- **Recommended fix:**
```css
@media (max-width: 480px) {
  .calendar-weekday {
    padding: 6px 0; font-size: 9px; letter-spacing: 0;
  }
}
```

### F14 — "Oggi" pill (small mint button) sits at 36×36 → reads as decorative not as today-jump CTA
- **Severity:** minor
- **Breakpoint:** both
- **Selector / element:** `.calendar-today-btn` (rendered by JS) inside `#calendar-toolbar`
- **Screenshot region:** between ◀ and ▶ navigation arrows.
- **Current behaviour:** mint pill with text "Oggi" cropped (4-letter word in a 32-36px button). Visual mass equal to nav arrows so users may not realize it's the "jump to today" button.
- **Root cause (CSS):** generic `.calendar-nav-btn` styling (calendar.css:106-118).
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .calendar-today-btn,
  #calendar-toolbar .btn-today {
    width: auto; padding: 0 14px; height: 36px;
    min-width: 56px;
  }
}
```
