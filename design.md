# Design System & Token Specifications: AlumniIstü (ALUMNI) — Mercury × Slash Living Architecture
> Alpine observatory at blue hour with **Navy (`#14213D`)** depths, **Cream (`#F4EDE0`)** typography, and **Coral (`#FF6B58`)** living illumination.

**Theme:** dark (Mercury Atmospheric Layout & Motion + Slash Editorial Discipline, strictly locked to AlumniIstü's Official Brand Palette)

---

## 1. Visual Philosophy & Living Motion
**AlumniIstü** blends **Mercury's** atmospheric blue-hour observance (one-step value lift cards, seamless attached left-half pill email capture `32px 0 0 32px` joined to a right-half primary action pill `0 32px 32px 0`, frosted-glass top navigation, and spacious `32px` card padding) with **living, kinetic micro-animations**:
- **Ambient Blue-Hour Aurora**: Slow-drifting Navy and Coral atmospheric light orbs behind the hero canvas (`@keyframes auroraFloat`).
- **Kinetic SVG Telemetry Path**: Self-drawing and pulsing career velocity curve (`@keyframes drawLine` & `@keyframes pulseBeacon`).
- **Infinite Partner & Cohort Marquee**: Smooth, continuous horizontal ticker showcasing top hiring enterprises (`@keyframes marqueeScroll`).
- **Staggered Scroll-Reveal & 3D Card Lift**: Cards smoothly reveal with cubic-bezier spring physics (`cubic-bezier(0.16, 1, 0.3, 1)`) and subtle interactive elevation on hover without heavy drop shadows.

---

## 2. Tokens — Colors (Locked to Navy, Cream & Coral)

| Name | Value | Token | Role |
|------|-------|-------|------|
| **Navy Canvas (Onyx Equivalent)** | `#14213D` | `--color-navy` / `--surface-onyx-canvas` | Dominant page background, hero overlay base, footer and section canvases |
| **Elevated Navy Card (Graphite Equivalent)** | `#1c2a4a` | `--color-surface` / `--surface-graphite-card` | Elevated card and section surfaces — one step lighter than `#14213D` for quiet separation |
| **Interactive Navy (Obsidian Equivalent)** | `#24355c` | `--surface-obsidian-button` | Secondary interactive button fills, hover card state, inline pill attachments |
| **Deep Recessed Navy** | `#0f1a30` | `--surface-deep-vault` | Chart backdrop, terminal telemetry well |
| **Cream Text (Ivory Equivalent)** | `#F4EDE0` | `--color-cream` / `--color-ivory-text` | Primary text, headings, icons, nav items, ghost-button outlines |
| **Ash Cream Text** | `rgba(244, 237, 224, 0.70)` | `--color-ash-text` | Muted body copy, helper text, secondary labels, placeholder text |
| **Mist Cream Border** | `rgba(244, 237, 224, 0.22)` | `--color-mist-border` | Light hairline borders, ghost-button strokes, attached input edges |
| **Slate Hairline Border** | `rgba(244, 237, 224, 0.12)` | `--color-slate-border` | Subtle structural dividers between cards and table rows |
| **Coral Action (Cobalt Equivalent)** | `#FF6B58` | `--color-coral` / `--color-accent` | Sole chromatic action color for filled primary CTA buttons, active states, and living telemetry beacons |

---

## 3. Tokens — Typography

### Display & Headings (`--font-ivy-presto` / `--font-arcadiadisplay`)
- **Family:** `'Ivy Presto'`, `'Playfair Display'`, `Georgia`, `serif`
- **Weights:** `400`, `500` (Mercury intermediate weight feel — confident, never overly heavy)
- **Sizes:** `28px`, `32px`, `42px`, `52px`, `65px`
- **Line height:** `1.10`–`1.20`
- **Letter spacing:** `0.01em` to `0.02em`

### UI & Body (`--font-inter` / `--font-arcadia`)
- **Family:** `'Inter'`, `-apple-system`, `BlinkMacSystemFont`, `sans-serif`
- **Weights:** `300`, `400`, `500`, `600`
- **Sizes:** `12px` (`caption`), `14px` (`body-sm`), `16px` (`body`, line-height `1.5`), `18px` (`body-lg`), `21px` (`subheading`)

---

## 4. Tokens — Spacing, Radii & Layout

- **Page Max-Width:** `1200px` (`--page-max-width`)
- **Section Vertical Rhythm:** `96px` to `128px` (`--section-gap`)
- **Card Padding:** `32px` (`--card-padding`)
- **Border Radius:**
  - `cards`: `12px` (`--radius-cards`)
  - `buttons` & `inputs`: `32px` / `9999px` (`--radius-buttons`, `--radius-inputs`)
  - `nav` & `tags`: `40px` / `9999px` (`--radius-nav`, `--radius-tags`)
  - `attached-input-left`: `32px 0 0 32px`
  - `attached-button-right`: `0 32px 32px 0`

---

## 5. CSS Custom Properties (`:root`)

```css
:root {
  /* Locked AlumniIstü Brand Colors */
  --color-navy: #14213D;
  --color-cream: #F4EDE0;
  --color-coral: #FF6B58;
  --color-canvas: #14213D;
  --color-surface: #1c2a4a;
  --color-text-main: #F4EDE0;
  --color-accent: #FF6B58;

  /* Mercury Surface Mapping (Navy Blue-Hour Palette) */
  --surface-onyx-canvas: #14213D;
  --surface-graphite-card: #1c2a4a;
  --surface-obsidian-button: #24355c;
  --surface-deep-vault: #0f1a30;
  --color-slate-border: rgba(244, 237, 224, 0.12);
  --color-mist-border: rgba(244, 237, 224, 0.28);
  --color-ash-text: rgba(244, 237, 224, 0.70);
  --color-ivory-text: #F4EDE0;

  /* Typography */
  --font-ivy-presto: 'Ivy Presto', 'Playfair Display', Georgia, serif;
  --font-inter: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;

  /* Layout & Radii */
  --page-max-width: 1200px;
  --section-gap: 112px;
  --card-padding: 32px;
  --radius-cards: 12px;
  --radius-inputs: 32px;
  --radius-buttons: 32px;
  --radius-nav: 40px;
  --radius-tags: 40px;
  --radius-full: 9999px;
}
```
