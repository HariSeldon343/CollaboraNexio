# Responsive audit — SHELL (sidebar + topbar + main-content)

> Scope: shell concerns that cascade across ALL 10 pages. Smoking gun for the
> "everything pushed 260px right at 768px" reported in the brief.

## Summary
- Total findings: 11
- Severity: critical=6, major=3, minor=2
- Worst breakpoint: 768px (tablet portrait — sidebar still rendered while page assumes mobile layout)

---

## Findings

### F1 — Hamburger toggle markup is MISSING on 14/19 authenticated pages
- **Severity:** critical
- **Breakpoint:** both (375px AND 768px)
- **Selector / element:** `<button class="sidebar-toggle" id="sidebarToggle">` is rendered ONLY in `chat.php:98`, `compliance.php:52`, `files.php:81`, `file_download_requests.php:171`, `zip_download_requests.php:252`. NOT present in `dashboard.php`, `audit_log.php`, `configurazioni.php`, `calendar.php`, `tasks.php`, `ticket.php`, `turni.php`, `aziende.php`, `utenti.php`, `profilo.php`, `planning.php`, etc.
- **Screenshot region:** Top-left of every page below 1024px — there is no way for the user to open the slid-off sidebar. JS in `assets/js/app.js:249-252` listens for `#sidebarToggle` but it never finds the element, so the listener is never bound.
- **Current behaviour:** On audit_log and configurazioni at 375/768, the user sees the topbar (search + theme toggle) but no menu button. Once the sidebar transforms off-screen at `max-width: 768px` (`assets/css/styles.css:1551-1558`), navigation becomes unreachable.
- **Root cause (CSS):** Not a CSS bug — markup omission. CSS rule `.sidebar-toggle { display: none; ... } @media (max-width: 1024px) { .sidebar-toggle { display: flex; } }` (`assets/css/styles.css:1258-1283`) is correct, but there is no element to display.
- **Recommended fix:** **REQUIRES MARKUP CHANGE.** Inject a hamburger button as the first child of `.cnx-app-topbar` from `assets/js/app.js → initAppTopbar()` (which already runs on every authenticated page that has `.sidebar[data-cnx-sidebar="true"]`). One JS change cascades to every page. Suggested CSS-only addition (after markup is in place):
  ```css
  /* assets/css/sidebar-responsive.css — append to file */
  .cnx-app-topbar__menu-btn {
      display: none;
      width: 40px;
      height: 40px;
      align-items: center;
      justify-content: center;
      background: var(--cnx-bg-subtle);
      border: 1px solid var(--cnx-border);
      border-radius: var(--cnx-radius-pill);
      color: var(--cnx-text-secondary);
      cursor: pointer;
      flex-shrink: 0;
  }
  @media (max-width: 1024px) {
      .cnx-app-topbar__menu-btn { display: inline-flex; }
  }
  .cnx-app-topbar__menu-btn:focus-visible {
      outline: none;
      box-shadow: var(--cnx-shadow-focus);
  }
  ```

---

### F2 — main-content margin-left bug at 768px (the documented smoking gun)
- **Severity:** critical
- **Breakpoint:** 768px (tablet portrait)
- **Selector / element:** `.main-content` (file: `assets/css/styles.css:1209-1220` — `margin-left: var(--sidebar-width)`)
- **Screenshot region:** Whole page — at 768px the entire content (page-header, stat cards, filter form) is shifted ~260px right. The right side of every stat card ("EVENTI OGGI 117", "UTENTI ATTIVI 4", "ACCESSI 106", "MODIFICHE 0") is cut by the viewport right edge. The "Elim..." red button at top-right is truncated. The filters form right column ("Data Al", "Azione", and the "Applica" CTA) is cut.
- **Current behaviour:** At viewport width 768 (or close), the media query `@media (max-width: 768px) { .main-content { margin-left: 0 } }` (`styles.css:1560-1564`) SHOULD fire. However the screenshot proves it does NOT — most likely because the actual viewport reported by the browser is 768px including scrollbar, while the layout viewport is 768 + scrollbar. The `(max-width: 768px)` boundary is too tight and is being missed at exactly 768px. Worse, all per-page CSS that uses `max-width: 1024px` (e.g. `audit_log.php:1026-1042`) treats 768-1024 as "tablet landscape" and assumes a fully visible sidebar — but that's wrong because the sidebar then takes 260px out of available 768px, leaving 508px for a 3-col stat grid.
- **Root cause (CSS):**
  ```css
  /* styles.css:1209 */
  .main-content {
      margin-left: var(--sidebar-width); /* 260px */
      ...
  }
  /* styles.css:1551 — only fires < 768px (and at 768 due to rounding) */
  @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
  }
  ```
  At 768-1023, the sidebar is fully visible AND the main-content keeps the 260px margin → only 508px (or less) available for content.
- **Recommended fix:** Move the breakpoint to 1024px so tablet portrait gets the mobile-drawer layout. Replace the existing block (and the 1024px sidebar-toggle media):
  ```css
  /* styles.css — REPLACE lines 1551-1574 */
  @media (max-width: 1023.98px) {
      .sidebar {
          position: fixed;
          top: 0;
          left: 0;
          height: 100vh;
          z-index: 300;
          transform: translateX(-100%);
          transition: transform 200ms ease;
          box-shadow: 0 0 24px rgba(0, 0, 0, 0.18);
      }
      .sidebar.is-open,
      .sidebar.open {
          transform: translateX(0);
      }
      .main-content {
          margin-left: 0;
      }
      .container { padding: 0 var(--space-4); }
      .page-content { padding: var(--space-4); }
  }
  ```
  Note: also change the JS class from `.collapsed` to `.is-open` (see F3), or add `.sidebar.collapsed { transform: translateX(0); }` as a temporary alias.

---

### F3 — JS toggleSidebar uses `.collapsed`, CSS uses `.open` → toggle is a no-op on mobile
- **Severity:** critical
- **Breakpoint:** both (375px and 768px)
- **Selector / element:** `assets/js/app.js:321-339` toggles `.collapsed`. CSS at `assets/css/styles.css:1556` reads `.sidebar.open { transform: translateX(0); }`.
- **Screenshot region:** N/A (functional, not visual)
- **Current behaviour:** Even on the 5 pages that DO have `<button id="sidebarToggle">`, clicking the button adds class `collapsed` to the sidebar, but the responsive CSS only respects `open`. So the sidebar stays slid off-screen. (`.sidebar.collapsed` rule exists at `styles.css:1580` but it's a desktop-collapse rule, not mobile-show.)
- **Root cause (CSS+JS):** Naming mismatch between the original `.collapsed` desktop-collapse pattern and the redesign mobile-drawer `.open` pattern.
- **Recommended fix:** **REQUIRES JS CHANGE.** In `assets/js/app.js:321-339`, change to:
  ```js
  toggleSidebar() {
      const sidebar = document.querySelector('.sidebar');
      if (!sidebar) return;
      const isMobile = window.matchMedia('(max-width: 1023.98px)').matches;
      if (isMobile) {
          sidebar.classList.toggle('is-open');
          document.body.classList.toggle('cnx-drawer-open', sidebar.classList.contains('is-open'));
      } else {
          sidebar.classList.toggle('collapsed');
          localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
      }
      window.dispatchEvent(new Event('resize'));
  }
  ```

---

### F4 — No backdrop / scrim behind the drawer when open
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** No element exists. There is no `.cnx-sidebar-backdrop` in markup or CSS.
- **Screenshot region:** N/A (only visible after fixing F1+F3)
- **Current behaviour:** Once the sidebar opens, there's nothing dimming the background, and clicking outside the sidebar does nothing.
- **Root cause (CSS):** Element + rule missing.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** (single `<div class="cnx-sidebar-backdrop"></div>` injected once by `initAppTopbar`). CSS to add:
  ```css
  /* assets/css/sidebar-responsive.css — append */
  .cnx-sidebar-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 26, 31, 0.55);
      z-index: 290; /* below sidebar (300), above content */
      opacity: 0;
      pointer-events: none;
      transition: opacity 200ms ease;
  }
  body.cnx-drawer-open .cnx-sidebar-backdrop {
      opacity: 1;
      pointer-events: auto;
  }
  @media (min-width: 1024px) {
      .cnx-sidebar-backdrop { display: none; }
  }
  ```
  JS handler: clicking `.cnx-sidebar-backdrop` calls `toggleSidebar()` and removes `.is-open`.

---

### F5 — Topbar `left: var(--sidebar-width)` not reset until 768px → topbar cut at 768-1023
- **Severity:** critical
- **Breakpoint:** 768px (and 769-1023)
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-49`)
- **Screenshot region:** Top of every page — between viewport widths 768 and 1023, the topbar is anchored `left: 260px right: 0` while the sidebar is also rendered, so the topbar still starts after the sidebar. Once we fix F2 (sidebar slides off at 1023.98), the topbar still has `left: 260px` until 768, so on a 768-1023 viewport the topbar would lose its left 260px region (search field starts mid-page).
- **Current behaviour:** Tablet 768 screenshot: search bar starts at left=260px, even though the screenshot shows the sidebar is hidden (per the rule). At wider tablet (e.g. 900), the topbar still shows `left: 260` — but at that width the sidebar is also still visible, so it's "OK" by accident.
- **Root cause (CSS):**
  ```css
  /* components.css:16-22 */
  .cnx-app-topbar { left: var(--sidebar-width); ... }
  /* components.css:46-49 */
  @media (max-width: 768px) { .cnx-app-topbar { left: 0; } }
  ```
- **Recommended fix:** Align with F2 — switch breakpoint to 1023.98:
  ```css
  /* components.css — REPLACE lines 46-49 */
  @media (max-width: 1023.98px) {
      .cnx-app-topbar { left: 0; }
      .main-content   { padding-top: 56px; }
      .cnx-app-topbar { padding: 0 16px; height: 56px; }
      .cnx-app-topbar__search .cnx-input { height: 40px; }
  }
  ```

---

### F6 — Topbar lacks horizontal padding for menu button + theme toggle on mobile
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-app-topbar` at `(max-width: 768px)` (`components.css:200-203`)
- **Screenshot region:** Mobile screenshot — search field nearly touches the right edge; theme toggle (sun/moon) takes ~60px and search bar is squeezed.
- **Current behaviour:** At 375px the topbar has `padding: 0 16px` and the search field is `flex: 1`. Once we add a 40px hamburger on the left AND keep the 60px theme toggle on the right, the search has only ~199px of width — the placeholder "Cerca file, persone, ticket..." truncates to "Cerca file, person..." or worse, becomes the only thing visible.
- **Root cause (CSS):** No accommodation for additional left affordance.
- **Recommended fix:** Reduce search prominence on mobile and stack the menu button left of search:
  ```css
  /* components.css — append after line 203 */
  @media (max-width: 480px) {
      .cnx-app-topbar { padding: 0 12px; gap: 8px; }
      .cnx-app-topbar__search { max-width: none; flex: 1; }
      .cnx-app-topbar__search .cnx-input::placeholder {
          /* keep short placeholder visible */
      }
      /* When the menu button is added (F1), give it 40px and shrink search */
      .cnx-app-topbar__menu-btn { width: 36px; height: 36px; }
  }
  ```

---

### F7 — `--sidebar-width: 260px` is too wide for the drawer on 375px viewport
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.sidebar` (`styles.css:696-705`) — width 260px = 69% of 375px viewport
- **Screenshot region:** Mobile screenshot (after fixing F1-F4): the drawer would cover 69% of the viewport, which is awkward. Reference iOS/Material drawers cap at ~280px AND leave ≥40px peek of the underlying content.
- **Current behaviour:** Sidebar always renders at 260px even on the smallest phones.
- **Root cause (CSS):** No mobile-specific sidebar-width.
- **Recommended fix:**
  ```css
  /* assets/css/sidebar-responsive.css — append */
  @media (max-width: 480px) {
      .sidebar {
          width: min(280px, calc(100vw - 56px)); /* always leave 56px peek */
      }
  }
  ```

---

### F8 — z-index conflict: hamburger / topbar / sidebar drawer not coherent
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** Multiple
  - `.cnx-app-topbar { z-index: 250 }` (`components.css:24`)
  - `.cnx-theme-toggle { z-index: 250 }` (`styles.css:1784`)
  - `.cnx-search-results { z-index: 260 }` (`components.css:1211`)
  - `.sidebar` no explicit z-index — relies on flex order; defaults to auto.
  - `.cnx-modal { z-index: 1050 }` (`components.css:867`)
- **Current behaviour:** When the drawer is opened over the topbar, the topbar (z-index 250) ends up on TOP of the sidebar (auto = 0 within flex). The hamburger button inside the topbar would be visible while the drawer is supposedly above. After fix: drawer must be above topbar but below modals.
- **Root cause (CSS):** Missing explicit stacking context for sidebar.
- **Recommended fix:**
  ```css
  /* assets/css/sidebar-responsive.css — append */
  @media (max-width: 1023.98px) {
      .sidebar { z-index: 300; }                 /* above topbar (250), backdrop (290) */
      .cnx-sidebar-backdrop { z-index: 290; }
  }
  /* Keep .cnx-modal at 1050 — above the drawer when drawer happens to be open */
  ```

---

### F9 — Drawer not focus-trapped, no Escape close, no aria-hidden toggle
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `.sidebar` (no JS focus trap, no `aria-hidden` toggling)
- **Screenshot region:** N/A (a11y, not visual)
- **Current behaviour:** Once the drawer is open, Tab moves focus into background content (still rendered). Escape does nothing. Screen readers see both sidebar and the page content.
- **Root cause (JS):** Missing accessibility wiring.
- **Recommended fix:** **REQUIRES JS CHANGE** (no CSS-only mitigation). In `assets/js/app.js → toggleSidebar()`:
  - When opening: set `sidebar.setAttribute('aria-hidden', 'false')`, focus the first nav-item, store the previously focused element, listen for Escape and Tab-loop.
  - When closing: restore focus, set `aria-hidden="true"`, remove keydown listener.
  - Also add `aria-controls` and `aria-expanded` on the hamburger button.

---

### F10 — Hamburger touch target only 40x40 — borderline below 44x44 WCAG SC 2.5.5
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.sidebar-toggle` (`styles.css:1258-1272`) — 40x40
- **Screenshot region:** Top-left
- **Current behaviour:** Per WCAG 2.5.5 (Level AAA) and Apple HIG, touch targets should be ≥44x44 CSS pixels. 40x40 is below.
- **Root cause (CSS):** Hardcoded 40px.
- **Recommended fix:**
  ```css
  /* styles.css:1258-1272 — bump to 44px on touch viewports, keep 40px on desktop fallback */
  @media (max-width: 1023.98px) {
      .sidebar-toggle,
      .cnx-app-topbar__menu-btn {
          width: 44px;
          height: 44px;
      }
  }
  ```

---

### F11 — `body { background-color: var(--cnx-bg-sidebar) }` causes black gutter on left of mobile content
- **Severity:** minor
- **Breakpoint:** 375px (cosmetic)
- **Selector / element:** `body` (`styles.css:1670-1672`)
- **Screenshot region:** Mobile audit_log + configurazioni screenshots — there is a visible dark band on the left of the content (the body bg = sidebar teal showing through). This is the body bg leaking when the sidebar is translated off but the body color remains the dark-teal sidebar tone.
- **Current behaviour:** Pleasant on desktop (the body fades into the visible sidebar), but on mobile when the sidebar is hidden, the user sees ~unused dark margin from any horizontal overflow / scrollbar gutter.
- **Root cause (CSS):** `body { background-color: var(--cnx-bg-sidebar); }` is unconditional.
- **Recommended fix:**
  ```css
  /* styles.css:1670 — wrap with a media query */
  @media (min-width: 1024px) {
      body { background-color: var(--cnx-bg-sidebar); }
  }
  @media (max-width: 1023.98px) {
      body { background-color: var(--cnx-bg-app); }
  }
  ```
