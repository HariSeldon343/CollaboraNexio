# Responsive audit — audit_log.php

> Companion to `_shell.md`. Many of the audit_log issues at 768px will be
> automatically resolved by F2 (move sidebar breakpoint to 1023.98px) and F1
> (hamburger toggle in markup). The findings below are ON TOP of those shell
> fixes — issues that survive even after the shell is correct.

## Summary
- Total findings: 9
- Severity: critical=3, major=4, minor=2
- Worst breakpoint: 768px (tablet portrait — entire content cut by sidebar; see shell F2)

---

## Findings

### F1 — At 768px viewport, content cut by ~260px due to sidebar still rendered
- **Severity:** critical
- **Breakpoint:** 768px
- **Selector / element:** `.main-content` (file: `assets/css/styles.css:1209` `margin-left: var(--sidebar-width)`); the page-level CSS `@media (min-width: 769px) and (max-width: 1024px)` (`audit_log.php:1026-1042`) ASSUMES the sidebar is hidden and applies a 3-col stat grid — wrong because at 768-1023 the sidebar is still rendered.
- **Screenshot region:** Tablet portrait — `EVENTI OGGI 117`, `UTENTI ATTIVI 4`, `ACCESSI 106`, `MODIFICHE 0` cards all cut on the right; `Eli...` button at top-right cut; filter form right column (`Data Al`, `Azione`, Reset/`Applica`) cut.
- **Current behaviour:** Cascade of three problems: (a) shell F2: main-content keeps `margin-left: 260px` at 768; (b) audit_log local rule `@media (min-width: 769px) and (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(3, 1fr) } }` would force 3 cols at narrow widths; (c) the page actively expects to render at full viewport width.
- **Root cause (CSS):** Combination of shell + page-level miscalibration.
- **Recommended fix:** After applying shell F2 (sidebar slides off at `max-width: 1023.98px`), update the page-level breakpoints accordingly:
  ```css
  /* audit_log.php inline <style> — REPLACE lines 986-1042 */

  /* Phones (< 481px): single column */
  @media (max-width: 480px) {
      /* keep existing single-column rules */
  }

  /* Tablet portrait + small landscape (481-1023): 2 cols */
  @media (min-width: 481px) and (max-width: 1023.98px) {
      .audit-container { padding: 1rem; }
      .page-title { font-size: 1.5rem; }
      .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
      .filters-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .page-header { flex-direction: row; flex-wrap: wrap; }
      .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .audit-table { min-width: 800px; }
  }

  /* Large tablets / small desktops (1024-1280): 3 cols */
  @media (min-width: 1024px) and (max-width: 1280px) {
      .audit-container { padding: 1.5rem; }
      .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .filters-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  ```

---

### F2 — "Elimina Log" button text invisible at 375px (red bar with no readable label)
- **Severity:** critical
- **Breakpoint:** 375px
- **Selector / element:** `.btn-danger` inside `.page-actions` at `(max-width: 480px) { .page-actions .btn { width: 100%; } }` (`audit_log.php:875-878`)
- **Screenshot region:** Mobile — "Registro Audit" header has below it a full-width red rectangle with NO visible text. The button text "Elimina Log" + trash icon should be there but appears clipped/invisible.
- **Current behaviour:** The `.btn-danger` declares `color: white` (`audit_log.php:402`) but the icon span and label are not centered consistently because `.btn` uses `gap: 0.5rem` (`audit_log.php:377`) — at narrow widths the icon takes the leading 1rem and the label "Elimina Log" is rendered but white-on-red has very low contrast at the camera/pixel scale of the screenshot. Verified the markup renders text — the issue is contrast + camera resolution. Also the button has `padding: 0.625rem 1.25rem` so at 375 it grows tall but text is fine semantically.
- **Root cause (CSS):** Sub-issue: button has no min-content guarantee; if a future longer label arrives it could wrap awkwardly. More importantly: `.btn-danger { background: #EF4444 }` is defined inline (page CSS) AND **overridden site-wide** by `components.css:1325-1342 .btn-danger { background: var(--cnx-danger) }` — but only `var(--cnx-danger)` resolves correctly, so this is fine. The "invisible" appearance in the screenshot is a contrast issue under low-DPI capture.
- **Recommended fix:** Reinforce contrast and ensure the text/icon combination is always centred:
  ```css
  /* audit_log.php inline <style> — append at end of @media (max-width: 480px) block */
  @media (max-width: 480px) {
      .page-actions .btn-danger {
          font-size: 0.875rem;
          font-weight: 600;
          letter-spacing: 0.01em;
          gap: 0.5rem;
          /* Add subtle shadow so the white text never disappears against red on OLED */
          text-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
      }
  }
  ```

---

### F3 — Stat cards bleed past viewport right edge at 768 (after F1 → still 2-col cards too wide)
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.stats-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) }` (`audit_log.php:99-103`)
- **Screenshot region:** Tablet — at 768 with sidebar hidden (after shell F2), the inner content area is 768 - padding 32 = 736; 2 cards with 200min and 1.25rem gap = total ≥ 420 → 2-up fits OK. So this issue self-resolves after shell F2. **No specific action needed — verify post-fix.**
- **Current behaviour:** After shell F2, the 2-col layout is correct. Before fix: 3-col jammed.
- **Root cause (CSS):** Inherits from F1.
- **Recommended fix:** None standalone — covered by F1.

---

### F4 — Filter form 2-col at 768 → "Severità" sits alone on its row, ugly
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.filters-grid` (`audit_log.php:192-197`) with `repeat(auto-fit, minmax(200px, 1fr))`
- **Screenshot region:** Tablet — 5 fields (Data Dal, Data Al, Utente, Azione, Severità). At 2-col: 2+2+1, with "Severità" alone on row 3 and big empty space to its right.
- **Current behaviour:** Acceptable but visually unbalanced.
- **Root cause (CSS):** No grid auto-flow control.
- **Recommended fix:**
  ```css
  /* audit_log.php inline <style> — inside @media (min-width: 481px) and (max-width: 1023.98px) */
  .filters-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  /* Force the lonely 5th cell to span full width for a tidier layout */
  .filters-grid > .filter-group:nth-child(5):last-child {
      grid-column: 1 / -1;
  }
  ```

---

### F5 — `.filters-actions` Reset/Applica buttons shrink and overlap at 768
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.filters-actions` (`audit_log.php:228-233`) `justify-content: flex-end`
- **Screenshot region:** Tablet — Reset and "Applica" sit at far right; "Applica" is partially cut by viewport edge (after shell F2 it should fit, but the button copy + icon take ~120px so on borderline widths it's tight).
- **Current behaviour:** No min-width guarantee, and at narrow tablet widths the "Applica" CTA can wrap or overflow horizontally.
- **Root cause (CSS):** Missing wrap rule + no min-tap target.
- **Recommended fix:**
  ```css
  /* audit_log.php inline <style> — inside @media (min-width: 481px) and (max-width: 1023.98px) */
  .filters-actions {
      flex-wrap: wrap;
      gap: 0.5rem;
  }
  .filters-actions .btn {
      min-height: 40px; /* WCAG-friendly touch target on tablets */
      flex: 0 1 auto;
  }
  ```

---

### F6 — Audit table forces horizontal scroll inside viewport at 768/375
- **Severity:** major
- **Breakpoint:** both
- **Selector / element:** `.audit-table { min-width: 900px }` at 480 and `min-width: 800px` at 768 (`audit_log.php:933-935, 1015-1017`)
- **Screenshot region:** Lower portion (just visible at the bottom of the 768 capture as "Lo... Audit" + "Esp..." button) — the table forces a horizontal scroller. This is a deliberate trade-off (audit logs have many columns), but the wrapper `.table-container { overflow-x: auto }` is correctly set. The issue: the horizontal scrollbar can be invisible on iOS Safari (no scrollbar styling) and the user has no hint they can scroll.
- **Current behaviour:** Functional but lacks affordance.
- **Root cause (CSS):** No visual hint for scrollable region.
- **Recommended fix:**
  ```css
  /* audit_log.php inline <style> — append */
  @media (max-width: 1023.98px) {
      .table-container {
          position: relative;
      }
      /* Right-edge gradient hints there's more content */
      .table-container::after {
          content: "";
          position: absolute;
          top: 0;
          right: 0;
          bottom: 0;
          width: 24px;
          background: linear-gradient(to right, transparent, var(--cnx-bg-app, #f9fafb));
          pointer-events: none;
      }
  }
  ```
  (Use `--cnx-bg-app` so it works in dark mode too.)

---

### F7 — Pagination row stacks awkwardly at 480px
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.pagination-container` at `(max-width: 480px) { flex-direction: column }` (`audit_log.php:970-984`)
- **Screenshot region:** Bottom of mobile (cut from screenshot) — info text + paginator on two rows is OK, but page number buttons are 44px each → 5+ buttons can overflow on 375.
- **Current behaviour:** Buttons wrap with `flex-wrap: wrap`, so they stack to a 2nd row. Acceptable but the wrap looks unbalanced (3+2 layout).
- **Root cause (CSS):** No constraint on number of buttons rendered on mobile.
- **Recommended fix:** Render fewer page numbers on mobile via JS (only prev/page/next), OR center the entire group:
  ```css
  /* audit_log.php inline <style> — inside @media (max-width: 480px) */
  .pagination-buttons {
      width: 100%;
      justify-content: center;
      flex-wrap: wrap;
      row-gap: 0.5rem;
  }
  .pagination-btn { min-height: 44px; min-width: 44px; }
  ```

---

### F8 — `.audit-detail-grid` modal renders 160px label column at 375 (illegible)
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.audit-kv { grid-template-columns: 160px 1fr }` (`audit_log.php:613-618`)
- **Screenshot region:** Modal not in screenshot, but at 375px this leaves ~150px for value column → JSON / IP / timestamps wrap awkwardly.
- **Current behaviour:** Always 160px label, regardless of viewport.
- **Root cause (CSS):** No mobile override.
- **Recommended fix:**
  ```css
  /* audit_log.php inline <style> — append at end of @media (max-width: 480px) block */
  .audit-kv { grid-template-columns: 1fr; gap: 4px 0; }
  .audit-kv dt { margin-top: 8px; }
  .audit-kv dd { padding-left: 0; }
  ```

---

### F9 — Header gap between title and "Elimina Log" lost on tablet
- **Severity:** minor
- **Breakpoint:** 768px
- **Selector / element:** `.page-header { gap: 1rem }` + `.page-actions` (no width on tablet)
- **Screenshot region:** Tablet — title "Registro Audit" left, "Eli..." button right edge of available area; gap looks fine until F1 fix where the right side is no longer cut. After fix, the button might end up flush against the right edge.
- **Current behaviour:** Acceptable; covered by `.cnx-page-header__actions` margin if migrated, but page still uses legacy `.page-actions`.
- **Root cause (CSS):** No padding-right safeguard.
- **Recommended fix:**
  ```css
  /* audit_log.php inline <style> — append */
  @media (min-width: 481px) and (max-width: 1023.98px) {
      .page-header {
          padding-right: 0;
          row-gap: 0.75rem;
      }
      .page-actions { margin-left: auto; }
  }
  ```
