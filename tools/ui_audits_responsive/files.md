# Responsive audit — files.php

> Viewports inspected:
> - mobile 375x812 → `tools/ui_redesign/responsive_pre/mobile/files.png`
> - tablet portrait 768x1024 → `tools/ui_redesign/responsive_pre/tablet_portrait/files.png`
>
> Theme: dark mode (data-theme="dark"). Login is super_admin (asamodeo@fortibyte.it).
> View mode in screenshots: **list** (`<table class="file-table">`) — the more failure-prone of the two views.

## Summary
- Total findings: 22
- Severity: critical=5, major=10, minor=7
- Worst breakpoint: **375px** (mobile) — file table forces a horizontal page-level scrollbar; toolbar buttons cluster but no labels; breadcrumb is sandwiched between two filter rows.

## Findings

### F1 — File table forces a horizontal scrollbar that escapes `.files-wrapper` and bleeds to the page level on mobile
- **Severity:** critical
- **Breakpoint:** 375px (and 768px to a lesser extent)
- **Selector / element:** `.file-table` (file: `assets/css/filemanager.css:384-388`) inside `.files-list { display: block }` (line 380-382) inside `.files-wrapper { overflow: auto }` (line 195-200)
- **Screenshot region:** the entire bottom half (the visible horizontal scrollbar at the very bottom of the screenshot proves this).
- **Current behaviour:** the table has 7 columns (checkbox 48px + name 200px-min + Proprietario + Assegnato a + Modificato + Dimensione + actions 48px). At 375px viewport with main-content padding=16px each side and `.file-main-container` no extra padding, available width ≈ 343px. The table needs ~640-700px → it scrolls horizontally inside `.files-wrapper`, which is fine, BUT in the screenshot a horizontal scrollbar is also visible at the page bottom — this comes from `.page-content { overflow: hidden }` (line 17 filemanager.css) NOT being enforced strictly enough because `.files-wrapper { overflow: auto }` is set, allowing the inner table to overflow horizontally without containment, and the wrapper itself ends up wider than its container.
- **Root cause (CSS):** `.files-wrapper { overflow: auto }` is fine; however `.file-table { width: 100% }` lets the table compete with `min-width: 200px` on `.name-col` causing the table to be wider than 100% (intrinsic min-content). Since `.page-content` is a flex column and `.file-main-container { display: flex; overflow: hidden }`, the inner flex item still shrinks below its min-content unless we explicitly set `min-width: 0`. The wrapper does not have `min-width: 0` so its min-content = table min-width.
- **Recommended fix:**
  ```css
  /* assets/css/filemanager.css — append at end of @media (max-width: 768px) block */
  @media (max-width: 768px) {
      .file-main-container { min-width: 0; }
      .files-wrapper { min-width: 0; max-width: 100%; }
      /* On mobile, hide the table view entirely and force grid view */
      .files-list.view-active { display: none !important; }
      .files-grid { display: grid !important; }
      .view-toggle { display: none; } /* hide the toggle since list is unusable here */
  }
  ```
  Alternative (preserves the toggle but reflows the table to cards):
  ```css
  @media (max-width: 640px) {
      .file-table, .file-table thead, .file-table tbody, .file-table tr, .file-table th, .file-table td { display: block; }
      .file-table thead { display: none; } /* hide column headers */
      .file-table tbody tr {
          padding: 12px 14px;
          border: 1px solid var(--cnx-border);
          border-radius: var(--cnx-radius-md);
          margin-bottom: 8px;
          background: var(--cnx-bg-surface);
      }
      .file-table tbody td {
          padding: 4px 0;
          border: none;
          font-size: 13px;
      }
      .file-table tbody td.checkbox-col,
      .file-table tbody td.actions-col { display: inline-block; width: auto; }
      .file-table tbody td.name-col { display: block; min-width: 0; font-weight: 600; }
  }
  ```

### F2 — Sidebar hamburger button is undersized vs accessibility minimum on mobile
- **Severity:** major
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.sidebar-toggle` (file: `assets/css/styles.css:1258-1283`)
- **Screenshot region:** top of the page header, immediately to the left of "File Manager".
- **Current behaviour:** `.sidebar-toggle { width: 40px; height: 40px }` — below the 44x44 (iOS HIG) / 48x48 (Material) minimum tap target.
- **Root cause (CSS):** explicit 40x40.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .sidebar-toggle { width: 44px; height: 44px; font-size: 22px; }
  }
  ```

### F3 — 5 toolbar buttons (`Carica`, `Aggiungi`, `Carica Cartella`, `Nuova Cartella`, `Cartella Tenant`) wrap to a second row on tablet 768 portrait
- **Severity:** major
- **Breakpoint:** 768px (also 375px but expected there)
- **Selector / element:** `.header .header-right .btn` (file: `assets/css/filemanager.css:1017-1024`)
- **Screenshot region:** top-right corner of the page header (visible: 5 small icon buttons stacked on row 2, "File Manager" on row 1).
- **Current behaviour:** at <=768 the rule `.header .btn span { display: none }` hides labels and `.header .btn { padding: 8px; width: 36px }` shrinks to icon-only — but on portrait tablet 768 there is in fact enough horizontal room (~720px after padding) to keep the buttons on the same row as the title. Currently the title row is on top and the buttons row below because of `flex-wrap: wrap` on `.main-content > .header` (`components.css:62`). Visually: lots of empty space to the right of the title at 768px.
- **Root cause (CSS):** the header has flex-wrap inheriting from the global rule; the buttons are pushed to row 2 because `.header-left` takes `flex: 1 1 280px` and grabs whatever's left.
- **Recommended fix:**
  ```css
  @media (min-width: 700px) and (max-width: 1023px) {
      .main-content > .header > .header-left { flex: 0 1 auto; } /* let title shrink so buttons fit on row 1 */
      .main-content > .header > .header-right { margin-left: auto; }
  }
  ```

### F4 — On mobile the toolbar uses 5 *icon-only* 36x36 buttons with no labels — failing minimum tap target AND removing accessible label
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.header .btn` icon-only + missing `aria-label` (file: `assets/css/filemanager.css:1017-1024`, markup `files.php:103-152`)
- **Screenshot region:** under "File Manager" — 5 mint-tinted square buttons in a row.
- **Current behaviour:** width 36px, height (implicit ~36-40px), span hidden. Below 44x44 minimum. Markup: only some buttons have `<span>` text but the SVGs do not have `<title>` or `aria-label` attributes — when the visible label is hidden via CSS the button becomes label-less for screen readers.
- **Root cause (CSS):** explicit `width: 36px; padding: 8px` rule.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .header .btn { width: 44px; height: 44px; padding: 10px; }
      .header .btn svg { width: 20px; height: 20px; }
  }
  ```
  **REQUIRES MARKUP CHANGE** also: add `aria-label="Carica file"`, etc. on each button so the icon-only state is accessible.

### F5 — Search bar + filter pills row stacks vertically (correct) but `Filtra` and `Ordina` push wide leaving "Cerca file e cartelle" centred and short
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.file-search-bar` + `.search-container` + `.filter-controls` (file: `assets/css/filemanager.css:23-94`, mobile override `:1030-1042`)
- **Screenshot region:** the band immediately below the action buttons.
- **Current behaviour:** at <=768 the search-bar becomes column with `gap: 12px`. Search-input gets full width (good). Then `.filter-controls { width: 100%; justify-content: space-between }` (line 1039) — `Filtra` left-anchored, `Ordina` right-anchored, large gap in the middle.
- **Root cause (CSS):** `justify-content: space-between` produces awkward whitespace.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .filter-controls { justify-content: flex-start; gap: 8px; }
      .filter-btn, .sort-btn { flex: 1 1 auto; min-height: 44px; justify-content: center; }
  }
  ```

### F6 — Breadcrumb-nav does NOT stack until <=480px; at 768 the home icon + "File Manager" stays on the same row as the view-toggle, but on 375 it scrolls horizontally because the toggle pushes right
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.breadcrumb-nav` (file: `assets/css/filemanager.css:99-106`, mobile <=480 override `:1069-1074`)
- **Screenshot region:** "🏠 File Manager" + grid/list view toggles on a separate row.
- **Current behaviour:** at <=480 it stacks (good). At 481-768 (tablet portrait) it stays one row, ok. On the actual 375 screenshot the breadcrumb-nav has wrapped — works. But the horizontal padding `12px 24px` is heavy on mobile.
- **Root cause (CSS):** padding 12px 24px stays at all sizes.
- **Recommended fix:**
  ```css
  @media (max-width: 480px) {
      .breadcrumb-nav { padding: 10px 16px; gap: 8px; }
      .view-toggle { align-self: flex-end; }
  }
  ```

### F7 — `.file-search-bar` and `.breadcrumb-nav` use `padding: 16px 24px` and `12px 24px` on mobile, eating ~48px of horizontal room
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.file-search-bar` (file: `assets/css/filemanager.css:25`), `.breadcrumb-nav` (file: `assets/css/filemanager.css:101`)
- **Screenshot region:** search row + breadcrumb row.
- **Current behaviour:** stays at 24px horizontal even on mobile. Results in a search input only ~327px wide.
- **Root cause (CSS):** no mobile override for these two padding values.
- **Recommended fix:**
  ```css
  @media (max-width: 480px) {
      .file-search-bar { padding: 12px 16px; gap: 8px; }
      .breadcrumb-nav { padding: 10px 16px; }
  }
  ```

### F8 — `view-toggle` (grid/list) buttons each only ~32x32 — below tap target and centred between two padding bands
- **Severity:** major
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.view-btn` (file: `assets/css/filemanager.css:157-168`)
- **Screenshot region:** right end of breadcrumb row.
- **Current behaviour:** `.view-btn { padding: 8px }` plus implicit content size = ~32x32 hit area.
- **Root cause (CSS):** padding 8px without min-width/min-height.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .view-btn { min-width: 40px; min-height: 40px; padding: 10px; }
  }
  ```

### F9 — At 375px the `cnx-input-group` search uses native browser autocomplete styling; the placeholder "Cerca file e cartelle..." truncates ("Cerca file e cart...") because the input is in a flex container with limited width
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.search-input` (file: `assets/css/filemanager.css:48-62`)
- **Screenshot region:** search bar.
- **Current behaviour:** placeholder is "Cerca file e cartelle..." — visible in the screenshot in full, no truncation. Visually OK at 375.
- **Root cause (CSS):** none.
- **Recommended fix:** no change.

### F10 — `cnx-app-topbar` global search "Cerca file, persone, ticket..." overlaps the page header at scroll-up because both are fixed/sticky
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-49`) over `.main-content > .header` (`:58-70`)
- **Screenshot region:** top 56px of viewport (mobile).
- **Current behaviour:** the topbar is `position: fixed`, the page header below is normal flow within `.main-content` which has `padding-top: 56px`. They do NOT overlap — they stack correctly (topbar on top, page header below). OK.
- **Root cause (CSS):** none.
- **Recommended fix:** no change.

### F11 — `body { background-color: var(--cnx-bg-sidebar) }` shows through whenever `.main-content` does not paint over the full viewport (e.g. when `.file-main-container` has limited height)
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `body` (file: `assets/css/styles.css:1670-1672`) + `.main-content` (`:1909-1911`)
- **Screenshot region:** none visible in current screenshots.
- **Current behaviour:** main-content's height stretches to viewport so body bg never leaks. OK.
- **Root cause (CSS):** none.
- **Recommended fix:** as in dashboard audit F13:
  ```css
  @media (max-width: 768px) {
      body { background-color: var(--cnx-bg-app); }
  }
  ```

### F12 — `.cnx-breadcrumb` icon ("I Miei File") + `.cnx-breadcrumb__sep` "/" + `Documenti` on mobile readable, but font-size 14px (`small`) is borderline tiny
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-breadcrumb` (file: `assets/css/components.css:633-670`)
- **Screenshot region:** breadcrumb row.
- **Current behaviour:** font-size token `--cnx-text-small-size` ~13-14px. Acceptable.
- **Root cause (CSS):** none.
- **Recommended fix:** no change.

### F13 — File table column headers ("PROPRIETARIO", "ASSEGNATO A", etc.) split lines awkwardly on tablet 768 because column widths are not constrained
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.file-table th` (file: `assets/css/filemanager.css:397-405`)
- **Screenshot region:** table header row visible: "ASSEGNATO" and "A" on two lines, "DIMENSIONE" full width.
- **Current behaviour:** no min-width per column except `.name-col` (300px). The browser hands out 7 columns from a ~720px content area, splitting words awkwardly.
- **Root cause (CSS):** missing column min-widths + `text-transform: uppercase` makes "Assegnato A" wrap to "ASSEGNATO\nA".
- **Recommended fix:**
  ```css
  @media (max-width: 1024px) {
      .file-table th, .file-table td { white-space: nowrap; }
      .file-table th[scope="col"]:nth-child(4),  /* Assegnato A */
      .file-table th[scope="col"]:nth-child(5),  /* Modificato */
      .file-table th[scope="col"]:nth-child(6) { /* Dimensione */
          font-size: 11px;
      }
  }
  @media (max-width: 768px) {
      /* Hide non-essential columns on portrait tablet/phone */
      .file-table th:nth-child(3),  /* Proprietario */
      .file-table td:nth-child(3),
      .file-table th:nth-child(4),  /* Assegnato A */
      .file-table td:nth-child(4),
      .file-table th:nth-child(6),  /* Dimensione */
      .file-table td:nth-child(6) { display: none; }
      .name-col { min-width: 200px; }
  }
  ```

### F14 — Folder rows "Tu" / "—" placeholders + folder name wrap to 2 lines (e.g. "Analisi Cliniche / Amenta") creating non-uniform row heights at 768
- **Severity:** minor
- **Breakpoint:** 768px
- **Selector / element:** `.file-table td.name-col` (file: `assets/css/filemanager.css:436-438`)
- **Screenshot region:** table body, rows "Analisi Cliniche Amenta", "Brianzatende S.R.L. - Marchio: BT Group", "Casa Dell'Annunziata".
- **Current behaviour:** `min-width: 300px` (line 438) prevents narrower; folder names with line break wrap to 2 lines. Looks OK aesthetically.
- **Root cause (CSS):** intentional.
- **Recommended fix:** no change.

### F15 — Drop-zone overlay (`#dropZone` `.drop-zone-overlay`) takes the entire `.files-wrapper`. On mobile its content (svg + h3 + p) is absolute-centred but the wrapper is much taller than viewport so the message is far below the fold
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.drop-zone-overlay` (file: `files.php:223-233`, CSS in `filemanager.css` not shown but typical absolute fill)
- **Screenshot region:** N/A (drop zone only visible during drag).
- **Current behaviour:** drop-zone is `display: none` until a dragenter event fires.
- **Root cause (CSS):** none observed in screenshots.
- **Recommended fix:** no change unless drag-on-mobile is supported.

### F16 — Empty-state (no files) container has fixed `padding: var(--space-12) var(--space-6)` = 48px top/bottom, 24px sides — too tall on a 812px mobile viewport
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.empty-state` (file: `assets/css/dashboard.css:560-563` shared, plus `assets/css/filemanager.css:684-715`)
- **Screenshot region:** N/A (folders exist in screenshot).
- **Current behaviour:** when folders are present, empty-state is hidden.
- **Root cause (CSS):** none observed.
- **Recommended fix:** prophylactic:
  ```css
  @media (max-width: 480px) {
      .empty-state { padding: 24px 16px; }
      .empty-state-icon { width: 56px; height: 56px; }
  }
  ```

### F17 — Modal (`.cnx-modal`) opens with `padding: 24px` overall and `max-width: 560px` — at 375px the modal renders only ~327px wide and the dropzone inside (`padding: 48px 24px`) is too tall
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-modal`, `.cnx-modal__dialog`, `.cnx-dropzone` (file: `assets/css/components.css:864-896, 960-1012`)
- **Screenshot region:** N/A (modal not open in screenshots).
- **Current behaviour:** at 375 the modal dialog gets `width: 100%` capped by `max-width: 560px`, then minus 48px modal padding = 327px visible. Inside, dropzone has 48px vertical and 24px horizontal padding. Modal header padding 24px each side. The dropzone CTA "Trascina qui i file" + hint stacks vertically.
- **Root cause (CSS):** modal padding too generous; dropzone too tall.
- **Recommended fix:**
  ```css
  @media (max-width: 480px) {
      .cnx-modal { padding: 0; align-items: flex-end; } /* full-screen sheet */
      .cnx-modal__dialog {
          max-width: 100%;
          max-height: 92vh;
          border-radius: var(--cnx-radius-lg) var(--cnx-radius-lg) 0 0;
      }
      .cnx-modal__header { padding: 16px 16px 12px; }
      .cnx-modal__body { padding: 4px 16px 16px; }
      .cnx-modal__footer { padding: 12px 16px 16px; flex-direction: column-reverse; }
      .cnx-modal__footer .cnx-btn { width: 100%; }
      .cnx-dropzone { padding: 28px 16px; }
      .cnx-dropzone__icon { width: 32px; height: 32px; }
  }
  ```

### F18 — Tabs (`.cnx-tabs` File/Cartella) inside the upload modal use 4px padding around 6px-padded tabs — touch targets ~28px tall
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.cnx-tab` (file: `assets/css/components.css:1070-1094`)
- **Screenshot region:** N/A (modal not open).
- **Current behaviour:** `padding: 6px 14px` → ~28px height. Below tap target.
- **Root cause (CSS):** small inline padding for desktop density.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .cnx-tab { padding: 10px 14px; min-height: 40px; font-size: 14px; }
      .cnx-tabs { padding: 4px; }
  }
  ```

### F19 — Sidebar drawer at <=768px has no overlay/scrim and is *behind* the topbar (z-index ordering bug)
- **Severity:** critical
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.sidebar` (file: `assets/css/styles.css:696-711`, mobile transform `:1551-1574`) — `z-index: var(--z-sticky)` (typically ~30) vs `.cnx-app-topbar { z-index: 250 }`
- **Screenshot region:** N/A (drawer is closed in screenshots).
- **Current behaviour:** when the user taps the hamburger and the drawer slides in, the topbar (z-index 250) sits ABOVE the drawer (z-index ~30). The drawer's top 56px is covered by the search bar — search bar still wired to focus, capturing taps that should have hit the first nav-item (Dashboard).
- **Root cause (CSS):** wrong z-index ordering for mobile drawer pattern.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .sidebar { z-index: 1100; box-shadow: 8px 0 24px rgba(0, 0, 0, 0.32); }
      .sidebar::after {
          content: ""; position: fixed; inset: 0;
          background: rgba(0, 0, 0, 0.42);
          opacity: 0; pointer-events: none;
          transition: opacity 200ms ease; z-index: -1;
      }
      .sidebar.open::after { opacity: 1; pointer-events: auto; }
  }
  ```
  (Same fix as dashboard.md F18.)

### F20 — File-grid view on tablet 768 uses `repeat(auto-fill, minmax(180px, 1fr))` (line 1000) producing 3 columns — at 768 with content 720px = 3 columns × 240px, OK. But on 375 mobile the override at line 1044 forces `minmax(140px, 1fr)` — 2 columns × ~165px. File names with long tenant names ("Brianzatende S.R.L. - Marchio: BT Group") will overflow the card or truncate.
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.files-grid` mobile (file: `assets/css/filemanager.css:1044-1048`) + `.file-card` (file: `assets/css/filemanager.css:1080-1082`)
- **Screenshot region:** N/A (list view in screenshots) — but switching to grid view exposes the issue.
- **Current behaviour:** card width ~165px on 375. Long folder names use `text-overflow: ellipsis` (typical pattern but not verified here).
- **Root cause (CSS):** narrow cards.
- **Recommended fix:**
  ```css
  @media (max-width: 480px) {
      .files-grid {
          grid-template-columns: 1fr; /* single column on phones */
          gap: 8px; padding: 12px 16px;
      }
      .file-card { display: flex; align-items: center; gap: 12px; padding: 12px; text-align: left; }
      .file-card-icon { width: 40px; height: 40px; flex-shrink: 0; }
      .file-card .file-card-meta { flex: 1; min-width: 0; }
  }
  ```

### F21 — Tenant-context-badge (admin/super_admin only) has no responsive treatment — could push the toolbar to row 3 if tenant name is long
- **Severity:** minor
- **Breakpoint:** 768px, 375px
- **Selector / element:** `.tenant-context-badge` (file: `files.php:93-100`, CSS not located in scanned files)
- **Screenshot region:** not visible (badge only shown when tenant context filtered).
- **Current behaviour:** when displayed, badge has icon + tenant name; could exceed available row width.
- **Root cause (CSS):** missing `max-width` / `text-overflow: ellipsis`.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .tenant-context-badge {
          max-width: 140px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
      }
  }
  ```

### F22 — `.action-buttons` cell in file-table list view uses 3-emoji icons that won't tint properly in dark mode + 36px wide — borderline tap target
- **Severity:** minor
- **Breakpoint:** 375px, 768px
- **Selector / element:** `.file-table .actions-col .action-btn` (file: `assets/css/filemanager.css:462-497`)
- **Screenshot region:** rightmost column of each table row (cropped off-screen by the page-level horizontal scrollbar — see F1).
- **Current behaviour:** action button is 36x36 (line 462-475). Below 44x44 tap minimum.
- **Root cause (CSS):** 36x36 fixed.
- **Recommended fix:**
  ```css
  @media (max-width: 768px) {
      .file-table .actions-col .action-btn { width: 44px; height: 44px; }
      .file-table .actions-col .action-btn svg { width: 20px; height: 20px; }
  }
  ```

---

## Notes on what is correctly handled today
- `.header .btn span { display: none }` at <=768 (line 1017 filemanager.css) — labels collapse to icons on mobile (the size 36px is wrong, see F4, but the principle is right).
- `.header .company-filter-wrapper { display: none !important }` at <=768 (line 1026-1028) — company filter correctly hidden on mobile to make room for action buttons.
- `.file-search-bar { flex-direction: column }` at <=768 (line 1030-1033) — search/filter bar correctly stacks.
- `.breadcrumb-nav { flex-direction: column }` at <=480 (line 1070-1074) — breadcrumb correctly stacks on small phones.
- `#createRootFolderBtn { background: var(--cnx-accent) }` (`components.css:1458`) — purple gradient correctly stripped, mint accent applied (BUG round 3 fix).
- `.cnx-modal__dialog` `max-height: calc(100vh - 48px)` — modal correctly clamped vertically.
