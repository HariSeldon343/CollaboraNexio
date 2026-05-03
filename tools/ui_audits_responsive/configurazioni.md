# Responsive audit — configurazioni.php

> Companion to `_shell.md`. The shell-level F1 (hamburger missing) and F2
> (sidebar still rendered at 768px) cascade here too. Findings below survive
> after those shell fixes.

## Summary
- Total findings: 7
- Severity: critical=2, major=3, minor=2
- Worst breakpoint: 768px (tab strip pushed off-screen by sidebar)

---

## Findings

### F1 — At 768px, content cut by sidebar (shell F2 cascade)
- **Severity:** critical
- **Breakpoint:** 768px
- **Selector / element:** `.main-content` margin-left bug (`assets/css/styles.css:1209`); page-level `.config-container { max-width: 1200px }` (`configurazioni.php:69-72`).
- **Screenshot region:** Tablet — `Visibilita Pagine` (last tab) is half cut by viewport right edge. Form fields below ("Nome Piattaforma", "URL Base", "Fuso Orario", "Lingua Predefinita") fit, but the `Modalità Manutenzione` toggle row is partially clipped on the right.
- **Current behaviour:** Inherits shell F2.
- **Root cause (CSS):** Shell.
- **Recommended fix:** Apply shell F2 (move breakpoint to 1023.98px). After that, also apply the page-level form-row stacking for tablet portrait:
  ```css
  /* configurazioni.php inline <style> — REPLACE lines 343-347 */
  @media (max-width: 1023.98px) {
      .config-form .form-row {
          grid-template-columns: 1fr;
          gap: var(--space-4);
      }
      .config-container {
          padding: var(--space-4);
      }
  }
  ```

---

### F2 — Tab strip ("Generale Sicurezza Email Backup Integrazioni Aspetto Visibilità Pagine") overflows at 768
- **Severity:** critical
- **Breakpoint:** 768px
- **Selector / element:** `.config-tabs { display: flex; gap: var(--space-2); border-bottom: 2px solid var(--color-gray-200); }` (`configurazioni.php:78-83`) — has NO `flex-wrap` and NO horizontal scrolling.
- **Screenshot region:** Tablet — tab strip appears clipped: "Generale | Sicurezza | Email | Backup | Integrazioni | Aspetto | Visibilita" then "Pagine" cut off (only "P..." visible).
- **Current behaviour:** Tabs render in one row, no wrap, no scroll → last tab(s) silently truncated. The `.tab-btn` has `padding: var(--space-3) var(--space-6)` ≈ 12px 24px, so 7 tabs × ~120px each = ~840px → exceeds the 768 - sidebar - container-padding available width.
- **Root cause (CSS):**
  ```css
  /* configurazioni.php:78-83 */
  .config-tabs {
      display: flex;          /* no flex-wrap */
      gap: var(--space-2);
      border-bottom: 2px solid var(--color-gray-200);
      margin-bottom: var(--space-8);
      /* NO overflow-x, NO scroll-snap */
  }
  ```
- **Recommended fix:** Make the tab strip horizontally scrollable on narrow widths and the active tab auto-scroll into view:
  ```css
  /* configurazioni.php inline <style> — REPLACE lines 78-83 */
  .config-tabs {
      display: flex;
      gap: var(--space-2);
      border-bottom: 2px solid var(--cnx-border);
      margin-bottom: var(--space-8);
      overflow-x: auto;
      overflow-y: hidden;
      -webkit-overflow-scrolling: touch;
      scroll-snap-type: x proximity;
      scrollbar-width: thin;
      /* Hide visible scrollbar but keep functional */
      &::-webkit-scrollbar { height: 4px; }
      &::-webkit-scrollbar-thumb { background: var(--cnx-border); border-radius: 2px; }
  }
  .tab-btn {
      flex: 0 0 auto;
      white-space: nowrap;
      scroll-snap-align: start;
  }
  /* Mobile: keep buttons visible without harsh wrap */
  @media (max-width: 480px) {
      .tab-btn { padding: var(--space-3) var(--space-4); font-size: 0.875rem; }
  }
  ```
  Plus a tiny JS hook (already feasible from `switchTab` in the page) to call `el.scrollIntoView({ inline: 'center', behavior: 'smooth' })` on the active tab.

---

### F3 — `.form-row` stays 2-col at 768 → fields truncated
- **Severity:** major
- **Breakpoint:** 768px
- **Selector / element:** `.form-row { grid-template-columns: 1fr 1fr; gap: var(--space-6) }` (`configurazioni.php:141-145`); the existing `@media (max-width: 768px) { .form-row { grid-template-columns: 1fr } }` (`configurazioni.php:343-347`) only fires below 768.
- **Screenshot region:** Tablet — at 768 (boundary), 2 cols still rendered, each ~300px wide, so "URL Base" with `https://collaboranexio.com` fits but the right column ("Fuso Orario", "Lingua Predefinita") is partially clipped because of shell F2.
- **Current behaviour:** Once shell F2 is fixed, the 2-col form fits at 768 (sidebar gone, full width). But still tight.
- **Root cause (CSS):** Breakpoint timing.
- **Recommended fix:** Already covered by F1 of this report (move stacking to `1023.98px`).

---

### F4 — `.toggle-group` (toggle switch with label) overflows at 480
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.toggle-group { display: flex; align-items: center; justify-content: space-between; padding: var(--space-4); }` (`configurazioni.php:215-222`)
- **Screenshot region:** Bottom of mobile screenshot — `Modalità Manutenzione` row has the toggle slightly visible to the right; the long "Configura le impostazioni base..." copy below would push the toggle off-screen on a narrower row.
- **Current behaviour:** Long descriptions push the toggle off-screen.
- **Root cause (CSS):** No mobile fallback for stacking the description.
- **Recommended fix:**
  ```css
  /* configurazioni.php inline <style> — append */
  @media (max-width: 480px) {
      .toggle-group {
          flex-direction: column;
          align-items: flex-start;
          gap: var(--space-3);
          padding: var(--space-3);
      }
      .toggle-info { width: 100%; }
      .toggle-switch { align-self: flex-end; }
  }
  ```

---

### F5 — `.config-actions` Save button row sits in unstyled flex-end at 480
- **Severity:** major
- **Breakpoint:** 375px
- **Selector / element:** `.config-actions { display: flex; justify-content: flex-end; gap: var(--space-3); border-top: 1px solid var(--color-gray-200) }` (`configurazioni.php:239-245`)
- **Screenshot region:** Below the visible area — Save / Reset buttons cluster at flex-end. On mobile (375), if there are 2 buttons of width 120px each + gap, total ~252px → fits, but borderline.
- **Current behaviour:** OK at 375 today, fragile if a third button is added.
- **Root cause (CSS):** No wrap / mobile-stack rule.
- **Recommended fix:**
  ```css
  /* configurazioni.php inline <style> — append */
  @media (max-width: 480px) {
      .config-actions {
          flex-direction: column-reverse; /* primary CTA on top on mobile */
          gap: var(--space-2);
          padding-top: var(--space-4);
      }
      .config-actions .btn { width: 100%; min-height: 44px; justify-content: center; }
  }
  ```

---

### F6 — Color picker group flex wrap leaves 40px squares orphaned at 375
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.color-picker-group { display: flex; gap: var(--space-4); flex-wrap: wrap }` (`configurazioni.php:268-272`)
- **Screenshot region:** Aspetto tab (not in screenshot, but documented in code).
- **Current behaviour:** Color presets wrap fine, but each `.color-preview` is 40x40 (`configurazioni.php:280-286`) — borderline below WCAG 44x44.
- **Root cause (CSS):** Hardcoded 40px, no mobile bump.
- **Recommended fix:**
  ```css
  /* configurazioni.php inline <style> — append */
  @media (max-width: 480px) {
      .color-preview { width: 44px; height: 44px; }
      .color-input-wrapper { gap: var(--space-3); }
  }
  ```

---

### F7 — `.backup-item` collapses awkwardly at 480 (info + actions side-by-side)
- **Severity:** minor
- **Breakpoint:** 375px
- **Selector / element:** `.backup-item { display: flex; justify-content: space-between; align-items: center }` (`configurazioni.php:288-296`)
- **Screenshot region:** Backup tab (not in screenshot)
- **Current behaviour:** Backup list rows render `name + details + 2-3 action buttons` in a single flex row → at 375 the buttons are squeezed and sometimes overflow.
- **Root cause (CSS):** No mobile stacking.
- **Recommended fix:**
  ```css
  /* configurazioni.php inline <style> — append */
  @media (max-width: 480px) {
      .backup-item {
          flex-direction: column;
          align-items: stretch;
          gap: var(--space-3);
      }
      .backup-actions {
          justify-content: flex-end;
          flex-wrap: wrap;
      }
      .backup-actions .btn { min-height: 40px; }
  }
  ```
