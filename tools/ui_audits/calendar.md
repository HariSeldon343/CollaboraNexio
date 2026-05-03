# Dark mode audit — calendar.php

Screenshot reviewed: `tools/ui_redesign/dark_round3/calendar.png`
Sources reviewed: `calendar.php`, `assets/css/calendar.css`, `assets/css/styles.css`, `assets/css/components.css`, `assets/js/calendar.js` (toolbar render @ line 3550-3647).

## Summary
- Total findings: 24
- Severity breakdown: critical=7, major=11, minor=6

## Findings

### F1 — Page-header view selector (Mese/Settimana/Giorno) sits on a white pill in dark mode
- **Severity:** critical
- **Selector / element:** `.calendar-view-selector.bg-gray-100` rendered in `<div class="header">` (file: `calendar.php:134`); utility rule `.bg-gray-100 { background-color: var(--color-gray-100); }` (file: `assets/css/styles.css:1447`)
- **Screenshot region:** top-right of the page header, the white rounded pill containing the three view buttons.
- **Current behaviour (dark mode):** the pill background stays nearly white because `--color-gray-100` is overridden to `#1B2F33` only for "subtle" use, but the inline classes `bg-gray-100` and Tailwind-like `text-gray-600 hover:text-gray-900` still resolve to bright values. The result is a luminous white capsule clashing against the `#0E1A1D` page bg.
- **Root cause (CSS):** Markup uses two utility classes (`bg-gray-100`, `text-gray-600`, `text-gray-900`) on the inline view selector. While `--color-gray-100` IS remapped in the dark theme block, the literal calls in `.calendar-view-btn.active { background: var(--color-white); ... }` (`calendar.css:158`) and the inline `bg-gray-100` produce an awkward bright pill because `--color-white` is forced to `#15262A` but `bg-gray-100` resolves to a subtle `#1B2F33` — and the active button on top still uses `--color-white` so it appears as a brighter card on the pill. The contrast inversion is wrong (active button should sit on a darker track, not the same color).
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-view-selector,
[data-theme="dark"] .calendar-view-selector.bg-gray-100 {
    background: var(--cnx-bg-subtle) !important;
    border: 1px solid var(--cnx-border);
}
[data-theme="dark"] .calendar-view-btn { color: var(--cnx-text-secondary); }
[data-theme="dark"] .calendar-view-btn:hover { color: var(--cnx-text-primary); }
[data-theme="dark"] .calendar-view-btn.active {
    background: var(--cnx-bg-surface);
    color: var(--cnx-text-primary);
    box-shadow: var(--cnx-shadow-sm);
}
```

### F2 — Toolbar "Mese / Settimana / Giorno" buttons read as grey-on-grey, with "Mese" mostly indistinguishable from non-active
- **Severity:** critical
- **Selector / element:** `#calendar-toolbar .view-switcher .btn` and `#calendar-toolbar .view-switcher .btn.active` injected by `CalendarToolbar.render()` (file: `assets/js/calendar.js:3569-3573`).
- **Screenshot region:** second row in toolbar — three pill buttons "Mese / Settimana / Giorno" (where "Mese" should be active).
- **Current behaviour (dark mode):** ALL three buttons render as identical dark-grey rectangles with white text. The `.active` state is invisible because `.btn.active` has no rule and the bare `.btn` defaults to a transparent border + no background, so they pick up whatever bg they sit on — there is no visual hierarchy between active and inactive.
- **Root cause (CSS):** `.btn` (file: `assets/css/styles.css:399`) is a base shell with `border: 1px solid transparent` and no background. The toolbar JS only adds `.active` (no variant class) and there is NO `.btn.active` rule anywhere. In dark mode this means three indistinguishable grey buttons.
- **Recommended fix:**
```css
#calendar-toolbar .view-switcher {
    background: var(--cnx-bg-subtle);
    border: 1px solid var(--cnx-border);
    border-radius: var(--cnx-radius-pill);
    padding: 4px;
    gap: 2px;
}
#calendar-toolbar .view-switcher .btn {
    background: transparent;
    color: var(--cnx-text-secondary);
    border-radius: var(--cnx-radius-pill);
    border: 1px solid transparent;
}
#calendar-toolbar .view-switcher .btn:hover {
    color: var(--cnx-text-primary);
}
#calendar-toolbar .view-switcher .btn.active {
    background: var(--cnx-bg-surface);
    color: var(--cnx-text-primary);
    box-shadow: var(--cnx-shadow-sm);
}
```

### F3 — "+ Nuovo" primary button reads as washed-out / disabled-looking mint
- **Severity:** major
- **Selector / element:** `<button class="btn btn-primary">+ Nuovo</button>` (file: `assets/js/calendar.js:3563`); rule at `components.css:206-217`
- **Screenshot region:** top-left of the toolbar block.
- **Current behaviour (dark mode):** The button uses the new mint `--cnx-accent` (#5FCAD3) with ink color `--cnx-accent-ink` (#0F2D31). Against the `#0E1A1D` page bg the contrast is OK, but the button's interior looks faded / pastel because no border-glow / shadow is applied and the ink-on-mint at small sizes feels low-contrast for a primary CTA.
- **Root cause (CSS):** The override at `components.css:208-211` forces `background-color: var(--cnx-accent)` but does NOT apply `--cnx-shadow-sm` or any depth in dark mode, so the button looks like a flat rectangle. The mint→ink combination is correct but lacks elevation cue against the dark surface.
- **Recommended fix:**
```css
[data-theme="dark"] .btn-primary,
[data-theme="dark"] .btn.btn-primary {
    box-shadow: 0 0 0 1px rgba(95, 202, 211, 0.18), var(--cnx-shadow-md);
}
[data-theme="dark"] .btn-primary:hover {
    box-shadow: 0 0 0 1px rgba(95, 202, 211, 0.32), var(--cnx-shadow-md);
}
```

### F4 — "Oggi" button looks deactivated (low contrast grey-on-grey)
- **Severity:** major
- **Selector / element:** `<button class="btn btn-outline">Oggi</button>` (file: `assets/js/calendar.js:3580`); `.btn-outline` rule at `components.css:225-230`
- **Screenshot region:** middle of toolbar between the prev/next arrows.
- **Current behaviour (dark mode):** The `.btn-outline` rule only sets `color: var(--cnx-accent)` but inherits `.btn` defaults (transparent bg, transparent border). On the dark `#15262A` toolbar surface the mint text is OK, but it has no visible chrome — looks like a label, not a button.
- **Root cause (CSS):** `.btn-outline` (`components.css:225`) doesn't set a border. In dark mode there is no contrast cue (the bg is the same as the toolbar card).
- **Recommended fix:**
```css
.btn-outline {
    background: transparent;
    border: 1px solid var(--cnx-border-strong);
}
[data-theme="dark"] .btn-outline {
    border-color: var(--cnx-border-strong);
    color: var(--cnx-accent) !important;
}
[data-theme="dark"] .btn-outline:hover {
    background: var(--cnx-accent-soft);
    border-color: var(--cnx-accent);
}
```

### F5 — Prev/Next arrow buttons (◀ ▶) have a barely-visible outline and look ghosted
- **Severity:** major
- **Selector / element:** `<button class="btn btn-icon">◀</button>` (file: `assets/js/calendar.js:3577,3583,3637-3645`)
- **Screenshot region:** prev/next/Today cluster + the print/import/export icon trio on the right.
- **Current behaviour (dark mode):** `.btn-icon` is not defined in either `styles.css` or `components.css` legacy. The buttons render as bare `.btn` with the unicode glyph — same flat grey-on-grey result as F2. The arrows are grey unicode characters on a grey button with no border definition.
- **Root cause (CSS):** No rule for `.btn-icon` exists in `styles.css` (only `.cnx-btn--icon` in components.css line 449 with `cnx-btn` namespace). The legacy `.btn-icon` falls through to the bare `.btn` defaults.
- **Recommended fix:**
```css
.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    background: var(--cnx-bg-subtle);
    border: 1px solid var(--cnx-border);
    color: var(--cnx-text-secondary);
}
.btn-icon:hover {
    background: var(--cnx-bg-surface);
    border-color: var(--cnx-border-strong);
    color: var(--cnx-text-primary);
}
[data-theme="dark"] .btn-icon {
    background: var(--cnx-bg-subtle);
    border-color: var(--cnx-border);
}
```

### F6 — Toolbar card has a near-black bg with no distinction from page bg; container "floats"
- **Severity:** major
- **Selector / element:** `#calendar-toolbar.calendar-header` (file: `assets/css/calendar.css:32`, base `.calendar-header` at line 17 uses `var(--color-white)`)
- **Screenshot region:** the entire toolbar wrapper containing all controls.
- **Current behaviour (dark mode):** The toolbar card bg is `--color-white` which is remapped to `#15262A` (a charcoal). Visually it now blends into the page-content area which uses `--cnx-bg-app` (#0E1A1D) — the differentiation is only ~6 luminance points so the toolbar card barely separates from the background.
- **Root cause (CSS):** `.calendar-header { background: var(--color-white); border: 1px solid var(--color-gray-200); }` at `calendar.css:18-21`. In dark mode `--color-gray-200` becomes `#22363B` — the border is nearly invisible, and there is no shadow.
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-header,
[data-theme="dark"] #calendar-toolbar.calendar-header {
    background: var(--cnx-bg-surface);
    border: 1px solid var(--cnx-border);
    box-shadow: var(--cnx-shadow-sm);
}
```

### F7 — Search input "Cerca eventi…" has invisible border and pure-white placeholder
- **Severity:** major
- **Selector / element:** `<input type="text" class="search-box" placeholder="Cerca eventi...">` (file: `assets/js/calendar.js:3633`); `.search-box` style is NOT defined globally — it inherits browser defaults.
- **Screenshot region:** bottom-left of toolbar, the input that says "Cerca eventi…".
- **Current behaviour (dark mode):** Border is hairline-thin, almost the same color as the toolbar card; placeholder text reads in default browser color. The input has white background in some browsers, dark in others — it is browser-default-styled.
- **Root cause (CSS):** No rule for `.search-box` exists in any CSS file. The element falls back to user-agent styling.
- **Recommended fix:**
```css
.search-box,
#calendar-toolbar .search-box {
    height: 36px;
    padding: 0 14px;
    background: var(--cnx-bg-subtle);
    border: 1px solid var(--cnx-border);
    border-radius: var(--cnx-radius-md);
    color: var(--cnx-text-primary);
    font-size: var(--cnx-text-body-size);
    transition: var(--cnx-transition);
}
.search-box::placeholder { color: var(--cnx-text-muted); }
.search-box:focus {
    outline: none;
    border-color: var(--cnx-accent);
    box-shadow: var(--cnx-shadow-focus);
    background: var(--cnx-bg-surface);
}
```

### F8 — Event chips are over-saturated neon (lime-green / electric-blue / magenta) clashing with mint accent
- **Severity:** critical
- **Selector / element:** `.calendar-event` rendered with `style="background: ${event.color}"` (file: `assets/js/calendar.js:1781-1782`). Hex literals come from server-stored event colors.
- **Screenshot region:** all event chips inside calendar cells (e.g. "07:30 gita Mustafa..." in lime green, "10:00 equipe" in magenta, "15:00 Corso Dasit..." in cyan).
- **Current behaviour (dark mode):** Server returns full-saturation hex (e.g. `#00FF00`, `#FF00FF`, `#0080FF`) which the JS forces directly via inline style. In dark mode these neon backgrounds with pure-white text vibrate harshly and break the calm mint/teal palette of the design system. The chips also bleed past their cells (overflow visible).
- **Root cause (CSS):** `div.style.background = event.color;` (`calendar.js:1782`) uses raw hex. There is no dark-mode tonal adjustment.
- **Recommended fix:** Wrap the event chip in a luminance filter for dark mode so the saturated colors get dampened uniformly. CSS-only:
```css
[data-theme="dark"] .calendar-event {
    /* Dampen saturated user-chosen colors so the calendar reads calm */
    filter: saturate(0.72) brightness(0.85);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
}
[data-theme="dark"] .calendar-event:hover {
    filter: saturate(0.85) brightness(0.95);
}
```
Optional follow-up: introduce a `--cnx-event-tonal-overlay` token + `mix-blend-mode` if a fully designer-driven solution is needed. **Note:** the JS inline style still wins over CSS rules unless we use `filter` (which composes), so this is the only CSS-only path.

### F9 — Event chip text loses contrast on light-yellow / light-cyan colors
- **Severity:** major
- **Selector / element:** `.calendar-event` text rendered with `color: white` baked into `.calendar-event` (file: `assets/css/calendar.css:355`).
- **Screenshot region:** chips with light backgrounds like the cyan "Corso Dasit - Giuseppe" / pale-green ones — white text on light-saturation chip = barely readable.
- **Current behaviour (dark mode):** Some user-chosen background colors are pale; with `color: var(--color-white)` the text becomes nearly invisible.
- **Root cause (CSS):** `.calendar-event { color: var(--color-white); }` at `calendar.css:355`. In dark mode `--color-white` is remapped to `#15262A` (almost black), so on dark-saturated chips the text now ALSO becomes black-on-dark-color = unreadable.
- **Recommended fix:**
```css
.calendar-event {
    /* Force white text regardless of theme, to keep contrast on user-colored chips */
    color: #FFFFFF !important;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.18);
}
```

### F10 — Calendar grid lines are nearly invisible (border `--color-gray-100` ~ `#1B2F33` on `#15262A` cell bg)
- **Severity:** critical
- **Selector / element:** `.calendar-month-grid .calendar-day { border-right: 1px solid var(--color-gray-100); border-bottom: 1px solid var(--color-gray-100); }` (file: `assets/css/calendar.css:253-256`)
- **Screenshot region:** entire month-grid — no visible cell separators between days.
- **Current behaviour (dark mode):** `--color-gray-100` is remapped to `#1B2F33` while `.calendar-day` background is `--color-white` = `#15262A`. The two values differ by ~3 luminance points → cell borders are essentially invisible. The grid feels like one big black soup.
- **Root cause (CSS):** Borders use `--color-gray-100` which is tuned for "subtle bg" not "border on top of surface". Need a stronger token in dark mode.
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-month-grid .calendar-day,
[data-theme="dark"] .calendar-grid-hour,
[data-theme="dark"] .calendar-week-day-column {
    border-color: var(--cnx-border) !important;
}
[data-theme="dark"] .calendar-week-day-header,
[data-theme="dark"] .calendar-week-allday-cell,
[data-theme="dark"] .calendar-time-column-allday {
    border-color: var(--cnx-border) !important;
}
```

### F11 — Weekday header bar (DOM/LUN/MAR/MER…) has light grey bg in dark mode
- **Severity:** major
- **Selector / element:** `.calendar-month-header { background: var(--color-gray-50); }` (file: `assets/css/calendar.css:212`); same on `.calendar-week-header` (line 541) and `.calendar-day-header` (line 687).
- **Screenshot region:** horizontal strip showing "DOM LUN MAR MER GIO VEN SAB" above the grid.
- **Current behaviour (dark mode):** The strip looks paler than the cells below (`--color-gray-50` → `#1B2F33` is slightly lighter than `--color-white` → `#15262A`, but the contrast goes the WRONG way: in light mode the header is darker than cells, in dark mode it's brighter — feels inverted).
- **Root cause (CSS):** `--color-gray-50` is remapped to `#1B2F33` (subtle bg), so in dark mode the header now sits ABOVE cells in luminance.
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-month-header,
[data-theme="dark"] .calendar-week-header,
[data-theme="dark"] .calendar-time-column-header,
[data-theme="dark"] .calendar-time-column-allday,
[data-theme="dark"] .calendar-day-header {
    background: var(--cnx-bg-subtle) !important;
    border-bottom-color: var(--cnx-border) !important;
}
[data-theme="dark"] .calendar-day-name,
[data-theme="dark"] .calendar-week-day-header .day-name {
    color: var(--cnx-text-muted);
}
```

### F12 — Calendar wrapper card has no visible boundary
- **Severity:** minor
- **Selector / element:** `.calendar-wrapper`, `.calendar-month`, `.calendar-week-view`, `.calendar-day-view` — all use `background: var(--color-white); border: 1px solid var(--color-gray-200);`
- **Screenshot region:** the rounded card containing the entire month grid.
- **Current behaviour (dark mode):** Same root issue as F6: the card bg `#15262A` and border `#22363B` differ only marginally from the page bg `#0E1A1D`, no shadow exists. The card outline is visible only under careful inspection.
- **Root cause (CSS):** `--color-gray-200` → `#22363B` is the border color, which is appropriately subtle. But there is no shadow elevation in dark mode.
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-wrapper,
[data-theme="dark"] .calendar-month,
[data-theme="dark"] .calendar-week-view,
[data-theme="dark"] .calendar-day-view {
    border-color: var(--cnx-border);
    box-shadow: var(--cnx-shadow-sm);
}
```

### F13 — `Calendari ( 9 )` button wrapped in `<summary class="btn btn-outline">` shows mint text but no button shape
- **Severity:** major
- **Selector / element:** `<summary class="btn btn-outline calendar-calendars-summary">` (file: `assets/js/calendar.js:3591`)
- **Screenshot region:** top-right area of toolbar, "Calendari ( 9 )" label.
- **Current behaviour (dark mode):** Looks like floating mint text with no clickable affordance. Same root cause as F4 — `.btn-outline` only sets a color, no chrome.
- **Root cause (CSS):** `.btn-outline` rule at `components.css:225-230` is incomplete (no border).
- **Recommended fix:** Same as F4 (`.btn-outline` global fix). No additional CSS needed if F4 is applied.

### F14 — "Eventi / Turni" checkbox filters: white check, no visible label-pill chrome
- **Severity:** minor
- **Selector / element:** `.filter-option { display:flex; gap:6px; align-items:center; }` (inline style at `calendar.js:3621`)
- **Screenshot region:** toolbar right-side, "[ ] Eventi [ ] Turni"
- **Current behaviour (dark mode):** Native browser checkboxes render with browser default chrome (white-on-dark in some browsers, white square with no theme integration). The labels next to them ("Eventi", "Turni") are white text — readable but unstyled.
- **Root cause (CSS):** No rule for `.filter-option` or its inputs.
- **Recommended fix:**
```css
#calendar-toolbar .filter-option {
    color: var(--cnx-text-secondary);
    font-size: var(--cnx-text-small-size);
    padding: 6px 10px;
    border-radius: var(--cnx-radius-pill);
    background: var(--cnx-bg-subtle);
    border: 1px solid var(--cnx-border);
}
#calendar-toolbar .filter-option input[type="checkbox"] {
    accent-color: var(--cnx-accent);
}
```

### F15 — Topbar (NEXIO row with global search) has light teal-tinted bg that disagrees with the dark page
- **Severity:** critical
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-33`)
- **Screenshot region:** very top horizontal bar containing the NEXIO logo on left, search field, and theme toggle.
- **Current behaviour (dark mode):** Topbar bg is `var(--cnx-accent-soft)` which in dark mode is `rgba(95, 202, 211, 0.12)` — a ghostly mint over `#0E1A1D` produces a pale-grey-with-greenish-tint band that doesn't match the rest of the dark UI. It looks like the topbar wasn't migrated.
- **Root cause (CSS):** `components.css:29` `background: var(--cnx-accent-soft);` works for light mode (a soft mint band) but dark mode needs an opaque dark-surface with mint-tinted top border.
- **Recommended fix:**
```css
[data-theme="dark"] .cnx-app-topbar {
    background: var(--cnx-bg-app);
    border-bottom: 1px solid var(--cnx-border);
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}
```

### F16 — Topbar global search input has no visible border, looks empty
- **Severity:** major
- **Selector / element:** `.cnx-app-topbar__search .cnx-input` (file: `assets/css/components.css:173-180`)
- **Screenshot region:** centered search field "Cerca file, persone, ticket…".
- **Current behaviour (dark mode):** The input has `border-color: transparent` and uses `--cnx-bg-surface` which in dark mode is `#15262A` — a dark slate that blends into the topbar's tinted bg. The placeholder text floats with no container shape.
- **Root cause (CSS):** Designed for light mode (white pill on mint-soft band). In dark mode the white-on-mint becomes dark-on-dark.
- **Recommended fix:**
```css
[data-theme="dark"] .cnx-app-topbar__search .cnx-input {
    background: var(--cnx-bg-subtle);
    border-color: var(--cnx-border);
}
[data-theme="dark"] .cnx-app-topbar__search .cnx-input::placeholder {
    color: var(--cnx-text-muted);
}
```

### F17 — Theme-toggle pills (sun/moon) appear as identical dark blobs with no active state
- **Severity:** minor
- **Selector / element:** `.cnx-theme-toggle` markup in `includes/sidebar.php` and rules in `styles.css` SHELL section (search for `cnx-theme-toggle`).
- **Screenshot region:** top-right of topbar, two circular icons.
- **Current behaviour (dark mode):** Both icons render as the same dim grey, with the active "moon" icon barely indicated. User cannot tell which mode is active without clicking.
- **Root cause (CSS):** Active-state styling for the toggle button likely targets `[aria-pressed="true"]` but does not give a clearly different bg in dark mode.
- **Recommended fix:**
```css
[data-theme="dark"] .cnx-theme-toggle[aria-pressed="true"] {
    background: var(--cnx-accent-soft);
    color: var(--cnx-accent);
    box-shadow: 0 0 0 1px rgba(95, 202, 211, 0.32);
}
```
**REQUIRES VERIFICATION** of exact selector — please grep `cnx-theme-toggle` in `styles.css` to confirm.

### F18 — Day numbers (1, 2, 3 ... in cells) are dim grey, hard to scan
- **Severity:** minor
- **Selector / element:** `.calendar-day-number { color: var(--color-gray-700); }` (file: `assets/css/calendar.css:340`)
- **Screenshot region:** top-left of every cell ("4", "5", "6"…).
- **Current behaviour (dark mode):** `--color-gray-700` is remapped to `#C5CFD3` — readable but rendered at small size with low weight, feels muted.
- **Root cause (CSS):** Default day-number color uses `--color-gray-700` (medium contrast) but day-of-month is a primary scanning element.
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-day-number {
    color: var(--cnx-text-primary);
    font-weight: 600;
}
[data-theme="dark"] .calendar-day.other-month .calendar-day-number {
    color: var(--cnx-text-muted);
}
```

### F19 — "Other-month" cells (e.g. May 27, 28, 29 from previous month visible at top) are nearly invisible
- **Severity:** minor
- **Selector / element:** `.calendar-day.other-month { background: var(--color-gray-50); color: var(--color-gray-400); }` (file: `assets/css/calendar.css:305-308`)
- **Screenshot region:** very first row of grid, "27 / 28 / 29 / 30" greyed-out cells.
- **Current behaviour (dark mode):** Bg is `#1B2F33` (lighter than `--color-white` cells which are `#15262A`) → in dark mode the "other-month" cells are now BRIGHTER than current-month cells, the opposite of the intended visual hierarchy.
- **Root cause (CSS):** Token semantics flipped in dark mode (gray-50 is now subtle-bg above gray-100/white).
- **Recommended fix:**
```css
[data-theme="dark"] .calendar-day.other-month {
    background: rgba(0, 0, 0, 0.16);
    color: var(--cnx-text-muted);
}
```

### F20 — Today indicator (dotted-blue circle around day number) clashes with mint palette
- **Severity:** minor
- **Selector / element:** `.calendar-day.today .calendar-day-number { background: var(--color-primary); ... }` (file: `assets/css/calendar.css:314-323`)
- **Screenshot region:** today's date marker (if visible — May 3 2026 not in the screenshot's visible range).
- **Current behaviour (dark mode):** Uses `--color-primary` = `#2563EB` (legacy blue), inconsistent with the mint accent everywhere else.
- **Root cause (CSS):** Hardcoded reference to `--color-primary`; the rebrand migrated `.btn-primary` but not `.calendar-day.today`.
- **Recommended fix:**
```css
.calendar-day.today {
    background: var(--cnx-accent-soft);
}
.calendar-day.today .calendar-day-number {
    background: var(--cnx-accent);
    color: var(--cnx-accent-ink);
}
```

### F21 — Page header bar background is a different teal than topbar, creating two competing tones
- **Severity:** major
- **Selector / element:** `.main-content > .header` (file: `assets/css/components.css:52-64`)
- **Screenshot region:** the band immediately below the topbar containing "Calendario" title + view selector.
- **Current behaviour (dark mode):** `background: transparent` falls through to `--cnx-bg-app` = `#0E1A1D`, but the topbar above it is `--cnx-accent-soft` (mint tint). The visual is two adjacent horizontal stripes with conflicting colors.
- **Root cause (CSS):** Header is transparent + topbar has tinted bg → adjacent contrasting bands.
- **Recommended fix:** Fix is at F15 (make the topbar opaque dark in dark mode). Once F15 is applied, the two bands unify.

### F22 — "Benvenuto, Antonio Amodeo" label in header is lost to dim grey
- **Severity:** minor
- **Selector / element:** `<span class="text-sm text-muted">` (file: `calendar.php:143`); `.text-muted { color: var(--cnx-text-muted); }` is mapped via legacy fallback.
- **Screenshot region:** top-right of page-header next to company filter.
- **Current behaviour (dark mode):** `--cnx-text-muted` = `#6B7A82` on `#0E1A1D` is at the lower bound of WCAG AA for non-essential text. Looks washed out.
- **Root cause (CSS):** Token `--cnx-text-muted` is correct but applied to a label that should be `--cnx-text-secondary` (more legible).
- **Recommended fix:**
```css
[data-theme="dark"] .header .text-muted {
    color: var(--cnx-text-secondary);
}
```

### F23 — Print/import/export icon buttons (🖨 ⬆ ⬇) are unicode glyphs rendered grey-on-grey
- **Severity:** minor
- **Selector / element:** `.btn.btn-icon` with unicode content (file: `assets/js/calendar.js:3637-3645`)
- **Screenshot region:** rightmost cluster of three icon buttons in toolbar.
- **Current behaviour (dark mode):** The unicode characters are not styled colors — relies on font color only. Sits as grey-on-grey since `.btn-icon` (see F5) has no chrome.
- **Root cause (CSS):** Combination of F5 (no `.btn-icon` rule) + unicode glyphs being un-themable.
- **Recommended fix:** F5 alone fixes the chrome. For better contrast also:
```css
[data-theme="dark"] .btn-icon { color: var(--cnx-text-secondary); }
[data-theme="dark"] .btn-icon:hover { color: var(--cnx-text-primary); }
```

### F24 — Page-header bottom border is missing in dark mode (horizontal divider invisible)
- **Severity:** minor
- **Selector / element:** `.main-content > .header { border-bottom: 1px solid var(--cnx-border); }` (file: `assets/css/components.css:63`)
- **Screenshot region:** thin horizontal line that should separate header from page content (below "Benvenuto, Antonio Amodeo").
- **Current behaviour (dark mode):** Border IS using `--cnx-border` = `#22363B` but on the dark page bg `#0E1A1D` it's at the threshold of visibility. Functionally OK but feels weak.
- **Root cause (CSS):** `--cnx-border` is tuned for surface-on-surface separators; against the page bg it's too subtle.
- **Recommended fix:**
```css
[data-theme="dark"] .main-content > .header {
    border-bottom-color: var(--cnx-border-strong);
}
```
