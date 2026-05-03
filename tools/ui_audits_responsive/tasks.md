# Responsive audit — tasks.php

## Summary
- Total findings: 16
- Severity: critical=5, major=8, minor=3
- Worst breakpoint: 375px

## Findings

### F1 — `.main-content` `margin-left: 260px` not zeroed at tablet portrait → horizontal overflow + scrollbar
- **Severity:** critical
- **Breakpoint:** 768px
- **Selector / element:** `.main-content` (file: `assets/css/styles.css:1209-1220` and `:1551-1564`)
- **Screenshot region:** entire viewport — vertical scrollbar visible far right of `tablet_portrait/tasks.png`; "+ Nuovo Task" button hugs the right edge as if anchored to a 1028px-wide viewport.
- **Current behaviour:** the media query that zeroes `margin-left` only fires at `<= 768px`, but tablet-portrait is exactly 768px and at `768.5px` (or higher e.g. 1024 with sidebar still wide) we're stuck. The sidebar takes 260px width (line 701) yet `main-content` keeps `margin-left: 260px` AND there's no media query that hides the sidebar between 769px and 1024px → at 1024 (and below to 768) the sidebar plus indented content sum to 1284px > 1024px viewport.
- **Root cause (CSS):** asymmetric breakpoints (sidebar visible at all desktop widths, content margin-tied to it, but topbar `.cnx-app-topbar` flips at 768).
- **Recommended fix:** see calendar.md F1; identical fix:
```css
@media (max-width: 1024px) {
  .main-content { margin-left: 0 !important; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .cnx-app-topbar { left: 0; }
}
```

### F2 — Kanban "Da Fare / In Corso / In Revisione / Completati" 4 columns at 375px → only 1.x columns fit, causing horizontal scrolling
- **Severity:** critical
- **Breakpoint:** 375px (also 768px — only 2 columns fit)
- **Selector / element:** `.tasks-board` (file: `tasks.php:79-83` inline `<style>`)
- **Screenshot region:** body of page from row 4 onwards in `mobile/tasks.png` — only "Da Fare" column visible.
- **Current behaviour:** `grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))` means each column requires a minimum of 300px before shrinking → at 375px it lays out exactly 1 column (good in theory) but in practice the inline content overflows because of the debug-text dump (F4) pushing each card to >375px.
- **Root cause (CSS):** the `auto-fit` works, but the cards inside don't have `min-width: 0` so they overflow the column track.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .tasks-board {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }
  .task-column { min-width: 0; }
  .task-card { min-width: 0; overflow: hidden; }
}
```

### F3 — Filter pills "Tutti / I Miei Task / Alta Priorità / Scadenza Oggi / Completati" wrap onto 2 rows at mobile (visible) but the row break happens between "Alta Priorità" and "Scadenza Oggi" creating uneven line lengths
- **Severity:** major
- **Breakpoint:** 375px (acceptable on 768px)
- **Selector / element:** `.tasks-filters` and `.filter-btn` (file: `tasks.php:51-77` inline `<style>`)
- **Screenshot region:** row of pills above kanban — `mobile/tasks.png` shows "Tutti / I Miei Task / Alta Priorità" on row 1 and "Scadenza Oggi / Completati" on row 2.
- **Current behaviour:** wrap is automatic but each pill has different width because text-content varies. Acceptable but the `padding: var(--space-2) var(--space-4)` (`8px 16px`) makes them barely tappable at 36px-ish height.
- **Root cause (CSS):** no min-height; padding-only sizing yields inconsistent touch targets.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .tasks-filters {
    gap: 6px !important;
    margin-bottom: 16px !important;
  }
  .tasks-filters .filter-btn {
    min-height: 36px; padding: 0 12px;
    font-size: 12px;
    flex: 0 1 auto;
  }
}
@media (max-width: 480px) {
  /* Optional: scroll horizontally instead of wrap */
  .tasks-filters {
    flex-wrap: nowrap !important;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }
  .tasks-filters::-webkit-scrollbar { display: none; }
  .tasks-filters .filter-btn { white-space: nowrap; flex: 0 0 auto; }
}
```

### F4 — Task cards dump raw debug strings in description ("Cliente: ... (#148) Piano: ISO 9001 (#168) ... title_hash=10911d3b1c..., assignee_user_id=49, client_tenant_id=148") which wraps brutally and dominates the card on mobile
- **Severity:** critical
- **Breakpoint:** both (375 + 768)
- **Selector / element:** `.task-description` (file: `tasks.php:131-136` inline). Content is server-rendered from `assets/js/tasks.js` task.description field; the debug dump is in the data, not the UI.
- **Screenshot region:** entire content of every task card — at 768px the description block is ~12 lines, at 375px it's ~16 lines. Hash strings break around hex characters making the layout look like garbage.
- **Current behaviour:** description has `font-size: var(--text-xs)` (~12px) and no truncation — full payload rendered.
- **Root cause (CSS + DATA):** the data itself is polluted (likely from a planning ingest), but CSS-side there's no clamp/ellipsis.
- **Recommended fix:** CSS-only mitigation — clamp to 3 lines mobile / 5 lines tablet:
```css
@media (max-width: 1024px) {
  .task-description {
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
}
@media (max-width: 768px) {
  .task-description { -webkit-line-clamp: 3; }
}
```
**Note:** the underlying data hygiene issue (descriptions containing `title_hash=...`, `client_tenant_id=...` debug fields) is a separate concern best fixed in `api/tasks.php` or the planning import.

### F5 — Yellow alert banners "Sei in modalità multi-azienda" + "46 task senza utente assegnato valido" have NO close handle on mobile
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `.alert.alert-warning` (file: `tasks.php:392-402` inline `<style>` — light mode rule; dark theme override at `assets/css/components.css:1411-1419`)
- **Screenshot region:** two yellow-bordered banners stacked at top of page content in `mobile/tasks.png` and `tablet_portrait/tasks.png`.
- **Current behaviour:** banners take ~150px each in vertical space (especially mobile where text wraps to 4 lines). No dismiss button → forever taking screen real estate even after the user has acknowledged the message.
- **Root cause (CSS + MARKUP):** the `.alert` markup at `tasks.php:612-619` and `:622-634` doesn't include a close button. CSS has nothing to dismiss either.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** — inject a close button + JS handler. CSS:
```css
.alert { position: relative; padding-right: 48px; }
.alert-close {
  position: absolute; top: 8px; right: 8px;
  width: 32px; height: 32px; border: none; background: transparent;
  font-size: 20px; line-height: 1; color: inherit; cursor: pointer;
  border-radius: var(--cnx-radius-pill);
}
.alert-close:hover { background: rgba(0,0,0,0.06); }
@media (max-width: 768px) {
  .alert { padding: 12px 40px 12px 12px; font-size: 13px; }
}
```

### F6 — Header "Gestione Task" + Company Filter dropdown + "+ Nuovo Task" button overflow horizontally at 375px
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.main-content > .header > .flex.items-center.gap-4` (file: `tasks.php:597-605`)
- **Screenshot region:** top of page in `mobile/tasks.png` — "Gestione Task" on row 1, "Azienda: Tutte le aziende ▾" on row 2 (clipped right), "+ Nuovo Task" mint pill on row 3 stretched to ~280px width.
- **Current behaviour:** components.css `.header` flex-wrap wraps the row OK, but each child is full-width because `.flex.items-center.gap-4` (Tailwind utility) lacks wrap. "+ Nuovo Task" gets stretched because it's inside a `display: flex` parent that gives all flex-items equal grow.
- **Root cause (CSS):** the inline `.flex.items-center.gap-4` is non-responsive.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .main-content > .header .flex.items-center.gap-4 {
    flex-wrap: wrap !important;
    width: 100%;
    gap: 8px !important;
  }
  .main-content > .header .flex.items-center .btn { flex: 0 0 auto; }
  .main-content > .header .flex.items-center .company-filter,
  .main-content > .header .company-filter-dropdown { flex: 1 1 auto; min-width: 0; }
  .main-content > .header #newTaskBtn {
    flex: 1 1 100%;
    min-height: 44px;
  }
}
```

### F7 — Sidebar visible on tablet but no hamburger toggle exists (same as calendar F11)
- **Severity:** critical
- **Breakpoint:** 768px (and 375px)
- **Selector / element:** `.sidebar` and missing `.sidebar-toggle` in markup (file: `includes/sidebar.php` and `includes/layout_start.php`)
- **Screenshot region:** in `tablet_portrait/tasks.png` no sidebar visible but content has `margin-left: 260px` → 260px-wide empty gutter on left edge. In `mobile/tasks.png` same pattern.
- **Current behaviour:** identical to calendar F11. The hamburger button exists as a CSS rule at `styles.css:1258-1283` but no DOM element actually carries the `.sidebar-toggle` class in `layout_start.php` or `sidebar.php`.
- **Root cause (CSS + missing markup):** described in calendar.md F11.
- **Recommended fix:** see calendar.md F11. **REQUIRES MARKUP CHANGE.**

### F8 — Task card body has fixed-style `assignee-avatar` (24×24) + filename-like multi-line title at 14px — readable but inconsistent gap rhythm
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.task-meta`, `.task-priority`, `.assignee-avatar` (file: `tasks.php:138-183`)
- **Screenshot region:** bottom of each task card in `tablet_portrait/tasks.png` — yellow "Media" badge + "S.CO Srls" tenant label + green-circle "FR" avatar + "Francesco Barreca" name spread across the meta row.
- **Current behaviour:** `display: flex; justify-content: space-between` on `.task-meta` works but on narrow widths the assignee name wraps under the avatar, leaving a misaligned 2nd line. Avatar is also small (24px) — touch target borderline.
- **Root cause (CSS):** no flex-wrap rules on `.task-meta`; size variables not bumped on mobile.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .task-meta {
    flex-wrap: wrap; gap: 8px;
    align-items: center;
  }
  .task-meta .task-assignee {
    flex: 1 1 100%;
    min-width: 0;
    margin-top: 4px;
  }
  .assignee-avatar { width: 28px; height: 28px; font-size: 11px; }
}
```

### F9 — Task title wraps with weird hyphens / breaks because of long brand name "PROMETEO SOC. COOP. SOCIALE O.N.L.U.S.: ISO 9001 - Audit Interno e Riesame"
- **Severity:** minor
- **Breakpoint:** both
- **Selector / element:** `.task-title` (file: `tasks.php:124-129`)
- **Screenshot region:** title block of every task card.
- **Current behaviour:** title wraps into 3 lines at 768px, 4 lines at 375px because dots inside ONLUS aren't natural break points; browser breaks at spaces only.
- **Root cause (CSS):** no `overflow-wrap` rule.
- **Recommended fix:**
```css
.task-title {
  word-break: break-word;
  overflow-wrap: anywhere;
}
@media (max-width: 768px) {
  .task-title {
    font-size: 13px; line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}
```

### F10 — Empty-column placeholder "Nessun task in questa colonna" centered with no min-height makes "In Corso" column collapse to ~80px on mobile while "Da Fare" is 800px+ (50 cards)
- **Severity:** major
- **Breakpoint:** 768px (visible) and 375px (less obvious because columns stack)
- **Selector / element:** `.task-column` and `.empty-state` (file: `tasks.php:85-89, 545-556`)
- **Screenshot region:** in `tablet_portrait/tasks.png` "In Corso 0" column is visibly tiny vs its neighbour.
- **Current behaviour:** unbalanced column heights; on stacked-mobile the empty placeholder shows but doesn't communicate dropzone affordance.
- **Root cause (CSS):** no `min-height` on `.task-column`.
- **Recommended fix:**
```css
.task-column { min-height: 200px; }
@media (max-width: 768px) {
  .task-column { min-height: 120px; padding: 12px !important; }
  .task-column .empty-state { padding: 24px 12px; font-size: 13px; }
}
```

### F11 — Task cards have `cursor: move` and `transform: translateY(-2px)` on hover — drag-and-drop totally broken on touch (no fallback move-up button)
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `.task-card` (file: `tasks.php:109-122`)
- **Screenshot region:** each card body in both screenshots.
- **Current behaviour:** desktop UX (drag between columns) has no touch equivalent. The `.task-card-actions` (file: `tasks.php:431-441`) only show on `:hover` (`display: none` → `display: flex` on hover), invisible on touch devices.
- **Root cause (CSS):** hover-only reveal pattern is anti-touch.
- **Recommended fix:**
```css
@media (max-width: 1024px) {
  .task-card { cursor: pointer !important; }
  .task-card:hover { transform: none !important; }
  .task-card .task-card-actions {
    display: flex !important;
    position: static; margin-top: 8px;
    border-top: 1px solid var(--cnx-border);
    padding-top: 8px;
  }
  .task-card .task-card-btn {
    width: 36px; height: 36px;
  }
}
```
**REQUIRES MARKUP CHANGE** if want to add a status-dropdown fallback (the "move to In Corso" requires JS to expose a `<select>` instead of drag).

### F12 — `assignee-progress-bar` 12px tall — on mobile not enough touch area to tap a specific 25/50/75/100 step
- **Severity:** minor
- **Breakpoint:** 375px (assignee modal)
- **Selector / element:** `.assignee-progress-bar` and `.assignee-step-btn` (file: `tasks.php:336-360`)
- **Screenshot region:** not visible in current screenshots (modal closed) — discoverable when opening a task as assignee.
- **Current behaviour:** 12px-tall click target violates 44px guideline.
- **Root cause (CSS):** explicit `height: 12px`.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .assignee-progress-bar {
    height: 24px !important;
  }
  .assignee-step-btn {
    min-height: 44px;
    flex: 1 1 22%;
  }
  .assignee-progress-steps { gap: 6px; }
}
```

### F13 — Modal `.modal-dialog` width `90%` + max-width 600px — at 375px usable width is 337px, but inputs inside use `padding: var(--space-2) var(--space-3)` and form-row 2-column grid breaks at 560px (rule exists, OK)
- **Severity:** minor
- **Breakpoint:** 375px (modal)
- **Selector / element:** `.modal-dialog`, `.form-row` (file: `tasks.php:208-219, 296-306`)
- **Screenshot region:** not visible in screenshots.
- **Current behaviour:** modal takes 90% of viewport (= 337px at 375px), inputs are full-width, OK. But the `.modal-header`, `.modal-body`, `.modal-footer` each use `padding: var(--space-6)` (24px) → cumulative 48px reduces real input width to 289px which is tight for a date picker + label.
- **Root cause (CSS):** desktop padding bleeds into mobile.
- **Recommended fix:**
```css
@media (max-width: 480px) {
  .modal-dialog { width: 100% !important; max-height: 100vh; border-radius: 0; }
  .modal-header, .modal-body, .modal-footer,
  #taskForm, #assigneeForm { padding: 16px !important; }
  .modal-footer {
    flex-direction: column-reverse;
  }
  .modal-footer .btn {
    width: 100%; min-height: 44px;
  }
}
```

### F14 — Toast notification position `top: 24px; right: 24px` overlaps the page header on mobile (no offset for the 56px topbar)
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.toast-container` (file: `tasks.php:471-479`)
- **Screenshot region:** N/A in screenshots.
- **Current behaviour:** toast at top-right collides with the topbar (which is `position: fixed; top: 0; height: 56px` at 768px).
- **Root cause (CSS):** absolute positioning ignores topbar height.
- **Recommended fix:**
```css
@media (max-width: 768px) {
  .toast-container {
    top: 64px !important;
    left: 16px;
    right: 16px;
    width: auto;
  }
  .toast {
    min-width: 0; width: 100%;
    font-size: 13px;
  }
}
```

### F15 — `.task-card-btn` (edit / delete icon button) is 24×24 with hidden-until-hover — invisible on touch + below 44px target
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `.task-card-btn` (file: `tasks.php:443-468`)
- **Screenshot region:** N/A (hidden by hover rule, never visible on touch).
- **Current behaviour:** see F11 — actions never appear on touch devices because `display: none` only flips on `:hover`.
- **Root cause (CSS):** hover-driven UX incompatible with touch.
- **Recommended fix:** covered by F11.

### F16 — Tasks filter pill "Tutti" active state uses legacy `--color-primary` (blue) — clashes with mint accent on dark mode (already handled in components.css:1428-1442 but light mode still blue)
- **Severity:** minor
- **Breakpoint:** both (more visible at desktop, included for completeness)
- **Selector / element:** `.filter-btn.active` (file: `tasks.php:73-77`)
- **Screenshot region:** the active "Tutti" pill in both screenshots — mint in dark mode (good), but light mode would be blue per rule `background: var(--color-primary)`.
- **Current behaviour:** in dark mode the `[data-theme="dark"] .filter-pill.active` override at components.css:1438-1442 fires, but the rule selectors `.filter-tabs .filter-tab` and `.filter-pill` don't match the actual class `.filter-btn` used in tasks.php → the dark-mode override actually never applies. The screenshots show "Tutti" mint because `--color-primary` itself was changed in tokens to mint? No — checking: `--color-primary: var(--cnx-accent)` so light mode is mint too. **OK in practice, but the components.css override is dead code.**
- **Root cause (CSS):** selector mismatch in components.css dark-mode block.
- **Recommended fix:** add `.filter-btn` and `.filter-btn.active` to the dark-mode override list at `assets/css/components.css:1428-1442`:
```css
[data-theme="dark"] .filter-tabs .filter-tab,
[data-theme="dark"] .task-filter-tab,
[data-theme="dark"] .filter-pill,
[data-theme="dark"] .filter-btn {
    background: var(--cnx-bg-subtle);
    color: var(--cnx-text-secondary);
    border: 1px solid var(--cnx-border);
}
[data-theme="dark"] .filter-tabs .filter-tab.active,
[data-theme="dark"] .task-filter-tab.active,
[data-theme="dark"] .filter-pill.active,
[data-theme="dark"] .filter-btn.active {
    background: var(--cnx-accent);
    color: var(--cnx-accent-ink);
    border-color: var(--cnx-accent);
}
```
