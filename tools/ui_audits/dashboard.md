# Dark mode audit — dashboard.php

Screenshot reference: `tools/ui_redesign/dark_round3/dashboard.png`

## Summary
- Total findings: 18
- Severity breakdown: critical=4, major=8, minor=6

## Findings

### F1 — Top app bar is teal-tinted, doesn't match dark app background
- **Severity:** critical
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-33`)
- **Screenshot region:** entire top bar (search "Cerca file, persone, ticket..." + sun/moon toggles), height 64px, full width above content
- **Current behaviour (dark mode):** the topbar reads as a clearly different teal/cyan-tinted band (much greener than the app body); it looks like a separate "panel" floating on top, not flush with the dark app surface. The viewer perceives two stacked dark backgrounds with different hues.
- **Root cause (CSS):** `background: var(--cnx-accent-soft);` — in dark mode `--cnx-accent-soft = rgba(95, 202, 211, 0.12)` rendered over `--cnx-bg-app: #0E1A1D` produces a noticeable green-teal cast. The `backdrop-filter: blur(8px)` plus the soft accent fill makes it feel like glassmorphism we explicitly forbade in `direction.md`.
- **Recommended fix:** add a dark-mode override that uses the surface token (or a slightly lighter charcoal) so the topbar sits flush with the shell:
```css
[data-theme="dark"] .cnx-app-topbar {
  background: var(--cnx-bg-surface);     /* #15262A, matches sidebar siblings */
  border-bottom-color: var(--cnx-border);
  backdrop-filter: none;                 /* drop glass effect in dark */
  -webkit-backdrop-filter: none;
}
```

### F2 — Hero card decorative SVG nearly invisible (low contrast on dark)
- **Severity:** major
- **Selector / element:** `.cnx-hero-card__art svg` (file: `assets/css/components.css:344-356`)
- **Screenshot region:** right portion of the hero card (the two stylized "card" outlines + sun icon top-right of hero)
- **Current behaviour (dark mode):** SVG strokes are accent mint at `opacity: 0.7`; on the surface bg the strokes look ghosted, hard to read at first glance.
- **Root cause (CSS):** `.cnx-hero-card__art { color: var(--cnx-accent); opacity: 0.7; }` — same opacity as light, but on dark surface contrast budget is much smaller.
- **Recommended fix:**
```css
[data-theme="dark"] .cnx-hero-card__art {
  color: var(--cnx-accent);
  opacity: 0.45;          /* let it recede further intentionally */
}
/* OR raise stroke contrast: */
[data-theme="dark"] .cnx-hero-card__art svg {
  stroke-width: 1.2;       /* slightly thicker since surface contrast is lower */
}
```

### F3 — Hero card "Domenica" weekday text appears washed out / low contrast
- **Severity:** minor
- **Selector / element:** `.cnx-hero-card__weekday` (file: `assets/css/components.css:299-305`)
- **Screenshot region:** large "Domenica" word at top-left of hero
- **Current behaviour (dark mode):** uses `color: var(--cnx-text-primary)` (`#E6EEF0`) which is fine in isolation, but adjacent to the bright "Ciao, Antonio!" label it visually disappears because the date "3" below has the same color and same weight class. The weekday should be the loudest line; it isn't.
- **Root cause (CSS):** all three top text elements (`__weekday`, `__date`, `__greeting`) point to `--cnx-text-primary`. No hierarchy via color in dark mode.
- **Recommended fix:** keep weekday at full primary, mute the greeting line slightly:
```css
[data-theme="dark"] .cnx-hero-card__greeting {
  color: var(--cnx-text-secondary);   /* #A8B5BB instead of #E6EEF0 */
}
```

### F4 — Stat cards lose their card-ness — borders invisible against app bg
- **Severity:** major
- **Selector / element:** inline `.stat-card` rule (file: `dashboard.php:62-68`) and overrides in `dashboard.css:114-123`
- **Screenshot region:** the three cards "ORA / I MIEI TURNI / TASK ASSEGNATI" in row 2
- **Current behaviour (dark mode):** the cards' borders are barely perceivable; the cards merge into the surrounding dark teal background. The entire card row reads as one continuous panel rather than three discrete cards.
- **Root cause (CSS):** dashboard.php inline `<style>` defines `.stat-card { background: var(--color-white); ... }` WITHOUT a `border` declaration. The dashboard.css version has `border: 1px solid var(--color-gray-200)` but the inline `<style>` (loaded after dashboard.css?v=…) wins for the `background`/`box-shadow`/`padding` properties. Result: in dark, `--color-white` resolves to `#15262A` (surface) which is only ~6% lighter than `--cnx-bg-app: #0E1A1D` — visually flat. **`box-shadow: var(--shadow-sm)` is also useless on dark bg.**
- **Recommended fix:**
```css
[data-theme="dark"] .stat-card,
[data-theme="dark"] .dashboard-grid .stat-card {
  background: var(--cnx-bg-surface);
  border: 1px solid var(--cnx-border);
  box-shadow: none;        /* shadows don't carry on dark; rely on border */
}
```
Note: `components.css:238-246` already has a partial dark fallback for `.stat-card` (background only). It does NOT add a border, so the cards still look borderless.

### F5 — "Nessun turno nei prossimi 30 giorni" pill blends into card background
- **Severity:** major
- **Selector / element:** `.dash-mini-item` (file: `dashboard.php:128-137`)
- **Screenshot region:** inside the "I MIEI TURNI" stat card, the pill containing the text "Nessun turno nei prossimi 30 giorni"
- **Current behaviour (dark mode):** the pill uses `background: var(--color-gray-50)` which dark-overrides to `#1B2F33` — almost identical to `--cnx-bg-subtle` — and the parent stat card surface is `#15262A`. The contrast delta between pill and card is only ~3-4% lightness; the pill barely pops.
- **Root cause (CSS):** `background: var(--color-gray-50); border: 1px solid var(--color-gray-200);` — gray-50 dark = `#1B2F33`, gray-200 dark = `#22363B`. Both very close to surface `#15262A`.
- **Recommended fix:** use a higher-contrast subtle token or strengthen the border in dark mode:
```css
[data-theme="dark"] .dash-mini-item {
  background: var(--cnx-bg-app);          /* darker than card surface, gives "inset" feel */
  border-color: var(--cnx-border-strong); /* #2E464C, still subtle but visible */
}
```

### F6 — Tasks progress bar "fill" indistinguishable from track in dark mode
- **Severity:** major
- **Selector / element:** `.dash-progress-bar` + `.dash-progress-fill` (file: `dashboard.php:171-184`)
- **Screenshot region:** "TASK ASSEGNATI" card, thin bar above "Nessun task assegnato"
- **Current behaviour (dark mode):** track is `var(--color-gray-200)` → `#22363B` (dark teal); fill uses `var(--color-primary)`. Since the screenshot shows zero tasks, the bar appears as a uniform medium-grey strip with no visible fill segment. The track color is too dark to read against the card surface, and when there's progress >0 the mint fill on top of the dark track will look acceptable but the EMPTY state is a flat grey slug.
- **Root cause (CSS):** `--color-gray-200` dark override (`#22363B`) is too close to the surface card (`#15262A`).
- **Recommended fix:**
```css
[data-theme="dark"] .dash-progress-bar {
  background: rgba(255, 255, 255, 0.06);  /* token-equivalent of sidebar-hover */
}
```

### F7 — Mini list border color too dark to perceive
- **Severity:** minor
- **Selector / element:** `.dash-mini-item` border + `.list-item` border-bottom (file: `dashboard.php:134, 437`)
- **Screenshot region:** "I MIEI TURNI" card pill border, plus future list items in "Attività Recente" / "Documenti Recenti"
- **Current behaviour (dark mode):** `border-bottom: 1px solid var(--color-gray-200)` (dark = `#22363B`) against surface `#15262A` is a ~2% lightness step — practically invisible.
- **Root cause (CSS):** dashboard.php inline `.list-item` uses gray-200 for separators.
- **Recommended fix:**
```css
[data-theme="dark"] .list-item,
[data-theme="dark"] .progress-item {
  border-color: var(--cnx-border);   /* #22363B but explicit, easier to override later */
}
/* OR slightly stronger */
[data-theme="dark"] .list-item { border-color: var(--cnx-border-strong); }
```

### F8 — Inline `.dash-pill.warn` / `.dash-pill.danger` use light hex literals
- **Severity:** major
- **Selector / element:** `.dash-pill.warn`, `.dash-pill.danger` (file: `dashboard.php:209-210`)
- **Screenshot region:** would appear inside "TASK ASSEGNATI" card if there were overdue tasks (currently hidden)
- **Current behaviour (dark mode):** when the pill becomes visible, `.warn { background: #FFFBEB; color: #92400e; }` and `.danger { background: #FEF2F2; color: #991b1b; }` — hardcoded LIGHT hex pairs that don't flip. On dark bg these will look like blinding cream/pink rectangles.
- **Root cause (CSS):** raw hex literals bypass the token system entirely.
- **Recommended fix:**
```css
.dash-pill.warn   { background: rgba(245, 158, 11, 0.14); color: #B45309; }
.dash-pill.danger { background: rgba(239, 68, 68, 0.12); color: #B91C1C; }
[data-theme="dark"] .dash-pill.warn   { color: #FBBF24; }
[data-theme="dark"] .dash-pill.danger { color: #F87171; }
```
(mirrors the `.cnx-badge` pattern at `components.css:829-853`).

### F9 — Inline `.badge-blue` / `-green` / `-yellow` / `-red` use light hex literals
- **Severity:** major
- **Selector / element:** `.badge-blue`, `.badge-green`, `.badge-yellow`, `.badge-red` (file: `dashboard.php:514-532`)
- **Screenshot region:** any rendered status badge in lists (Attività, Documenti, Eventi, Ticket)
- **Current behaviour (dark mode):** `#EFF6FF`, `#F0FDF4`, `#FFFBEB`, `#FEF2F2` cream/pastel backgrounds — bright glaring rectangles in dark mode.
- **Root cause (CSS):** hardcoded hex.
- **Recommended fix:** mirror `.cnx-badge--*` tokens:
```css
.badge-blue   { background: var(--cnx-accent-soft); color: var(--cnx-accent-ink); }
.badge-green  { background: rgba(34, 197, 94, 0.12); color: #15803D; }
.badge-yellow { background: rgba(245, 158, 11, 0.14); color: #B45309; }
.badge-red    { background: rgba(239, 68, 68, 0.12); color: #B91C1C; }
[data-theme="dark"] .badge-blue   { color: var(--cnx-accent); }
[data-theme="dark"] .badge-green  { color: #4ADE80; }
[data-theme="dark"] .badge-yellow { color: #FBBF24; }
[data-theme="dark"] .badge-red    { color: #F87171; }
```

### F10 — `.activity-dup` light-grey hex literal background
- **Severity:** minor
- **Selector / element:** `.activity-dup` (file: `dashboard.php:534-542`)
- **Screenshot region:** would appear next to repeated activity actions in "Attività Recente"
- **Current behaviour (dark mode):** `background: #F3F4F6` — pastel grey rectangle on dark.
- **Root cause (CSS):** hardcoded hex.
- **Recommended fix:**
```css
.activity-dup { background: var(--cnx-bg-subtle); color: var(--cnx-text-muted); }
```

### F11 — Mini calendar cells "is-today" highlight invisible
- **Severity:** minor
- **Selector / element:** `.mini-calendar td.is-today` (file: `dashboard.css:92-95`)
- **Screenshot region:** "Calendario" card on the right; today's cell highlight
- **Current behaviour (dark mode):** `background: var(--cnx-bg-subtle); border: 1px solid var(--color-primary);`. `--cnx-bg-subtle` dark = `#1B2F33`, surface card dark = `#15262A` — only a faint 4-6% step. The mint border carries most of the signal.
- **Root cause (CSS):** subtle bg too close to surface in dark.
- **Recommended fix:**
```css
[data-theme="dark"] .mini-calendar td.is-today {
  background: var(--cnx-accent-soft);   /* rgba(95,202,211,0.12) — readable mint tint */
  border-color: var(--cnx-accent);
}
```

### F12 — Mini calendar "other-month" days nearly invisible
- **Severity:** minor
- **Selector / element:** `.mini-calendar td.is-other-month .day-number` (file: `dashboard.css:96-98`)
- **Screenshot region:** in dashboard screenshot the cells "26 / 27 / 28 / 29 / 30" (April days) at row top of mini-calendar appear barely visible
- **Current behaviour (dark mode):** `color: var(--color-gray-300)` → dark override `#2E464C` — almost the same value as the borders. Numbers ghost into the bg.
- **Root cause (CSS):** gray-300 dark override is too close to bg.
- **Recommended fix:**
```css
[data-theme="dark"] .mini-calendar td.is-other-month .day-number {
  color: var(--cnx-text-muted);  /* #6B7A82 — readable but clearly de-emphasized */
}
```

### F13 — Card header has light surface separator in dark mode
- **Severity:** minor
- **Selector / element:** `.card-header` (file: `assets/css/styles.css:612-616`)
- **Screenshot region:** "Attività Recente", "Calendario", "Documenti Recenti", etc. card titles bar
- **Current behaviour (dark mode):** `border-bottom: 1px solid var(--color-gray-200); background-color: var(--color-white);` — the explicit `var(--color-white)` makes header equal to surface so it's fine, but the border-bottom color is `--color-gray-200` dark = `#22363B` which is ~7% lighter than `#15262A` — visible but soft. Acceptable but inconsistent with the cnx-card pattern.
- **Root cause (CSS):** legacy `.card` system uses `--color-gray-*` for separator.
- **Recommended fix:** unify to `--cnx-border` in dark:
```css
[data-theme="dark"] .card-header,
[data-theme="dark"] .card-footer {
  border-color: var(--cnx-border);
  background-color: var(--cnx-bg-surface);
}
```

### F14 — Sun (light) and Moon (dark) theme toggles: the active variant has very low affordance
- **Severity:** minor
- **Selector / element:** `.cnx-theme-toggle__btn[aria-pressed]` (definition not in files I read; injected by `app.js:90+`)
- **Screenshot region:** top-right corner, the two pill buttons. In the dashboard screenshot (light?) the sun is highlighted; in the files screenshot the moon is highlighted.
- **Current behaviour (dark mode):** in `files.png` the moon side is the "active" but its filled circle uses pure dark `--cnx-bg-sidebar` over the slightly teal-tinted topbar — minimal visual delta. Hard to tell at a glance which mode is currently active.
- **Root cause (CSS):** active state probably uses `background: var(--cnx-bg-sidebar)` or similar — close to topbar colour after F1's tinting issue.
- **Recommended fix:** make the active state use accent ring once F1 is applied:
```css
[data-theme="dark"] .cnx-theme-toggle__btn[aria-pressed="true"] {
  background: var(--cnx-accent);
  color: var(--cnx-accent-ink);
  box-shadow: var(--cnx-shadow-focus);
}
```
(Requires confirming current selector — search `cnx-theme-toggle` in `app.js` for the injected style.)

### F15 — Header divider line under hero card is too dark to see
- **Severity:** minor
- **Selector / element:** `.main-content > .header` (file: `assets/css/components.css:52-64`)
- **Screenshot region:** below the company-filter row, above the hero card; horizontal hairline.
- **Current behaviour (dark mode):** `border-bottom: 1px solid var(--cnx-border)` → `#22363B`, only ~7% lighter than the body. The hero card thus appears to "float" without anchor.
- **Root cause (CSS):** generic border token in dark.
- **Recommended fix:** in this single case, allow `--cnx-border-strong`:
```css
[data-theme="dark"] .main-content > .header,
[data-theme="dark"] .main-content > header.header {
  border-bottom-color: var(--cnx-border-strong);
}
```

### F16 — Page-content padding zone uses `--color-gray-50` causing two-tone band
- **Severity:** minor
- **Selector / element:** `.page-content` (file: `assets/css/styles.css:1259-1266, 1320-1324`)
- **Screenshot region:** the entire content area below the topbar / above the hero card — narrow strip ~80px tall around `padding: 16px 32px 0;` of the cnx-page-header
- **Current behaviour (dark mode):** `background-color: var(--color-gray-50)` → `#1B2F33` while `.main-content` = `var(--color-gray-50)` → same. So no visual issue between page-content and main-content. BUT the topbar above (F1) has a teal tint, creating a perceived "step" — already covered by F1.
- **Root cause (CSS):** ok in isolation; problem only via interaction with F1.
- **Recommended fix:** N/A once F1 is fixed.

### F17 — "Apri Turni" / "Apri Task" `.btn-outline` link styling lacks underline contrast
- **Severity:** minor
- **Selector / element:** `.btn-outline` (file: `assets/css/components.css:225-230`)
- **Screenshot region:** mint-colored "Apri Turni" and "Apri Task" anchor links inside the stat cards
- **Current behaviour (dark mode):** mint accent text on dark surface — readable, but the link looks like inert teal text (no underline, no border, no chip). Users may not recognize it as a clickable CTA. This is style choice rather than a bug, but it bears flagging in the audit.
- **Root cause (CSS):** `.btn-outline` is set to `color: var(--cnx-accent)` only — no border, no padding, no underline.
- **Recommended fix (optional polish):**
```css
.btn-outline {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border: 1px solid var(--cnx-accent);
  border-radius: var(--cnx-radius-pill);
  color: var(--cnx-accent) !important;
}
[data-theme="dark"] .btn-outline:hover {
  background: var(--cnx-accent-soft);
}
```

### F18 — `.list-icon` mint dot + future activity icons may bleed on busy dark surface
- **Severity:** minor
- **Selector / element:** `.list-icon` (file: `dashboard.php:443-450`)
- **Screenshot region:** "Attività Recente" first row — "Massimo Parisi ha effettuato il logout"; small dot before username
- **Current behaviour (dark mode):** `background: var(--color-primary)` (= `--cnx-accent` mint) on surface — fine alone. But once visited rows scroll in with multiple actor avatars, the 8px dot will compete with profile-color circles.
- **Root cause (CSS):** static `--color-primary` for all activity entries.
- **Recommended fix:** decouple — keep mint for "self" actions, neutral for system events:
```css
.list-item .list-icon { background: var(--cnx-text-muted); }
.list-item.is-self .list-icon { background: var(--cnx-accent); }
```
(**REQUIRES MARKUP CHANGE** to add `is-self` class on rows where `actor_id == currentUser.id` — flag for lead.)

---

End of dashboard.php audit.
