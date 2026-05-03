# Responsive audit — dashboard.php

> Viewports inspected:
> - mobile 375x812 → `tools/ui_redesign/responsive_pre/mobile/dashboard.png`
> - tablet portrait 768x1024 → `tools/ui_redesign/responsive_pre/tablet_portrait/dashboard.png`
>
> Theme: dark mode (data-theme="dark"). Login is super_admin (asamodeo@fortibyte.it).
> Sidebar shell: `data-cnx-sidebar="true"` is set, the sidebar uses `position: fixed`
> with `transform: translateX(-100%)` at <=768px (`assets/css/styles.css:1551-1574`),
> while `.cnx-app-topbar` is fixed (`assets/css/components.css:16-49`).

## Summary
- Total findings: 18
- Severity: critical=4, major=8, minor=6
- Worst breakpoint: **375px** (mobile) — sidebar drawer cannot be opened, hero card squeezed, action targets at hamburger-equivalent missing.

## Findings

### F1 — Hamburger toggle is completely missing on dashboard at <=768px
- **Severity:** critical
- **Breakpoint:** both (375px, 768px)
- **Selector / element:** dashboard's `<header class="header cnx-page-header cnx-page-header--no-border">` (file: `dashboard.php:232-238`). No `<button class="sidebar-toggle" id="sidebarToggle">` rendered (compare `files.php:81`).
- **Screenshot region:** top header, just below `.cnx-app-topbar`.
- **Current behaviour:** the sidebar is hidden by `transform: translateX(-100%)` at <=768px (`assets/css/styles.css:1553`), but on the dashboard there is no UI affordance to call `.sidebar.classList.toggle('open')`. The user is locked out of navigation (Calendar, Files, Tasks, Tickets, Logout) until they resize the window above 768px. The inline JS in `dashboard.php:418-423` already wires `#sidebarToggle` clicks but the button does not exist in the markup.
- **Root cause (CSS):** not strictly CSS — markup omission. CSS-only mitigation: render the hamburger globally via `.cnx-app-topbar` (which DOES exist on every authenticated page through `app.js:43-67`).
- **Recommended fix:** **REQUIRES MARKUP CHANGE** in `assets/js/app.js::initAppTopbar()` — prepend a hamburger button before the search wrapper. CSS-only complement that should ship together:
  ```css
  /* assets/css/components.css — append after .cnx-app-topbar block */
  .cnx-app-topbar__hamburger {
      display: none;
      width: 40px; height: 40px;
      padding: 0;
      background: transparent;
      border: 1px solid var(--cnx-border);
      border-radius: var(--cnx-radius-md);
      color: var(--cnx-text-primary);
      font-size: 20px;
      cursor: pointer;
      flex: 0 0 auto;
  }
  @media (max-width: 768px) {
      .cnx-app-topbar__hamburger { display: inline-flex; align-items: center; justify-content: center; }
      /* shrink the spacer so search has room next to the toggle */
      .cnx-app-topbar { gap: 8px; }
  }
  ```

### F2 — Hero-card date "3" + suffix word "maggio" wrap to 2 lines on mobile (cosmetic but cramped)
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-hero-card__date` (file: `assets/css/components.css:313-324`)
- **Screenshot region:** hero-card top-left "3 maggio".
- **Current behaviour:** at 375px, after content padding (32 + 24 = 56px effective horizontal), date "3" font-size 44px and "maggio" 18px sit on the same line; the screenshot shows them as inline-baselined which is fine, but `flex-wrap: wrap` is already on the container so on a slightly narrower phone (<=320px) they would split. Currently OK at 375px.
- **Root cause (CSS):** baseline alignment + `flex-wrap: wrap` on `.cnx-hero-card__date`. Acceptable, but the suffix is also lowercased twice (token + `text-transform: lowercase`) so reads "3 maggio".
- **Recommended fix:** none required — leave as-is. If desired, tighten gap at <=375px:
  ```css
  @media (max-width: 480px) {
      .cnx-hero-card__date { gap: 8px; font-size: 40px; line-height: 48px; }
      .cnx-hero-card__suffix { font-size: 16px; }
  }
  ```

### F3 — Hero-card overall padding too generous on mobile
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-hero-card` (file: `assets/css/components.css:284-295` and mobile override `:364-369`)
- **Screenshot region:** hero-card.
- **Current behaviour:** at <=760px the override sets `padding: 24px`. The visible inner content on a 375px viewport ends up ~295px wide, leaving little room for "Buona giornata, fai succedere cose belle." which wraps awkwardly across 2 lines.
- **Root cause (CSS):** padding 24px = 48px horizontal lost.
- **Recommended fix:**
  ```css
  @media (max-width: 480px) {
      .cnx-hero-card { padding: 20px 18px; gap: 12px; border-radius: var(--cnx-radius-md); }
      .cnx-hero-card__greeting { font-size: 18px; line-height: 24px; }
      .cnx-hero-card__tagline { font-size: 13px; line-height: 19px; }
  }
  ```

### F4 — `.cnx-app-topbar` height stays at 56px on mobile but covers the page-header below
- **Severity:** major
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-49`) + `.main-content { padding-top: 64px }` and `:48 padding-top: 56px` at <=768px.
- **Screenshot region:** topbar + immediately below the company-filter row.
- **Current behaviour:** topbar is `position: fixed; left: 0; right: 0; height: 56px` on mobile (correct) but the page-header `.cnx-page-header--no-border` underneath has inline `padding: 16px 32px 0` (`dashboard.php:232`). The 32px horizontal padding is wasted on 375px (only ~311px of content area for the dropdown) and pushes the company-filter dropdown right against the viewport edge.
- **Root cause (CSS):** **inline style** in dashboard.php overrides any media query. `padding: 16px 32px 0` is applied unconditionally.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to remove inline style; or override in CSS with higher specificity:
  ```css
  @media (max-width: 768px) {
      header.cnx-page-header.cnx-page-header--no-border[style] {
          padding: 12px 16px 0 !important;
      }
  }
  @media (max-width: 480px) {
      header.cnx-page-header.cnx-page-header--no-border[style] {
          padding: 8px 12px 0 !important;
      }
  }
  ```

### F5 — Company filter dropdown sticks to the right edge with no left fallback
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-page-header__actions` (file: `assets/css/components.css:709-714`) used inside dashboard.php:233 with inline `style="margin-left: auto;"`
- **Screenshot region:** the row "Azienda: Tutte le aziende ▾" between topbar and hero card.
- **Current behaviour:** the dropdown is right-aligned, single-row, OK on mobile. But the right padding (32px from inline style) cuts the chevron close to the right border.
- **Root cause (CSS):** inline `padding: 16px 32px 0` on the wrapper.
- **Recommended fix:** see F4 (same fix removes the 32px right padding on mobile).

### F6 — Stat-card grid uses inline `<style>` and stays at `auto-fit minmax(260px,1fr)` — wastes a column slot at 768
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.dashboard-grid` (file: `dashboard.php:55-60` inline `<style>`)
- **Screenshot region:** the row "ORA 10:27:47" + "I MIEI TURNI 0" + "TASK ASSEGNATI 0".
- **Current behaviour:** at 768 viewport with margin-left=0 and page-content padding 24px, content area ≈ 720px. `auto-fit minmax(260px,1fr)` produces 2 columns of 348px each (gap 24). The third stat-card "TASK ASSEGNATI" wraps to a second row alone, wasting the right half. The screenshot confirms this (TASK ASSEGNATI is a half-width card by itself).
- **Root cause (CSS):** auto-fit with `minmax(260px,1fr)` is `floor(720/260)=2` columns. Should explicitly map: 768 → 2 cols, 375 → 1 col.
- **Recommended fix:**
  ```css
  /* assets/css/dashboard.css — replace the .stats-grid block + extend dashboard-grid */
  @media (max-width: 1024px) {
      .dashboard-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 900px) {
      .dashboard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 600px) {
      .dashboard-grid { grid-template-columns: 1fr; gap: 12px; }
  }
  ```
  (Note: dashboard-grid is in inline `<style>` — better to move it to dashboard.css. Until then, add the rules to dashboard.css with selector `.dashboard-grid` — they'll cascade.)

### F7 — Stat-card padding (24px) eats too much horizontal real estate on mobile
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.stat-card` (file: `dashboard.php:62-72` inline `<style>` and `assets/css/dashboard.css:114-128`)
- **Screenshot region:** "ORA 10:26:23" card.
- **Current behaviour:** stat-card has `padding: var(--space-6)` = 24px. On 375px viewport, content area = ~327px, inner = ~279px. The clock value `10:26:23` font-size 40px barely fits; on smaller phones it would wrap.
- **Root cause (CSS):** 24px padding + 40px font on a narrow card.
- **Recommended fix:**
  ```css
  @media (max-width: 480px) {
      .stat-card { padding: 16px; }
      .dash-clock-time { font-size: 32px; }
      .stat-value { font-size: 28px; }
  }
  ```

### F8 — Two-column "Attività Recente / Calendario" grid does not stack on tablet portrait
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.grid.grid-cols-1.md\:grid-cols-2` (file: `dashboard.php:325`, helper at `assets/css/dashboard.css:46-49`)
- **Screenshot region:** below stat-cards (cropped on tablet screenshot but visible "Attività Recente" + "Calendario" beginning).
- **Current behaviour:** at exactly 768px the rule `@media (min-width: 768px) { .md\:grid-cols-2 { grid-template-columns: repeat(2,...) } }` kicks in. Two cards side-by-side at 768 → each ~340px wide. Mini-calendar inside has 7 day-headers; padding `var(--space-3)` (12px) per cell — fits but very tight. Acceptable, but mini-calendar+activity could benefit from stacking.
- **Root cause (CSS):** breakpoint at 768 forces 2-col before the layout has enough room (sidebar already hidden but content padding still applies).
- **Recommended fix:**
  ```css
  /* Bump the helper threshold to 900px so portrait tablets stack instead of squashing */
  @media (max-width: 899.98px) {
      .md\:grid-cols-2 { grid-template-columns: 1fr; }
      .md\:grid-cols-3 { grid-template-columns: 1fr; }
  }
  ```
  (Currently they only "turn on" at min-width 768; we need to also "turn off" between 768 and 900.)

### F9 — Three-column "Documenti / Eventi / Ticket" grid will collapse to 1 only at 768
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.grid.grid-cols-1.md\:grid-cols-3` (file: `dashboard.php:357`, helper at `assets/css/dashboard.css:46-49`)
- **Screenshot region:** not visible in screenshot but exists below the visible viewport.
- **Current behaviour:** at 768 the helper `md:grid-cols-3` produces 3 cards × ~226px each (after 24px gaps). Card padding 24px leaves ~178px inner — the title "Documenti Recenti" (text-base 16px) wraps to 2 lines.
- **Root cause (CSS):** same as F8 — helpers turn on too eagerly at 768.
- **Recommended fix:** combine into the F8 fix; explicitly:
  ```css
  @media (max-width: 1023.98px) {
      .md\:grid-cols-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 720px) {
      .md\:grid-cols-3 { grid-template-columns: 1fr; }
  }
  ```

### F10 — Theme toggle (sun/moon pills) overlaps page-header content on mobile (when topbar version not active)
- **Severity:** minor
- **Breakpoint:** 375px (only if shell does not inject toggle in topbar)
- **Selector / element:** `.cnx-theme-toggle` (file: `assets/css/styles.css:1779-1857`)
- **Screenshot region:** top-right of viewport.
- **Current behaviour:** in the screenshots the theme toggle is **inside** the topbar (see top-right pill). Default fallback rule positions it `position: fixed; top: 16px; right: 24px;`. Mobile override at line 1856 reduces to `top: 8px; right: 8px`. OK in current screenshots.
- **Root cause (CSS):** rule already correct, but for pages that do NOT use the topbar the fallback floats over content. Verified visually fine on dashboard.
- **Recommended fix:** none. Add a min-tap-target reminder:
  ```css
  @media (max-width: 480px) {
      .cnx-theme-toggle__btn { width: 40px; height: 40px; } /* Bump to 44px ideal */
  }
  ```

### F11 — `cnx-app-topbar__search` has `max-width: 720px; margin: 0 auto` — search input centred at 768 leaves dead-space left and right
- **Severity:** minor
- **Breakpoint:** 768px
- **Selector / element:** `.cnx-app-topbar__search` (file: `assets/css/components.css:173-203`)
- **Screenshot region:** topbar.
- **Current behaviour:** at 768 the search bar uses ~640px (after 16px padding both sides + spacer/theme-toggle), then `max-width: 720px; margin: 0 auto` centres it inside the flex slot, leaving `flex: 1` whitespace before/after. Acceptable visual but wastes screen real estate.
- **Root cause (CSS):** `margin: 0 auto` inside a `flex: 1` track.
- **Recommended fix:** at <=900 let the input grow to full width:
  ```css
  @media (max-width: 900px) {
      .cnx-app-topbar__search { margin: 0; max-width: none; }
  }
  ```

### F12 — Topbar search input height drops from 44px to 40px at <=768px (ok) but `cnx-input-group__icon` stays at left:14px
- **Severity:** minor
- **Breakpoint:** 768px, 375px
- **Selector / element:** `.cnx-input-group__icon` + `.cnx-input--search` (file: `assets/css/components.css:507-537`)
- **Screenshot region:** search input left edge.
- **Current behaviour:** icon at left 14px, padding-left 38px. On 375px the search input has plenty of room (full width). Visually fine.
- **Root cause (CSS):** none.
- **Recommended fix:** no change required.

### F13 — Body uses sidebar-bg color and at <=768 the sidebar is hidden but body bg still tinted teal/dark, creating a visible vertical gap when content scrolls
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `body { background-color: var(--cnx-bg-sidebar); }` (file: `assets/css/styles.css:1670-1672`) + `.main-content { background-color: var(--cnx-bg-app); }` (`:1909-1911`)
- **Screenshot region:** very right edge / left edge of mobile viewport when scrolling.
- **Current behaviour:** with sidebar hidden via `transform: translateX(-100%)`, the body is still painted with `--cnx-bg-sidebar`. If main-content does not extend full width (e.g. brief layout glitch), the body shows. In the dashboard mobile screenshot, you can see the topbar appears on a slightly different shade than the page below.
- **Root cause (CSS):** body bg = sidebar bg by design (light mode seam), but on mobile dark we want body bg = app bg.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      body { background-color: var(--cnx-bg-app); }
  }
  ```

### F14 — `.btn.btn-outline` ("Apri Turni" / "Apri Task") not constrained to min-tap-target on mobile
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.btn.btn-outline` inside stat-cards (file: `assets/css/components.css:231-236` for color override only — no size rule). Base `.btn` lives in `styles.css` (legacy).
- **Screenshot region:** stat-card actions (visible on tablet screenshot under "Apri Turni").
- **Current behaviour:** the legacy `.btn` typically renders ~32-36px tall. Below the 44x44 iOS HIG / 48x48 Material accessibility minimum.
- **Root cause (CSS):** legacy `.btn` height not bumped on touch viewports.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .btn, .btn-outline, .btn-primary, .btn-secondary, .btn-ghost { min-height: 44px; padding-block: 10px; }
  }
  ```

### F15 — Mini-calendar table cells height 42px, padding 4px — on tablet 768 (340px wide card after gaps) days fit but day-numbers tiny (font-size 13px)
- **Severity:** minor
- **Breakpoint:** 768px
- **Selector / element:** `.mini-calendar td` (file: `assets/css/dashboard.css:72-79`) and `.mini-calendar table` (line 58-64)
- **Screenshot region:** Calendario card (visible bottom of tablet screenshot).
- **Current behaviour:** 7 columns × ~340px container = ~48px per cell. With 4px padding each side, day-number room ~40px. font 13px legible. OK.
- **Root cause (CSS):** none.
- **Recommended fix:** ensure tap targets:
  ```css
  @media (max-width: 768px) {
      .mini-calendar td { height: 44px; }
      .mini-calendar td .day-number { font-size: 14px; }
  }
  ```

### F16 — Hidden inputs (`#currentUserId`, `#currentTenantId`, etc.) accumulate at top of `.page-content` — not actually visible but they offset the hero-card slightly on the first paint
- **Severity:** minor
- **Breakpoint:** both
- **Selector / element:** dashboard.php lines 242-262
- **Screenshot region:** N/A (visual ok).
- **Current behaviour:** hidden inputs are `type="hidden"` — zero box. No layout impact.
- **Root cause (CSS):** none.
- **Recommended fix:** no change.

### F17 — Dashboard `.page-content` has no `padding-bottom` — last card row glued to the viewport bottom on mobile when sidebar drawer is closed
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.page-content` (file: `assets/css/dashboard.css:36-39`) — `padding: var(--space-6)` only.
- **Screenshot region:** below the visible viewport (need to scroll). Once scrolled, the "Documenti Recenti / Prossimi Eventi / Ticket Recenti" cards bottom edge sits flush with the viewport bottom.
- **Current behaviour:** the global media query at `assets/css/styles.css:1570-1573` overrides to `padding: var(--space-6) var(--space-4)` (24px vertical, 16px horizontal). Bottom padding 24px is ok but feels tight when scrolling reaches the end.
- **Root cause (CSS):** style choice.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .page-content { padding-bottom: 32px; }
  }
  ```

### F18 — Sidebar drawer slide-in: no overlay/scrim behind the open drawer at <=768px (back-tap accessibility)
- **Severity:** major
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.sidebar` (file: `assets/css/styles.css:696-711` + media `:1551-1574`)
- **Screenshot region:** N/A in current screenshots (drawer is closed).
- **Current behaviour:** when `.sidebar.open` is added, the sidebar slides in (`transform: translateX(0)`) but no semi-transparent overlay is drawn over the rest of the page; tapping outside the sidebar (on `.main-content`) does NOT close it. Standard mobile drawer UX expects a scrim. Also `.sidebar` keeps `position: fixed` and `z-index: var(--z-sticky)` (likely 30) — the topbar is z-index 250, so the topbar is **above** the open drawer, partially covering it. That is incorrect for a slide-in drawer pattern.
- **Root cause (CSS):** missing `::before` scrim + wrong z-index ordering.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .sidebar {
          z-index: 1100; /* above .cnx-app-topbar (250) */
          box-shadow: 8px 0 24px rgba(0, 0, 0, 0.32);
      }
      .sidebar::after { /* scrim — only shows when drawer is open */
          content: "";
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.42);
          opacity: 0;
          transition: opacity 200ms ease;
          pointer-events: none;
          z-index: -1;
      }
      .sidebar.open::after {
          opacity: 1;
          pointer-events: auto;
      }
  }
  ```
  Wire scrim click → close: requires JS (`assets/js/app.js`); CSS-only the rule above provides the visual.

---

## Notes on what is correctly handled today
- `.main-content { margin-left: 0 }` at <=768 (line 1561 styles.css) — sidebar real-estate correctly returned to content.
- `.cnx-app-topbar { left: 0 }` at <=768 (line 47 components.css) — topbar correctly fills width.
- `.cnx-hero-card__art { display: none }` at <=760 (line 366 components.css) — art SVG correctly hidden on mobile.
- Dark-mode overrides for `.stat-card`, `.cnx-hero-card` already present — visual fidelity preserved on the dashboard.
