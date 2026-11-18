# Design System & Token Specifications: AlumniIstü (ALUMNI)

## 1. Surfaces & Elevation
- **Canvas/Background**: `#14213D` (Navy)
- **Card Surfaces**: `#1c2a4a` atau `#14213D` dengan border tipis.
- **Borders & Dividers**: Garis halus transparan atau warna slate gelap. Elevasi murni mengandalkan kontras permukaan dan garis tipis, tanpa *drop shadow* berat.

## 2. Spacing & Shapes
- **Max Width**: 1216px (konten terpusat).
- **Section Gap**: 160px (jarak vertikal antar bagian).
- **Border Radius**: 
  - Tombol, input, tag, badge status: `9999px` (bentuk pil / pill-shaped)
  - Kartu (*cards*): `10px`
  - Garis bawah navigasi: `2px`

## 3. Core Components
- **Primary Action Button**: Bentuk pil (`9999px`), latar penuh **Coral (`#FF6B58`)** dengan teks putih/krem, padding 10px 20px.
- **Ghost Outline Button**: Bentuk pil, latar transparan dengan border 1px **Cream (`#F4EDE0`)**, teks Cream.
- **Email Capture / Inputs**: Bentuk pil, border 1px Cream transparan, teks *placeholder* abu-abu terang.

## 4. CSS Custom Properties
```css
:root {
  --color-navy: #14213D;
  --color-cream: #F4EDE0;
  --color-coral: #FF6B58;
  --color-canvas: #14213D;
  --color-surface: #1c2a4a;
  --color-text-main: #F4EDE0;
  --color-accent: #FF6B58;

  --font-ivy-presto: 'Ivy Presto', 'Playfair Display', serif;
  --font-inter: 'Inter', sans-serif;
  
  --radius-full: 9999px;
  --radius-lg: 10px;
  --page-max-width: 1216px;
  --section-gap: 160px;
}
```
