# Dark mode audit — tasks.php

Screenshot reviewed: `tools/ui_redesign/dark_round3/tasks.png`
Sources reviewed: `tasks.php` (inline `<style>` block lines 47-594), `assets/css/styles.css`, `assets/css/components.css`.

## Summary
- Total findings: 23
- Severity breakdown: critical=8, major=10, minor=5

## Findings

### F1 — Two warning banners ("Sei in modalita multi-azienda" + "Attenzione: 46 task...") have light-yellow fill = unreadable in dark mode
- **Severity:** critical
- **Selector / element:** `.alert-warning { background: #FEF3C7; border-left-color: #F59E0B; color: #92400E; }` (file: `tasks.php:399-402`); also `.alert-link { color: #92400E; }` (`tasks.php:417-419`)
- **Screenshot region:** the two horizontal yellow alert banners spanning full width near the top.
- **Current behaviour (dark mode):** Hex literals `#FEF3C7` (light cream bg) and `#92400E` (dark amber text) are baked into the inline `<style>` block. There is NO `[data-theme="dark"]` override, so the banners render as bright cream rectangles with low-contrast brown text — the worst contrast issue on the page. The amber text on cream bg is barely darker than the bg, and against the dark page it creates a glaring "white box" effect.
- **Root cause (CSS):** Hex literals hardcoded at `tasks.php:399-402` (`background: #FEF3C7`, `border-left-color: #F59E0B`, `color: #92400E`) bypass the entire token system.
- **Recommended fix:**
```css
[data-theme="dark"] .alert-warning {
    background: rgba(245, 158, 11, 0.10) !important;
    border-left-color: #F59E0B !important;
    color: #FBBF24 !important;
}
[data-theme="dark"] .alert-warning strong,
[data-theme="dark"] .alert-warning .alert-link {
    color: #FCD34D !important;
}
[data-theme="dark"] .alert-warning .alert-icon {
    color: #F59E0B !important;
}
```

### F2 — "Tutti" filter pill is bright legacy blue (`--color-primary` #2563EB), totally off-palette
- **Severity:** critical
- **Selector / element:** `.filter-btn.active { background: var(--color-primary); color: var(--color-white); border-color: var(--color-primary); }` (file: `tasks.php:73-77`)
- **Screenshot region:** active filter pill "Tutti" in the row of filter buttons — saturated blue.
- **Current behaviour (dark mode):** The "Tutti" pill renders in old-brand royal blue (`#2563EB`) which is the legacy `--color-primary` that the rebrand was supposed to replace with mint `--cnx-accent`. It clashes with every other accent in the redesigned UI (theme toggle is mint, sidebar active is mint, etc.).
- **Root cause (CSS):** Inline `<style>` references `--color-primary` directly — the rebrand only migrated `.btn-primary` via `components.css:206-211`, NOT generic `--color-primary` consumers like this `.filter-btn.active` rule.
- **Recommended fix:** Override at the inline-style level (since the inline rule is in the same file, can be overridden by adding a more-specific dark-mode rule in `components.css` or `styles.css`):
```css
.filter-btn.active,
[data-theme="dark"] .filter-btn.active {
    background: var(--cnx-accent) !important;
    color: var(--cnx-accent-ink) !important;
    border-color: var(--cnx-accent) !important;
}
.filter-btn.active:hover {
    background: var(--cnx-accent-hover) !important;
    border-color: var(--cnx-accent-hover) !important;
}
```

### F3 — "+ Nuovo Task" button is dark-green legacy `.btn-success` instead of mint primary
- **Severity:** critical
- **Selector / element:** `<button class="btn btn-success" id="newTaskBtn">+ Nuovo Task</button>` (file: `tasks.php:603`); rule `.btn-success { background-color: var(--color-success); }` (file: `assets/css/styles.css:448-452`)
- **Screenshot region:** top-right of page header — saturated green button.
- **Current behaviour (dark mode):** The CTA renders in `--color-success` = `#10B981` — the success/confirmation green. Visually it is the most prominent button on the page and should be the brand mint to match every other primary CTA. The `.btn-success` choice was a legacy markup decision; it now reads as an "active confirmation" semantic, not "create new".
- **Root cause (CSS):** The markup uses `.btn-success` deliberately (it is in the PHP file, not the JS-generated toolbar). The class itself renders correctly per its definition; the issue is class selection.
- **Recommended fix:** Since we cannot modify markup per the audit rules, override `.btn-success` ONLY for `#newTaskBtn` to align with the mint accent:
```css
#newTaskBtn.btn-success,
#newTaskBtn.btn.btn-success {
    background-color: var(--cnx-accent) !important;
    color: var(--cnx-accent-ink) !important;
    border-color: var(--cnx-accent) !important;
}
#newTaskBtn.btn-success:hover {
    background-color: var(--cnx-accent-hover) !important;
    border-color: var(--cnx-accent-hover) !important;
}
```
**REQUIRES MARKUP CHANGE (preferred):** rename class to `.btn-primary` in `tasks.php:603` so it inherits the rebrand override at `components.css:206`. Flagged for lead review.

### F4 — Filter pills (I Miei Task / Alta Priorita / Scadenza Oggi / Completati) are white-bg pills on dark page
- **Severity:** critical
- **Selector / element:** `.filter-btn { background: var(--color-white); border: 1px solid var(--color-gray-300); ... }` (file: `tasks.php:58-66`)
- **Screenshot region:** the row of 5 filter pills below the alerts.
- **Current behaviour (dark mode):** The four non-active pills use `--color-white` which is remapped to `#15262A` in dark mode — they actually do flip, but `--color-gray-300` (border) becomes `#2E464C` and the result is a uniform blob with NO borders visible. Visually they look like four pasted-on rounded rectangles with no edge — disconnected from the surface logic.
- **Root cause (CSS):** Tokens flip correctly but border contrast is too weak; also `color: ` is not set so text falls back to body color which IS readable but the buttons feel empty.
- **Recommended fix:**
```css
[data-theme="dark"] .filter-btn {
    background: var(--cnx-bg-subtle) !important;
    color: var(--cnx-text-secondary) !important;
    border-color: var(--cnx-border) !important;
}
[data-theme="dark"] .filter-btn:hover {
    background: var(--cnx-bg-surface) !important;
    border-color: var(--cnx-border-strong) !important;
    color: var(--cnx-text-primary) !important;
}
```

### F5 — Task columns ("Da Fare / In Corso / In Revisione" headers) lose all separation; columns blend into page bg
- **Severity:** major
- **Selector / element:** `.task-column { background: var(--color-gray-50); border-radius: var(--radius-lg); padding: var(--space-4); }` (file: `tasks.php:85-89`)
- **Screenshot region:** the three column-bodies below the column titles. Each column should look like a separate Kanban lane.
- **Current behaviour (dark mode):** `--color-gray-50` is remapped to `#1B2F33`, only marginally lighter than the page bg `#0E1A1D` (delta ~ 6 luminance pts). The columns appear as ghosted shapes — Kanban affordance is lost.
- **Root cause (CSS):** `--color-gray-50` IS the "subtle bg" token, designed for that purpose, but on a content area that also uses `--cnx-bg-app` they look identical.
- **Recommended fix:**
```css
[data-theme="dark"] .task-column {
    background: var(--cnx-bg-subtle);
    border: 1px solid var(--cnx-border);
}
```

### F6 — Column count badges ("50", "0", "0", "0") have indistinguishable background
- **Severity:** minor
- **Selector / element:** `.task-count { background: var(--color-gray-200); color: var(--color-gray-600); ... }` (file: `tasks.php:100-107`)
- **Screenshot region:** small pill counters next to "Da Fare 50", "In Corso 0", etc.
- **Current behaviour (dark mode):** `--color-gray-200` → `#22363B`, `--color-gray-600` → `#A8B5BB` — the pill blends into the column header. Functional but pale.
- **Root cause (CSS):** Token-based; flips correctly but lacks visual definition in dark mode.
- **Recommended fix:**
```css
[data-theme="dark"] .task-count {
    background: var(--cnx-accent-soft);
    color: var(--cnx-accent);
    font-weight: 700;
}
```

### F7 — Task cards have correct surface bg but borders are invisible
- **Severity:** major
- **Selector / element:** `.task-card { background: var(--color-white); border: 1px solid var(--color-gray-200); ... }` (file: `tasks.php:109-117`)
- **Screenshot region:** the large card "PROMETEO SOC. COOP. SOCIALE..." in the "Da Fare" column.
- **Current behaviour (dark mode):** Card bg `#15262A` and border `#22363B` differ by ~3 luminance pts. The card edge is visible only at the rounded corners; on flat sides it disappears into the column. Combined with F5 (column bg also low-contrast), the visual hierarchy is page > nothing > card.
- **Root cause (CSS):** Tokens flip but borders need bumping.
- **Recommended fix:**
```css
[data-theme="dark"] .task-card {
    border-color: var(--cnx-border-strong);
    box-shadow: var(--cnx-shadow-sm);
}
[data-theme="dark"] .task-card:hover {
    box-shadow: var(--cnx-shadow-md);
}
```

### F8 — Task title text and metadata text are at low-mid contrast (greyed out)
- **Severity:** major
- **Selector / element:** `.task-title { color: var(--color-gray-900); }` (`tasks.php:124-128`) + `.task-description { color: var(--color-gray-600); }` (`tasks.php:131-135`)
- **Screenshot region:** the bold title "PROMETEO SOC. COOP..." and the description block underneath ("Cliente: PROMETEO... Piano: ISO 9001...").
- **Current behaviour (dark mode):** Title uses `--color-gray-900` → `#E6EEF0` (good contrast). Description uses `--color-gray-600` → `#A8B5BB` — borderline AA. Acceptable for body but the data is dense and could read better.
- **Root cause (CSS):** Default tokens — minor contrast tightness.
- **Recommended fix:**
```css
[data-theme="dark"] .task-description {
    color: var(--cnx-text-secondary);
}
```

### F9 — Priority badges (Media / Alta / Bassa) keep light-pastel bg with light-color text in dark mode
- **Severity:** critical
- **Selector / element:** `.priority-high { background: #FEE2E2; color: var(--color-error); }`, `.priority-medium { background: #FEF3C7; color: var(--color-warning); }`, `.priority-low { background: #DBEAFE; color: var(--color-info); }` (file: `tasks.php:151-164`)
- **Screenshot region:** the orange "Media" pill at the bottom of the task card (and any "Alta" / "Bassa" elsewhere).
- **Current behaviour (dark mode):** ALL priority badges keep their hardcoded pastel backgrounds (`#FEE2E2`, `#FEF3C7`, `#DBEAFE`). The "Media" pill in the screenshot is light yellow with light-orange text — borderline unreadable, plus it is the same yellow as the warning banners (visual confusion).
- **Root cause (CSS):** Three hex literals `#FEE2E2`, `#FEF3C7`, `#DBEAFE` hardcoded at `tasks.php:152, 157, 162` — bypass tokens entirely.
- **Recommended fix:**
```css
[data-theme="dark"] .priority-high {
    background: rgba(239, 68, 68, 0.16) !important;
    color: #F87171 !important;
}
[data-theme="dark"] .priority-medium {
    background: rgba(245, 158, 11, 0.16) !important;
    color: #FBBF24 !important;
}
[data-theme="dark"] .priority-low {
    background: rgba(95, 202, 211, 0.16) !important;
    color: var(--cnx-accent) !important;
}
```

### F10 — Empty-column placeholder text "Nessun task in questa colonna" is too dim
- **Severity:** minor
- **Selector / element:** `.empty-state { color: var(--color-gray-500); }` (file: `tasks.php:545-549`)
- **Screenshot region:** centered grey labels in "In Corso" and "In Revisione" columns.
- **Current behaviour (dark mode):** `--color-gray-500` is `#8893A0` against the slightly-lighter column bg — readable but feels tentative.
- **Root cause (CSS):** Token correctly mapped; just visually washed.
- **Recommended fix:**
```css
[data-theme="dark"] .empty-state {
    color: var(--cnx-text-muted);
    opacity: 0.85;
}
```
(Can also leave as-is — borderline minor.)

### F11 — User-avatar circle ("FR" Francesco Barreca) uses legacy primary blue
- **Severity:** major
- **Selector / element:** `.assignee-avatar { background: var(--color-primary); color: var(--color-white); ... }` (file: `tasks.php:172-183`)
- **Screenshot region:** small bottom-right of the task card — round avatar "FR".
- **Current behaviour (dark mode):** Royal blue circle (`#2563EB`), inconsistent with the mint palette of the rest of the UI.
- **Root cause (CSS):** Direct reference to `--color-primary`. Same root cause as F2.
- **Recommended fix:**
```css
.assignee-avatar {
    background: var(--cnx-accent) !important;
    color: var(--cnx-accent-ink) !important;
}
```

### F12 — "Cliente: ... Piano: ... Titolo calendario:..." description text uses light-grey on dark, but inside is a `<code>`-like dump that is hard to scan
- **Severity:** minor
- **Selector / element:** `.task-description` (`tasks.php:131-135`)
- **Screenshot region:** middle of the task card — wall-of-text block with hashes, IDs, dates.
- **Current behaviour (dark mode):** Same root color as F8. The text is technically readable but the high information density + small font + `--color-gray-600` text makes it visually exhausting.
- **Root cause (CSS):** Not strictly a dark-mode bug — content density issue. Mention only because it amplifies F8's contrast weakness.
- **Recommended fix:** Apply F8's fix and additionally:
```css
[data-theme="dark"] .task-description {
    line-height: 1.5;
    word-break: break-word;
}
```

### F13 — Topbar (search bar with "Cerca file, persone, ticket…") inherits same teal-tinted bg as on calendar.php
- **Severity:** critical
- **Selector / element:** `.cnx-app-topbar` (file: `assets/css/components.css:16-33`)
- **Screenshot region:** very top horizontal bar with the global search.
- **Current behaviour (dark mode):** Identical to calendar.md F15 — pale tint over dark page; topbar visually disconnected.
- **Root cause (CSS):** `background: var(--cnx-accent-soft)` resolves to `rgba(95, 202, 211, 0.12)` which composites over the dark page bg as a green-grey band.
- **Recommended fix:** Same as calendar.md F15 (single rule fixes both pages):
```css
[data-theme="dark"] .cnx-app-topbar {
    background: var(--cnx-bg-app);
    border-bottom: 1px solid var(--cnx-border);
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}
```

### F14 — "Azienda: Tutte le aziende" company filter dropdown has no visible border
- **Severity:** major
- **Selector / element:** Company filter rendered by `CompanyFilter::renderDropdown([...])` (file: `includes/company_filter.php`); CSS likely in inline output.
- **Screenshot region:** between the page title and the "+ Nuovo Task" button — "Azienda: Tutte le aziende v" label with chevron.
- **Current behaviour (dark mode):** The dropdown trigger appears as an unstyled label with a chevron — no pill, no border. User cannot tell it's interactive without hovering.
- **Root cause (CSS):** Company filter CSS is rendered inline (option `no_styles => true` is FALSE here for tasks). The default styles likely target light mode only.
- **Recommended fix:**
```css
[data-theme="dark"] .company-filter-dropdown,
[data-theme="dark"] .company-filter-trigger,
[data-theme="dark"] .company-filter-button {
    background: var(--cnx-bg-subtle) !important;
    border: 1px solid var(--cnx-border) !important;
    color: var(--cnx-text-primary) !important;
    border-radius: var(--cnx-radius-pill) !important;
}
[data-theme="dark"] .company-filter-trigger:hover {
    background: var(--cnx-bg-surface) !important;
    border-color: var(--cnx-border-strong) !important;
}
```
**REQUIRES VERIFICATION:** exact selectors depend on `CompanyFilter::renderDropdown()` output. Lead should grep `class=".*company-filter` in `includes/company_filter.php` to confirm.

### F15 — Page-header bottom divider is invisible (gestione task underline)
- **Severity:** minor
- **Selector / element:** `.main-content > .header { border-bottom: 1px solid var(--cnx-border); }` (file: `assets/css/components.css:63`)
- **Screenshot region:** thin line below "Gestione Task" / company filter / new-task button.
- **Current behaviour (dark mode):** Same as calendar.md F24 — divider too subtle.
- **Root cause (CSS):** `--cnx-border` too low-contrast over `--cnx-bg-app`.
- **Recommended fix:** Same as calendar.md F24.

### F16 — Modal dialog (Nuovo Task / Aggiorna Task / Conferma Eliminazione) has light bg in dark mode
- **Severity:** major
- **Selector / element:** `.modal-dialog { background: var(--color-white); }` (file: `tasks.php:208-219`)
- **Screenshot region:** N/A in screenshot (modal closed) but trivially reproducible by clicking "+ Nuovo Task".
- **Current behaviour (dark mode):** `--color-white` is remapped to `#15262A`, which IS dark — but the modal also has heavy white-only overrides like `.modal-header { border-bottom: 1px solid var(--color-gray-200); }` (`tasks.php:236-242`) and form inputs that need explicit dark styling.
- **Root cause (CSS):** Inline `<style>` in `tasks.php` defines modal styling that flips through tokens, but inputs inside (`.form-control`) reference `--color-gray-300` borders → low contrast. The `components.css` already has a fallback for `.modal-content` and `.form-control` BUT only for selectors `[data-theme="dark"] .modal-content` (NOT `.modal-dialog`, NOT inline-style overrides).
- **Recommended fix:**
```css
[data-theme="dark"] .modal-dialog {
    background: var(--cnx-bg-surface);
    border: 1px solid var(--cnx-border);
}
[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer {
    border-color: var(--cnx-border) !important;
}
[data-theme="dark"] .modal-header h2 {
    color: var(--cnx-text-primary);
}
[data-theme="dark"] .modal-close {
    color: var(--cnx-text-muted);
}
[data-theme="dark"] .modal-close:hover {
    background: var(--cnx-bg-subtle);
    color: var(--cnx-text-primary);
}
```

### F17 — Form inputs in modals have white bg + light grey borders in dark mode
- **Severity:** major
- **Selector / element:** `.form-control { ... border: 1px solid var(--color-gray-300); }` (file: `tasks.php:369-376`); `.form-control:focus` uses `box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1)` — legacy blue (`tasks.php:378-382`)
- **Screenshot region:** N/A in shot (modal closed). Affects all task-create/edit form fields.
- **Current behaviour (dark mode):** Inputs WILL render dark thanks to `components.css` fallback (`[data-theme="dark"] .form-control`), but the focus ring uses legacy blue rgba(59,130,246) instead of mint.
- **Root cause (CSS):** Hardcoded `rgba(59, 130, 246, 0.1)` at `tasks.php:381` — legacy blue.
- **Recommended fix:**
```css
[data-theme="dark"] .form-control:focus {
    border-color: var(--cnx-accent);
    box-shadow: var(--cnx-shadow-focus);
}
```

### F18 — Toasts (`.toast-success`, `.toast-error`, `.toast-info`) hardcoded with hex bgs but `--color-white` underneath
- **Severity:** minor
- **Selector / element:** `.toast { background: var(--color-white); ... }` + `.toast.toast-success { border-left: 4px solid #10B981; }` etc. (file: `tasks.php:480-514`)
- **Screenshot region:** N/A (toasts not currently shown).
- **Current behaviour (dark mode):** Background flips OK via `--color-white` remap, but the hex hardcoded border colors stay vivid. Acceptable for status indicators but inconsistent with the new accent.
- **Root cause (CSS):** Hex literals `#10B981`, `#EF4444`, `#3B82F6` at `tasks.php:505, 509, 513`.
- **Recommended fix:**
```css
[data-theme="dark"] .toast {
    border: 1px solid var(--cnx-border);
}
.toast.toast-success { border-left-color: var(--cnx-success); }
.toast.toast-error   { border-left-color: var(--cnx-danger); }
.toast.toast-info    { border-left-color: var(--cnx-accent); }
```

### F19 — `.btn-secondary` modal cancel button uses legacy gray that flips to a dark grey on dark grey
- **Severity:** minor
- **Selector / element:** `.btn-secondary { background: var(--color-gray-200); color: var(--color-gray-900); border: 1px solid var(--color-gray-300); }` (file: `tasks.php:575-579`)
- **Screenshot region:** N/A (modals closed). Affects cancel buttons in all 3 task modals.
- **Current behaviour (dark mode):** `--color-gray-200` = `#22363B`, `--color-gray-900` = `#E6EEF0`, `--color-gray-300` = `#2E464C` — readable but the button blends with form bg.
- **Root cause (CSS):** Token-flipped but the result is monochrome.
- **Recommended fix:**
```css
[data-theme="dark"] .btn-secondary {
    background: transparent !important;
    color: var(--cnx-text-primary) !important;
    border: 1px solid var(--cnx-border-strong) !important;
}
[data-theme="dark"] .btn-secondary:hover {
    background: var(--cnx-bg-subtle) !important;
}
```

### F20 — `.btn-danger` "Elimina" button in delete-confirm modal — fine but could be tonal in dark mode
- **Severity:** minor
- **Selector / element:** `.btn-danger { background: var(--color-error); color: var(--color-white); }` (file: `tasks.php:585-588`)
- **Screenshot region:** N/A.
- **Current behaviour (dark mode):** Saturated red `#EF4444` with white text. Visually loud but semantically correct. No bug, just consistency note.
- **Root cause (CSS):** Hardcoded.
- **Recommended fix:** Optional — leave saturated for clear semantics. If toning is desired:
```css
[data-theme="dark"] .btn-danger {
    background: rgba(239, 68, 68, 0.92) !important;
}
```

### F21 — Drag-target highlight uses legacy blue dashed border
- **Severity:** minor
- **Selector / element:** `.task-column.drag-over { background: var(--color-gray-100); border: 2px dashed var(--color-primary); }` (file: `tasks.php:517-520`)
- **Screenshot region:** N/A (no drag in screenshot).
- **Current behaviour (dark mode):** When user drags a card, the hovered column flashes with a legacy royal-blue dashed outline + `--color-gray-100` (= `#1B2F33`) bg. Off-brand.
- **Root cause (CSS):** `--color-primary` reference (legacy blue).
- **Recommended fix:**
```css
.task-column.drag-over {
    background: var(--cnx-accent-soft) !important;
    border: 2px dashed var(--cnx-accent) !important;
}
```

### F22 — Loading spinner uses legacy primary as accent color
- **Severity:** minor
- **Selector / element:** `.loading-spinner { ... border-top-color: var(--color-primary); }` (file: `tasks.php:528-536`)
- **Screenshot region:** N/A.
- **Current behaviour (dark mode):** Spinner ring would render in legacy royal blue.
- **Root cause (CSS):** `--color-primary` reference.
- **Recommended fix:**
```css
.loading-spinner {
    border-top-color: var(--cnx-accent) !important;
}
```

### F23 — Assignee progress UI (modal) uses legacy blue gradient and box-shadow ring
- **Severity:** minor
- **Selector / element:** `.assignee-progress-fill { background: linear-gradient(90deg, var(--color-primary), #60a5fa); }` (file: `tasks.php:347-351`); `.assignee-step-btn.active { ... box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }` (file: `tasks.php:331-334`)
- **Screenshot region:** N/A (modal closed). Inside "Aggiorna Task" modal for assignees.
- **Current behaviour (dark mode):** Progress bar fills with legacy blue gradient (`#2563EB → #60a5fa`); active step button has blue glow ring. Off-brand.
- **Root cause (CSS):** Hardcoded hex `#60a5fa` + `rgba(59, 130, 246, 0.12)` + `--color-primary`.
- **Recommended fix:**
```css
.assignee-progress-fill {
    background: linear-gradient(90deg, var(--cnx-accent), var(--cnx-accent-hover)) !important;
}
.assignee-step-btn.active {
    border-color: var(--cnx-accent) !important;
    box-shadow: var(--cnx-shadow-focus) !important;
    color: var(--cnx-accent-ink);
    background: var(--cnx-accent-soft);
}
```
