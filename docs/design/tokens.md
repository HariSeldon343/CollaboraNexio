# CollaboraNexio — Design Tokens (UI Redesign 2026-05)

> Reference per shell-engineer, component-engineer, page-migrator.
> Implementati in `assets/css/styles.css` come estensione di `:root` (light) + override `[data-theme="dark"]`.
> Spec sorgente: `docs/design/direction.md`.

Tutti i token hanno prefisso `--cnx-*` per non collidere con i token legacy (`--color-*`, `--text-*`, `--space-*`, `--radius-*`, `--shadow-*`) che restano disponibili e referenziati da CSS esistenti. Niente token legacy e' stato rimosso.

---

## 1. Surfaces & Backgrounds

| Token | Light | Dark | Uso semantico |
|---|---|---|---|
| `--cnx-bg-app` | `#F5F7F8` | `#0E1A1D` | Background content area (sotto card) |
| `--cnx-bg-surface` | `#FFFFFF` | `#15262A` | Card, modal, table wrapper, header |
| `--cnx-bg-subtle` | `#EEF2F3` | `#1B2F33` | Hover row, input bg, sezioni interne, table header |
| `--cnx-bg-sidebar` | `#1F3D3D` | `#0A1517` | Sidebar full-height (dark anche in light mode) |
| `--cnx-bg-sidebar-hover` | `rgba(255,255,255,0.06)` | invariato | Sidebar link hover |
| `--cnx-bg-sidebar-active` | `rgba(95,202,211,0.16)` | invariato | Sidebar link attivo |

## 2. Accent (mint/teal — single accent)

| Token | Light | Dark | Uso semantico |
|---|---|---|---|
| `--cnx-accent` | `#5FCAD3` | `#5FCAD3` | CTA primaria, focus ring, stati attivi sidebar |
| `--cnx-accent-hover` | `#4FB8C2` | invariato | Hover CTA primaria |
| `--cnx-accent-soft` | `#E8F7F8` | `rgba(95,202,211,0.12)` | Background badge accent, dropzone, filter pill attiva |
| `--cnx-accent-ink` | `#0F2D31` | invariato | Testo su accent (contrasto AA) |

## 3. Text

| Token | Light | Dark | Uso semantico |
|---|---|---|---|
| `--cnx-text-primary` | `#0F1A1F` | `#E6EEF0` | H1-H3, label form |
| `--cnx-text-secondary` | `#4A5560` | `#A8B5BB` | Body text |
| `--cnx-text-muted` | `#8893A0` | `#6B7A82` | Caption, helper, breadcrumb inattivo, table header |
| `--cnx-text-on-sidebar` | `#FFFFFF` | invariato | Link sidebar attivo, profilo |
| `--cnx-text-on-sidebar-muted` | `rgba(255,255,255,0.62)` | invariato | Link sidebar inattivo, section label sidebar |

## 4. Borders

| Token | Light | Dark | Uso semantico |
|---|---|---|---|
| `--cnx-border` | `#E4E8EC` | `#22363B` | Border card, input, table row, header bottom |
| `--cnx-border-strong` | `#CFD6DC` | `#2E464C` | Border focus non-accent, divider forte, button secondary |

## 5. Status

| Token | Light | Dark | Uso semantico |
|---|---|---|---|
| `--cnx-success` | `#22C55E` | invariato | Badge success, status ok |
| `--cnx-warning` | `#F59E0B` | invariato | Badge warning |
| `--cnx-danger` | `#EF4444` | invariato | Badge error, delete |
| `--cnx-info` | `#5FCAD3` | invariato | Badge info (= accent) |

## 6. Typography

Font stack via `--cnx-font-sans`. Inter self-hosted (caricamento font da definire in shell-engineer).

| Token | Size | Line | Weight | Uso |
|---|---|---|---|---|
| `--cnx-text-display-*` | 32px | 40px | 700 | Solo greeting dashboard |
| `--cnx-text-h1-*` | 24px | 32px | 600 | Page title |
| `--cnx-text-h2-*` | 18px | 26px | 600 | Card title |
| `--cnx-text-h3-*` | 15px | 22px | 600 | Sub-section |
| `--cnx-text-body-*` | 14px | 22px | 400 | Body |
| `--cnx-text-small-*` | 13px | 20px | 400 | Small / helper |
| `--cnx-text-caption-*` | 12px | 16px | 500 | Section labels (uppercase tracking 0.06em) |

Letter-spacing token aggiuntivi:
- `--cnx-text-caption-tracking: 0.06em` — section labels sidebar e table header
- `--cnx-tracking-tight: -0.01em` — display + h1

## 7. Spacing extensions

| Token | Value | Uso |
|---|---|---|
| `--cnx-space-card-padding` | `24px` | Padding interno card |
| `--cnx-space-section-gap` | `32px` | Gap tra sezioni di pagina |

> Scale 8px base resta `--space-1`..`--space-24` (immutata).

## 8. Radii

| Token | Value | Uso |
|---|---|---|
| `--cnx-radius-sm` | `6px` | Badge, tag |
| `--cnx-radius-md` | `10px` | Input squared, dropdown, theme toggle |
| `--cnx-radius-lg` | `16px` | Card, table wrapper, dropzone |
| `--cnx-radius-xl` | `20px` | Modal |
| `--cnx-radius-pill` | `9999px` | Button, search input, filter pill |

## 9. Shadows

| Token | Value | Uso |
|---|---|---|
| `--cnx-shadow-sm` | `0 1px 2px rgba(15,26,31,0.04)` | Input default |
| `--cnx-shadow-md` | `0 4px 12px rgba(15,26,31,0.06)` | Card resting |
| `--cnx-shadow-lg` | `0 12px 24px rgba(15,26,31,0.08)` | Modal, dropdown open |
| `--cnx-shadow-focus` | `0 0 0 3px rgba(95,202,211,0.32)` | Focus ring accent |

> Vincolo: max y-12, alpha <= 0.08. Niente shadow profondi.

## 10. Motion

| Token | Value | Uso |
|---|---|---|
| `--cnx-transition` | `all 180ms cubic-bezier(0.4, 0, 0.2, 1)` | Default per hover/focus/state. Mai > 250ms. |

---

## Note di compatibilita

- I token legacy (`--color-*`, `--text-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--transition-*`, `--sidebar-width`, `--header-height`, ...) **non sono stati toccati** — restano disponibili per CSS pre-redesign.
- `--sidebar-width: 260px` e `--header-height: 60px` sono compatibili con la spec (header nuovo a 64px sara' gestito in shell-engineer via override locale del componente o aggiornamento dedicato).
- Sidebar ha lo stesso aspetto in entrambi i temi: dark by design (vedi spec Direction sezione "Colors dark").

## Prossimi step (out of scope per design-system-architect)

1. Shell-engineer: applicare i token alla sidebar/header in `includes/sidebar.php` + CSS shell.
2. Component-engineer: implementare classi `.cnx-btn`, `.cnx-card`, `.cnx-input`, `.cnx-modal`, ecc.
3. Page-migrator: pilotare `dashboard.php` e `files.php`.
4. Theme toggle: persistenza `localStorage`, attributo `data-theme` su `<html>`.
