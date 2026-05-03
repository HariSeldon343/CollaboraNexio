# CollaboraNexio — Design Direction (UI Redesign 2026-05)

> Documento di indirizzo per il team `ui-redesign-2026-05`. Vincolante per design-system-architect, shell-engineer, component-engineer, page-migrator. Nessuna PHP business logic viene toccata: il redesign è puramente visivo (CSS, markup di presentazione, asset).

---

## Mood / Visual Identity

CollaboraNexio diventa **enterprise calm**: un workspace premium, silenzioso, con generosa whitespace, tipografia chiara e una sola "spike" cromatica — il **mint/teal** — che guida l'occhio verso le azioni primarie e gli stati attivi. La sidebar **dark teal** funge da ancora visiva costante; il content area è quasi-bianco con card bianche piene a radius 16px. Niente decoration superflua, niente gradient, niente glass morphism: ogni pixel deve giustificare la propria presenza. La voce visiva è quella di uno strumento che si usa 8 ore al giorno — riposante, leggibile, senza urlare.

**Principi guida (non negoziabili):**
1. **Una sola accent color** (mint `#5FCAD3`) — usata con parsimonia su CTA, focus rings, stati attivi sidebar.
2. **Whitespace prima della densità** — padding card ≥ 24px, gap tra sezioni ≥ 32px.
3. **Tipografia come gerarchia primaria** — pesi (500/600/700) + size scale, mai colore o decorazione per gerarchia.
4. **Shadow soft, max y-12, alpha ≤ 0.08** — niente drop shadow drammatici.
5. **Radius coerente**: card 16px, button/input pill 999px, modal 20px, badge 8px.

---

## Mapping reference → CollaboraNexio

| Elemento reference | Stato attuale CNX | Decisione |
|---|---|---|
| Sidebar dark teal `#1F3D3D` con sezioni in caps | Sidebar dark navy `#0f1922→#1a2332` con label "NEXIO" + tagline | **Adapt**: nuovo colore teal, mantieni struttura sezioni (AREA OPERATIVA / GESTIONE / ADMIN), rimuovi tagline "Semplifica, Connetti, Cresci" dall'header sidebar |
| Search bar centrale rounded-full nel header | Header con titolo pagina + selettore azienda | **Adopt**: aggiungi search bar globale al centro, mantieni selettore azienda a sinistra del search |
| Theme toggle luna/sole | Assente | **Adopt**: nuovo controllo nel header destro |
| Greeting "Hello, Dean Ferrera!" + data | Header generico "Dashboard" | **Adapt**: dashboard greeting personalizzato `Ciao, {nome}!` + data corrente in italiano |
| Card bianche radius 16px con shadow soft | Card bianche radius variabile, shadow ok | **Adopt** uniformemente |
| Filter pill rounded con dropdown | Bottoni "Filtra" / "Ordina" outlined squared | **Adapt**: pill rounded con icona chevron |
| Modale "Add file or folder" con dropzone tratteggiata accent | Modal upload generico | **Adopt** integralmente per files.php |
| Table righe pulite, no border verticali, hover sottile | Table con border, checkbox, hover ok | **Adapt**: rimuovi border verticali, hover row più morbido |
| Breadcrumb minimale con `/` separator | Assente / inconsistente | **Adopt**: nuovo componente breadcrumb su files.php |
| Dashboard widget grid 3 colonne (To-do, Documents, Timesheet) | Grid esistente con widget Ora/Turni/Task/Attività/Calendario | **Adapt**: mantieni i widget CNX, applica nuovo card style |
| Avatar utente colorato pill in basso sidebar | Card profilo dark in basso sidebar | **Adopt**: stesso layout, aggiorna stile |
| Logo "Sparkle/diamond" mark | Logo sparkle blu esistente | **Keep**: ricolora il mark in mint |

**Skip integralmente** dalle reference: illustrazioni decorative, icone illustrate colorate (manteniamo line icons monocrome), badge emoji.

---

## Tokens (per design-system-architect)

### Colors — Light theme `:root`

| Token | Hex | Uso semantico |
|---|---|---|
| `--cnx-bg-app` | `#F5F7F8` | Background content area |
| `--cnx-bg-surface` | `#FFFFFF` | Card, modal, table |
| `--cnx-bg-subtle` | `#EEF2F3` | Hover row, input bg, sezioni interne |
| `--cnx-bg-sidebar` | `#1F3D3D` | Sidebar full-height |
| `--cnx-bg-sidebar-hover` | `rgba(255,255,255,0.06)` | Sidebar link hover |
| `--cnx-bg-sidebar-active` | `rgba(95,202,211,0.16)` | Sidebar link attivo |
| `--cnx-accent` | `#5FCAD3` | CTA primaria, focus ring, attivo |
| `--cnx-accent-hover` | `#4FB8C2` | Hover CTA primaria |
| `--cnx-accent-soft` | `#E8F7F8` | Background badge accent, dropzone |
| `--cnx-accent-ink` | `#0F2D31` | Testo su accent (contrasto AA) |
| `--cnx-text-primary` | `#0F1A1F` | H1-H3, label form |
| `--cnx-text-secondary` | `#4A5560` | Body text |
| `--cnx-text-muted` | `#8893A0` | Caption, helper, breadcrumb inattivo |
| `--cnx-text-on-sidebar` | `#FFFFFF` | Link sidebar attivo, profilo |
| `--cnx-text-on-sidebar-muted` | `rgba(255,255,255,0.62)` | Link sidebar inattivo, sezioni |
| `--cnx-border` | `#E4E8EC` | Border card, input, table row |
| `--cnx-border-strong` | `#CFD6DC` | Border focus non-accent, divider forte |
| `--cnx-success` | `#22C55E` | Badge success, status ok |
| `--cnx-warning` | `#F59E0B` | Badge warning |
| `--cnx-danger` | `#EF4444` | Badge error, delete |
| `--cnx-info` | `#5FCAD3` | Badge info (= accent) |

### Colors — Dark theme `[data-theme="dark"]`

| Token | Hex | Note |
|---|---|---|
| `--cnx-bg-app` | `#0E1A1D` | Content bg |
| `--cnx-bg-surface` | `#15262A` | Card |
| `--cnx-bg-subtle` | `#1B2F33` | Input, hover row |
| `--cnx-bg-sidebar` | `#0A1517` | Sidebar (più scura del content) |
| `--cnx-accent` | `#5FCAD3` | invariato — accent identico |
| `--cnx-accent-soft` | `rgba(95,202,211,0.12)` | tinta scura |
| `--cnx-text-primary` | `#E6EEF0` | |
| `--cnx-text-secondary` | `#A8B5BB` | |
| `--cnx-text-muted` | `#6B7A82` | |
| `--cnx-border` | `#22363B` | |
| `--cnx-border-strong` | `#2E464C` | |

Sidebar mantiene lo stesso aspetto in entrambi i temi (è già dark by design); cambia solo il content.

### Typography scale

- Font stack: `"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif` (Inter via `@font-face` self-hosted).
- Scale (preserva `--text-*` esistenti, aggiunge):
  - `--cnx-text-display`: 32px / 40px / 700 — solo greeting dashboard
  - `--cnx-text-h1`: 24px / 32px / 600 — page title
  - `--cnx-text-h2`: 18px / 26px / 600 — card title
  - `--cnx-text-h3`: 15px / 22px / 600 — sub-section
  - `--cnx-text-body`: 14px / 22px / 400
  - `--cnx-text-small`: 13px / 20px / 400
  - `--cnx-text-caption`: 12px / 16px / 500 — uppercase tracking 0.06em per section labels sidebar
- Letter-spacing: `-0.01em` su display/h1, normale altrove.

### Spacing & radii

- Scale conferma 8px base: `--space-1`..`--space-24` esistenti.
- Aggiungi `--cnx-space-card-padding: 24px` e `--cnx-space-section-gap: 32px`.
- Radii:
  - `--cnx-radius-sm: 6px` (badge, tag)
  - `--cnx-radius-md: 10px` (input squared, dropdown)
  - `--cnx-radius-lg: 16px` (card, table wrapper)
  - `--cnx-radius-xl: 20px` (modal)
  - `--cnx-radius-pill: 9999px` (button, search input, filter pill)

### Shadows

- `--cnx-shadow-sm: 0 1px 2px rgba(15,26,31,0.04)` — input default
- `--cnx-shadow-md: 0 4px 12px rgba(15,26,31,0.06)` — card resting
- `--cnx-shadow-lg: 0 12px 24px rgba(15,26,31,0.08)` — modal, dropdown open
- `--cnx-shadow-focus: 0 0 0 3px rgba(95,202,211,0.32)` — focus ring accent

Niente shadow > y-12 o alpha > 0.08.

---

## Shell (per shell-engineer)

### Sidebar
- Background `--cnx-bg-sidebar` (#1F3D3D) full-height, width 260px (preserva `--sidebar-width`).
- Logo area in alto: mark sparkle in mint + wordmark "NEXIO" (rimuovi tagline).
- Sezioni: `AREA OPERATIVA`, `GESTIONE`, `AMMINISTRAZIONE`, `ACCOUNT` come header `--cnx-text-caption` uppercase tracking 0.06em color `--cnx-text-on-sidebar-muted`, padding-top 24px primo elemento di sezione.
- Link: padding 10px 16px, radius `--cnx-radius-md`, gap 12px tra icona e label, hover `--cnx-bg-sidebar-hover`, attivo `--cnx-bg-sidebar-active` con border-left 3px `--cnx-accent` e icon/text in `--cnx-accent`.
- Icone: line icons monocrome 18px, currentColor.
- Profile card in basso: avatar circolare 36px (iniziali su bg accent-soft), nome in `--cnx-text-on-sidebar`, ruolo in `--cnx-text-on-sidebar-muted` 12px, padding 16px, border-top `rgba(255,255,255,0.08)`.
- **NO PHP business logic touched** — solo classi e markup di presentazione in `includes/sidebar.php`.

### Header
- Height 64px, bg `--cnx-bg-surface`, border-bottom `--cnx-border` (1px), padding 0 32px.
- Layout 3 colonne: `[selettore azienda + breadcrumb] | [search bar pill centrata] | [theme toggle + icone secondarie + avatar]`.
- Search bar: `<input>` in pill rounded-full, bg `--cnx-bg-subtle`, height 40px, max-width 480px, icona search 16px sinistra, placeholder "Cerca file, persone, ticket…", focus → `--cnx-shadow-focus`.
- Theme toggle: button squared 36px radius `--cnx-radius-md`, icona luna (light mode) / sole (dark mode), bg subtle, attiva via `data-theme` su `<html>`, persisti scelta in `localStorage`.
- Avatar 32px in pill cliccabile (apre dropdown profilo).

---

## Components (per component-engineer)

| Componente | Spec essenziale | Classe |
|---|---|---|
| **Button primary** | pill rounded-full, bg `--cnx-accent`, text `--cnx-accent-ink`, height 40px, padding 0 20px, font-weight 600, hover `--cnx-accent-hover`, focus `--cnx-shadow-focus` | `.cnx-btn .cnx-btn--primary` |
| **Button secondary** | pill outlined, bg transparent, border 1px `--cnx-border-strong`, text `--cnx-text-primary`, hover bg `--cnx-bg-subtle` | `.cnx-btn .cnx-btn--secondary` |
| **Button ghost** | pill, bg transparent, no border, text `--cnx-text-secondary`, hover bg `--cnx-bg-subtle` | `.cnx-btn .cnx-btn--ghost` |
| **Input text** | radius `--cnx-radius-md` (10px) — non pill, height 40px, bg `--cnx-bg-surface`, border 1px `--cnx-border`, focus border `--cnx-accent` + `--cnx-shadow-focus`, padding 0 14px | `.cnx-input` |
| **Search input** | variante pill (rounded-full) dell'input, con icona search prefix | `.cnx-input--search` |
| **Filter pill** | pill rounded-full bg `--cnx-bg-subtle`, height 36px, padding 0 16px, icona chevron suffix, attivo bg `--cnx-accent-soft` text `--cnx-accent-ink` | `.cnx-filter-pill` |
| **Card** | bg `--cnx-bg-surface`, radius `--cnx-radius-lg` (16px), shadow `--cnx-shadow-md`, padding `--cnx-space-card-padding` (24px), border 1px `--cnx-border` opzionale | `.cnx-card` |
| **Modal** | overlay `rgba(15,26,31,0.48)`, dialog bg `--cnx-bg-surface` radius `--cnx-radius-xl` (20px), shadow `--cnx-shadow-lg`, max-width 560px, padding 32px, header con title h2 + close icon ghost | `.cnx-modal` `.cnx-modal__dialog` |
| **Dropzone** | usata in modal upload: border 2px dashed `--cnx-accent`, bg `--cnx-accent-soft`, radius `--cnx-radius-lg`, padding 48px, icona upload + testo "Trascina file qui o clicca per sfogliare" | `.cnx-dropzone` |
| **Table** | `.cnx-table` wrapper card, header row bg `--cnx-bg-subtle` text uppercase 12px tracking 0.04em color `--cnx-text-muted`, body row border-bottom `--cnx-border` (1px), padding cella 14px 16px, NO border verticali, hover row bg `--cnx-bg-subtle`, checkbox a sinistra | `.cnx-table` |
| **Badge** | inline-flex, height 22px, padding 0 10px, radius `--cnx-radius-sm` (6px), text 12px/600, bg + text contestuali (success/warning/danger/info via accent-soft + accent-ink) | `.cnx-badge .cnx-badge--{variant}` |
| **Toast** | bottom-right, card 320px, radius `--cnx-radius-lg`, shadow `--cnx-shadow-lg`, border-left 3px colore variant, auto-dismiss 4s, transition fade+slide ≤ 200ms | `.cnx-toast` |
| **Breadcrumb** | inline flex, separator `/` color `--cnx-text-muted`, link `--cnx-text-secondary` hover accent, ultimo segmento `--cnx-text-primary` 600 | `.cnx-breadcrumb` |
| **Page header** | flex: title h1 + opzionale subtitle/breadcrumb a sinistra, actions a destra (CTA primary + secondary), padding-bottom 24px, border-bottom opzionale | `.cnx-page-header` |
| **Section label** | uppercase tracking 0.06em 12px/600 color `--cnx-text-muted`, margin-bottom 12px (= sezioni sidebar e gruppi form) | `.cnx-section-label` |

Tutte le animazioni: `transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1)`. **Mai > 250ms.**

---

## Pages pilota (per page-migrator)

### `dashboard.php`
- Layout: page-header con greeting `<h1 class="cnx-display">Ciao, {nome}!</h1>` + sub `<p class="cnx-text-muted">{data_corrente_it}</p>`.
- Grid 3 colonne (`grid-template-columns: repeat(3, 1fr)`, gap 24px) con card "To-do" (Task assegnati), "Documents" (Documenti recenti), "Timesheet" (I miei turni / Ora corrente).
- Seconda riga: grid 2 colonne — "Attività Recente" + "Calendario mini" come reference.
- **Preserva integralmente i loop PHP esistenti** (query widget, contatori). Solo wrapper markup e classi cambiano.

### `files.php`
- Page-header: breadcrumb (`Home / File Manager / {cartella}`) + h1 "File Manager" + actions row destra: CTA primary `+ Aggiungi` (apre modal Add file/folder) + secondary `Carica cartella`, `Cartella tenant`.
- Toolbar row: search input pill (full-width sinistra, max-width 480px) + filter pill row (`Filtra`, `Ordina`, eventuali tag attivi) + view toggle grid/list a destra.
- Table `.cnx-table` con colonne attuali (checkbox, Nome con icona folder/file, Proprietario, Assegnato a, Modificato, Dimensione, azioni `⋯`).
- **Modal "Aggiungi file o cartella"** (reference #3): tab `File` / `Cartella`; in tab File → dropzone accent + lista file in upload con progress bar pill mint; CTA primary `Carica`.
- **Preserva backend upload, validazione MIME, path tenant** (BUG-136 zero-byte check resta valido).

---

## Anti-patterns (NON fare)

- **Niente gradients** — nessun `linear-gradient` su bg, button, header. Colori piatti.
- **Niente glass morphism** — `backdrop-filter: blur` consentito SOLO su overlay modal, mai su card/header/sidebar.
- **Niente shadow profondi** — max `y: 12px`, alpha max `0.08`. Mai `0 20px 40px`.
- **Niente radius eccessivi su card** — card sempre 16px, modal 20px. Pill (9999px) **solo** su button, input search, filter pill, badge. **Mai** su card o sezioni intere.
- **Niente colori vivaci hardcoded** — vietati `#FF…`, `#…orange`, ecc. nel CSS componenti. Sempre via token `--cnx-*`.
- **Niente animation > 250ms** — transition default 180ms, easing `cubic-bezier(0.4,0,0.2,1)`. Niente bounce, niente elastic.
- **Niente icone colorate piene** — solo line icons monocrome currentColor.
- **Niente double-shadow + border** sullo stesso elemento — scegli uno dei due.
- **Niente font weight < 400** o > 700 — scale 400/500/600/700 è completa.
- **Niente uppercase su body text** — uppercase solo su section label e table header (caption 12px tracking 0.06em).
- **Niente mix di radius** sullo stesso elemento (es. button con `border-radius: 8px 24px 8px 24px`).
- **Niente decoration sotto link** — link sempre `text-decoration: none`, hover via colore o underline mirato.

---

**Fine direction.** Prossimo step: design-system-architect implementa i token in `assets/css/_tokens.css` e `[data-theme="dark"]` overrides.
