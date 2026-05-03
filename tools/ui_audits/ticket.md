# Dark mode audit — ticket.php

## Summary
- Total findings: 14
- Severity breakdown: critical=4, major=7, minor=3

## Findings

### F1 — "+ Nuovo Ticket" CTA is legacy cobalt blue, clashes with mint/teal palette
- **Severity:** critical
- **Selector / element:** `.btn.btn--primary` on the "+ Nuovo Ticket" button (`ticket.php:465-467`)
- **Screenshot region:** Top-right of the "Gestione Ticket" header, prominent rectangular blue button.
- **Current behaviour (dark mode):** Button is a saturated `#2563EB` cobalt blue, visually clashing with the mint accent used everywhere else in the redesign (sidebar active item, theme toggle, topbar). Reads as un-redesigned legacy chrome.
- **Root cause (CSS):** `.btn--primary` (double-dash variant) at `assets/css/styles.css:1085-1089` sets `background-color: var(--color-primary)` = `#2563EB`. The redesign override in `assets/css/components.css:206-211` only targets `.btn-primary` (single-dash), so the double-dash variant is NOT remapped to `--cnx-accent`.
- **Recommended fix:** Add a sibling override in `assets/css/components.css` right after the `.btn-primary` block:
```css
.btn--primary,
.btn.btn--primary {
    background-color: var(--cnx-accent) !important;
    border-color: var(--cnx-accent) !important;
    color: var(--cnx-accent-ink) !important;
}
.btn--primary:hover,
.btn.btn--primary:hover {
    background-color: var(--cnx-accent-hover) !important;
    border-color: var(--cnx-accent-hover) !important;
}
.btn--primary:focus-visible {
    outline: none;
    box-shadow: var(--cnx-shadow-focus);
}
```

### F2 — Stat cards have no visible border/elevation in dark mode
- **Severity:** critical
- **Selector / element:** `.tickets-stats > .stat-card` (inline `<style>` at `ticket.php:66-71`)
- **Screenshot region:** Row of 4 boxes "TICKET APERTI / IN LAVORAZIONE / RISOLTI OGGI / TEMPO MEDIO RISOLUZIONE".
- **Current behaviour (dark mode):** Cards have only `box-shadow: var(--shadow-sm)` (which is invisible on dark backgrounds because the shadow is also dark) and NO border. They render as flat blocks barely separable from `--cnx-bg-app`.
- **Root cause (CSS):** Inline `.stat-card` at `ticket.php:66-71` defines:
  ```
  background: var(--color-white);     /* OK -> #15262A in dark via override */
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);       /* invisible on dark */
  ```
  No `border` rule, and `--shadow-sm` is `rgba(0,0,0,*)` based which disappears on a near-black background.
- **Recommended fix:** Override in `assets/css/components.css` (alongside the existing `[data-theme="dark"] .stat-card` block at line 238):
```css
[data-theme="dark"] .tickets-stats .stat-card,
[data-theme="dark"] .stat-card {
    background: var(--cnx-bg-surface) !important;
    border: 1px solid var(--cnx-border) !important;
    box-shadow: none !important;
}
```

### F3 — Status badges have no pill background, only colored text
- **Severity:** critical
- **Selector / element:** `.status-badge.status-aperto` / `.status-in-corso` / `.status-risolto` / `.status-chiuso` (inline `<style>` at `ticket.php:155-158`)
- **Screenshot region:** "STATO" column shows entries (e.g. `general`, `technical`, `feature_request`) as plain teal-tinted text without any pill background.
- **Current behaviour (dark mode):** The badges are supposed to look like coloured pills (`bg + matching text`). They render as bare text because the `--color-*-100` variants used for the bg don't exist in `:root` and so resolve to nothing.
- **Root cause (CSS):** `ticket.php:155-158` references undefined custom properties:
  - `var(--color-primary-100)` — undefined
  - `var(--color-warning-100)` — undefined
  - `var(--color-success-100)` — undefined
  - `var(--color-gray-200)` — defined, only the 200-grey one resolves
  None of these tokens are declared in `assets/css/styles.css` `:root`. The `background:` declaration becomes invalid → no fill.
- **Recommended fix:** Use existing token-soft-tints (alpha overlays). Override in `assets/css/components.css` after the `[data-theme="dark"] .stat-label` block (around line 260):
```css
/* Ticket priority/status badges: soft pill background + colored text */
.priority-badge,
.status-badge {
    border: 1px solid transparent;
}
.priority-alta,
.status-aperto.is-error {
    background: rgba(239, 68, 68, 0.14);
    color: var(--cnx-danger);
    border-color: rgba(239, 68, 68, 0.32);
}
.priority-media,
.status-in-corso {
    background: rgba(245, 158, 11, 0.14);
    color: var(--cnx-warning);
    border-color: rgba(245, 158, 11, 0.32);
}
.priority-bassa,
.status-risolto {
    background: rgba(34, 197, 94, 0.14);
    color: var(--cnx-success);
    border-color: rgba(34, 197, 94, 0.32);
}
.status-aperto {
    background: var(--cnx-accent-soft);
    color: var(--cnx-accent);
    border-color: rgba(95, 202, 211, 0.32);
}
.status-chiuso {
    background: var(--cnx-bg-subtle);
    color: var(--cnx-text-muted);
    border-color: var(--cnx-border);
}
[data-theme="dark"] .priority-alta,
[data-theme="dark"] .status-aperto.is-error  { color: #F87171; }
[data-theme="dark"] .priority-media,
[data-theme="dark"] .status-in-corso         { color: #FBBF24; }
[data-theme="dark"] .priority-bassa,
[data-theme="dark"] .status-risolto          { color: #4ADE80; }
```

### F4 — Filter inputs (selects + search) have invisible borders in dark mode
- **Severity:** critical
- **Selector / element:** `.tickets-filters .form-control` (4 elements: assigned-filter, status-filter, priority-filter, search-input) at `ticket.php:493-519`
- **Screenshot region:** Row of 4 controls between stat cards and the table — "Tutti i ticket / Tutti gli stati / Tutte le urgenze / Cerca ticket...".
- **Current behaviour (dark mode):** The 3 selects have a faint mint outline (likely focus-leftover styling on first paint). The search field has NO visible border at all. They blend into `--cnx-bg-subtle` and look like floating labels rather than input controls.
- **Root cause (CSS):** `assets/css/components.css:262-273` overrides `.form-control` to `bg: var(--cnx-bg-subtle)` and `border-color: var(--cnx-border)`. In dark mode `--cnx-bg-subtle = #1B2F33` and `--cnx-border = #22363B` — the delta between these two greys is < 8/255, well below WCAG non-text contrast (3:1) for UI components.
- **Recommended fix:** Use `--cnx-border-strong` for inputs in dark mode and switch the bg to a lower neutral (`--cnx-bg-app`) so the border has more contrast:
```css
[data-theme="dark"] .form-control,
[data-theme="dark"] input[type="text"],
[data-theme="dark"] input[type="email"],
[data-theme="dark"] input[type="search"],
[data-theme="dark"] input[type="date"],
[data-theme="dark"] input[type="datetime-local"],
[data-theme="dark"] select,
[data-theme="dark"] textarea {
    background: var(--cnx-bg-app) !important;       /* was --cnx-bg-subtle */
    border-color: var(--cnx-border-strong) !important; /* was --cnx-border */
    color: var(--cnx-text-primary) !important;
}
```
(This is a global edit — verify it doesn't regress other pages, but the contrast win is universal.)

### F5 — Table column headers visually disconnected from rows
- **Severity:** major
- **Selector / element:** `.tickets-table th` (inline `<style>` at `ticket.php:115-124`)
- **Screenshot region:** Table header band "ID | OGGETTO | CATEGORIA | PRIORITÀ | STATO | ASSEGNATO A | CREATO | AZIONI".
- **Current behaviour (dark mode):** Header band has slightly teal-tinted bg (`--color-gray-50` in dark = `#1B2F33`), then rows below have `--color-white` (= `#15262A`) — i.e. the header is LIGHTER than rows. In a dark theme, headers are conventionally DARKER or accent-tinted with a clear divider below.
- **Root cause (CSS):** `ticket.php:118` uses `background: var(--color-gray-50)` which inverts to be lighter-than-card in the dark override block at `styles.css:239`.
- **Recommended fix:** In `assets/css/components.css`, add a dark-mode override for the ticket table head:
```css
[data-theme="dark"] .tickets-table thead th {
    background: var(--cnx-bg-subtle) !important;
    color: var(--cnx-text-secondary) !important;
    border-bottom: 1px solid var(--cnx-border-strong) !important;
}
```

### F6 — Table rows have no zebra banding
- **Severity:** major
- **Selector / element:** `.tickets-table tbody tr` / `td` (inline `<style>` at `ticket.php:126-129`)
- **Screenshot region:** Body of the tickets table — all 5 visible rows look identical and the eye loses position.
- **Current behaviour (dark mode):** All `<tr>` share the same `--color-white` (= `#15262A`) bg, with thin `--color-gray-200` (= `#22363B`) border-tops. Reading across rows is hard — there is no visual rhythm.
- **Root cause (CSS):** No `tr:nth-child(even)` rule exists in `ticket.php` inline style or in `assets/css/styles.css` for `.tickets-table`. Light mode could "get away" with subtle shadows, but dark mode needs explicit banding.
- **Recommended fix:** Add to `assets/css/components.css`:
```css
.tickets-table tbody tr:nth-child(even) {
    background: var(--cnx-bg-subtle);
}
[data-theme="dark"] .tickets-table tbody tr:nth-child(even) {
    background: rgba(95, 202, 211, 0.04);   /* ultra-subtle mint wash */
}
```

### F7 — Hover state on table rows uses light-grey color in dark mode
- **Severity:** major
- **Selector / element:** `tr:hover` (inline `<style>` at `ticket.php:131-133`)
- **Screenshot region:** N/A in static screenshot but reproducible on hover.
- **Current behaviour (dark mode):** `tr:hover` sets `bg: var(--color-gray-50)` which becomes `#1B2F33`. With even rows already on the same value (after F6 fix), hover becomes invisible.
- **Root cause (CSS):** `ticket.php:131-133` — same legacy gray reference.
- **Recommended fix:**
```css
[data-theme="dark"] .tickets-table tbody tr:hover {
    background: rgba(95, 202, 211, 0.08) !important;
}
```

### F8 — `.action-btn` icons effectively invisible (transparent on dark)
- **Severity:** major
- **Selector / element:** `.action-btn` in `.ticket-actions` (inline `<style>` at `ticket.php:165-178`)
- **Screenshot region:** "AZIONI" column at far-right of each row — appears empty in screenshot.
- **Current behaviour (dark mode):** `bg: transparent`, `color: var(--color-gray-600)` (= `#A8B5BB` in dark). The mask-icon SVGs render as a low-luminance grey on `#15262A` table bg, near-imperceptible at thumbnail size.
- **Root cause (CSS):** `ticket.php:165-178` — color value relies on `--color-gray-600` which after dark-override is `#A8B5BB`. That's actually OK in theory; the issue is more the transparent bg on a dark surface — eye expects an interactive control.
- **Recommended fix:** Provide a soft hover-able chip:
```css
[data-theme="dark"] .ticket-actions .action-btn {
    color: var(--cnx-text-secondary);
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid var(--cnx-border);
}
[data-theme="dark"] .ticket-actions .action-btn:hover {
    background: var(--cnx-accent-soft);
    color: var(--cnx-accent);
    border-color: rgba(95, 202, 211, 0.32);
}
```

### F9 — Per-page header underline is too faint
- **Severity:** minor
- **Selector / element:** `.main-content > .header` (`assets/css/components.css:52-64`)
- **Screenshot region:** Hairline below "Sistema Ticket" / "Tutte le aziende".
- **Current behaviour (dark mode):** `border-bottom: 1px solid var(--cnx-border)` (= `#22363B`) is barely visible against `--cnx-bg-app` (= `#0E1A1D`) — separation between the global topbar (mint-tinted) and the page header is unclear.
- **Root cause (CSS):** `components.css:63` uses `--cnx-border` (the soft border).
- **Recommended fix:**
```css
[data-theme="dark"] .main-content > .header,
[data-theme="dark"] .main-content > header.header {
    border-bottom-color: var(--cnx-border-strong);
}
```

### F10 — "Gestione Ticket" h2 inherits muted color, low contrast
- **Severity:** minor
- **Selector / element:** `.tickets-header h2` (no explicit rule; inherits from cascade)
- **Screenshot region:** Sub-header above stat cards: "Gestione Ticket".
- **Current behaviour (dark mode):** Renders fine (white) but slightly muted vs the page-title above, no visual hierarchy stating "this is THE page title block".
- **Root cause (CSS):** `.tickets-header h2` has no specific styling. Default `h2` rule sets `color: var(--color-gray-900)` which after override = `#E6EEF0`. Adequate, but consider strengthening.
- **Recommended fix (optional):**
```css
.tickets-header h2 {
    font-size: var(--cnx-text-h1-size);
    line-height: var(--cnx-text-h1-line);
    font-weight: var(--cnx-text-h1-weight);
    color: var(--cnx-text-primary);
    letter-spacing: var(--cnx-tracking-tight);
}
```

### F11 — `--color-primary` references in stat-card hover bar still cobalt
- **Severity:** major
- **Selector / element:** `.stat-card::before` (`assets/css/dashboard.css:130-140`) — only triggers on hover
- **Screenshot region:** Not in screenshot but appears on hover at top edge of card.
- **Current behaviour (dark mode):** Hover decoration bar uses `background: var(--color-primary)` — cobalt blue, contradicting the mint accent.
- **Root cause (CSS):** `dashboard.css:137` literal `--color-primary` reference.
- **Recommended fix:** Replace in `assets/css/dashboard.css:137` (or override globally in components.css):
```css
[data-theme="dark"] .stat-card::before { background: var(--cnx-accent); }
.stat-card::before { background: var(--cnx-accent); }
```

### F12 — Modal "alert-danger" hardcodes `#FEE2E2 / #991B1B` (white-bg pink), unreadable in dark mode
- **Severity:** major
- **Selector / element:** `.modal-body .alert-danger` (`ticket.php:328-332`) — present inside the create-ticket modal
- **Screenshot region:** Not visible in static screenshot but present on validation error.
- **Current behaviour (dark mode):** When the modal is open and an error fires, the alert has `bg: #FEE2E2` (light pink) and `color: #991B1B` (dark red text). On the dark modal, the light-pink panel is jarring.
- **Root cause (CSS):** `ticket.php:328-332` hardcoded hex literals.
- **Recommended fix:**
```css
[data-theme="dark"] .modal-body .alert-danger {
    background-color: rgba(239, 68, 68, 0.10);
    border-color: rgba(239, 68, 68, 0.32);
    color: #F87171;
}
```

### F13 — Modal-content + nested inline styles use `background: white`, `#F9FAFB`, `#1F2937` — bleed-through if modal opens
- **Severity:** major
- **Selector / element:** `.modal-content` (`ticket.php:206-217`), nested `style="background: #F9FAFB"` (`ticket.php:683`), `color: #1F2937` (`ticket.php:687, 699...`), and many more inline literals throughout the detail modal.
- **Screenshot region:** Not visible in static screenshot but present when "Visualizza" / "Modifica" actions open detail/create modal.
- **Current behaviour (dark mode):** The `.modal-content` rule has `background: white` (literal `white`, not a token). Components.css `[data-theme="dark"] .modal-content` overrides to `--cnx-bg-surface !important` so OUTER works. But the inline-style `background: #F9FAFB`, `background: #FFFFFF`, `background: #FEF2F2` etc. on inner detail panels (`ticket.php:683, 697, 701, 705, 709, 718, 724, 761, 772-779, 788, 791, 795, 802, 810` ...) are NOT overridden and will paint as light bands inside an otherwise dark modal.
- **Root cause (CSS):** Inline `style="background: #F9FAFB"` cannot be overridden by CSS without `!important` AND a strong selector (and even then it's awkward against `style=""` which has higher specificity than ATTR-class rules).
- **Recommended fix:** **REQUIRES MARKUP CHANGE** — the cleanest fix is to migrate the inline-style hex literals to classes. As a CSS-only stopgap, target the inline-styles via attribute selector with `!important`:
```css
[data-theme="dark"] #ticket-detail-modal [style*="background: white"],
[data-theme="dark"] #ticket-detail-modal [style*="background:#fff"],
[data-theme="dark"] #ticket-detail-modal [style*="background: #F9FAFB"],
[data-theme="dark"] #ticket-detail-modal [style*="background:#F9FAFB"] {
    background: var(--cnx-bg-subtle) !important;
}
[data-theme="dark"] #ticket-detail-modal [style*="color: #1F2937"],
[data-theme="dark"] #ticket-detail-modal [style*="color:#1F2937"] {
    color: var(--cnx-text-primary) !important;
}
[data-theme="dark"] #ticket-detail-modal [style*="color: #6B7280"],
[data-theme="dark"] #ticket-detail-modal [style*="color:#6B7280"] {
    color: var(--cnx-text-muted) !important;
}
```
Flag for refactor: lots of inline hex literals in the detail modal — should be moved to classes.

### F14 — Conversation count badge `#2563EB` is cobalt blue, breaks accent harmony
- **Severity:** minor
- **Selector / element:** Inline `style="background: #2563EB"` on `#detail-response-count` (`ticket.php:791`)
- **Screenshot region:** Not visible in screenshot (modal closed) but present on detail-modal "💬 Conversazione 0".
- **Current behaviour (dark mode):** Cobalt blue bubble, clashing with mint accent.
- **Root cause (CSS):** Hardcoded inline `background: #2563EB`.
- **Recommended fix:** **REQUIRES MARKUP CHANGE** to remove inline style. CSS-only stopgap:
```css
[data-theme="dark"] #detail-response-count[style*="#2563EB"],
#detail-response-count[style*="#2563EB"] {
    background: var(--cnx-accent) !important;
    color: var(--cnx-accent-ink) !important;
}
```
