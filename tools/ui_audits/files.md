# Dark mode audit — files.php

Screenshot reference: `tools/ui_redesign/dark_round3/files.png`

## Summary
- Total findings: 22
- Severity breakdown: critical=6, major=10, minor=6

## Findings

### F1 — "Cartella Tenant" button has hardcoded purple gradient — clashes with palette
- **Severity:** critical
- **Selector / element:** `#createRootFolderBtn` (file: `files.php:918-922`, inline `<style>`)
- **Screenshot region:** rightmost button in the header toolbar, labelled "Cartella Tenant"
- **Current behaviour (dark mode):** rendered as a vivid indigo→purple gradient (`#667eea` → `#764ba2`) — completely off-palette next to the surrounding mint/teal CTAs ("Carica", "Aggiungi", "Carica Cartella"). It looks like a button inherited from a different design system.
- **Root cause (CSS):** inline `<style>` literal:
```css
#createRootFolderBtn {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
}
```
This rule has higher specificity (id selector) AND is a literal hex pair, so it bypasses `--cnx-*` and `--color-*` tokens entirely. It also violates the "no gradients" rule from `docs/design/direction.md`.
- **Recommended fix:** drop the gradient, use the mint accent (or, if differentiation is desired, use a subtle outline variant):
```css
#createRootFolderBtn {
  background: var(--cnx-accent);
  color: var(--cnx-accent-ink);
  border: 1px solid var(--cnx-accent);
}
#createRootFolderBtn:hover {
  background: var(--cnx-accent-hover);
  border-color: var(--cnx-accent-hover);
  transform: none;   /* remove translateY hop, not in design language */
}
```
Or, if the lead wants a *secondary* visual weight to distinguish "tenant root" from regular upload:
```css
#createRootFolderBtn {
  background: transparent;
  color: var(--cnx-accent);
  border: 1px solid var(--cnx-accent);
}
```

### F2 — `.tenant-context-badge` uses same purple gradient (not visible in screenshot but present in DOM)
- **Severity:** critical
- **Selector / element:** `.tenant-context-badge` (file: `files.php:768-780`, inline `<style>`)
- **Screenshot region:** would appear in `.header-right` for admin/super_admin when a single tenant is in scope; currently `display: none`
- **Current behaviour (dark mode):** `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 8px rgba(102,126,234,0.3);` — same purple as F1.
- **Root cause (CSS):** hardcoded gradient + colored shadow.
- **Recommended fix:** replace with a flat accent-soft pill:
```css
.tenant-context-badge {
  background: var(--cnx-accent-soft);
  color: var(--cnx-accent-ink);
  box-shadow: none;
}
[data-theme="dark"] .tenant-context-badge {
  color: var(--cnx-accent);
}
```

### F3 — "Nuovo Documento" button is mid-grey (`.btn-secondary` overridden in workflow.css)
- **Severity:** critical
- **Selector / element:** `.btn-secondary` (file: `assets/css/workflow.css:548-555`)
- **Screenshot region:** second button in header toolbar, "Nuovo Documento" — appears as a grey rounded rectangle
- **Current behaviour (dark mode):** the button uses `background: #6b7280; color: white;` — a flat grey. It clashes with the four mint/teal pills around it ("Carica", "Aggiungi", "Carica Cartella") and with the purple "Cartella Tenant" (F1). The user sees four distinct accents in one toolbar — typography is OK, palette is broken.
- **Root cause (CSS):** `workflow.css` (loaded LAST via `?v=` cache-bust) hardcodes `.btn-secondary { background: #6b7280; color: white; }` — overrides BOTH `styles.css:437-441` and `filemanager.css:967-976`.
- **Recommended fix:** remove the workflow.css override (it should not be in workflow.css at all — it's a generic button style leaking from an old workflow PR), or scope it tightly to a `.workflow-*` ancestor:
```css
/* PREFERRED: remove lines 548-555 from workflow.css */
/* OR scope: */
.workflow-modal .btn-secondary { background: #6b7280; color: white; }
```
Then `.btn-secondary` falls back to `filemanager.css:967-976` (transparent + border) which is correct.
**Once fixed**, "Nuovo Documento" will render as a ghost-style button with `--cnx-text-primary` text and `--cnx-border` border — consistent with "Aggiungi" / "Carica" mint pills as ghost-secondary actions.

### F4 — Toolbar has 5 disjoint button styles (inconsistent visual hierarchy)
- **Severity:** critical
- **Selector / element:** `.header-right .btn` (file: `files.php:90-152`)
- **Screenshot region:** entire header right-side toolbar
- **Current behaviour (dark mode):** five buttons in a row, each with a different look:
  1. "Carica" — `.btn-primary` mint pill
  2. "Nuovo Documento" — `.btn-secondary` grey via workflow.css (F3)
  3. "Aggiungi" — `.btn-primary` + INLINE STYLE forcing accent (`files.php:113-114`)
  4. "Carica Cartella" — `.btn-primary` mint pill
  5. "Cartella Tenant" — purple gradient via inline rule (F1)
  Plus "Nuova Cartella" `.btn-ghost` (visible elsewhere).
- **Root cause (CSS):** each button accumulates conflicting overrides (workflow.css, components.css, filemanager.css, inline `<style>`, inline `style=""`) without a unified hierarchy.
- **Recommended fix:** define **one primary** action ("Aggiungi" recommended) and demote the rest to ghost/secondary. Apply the `.cnx-btn` system instead of `.btn`. Concretely:
```css
/* In files.php, simplify markup so only one .btn-primary exists */
/* Keep "Aggiungi" as primary, "Carica" + "Carica Cartella" + "Nuovo Documento" + "Cartella Tenant" + "Nuova Cartella" as ghost: */
#uploadBtn,
#uploadFolderBtn,
#newFolderBtn,
#createRootFolderBtn,
#createDocumentBtn { /* injected by JS */
  background: transparent;
  color: var(--cnx-text-primary);
  border: 1px solid var(--cnx-border-strong);
}
```
**REQUIRES MARKUP CHANGE** if we move from `.btn-primary` to `.btn-ghost` on those buttons (lead approval needed). CSS-only path: scope each button by ID and override.

### F5 — Header per-page bar has a teal cast (top-bar topbar tints whole header zone)
- **Severity:** major
- **Selector / element:** `.cnx-app-topbar` + `.main-content > .header` interaction (files: `assets/css/components.css:16-33` and `assets/css/components.css:52-64`)
- **Screenshot region:** the 64px topbar (search "Cerca file, persone, ticket...") AND the per-page header just below it ("File Manager" title + buttons)
- **Current behaviour (dark mode):** the topbar is markedly teal/green (`--cnx-accent-soft = rgba(95,202,211,0.12)` over `--cnx-bg-app`); the per-page header below also looks subtly teal because they sit immediately adjacent without a clear surface boundary. The eye reads "two stacked teal panels" instead of "one app shell."
- **Root cause (CSS):** topbar uses accent-soft as base colour; per-page header uses `background: transparent` so it inherits the body bg (`--color-gray-50` dark = `#1B2F33`), but the proximity to the topbar plus the soft accent residue makes them look unified-teal. **`backdrop-filter: blur(8px)` on the topbar ALSO violates the "no glassmorphism" rule in direction.md.**
- **Recommended fix:**
```css
[data-theme="dark"] .cnx-app-topbar {
  background: var(--cnx-bg-surface);   /* solid #15262A */
  border-bottom-color: var(--cnx-border);
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
}
[data-theme="dark"] .main-content > .header {
  background: var(--cnx-bg-app);       /* explicit, not transparent */
  border-bottom-color: var(--cnx-border-strong);  /* visible separator */
}
```

### F6 — Search input ("Cerca file e cartelle...") has insufficient contrast against page bg
- **Severity:** major
- **Selector / element:** `.search-input` (file: `assets/css/filemanager.css:48-56`)
- **Screenshot region:** the wide search input in the file-search-bar row, between header and breadcrumb
- **Current behaviour (dark mode):** input bg = `var(--cnx-bg-surface)` (`#15262A`), parent `.file-search-bar` bg also `var(--cnx-bg-surface)` — input merges with its container. Border is `--cnx-border` (`#22363B`) which is +7% lighter so it's BARELY visible. The input reads as a blob rather than a defined field.
- **Root cause (CSS):** `.file-search-bar { background: var(--cnx-bg-surface); }` and `.search-input { background: var(--cnx-bg-surface); border: 1px solid var(--cnx-border); }` — same surface, weak border.
- **Recommended fix:**
```css
[data-theme="dark"] .search-input {
  background: var(--cnx-bg-app);              /* darker than parent surface */
  border-color: var(--cnx-border-strong);     /* visible edge */
}
[data-theme="dark"] .search-input:focus {
  background: var(--cnx-bg-surface);
  border-color: var(--cnx-accent);
}
```

### F7 — "Filtra" / "Ordina" buttons have no contrast against file-search-bar
- **Severity:** major
- **Selector / element:** `.filter-btn`, `.sort-btn` (file: `assets/css/filemanager.css:69-94`)
- **Screenshot region:** two buttons on the right of the search bar, "Filtra" (with funnel icon) and "Ordina" (with lines icon)
- **Current behaviour (dark mode):** buttons have `background: var(--cnx-bg-surface); border: 1px solid var(--cnx-border);` — identical to parent. They appear as faint outlined chips, easy to miss.
- **Root cause (CSS):** same surface as parent + soft border in dark.
- **Recommended fix:**
```css
[data-theme="dark"] .filter-btn,
[data-theme="dark"] .sort-btn {
  background: var(--cnx-bg-subtle);
  border-color: var(--cnx-border-strong);
}
[data-theme="dark"] .filter-btn:hover,
[data-theme="dark"] .sort-btn:hover {
  background: var(--cnx-border);
}
```

### F8 — Drop-zone overlay has hardcoded `rgba(255,255,255,0.97)` background
- **Severity:** critical
- **Selector / element:** `.drop-zone-overlay` (file: `assets/css/filemanager.css:210-228`)
- **Screenshot region:** activates when user drags a file onto the page; covers the entire `.files-wrapper` area
- **Current behaviour (dark mode):** when a user starts a drag-drop upload, the overlay flashes a near-white panel (97% opacity) over a dark UI — extremely jarring, looks like the app crashed or rendered the wrong theme.
- **Root cause (CSS):** literal `background: rgba(255, 255, 255, 0.97);` — bypasses tokens.
- **Recommended fix:**
```css
.drop-zone-overlay {
  background: rgba(15, 26, 31, 0.92);   /* matches design's --cnx-bg-app at high alpha */
  border: 3px dashed var(--cnx-accent);
}
[data-theme="dark"] .drop-zone-overlay {
  background: rgba(14, 26, 29, 0.92);
}
```
Note: there's also a SECOND overlay rule with hardcoded gradients in `filemanager_enhanced.css:491+` that needs the same treatment (see F22).

### F9 — File table header `.file-table thead` uses `--cnx-bg-app` — same as page bg
- **Severity:** major
- **Selector / element:** `.file-table thead` (file: `assets/css/filemanager.css:389-395`)
- **Screenshot region:** sticky table header row containing "NOME", "PROPRIETARIO", "ASSEGNATO A", "MODIFICATO", "DIMENSIONE"
- **Current behaviour (dark mode):** header bg = `--cnx-bg-app` (`#0E1A1D`); the table body bg = `--cnx-bg-surface` (`#15262A`). So the header is DARKER than the rows — but only by ~3-4%. From the screenshot the header reads as "barely a column header strip"; the column titles themselves are visible (uppercase grey letters), but the band has no clear card-like containment.
- **Root cause (CSS):** chose darker tone for header which works in light theme (light grey vs white) but inverts in dark.
- **Recommended fix:**
```css
[data-theme="dark"] .file-table thead {
  background: var(--cnx-bg-subtle);   /* slightly lighter than surface — clearer header */
  border-bottom-color: var(--cnx-border-strong);
}
```

### F10 — File table row separators use `--cnx-bg-subtle` (visually invisible)
- **Severity:** major
- **Selector / element:** `.file-table tbody tr` (file: `assets/css/filemanager.css:407-410`)
- **Screenshot region:** between each row in the file list ("Agrumi Gel s.n.c", "Analisi Cliniche Amenta", "APSEMa"...)
- **Current behaviour (dark mode):** `border-bottom: 1px solid var(--cnx-bg-subtle);` — bg-subtle dark = `#1B2F33`, only ~4% lighter than `--cnx-bg-surface: #15262A`. Rows have effectively NO separator. The list looks like a single flat slab; users lose row tracking on long lists.
- **Root cause (CSS):** using bg-subtle as a border colour is semantically wrong (subtle = surface variant, not border).
- **Recommended fix:**
```css
.file-table tbody tr {
  border-bottom: 1px solid var(--cnx-border);   /* light: #E4E8EC, dark: #22363B */
}
[data-theme="dark"] .file-table tbody tr {
  border-bottom-color: rgba(255, 255, 255, 0.06);  /* sidebar-hover token style */
}
```

### F11 — Hover row state uses `--cnx-bg-app` which is DARKER than surface — counter-intuitive
- **Severity:** major
- **Selector / element:** `.file-table tbody tr:hover` (file: `assets/css/filemanager.css:412-414`)
- **Screenshot region:** any row when mouse hovers (not visible in static screenshot but applies on interaction)
- **Current behaviour (dark mode):** hover bg = `--cnx-bg-app` (`#0E1A1D`) which is DARKER than the table surface (`#15262A`). Hover should typically *lift* (lighten) on dark, not deepen. Result: hover looks like the row is being de-selected or pressed in.
- **Root cause (CSS):** rule designed for light mode (hover = subtle gray-50 wash) flipped wrong way in dark mode override.
- **Recommended fix:**
```css
[data-theme="dark"] .file-table tbody tr:hover {
  background: var(--cnx-bg-subtle);   /* lighter, signals affordance */
}
```

### F12 — Folder icons render bright yellow on dark (saturation too high)
- **Severity:** minor
- **Selector / element:** folder SVG icons inside `.file-name-wrapper .file-icon` (rendered by JS in `filemanager_enhanced.js`; markup in `filemanager.css:446-451`)
- **Screenshot region:** every "folder" row in the file list — yellow folder glyph
- **Current behaviour (dark mode):** the inline SVG fill is a vivid mustard-yellow (likely `#F5C842` or similar) directly inline in the JS template. On dark it appears overly saturated; reads as "alert" rather than "folder."
- **Root cause (CSS):** SVG `fill` attribute is hardcoded in `filemanager_enhanced.js` (or wherever the folder icon HTML is built); cannot be tinted via CSS unless using `currentColor`.
- **Recommended fix (CSS-only approximation):**
```css
[data-theme="dark"] .file-name-wrapper .file-icon[data-type="folder"] {
  filter: brightness(0.85) saturate(0.85);   /* desaturate without touching SVG */
}
```
Or **REQUIRES MARKUP CHANGE** in `filemanager_enhanced.js` to use `fill="currentColor"` so we can drive icon color via `color: var(--cnx-warning)` etc.

### F13 — Action `⋮` button (right column) is a large pill that floats on row, pulling focus
- **Severity:** minor
- **Selector / element:** `.file-table .actions-col .action-btn` (file: `assets/css/filemanager.css:462-475`)
- **Screenshot region:** rightmost column on every row, the `⋮` (more menu) button
- **Current behaviour (dark mode):** the button has `border: 1px solid var(--cnx-border-strong); background: var(--cnx-bg-surface);` — same as table bg, but the strong border makes the chip pop hard. Every row has a "click me" button — too noisy.
- **Root cause (CSS):** button is always-visible with full border. In light mode a 1px subtle border was OK; in dark, `--cnx-border-strong` (`#2E464C`) on `#15262A` is +13% lightness — high contrast.
- **Recommended fix:**
```css
[data-theme="dark"] .file-table .actions-col .action-btn {
  background: transparent;
  border-color: transparent;
  color: var(--cnx-text-muted);
}
[data-theme="dark"] .file-table tbody tr:hover .actions-col .action-btn {
  background: var(--cnx-bg-subtle);
  border-color: var(--cnx-border);
  color: var(--cnx-text-primary);
}
```

### F14 — Breadcrumb home icon is invisible / extremely faint
- **Severity:** minor
- **Selector / element:** `.breadcrumb-item svg` inside the breadcrumb home link (file: `files.php:185-191` + `filemanager.css:131-134`)
- **Screenshot region:** breadcrumb bar — text "File Manager" with small house icon next to it
- **Current behaviour (dark mode):** the SVG inherits `currentColor` from `.cnx-breadcrumb__link` → `var(--cnx-text-secondary)` (`#A8B5BB`) — visible but pale. The house icon is small (14×14) — at that size pale grey on dark is hard to parse.
- **Root cause (CSS):** OK in principle but icon size / weight not adjusted for dark.
- **Recommended fix:**
```css
[data-theme="dark"] .cnx-breadcrumb__link svg,
[data-theme="dark"] .breadcrumb-item svg {
  color: var(--cnx-text-primary);
  opacity: 0.7;
}
```

### F15 — View toggle (grid/list) icons fade into the toggle bg
- **Severity:** minor
- **Selector / element:** `.view-btn`, `.view-btn.active` (file: `assets/css/filemanager.css:157-183`)
- **Screenshot region:** top-right of breadcrumb row, two icon buttons (grid + list)
- **Current behaviour (dark mode):** inactive `.view-btn` has `color: var(--cnx-text-secondary)` over `.view-toggle` bg `--cnx-bg-subtle`. Active `.view-btn.active { background: var(--cnx-bg-surface); }` — surface on top of subtle is only +4% lift. Hard to tell which view mode is active.
- **Root cause (CSS):** insufficient delta between active and inactive in dark mode.
- **Recommended fix:**
```css
[data-theme="dark"] .view-btn.active {
  background: var(--cnx-bg-surface);
  color: var(--cnx-accent);                 /* mint highlight */
  box-shadow: 0 0 0 1px var(--cnx-border-strong);
}
```

### F16 — Empty checkbox cells render as squares with no fill (default OS chrome)
- **Severity:** minor
- **Selector / element:** `.checkbox-col input[type="checkbox"]` (file: `assets/css/filemanager.css:430-434`)
- **Screenshot region:** leftmost column of each row, plus header — small square checkboxes
- **Current behaviour (dark mode):** native checkboxes render with default user-agent style (light squares with grey border) on dark bg — they look like white pixels on dark, not native dark widgets.
- **Root cause (CSS):** no `accent-color` or custom checkbox styling applied.
- **Recommended fix:**
```css
.file-table input[type="checkbox"],
.checkbox-col input[type="checkbox"] {
  accent-color: var(--cnx-accent);
}
[data-theme="dark"] .file-table input[type="checkbox"],
[data-theme="dark"] .checkbox-col input[type="checkbox"] {
  color-scheme: dark;   /* tells browser to render dark-themed native widgets */
}
```

### F17 — `.modal-content` (Crea Cartella Tenant Root modal) hardcoded to white
- **Severity:** critical
- **Selector / element:** `.modal-content` (file: `files.php:797-806`, inline `<style>`)
- **Screenshot region:** not visible in static screenshot — opens when "Cartella Tenant" button is clicked
- **Current behaviour (dark mode):** `background: white;` literal — when modal opens in dark mode, a stark white card pops over the dim overlay. Plus `.modal-header h2 { color: #1F2937; }` and `.modal-header { border-bottom: 1px solid #E5E7EB; }`. All hardcoded.
- **Root cause (CSS):** raw hex / `white` literals in inline style.
- **Recommended fix:** rewrite the inline block to use tokens:
```css
.modal-content { background: var(--cnx-bg-surface); }
.modal-header,
.modal-footer { border-color: var(--cnx-border); }
.modal-header h2 { color: var(--cnx-text-primary); }
.modal-close { color: var(--cnx-text-muted); }
.modal-close:hover { color: var(--cnx-text-primary); }
.form-group label { color: var(--cnx-text-secondary); }
.form-control {
  background: var(--cnx-bg-app);
  color: var(--cnx-text-primary);
  border-color: var(--cnx-border);
}
.form-control:focus { border-color: var(--cnx-accent); }
.form-text { color: var(--cnx-text-muted); }
```
The components.css block at line 238-273 already covers `.modal-content` and `.form-control` with `[data-theme="dark"]` overrides — the problem is the inline `<style>` in `files.php:797+` has the SAME specificity but is loaded LATER and uses `!important`-level literal colours. **Easiest fix: move the inline `<style>` rules out of `files.php` into a stylesheet that loads BEFORE `components.css` so the dark-theme overrides win.**

### F18 — `.tenant-label` chip uses `#F3F4F6` / `#6B7280` light hex pair
- **Severity:** minor
- **Selector / element:** `.tenant-label` (file: `files.php:898-915`, inline `<style>`)
- **Screenshot region:** would appear inside folder cards showing the tenant name (off-screen in current view)
- **Current behaviour (dark mode):** `background: #F3F4F6; color: #6B7280;` — pastel grey chip on dark.
- **Root cause (CSS):** literal hex.
- **Recommended fix:**
```css
.tenant-label {
  background: var(--cnx-bg-subtle);
  color: var(--cnx-text-muted);
}
[data-theme="dark"] .tenant-label {
  background: var(--cnx-border);
  color: var(--cnx-text-secondary);
}
```

### F19 — `.modal-overlay` 50% black backdrop is fine — but no token reference
- **Severity:** minor
- **Selector / element:** `.modal-overlay` (file: `files.php:783-795`, inline `<style>`)
- **Screenshot region:** modal backdrop
- **Current behaviour (dark mode):** `background: rgba(0, 0, 0, 0.5)` works but is inconsistent with `cnx-modal` which uses `rgba(15, 26, 31, 0.48)`.
- **Root cause (CSS):** literal rgba.
- **Recommended fix:** align with cnx-modal token pattern:
```css
.modal-overlay { background: rgba(15, 26, 31, 0.48); }
```

### F20 — `filemanager_enhanced.css` contains ~30+ hardcoded hex literals (full file rot)
- **Severity:** critical
- **Selector / element:** entire file `assets/css/filemanager_enhanced.css` (lines 22, 34, 35, 41, 42, 63, 69, 82, 123, 129, 137, 148, 149, 165, 178, 179, 183, 184, 222, 231, 238, 246, 247, 265, 272, 278, 284, 297, 311, 330, 349, 382, 386, 390, 396, 397, 434, 444, 449, 454, 463, 467, 484, 493, 542, 557, 583, 589)
- **Screenshot region:** affects file workflow/assignment UI panels, bulk action toolbars, drop zone enhanced overlay (sample lines below)
- **Current behaviour (dark mode):** at least 48 raw hex values bypass the token system entirely. Examples:
  - line 22: `background: #FFFFFF` — modal/panel bg stays white in dark
  - line 41: `border-color: #2563EB` — old blue accent (pre-rebrand) leaks through
  - line 63: `color: #111827` — pure black text on dark surface = INVISIBLE
  - line 165: `background: #F9FAFB` — light grey panel
  - line 272: `background: linear-gradient(90deg, #2563EB 0%, #3B82F6 100%)` — old-blue progress bar gradient
  - line 349: `background: white;` literal
  - line 484: `background: #9CA3AF;` for `.btn-primary:disabled` — bright grey button
  - line 493: `background: linear-gradient(135deg, ...)` — drop zone enhanced gradient (violates "no gradient" rule)
  - line 542: `background: linear-gradient(90deg, #2563EB, #3B82F6)` — second blue gradient
  - line 557: `background: rgba(255, 255, 255, 0.95);` — white over content in dark
- **Root cause (CSS):** the file predates the rebrand and was NEVER migrated. The migration commit (`82ecaf1`) only touched `filemanager.css` + `dashboard.css`.
- **Recommended fix:** full migration of `filemanager_enhanced.css` to `--cnx-*` tokens. This is the largest single source of dark-mode bugs in the file manager. Sample mappings:
```
#FFFFFF / white       -> var(--cnx-bg-surface)
#F9FAFB / #F3F4F6     -> var(--cnx-bg-subtle)
#E5E7EB / #D1D5DB     -> var(--cnx-border)
#9CA3AF               -> var(--cnx-text-muted)
#6B7280               -> var(--cnx-text-secondary)
#374151               -> var(--cnx-text-secondary) (or primary if heading)
#1F2937 / #111827     -> var(--cnx-text-primary)
#2563EB / #3B82F6     -> var(--cnx-accent)        (old blue rebranded to mint)
rgba(37,99,235,...)   -> rgba(95,202,211,...)     (mint with same alpha)
linear-gradient(...)  -> flat var(--cnx-accent)   (drop gradients per direction.md)
```
Estimated diff: ~50 line edits, no markup change.

### F21 — `prefers-color-scheme: dark` block at line 670 conflicts with `[data-theme="dark"]`
- **Severity:** minor
- **Selector / element:** `@media (prefers-color-scheme: dark) { ... }` (file: `assets/css/filemanager_enhanced.css:670+`)
- **Screenshot region:** any element styled inside that media query
- **Current behaviour (dark mode):** the file uses an OS-preference media query, but the rest of the app uses an explicit `[data-theme="dark"]` attribute. If a user has dark OS prefs but selected LIGHT app mode (or vice-versa), the two systems disagree and rules from this media query may apply when they shouldn't, or fail to apply when they should.
- **Root cause (CSS):** dual theme detection mechanism.
- **Recommended fix:** convert the entire `@media (prefers-color-scheme: dark)` block to `[data-theme="dark"]` selectors so the user-controlled theme toggle is the single source of truth:
```css
/* before */
@media (prefers-color-scheme: dark) { .x { ... } }
/* after */
[data-theme="dark"] .x { ... }
```

### F22 — Drop zone enhanced overlay (filemanager_enhanced.css) layers over F8 with second hardcoded gradient
- **Severity:** major
- **Selector / element:** `.drop-zone-overlay` (file: `assets/css/filemanager_enhanced.css:491-510` approx)
- **Screenshot region:** same as F8 — drag-drop active overlay
- **Current behaviour (dark mode):** in addition to the white background from F8, a `linear-gradient(135deg, rgba(37,99,235,0.03) ...)` stacks the OLD blue accent on top — white panel with blue tint.
- **Root cause (CSS):** layered overrides with old-brand colors.
- **Recommended fix:** remove the gradient layer entirely (per direction.md), let F8's flat-token rule stand:
```css
.drop-zone-overlay {
  background: rgba(15, 26, 31, 0.92);
  /* DELETE the linear-gradient overlay */
}
```

---

End of files.php audit.
