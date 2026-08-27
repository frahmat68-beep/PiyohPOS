# Piyoh Kopi — Design System & Brand Identity

Dokumen ini adalah acuan resmi desain antarmuka (*Design System*) untuk ekosistem **Piyoh Kopi** (**PiyohWeb** dan **PiyohPOS**), disusun berdasarkan identitas visual asli dari logo resmi (`public/Logo/PK-LOGOTYPE.png`) dan esensi brand *"Coffee - Slowbar - Ambience: Brew for joyful living."*

---

## 1. Brand Essence & Philosophy
* **Atmosphere**: Tenang, hangat (*warm*), santai (*slowbar*), alami, dan artisanal.
* **Visual Tone**: Natural minimalist aesthetic dengan sentuhan earthy forest green (matcha/olive), warm ivory cream, roasted caramel/amber, dan deep charcoal.
* **Target Experience**: Antarmuka yang bersih, elegan, tidak berisik (*minimal clutter*), berjarak lapang (*generous whitespace*), dan nyaman di mata baik untuk browsing menu santai maupun pemesanan cepat dari meja.

---

## 2. Color Tokens (CSS Variables)

```css
:root {
  /* --- Light Mode (Default) --- */
  --bg-primary: #FAF7F2;          /* Warm Ivory Linen Background */
  --bg-surface: #FFFFFF;          /* Pure White Card/Surface */
  --bg-secondary: #F3ECE1;        /* Soft Sand / Light Cream Surface */
  --bg-tertiary: #E8DEC8;         /* Subtle Warm Divider/Pill Background */

  --text-primary: #22261E;        /* Deep Charcoal Olive (High Contrast) */
  --text-secondary: #575E50;      /* Muted Earthy Green/Grey (Readable) */
  --text-muted: #889180;          /* Subdued Caption Text */
  --text-on-accent: #FFFFFF;      /* High contrast white on dark buttons */

  /* Brand Accent (Extracted directly from PK-LOGOTYPE) */
  --accent-primary: #475638;      /* Deep Matcha / Forest Olive Green */
  --accent-primary-hover: #36422A;/* Darker Olive on hover/active */
  --accent-primary-light: #EBF0E6;/* Tint for badges/subtle highlights */

  /* Secondary Accent */
  --accent-secondary: #C4823F;    /* Warm Roasted Amber / Caramel Gold */
  --accent-secondary-light: #FBF2E8;/* Soft Amber Tint */

  /* Borders & Dividers */
  --border-subtle: #EBE4D8;       /* Subtle warm border */
  --border-default: #DDD4C5;      /* Form input / card border */
  --border-focus: #475638;        /* Input focus outline */

  /* Status Colors */
  --status-success: #15803D;
  --status-success-bg: #F0FDF4;
  --status-warning: #B45309;
  --status-warning-bg: #FFFBEB;
  --status-danger: #B91C1C;
  --status-danger-bg: #FEF2F2;

  /* Shadows */
  --shadow-sm: 0 1px 2px 0 rgba(71, 86, 56, 0.05);
  --shadow-md: 0 4px 12px -2px rgba(71, 86, 56, 0.08), 0 2px 6px -2px rgba(71, 86, 56, 0.04);
  --shadow-lg: 0 12px 24px -4px rgba(71, 86, 56, 0.12), 0 4px 8px -2px rgba(71, 86, 56, 0.06);
}

[data-theme="dark"] {
  /* --- Dark Mode (Slowbar Evening Ambience) --- */
  --bg-primary: #141713;          /* Deep Night Olive */
  --bg-surface: #1B201A;          /* Dark Charcoal Sage Surface */
  --bg-secondary: #242B23;        /* Elevated Card Background */
  --bg-tertiary: #2F382E;         /* Pill/Input Dark Background */

  --text-primary: #F5F2EB;        /* Warm Cream White */
  --text-secondary: #B2BBAE;      /* Muted Pale Sage */
  --text-muted: #7E877A;          /* Dark Mode Caption */
  --text-on-accent: #FFFFFF;

  --accent-primary: #758D5F;      /* Luminous Matcha Green */
  --accent-primary-hover: #8AA472;
  --accent-primary-light: #253120;

  --accent-secondary: #D89856;    /* Glowing Amber Gold */
  --accent-secondary-light: #332617;

  --border-subtle: #2B3329;
  --border-default: #3A4437;
  --border-focus: #758D5F;

  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.4);
  --shadow-md: 0 4px 14px 0 rgba(0, 0, 0, 0.5);
  --shadow-lg: 0 12px 28px 0 rgba(0, 0, 0, 0.6);
}
```

---

## 3. Typography Hierarchy

### A. Font Families
* **Display / Headings**: `'Playfair Display', serif` (Google Fonts) — Elegan, editorial, mencerminkan keahlian seduh dan suasana santai slowbar.
* **Body & UI**: `'Plus Jakarta Sans', sans-serif` (Google Fonts) — Bersih, sangat mudah dibaca (*high legibility*), modern geometric.

### B. Type Scale
| Level | Font Family | Size (Desktop) | Size (Mobile) | Weight | Line Height |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Hero Title** | Playfair Display | `52px (3.25rem)` | `36px (2.25rem)` | 700 (Bold) | 1.15 |
| **Heading 1 (H1)** | Playfair Display | `40px (2.5rem)` | `28px (1.75rem)` | 700 (Bold) | 1.2 |
| **Heading 2 (H2)** | Playfair Display | `30px (1.875rem)` | `24px (1.5rem)` | 600 (SemiBold) | 1.25 |
| **Heading 3 (H3)** | Playfair Display | `22px (1.375rem)` | `18px (1.125rem)` | 600 (SemiBold) | 1.3 |
| **Body Large (Lead)** | Plus Jakarta Sans | `18px (1.125rem)` | `16px (1rem)` | 400 (Regular) | 1.6 |
| **Body Regular** | Plus Jakarta Sans | `15px (0.9375rem)` | `14px (0.875rem)` | 400 / 500 | 1.6 |
| **Caption / Meta** | Plus Jakarta Sans | `13px (0.8125rem)` | `12px (0.75rem)` | 500 (Medium) | 1.4 |
| **Button / CTA** | Plus Jakarta Sans | `15px (0.9375rem)` | `15px (0.9375rem)` | 600 (SemiBold) | 1 |

---

## 4. Spacing, Radius, and Layout Rules

### A. Radius Tokens
* **Buttons & Badges**: `rounded-full` (`9999px`) atau `rounded-xl` (`12px`)
* **Cards (Menu, Outlet, Feature)**: `rounded-2xl` (`16px`)
* **Input Fields & Modals**: `rounded-xl` (`12px`)
* **Hero / Feature Images**: `rounded-3xl` (`24px`)

### B. Container & Layout
* **Max Width**: `max-w-7xl` (`1280px`) untuk halaman publik luas, `max-w-xl` (`640px`) untuk alur mobile QR POS.
* **Section Padding**: `py-16 md:py-24` untuk ritme visual yang lapang.

---

## 5. UI Component Guidelines

### A. Buttons
* **Primary Button**:
  - Background: `var(--accent-primary)` (`#475638`)
  - Text: `var(--text-on-accent)` (`#FFFFFF`)
  - Hover: `var(--accent-primary-hover)` (`#36422A`) dengan `translate-y-[-1px]` dan bayangan lembut.
* **Secondary / Outline Button**:
  - Border: `1.5px solid var(--accent-primary)`
  - Background: `transparent` (atau `var(--bg-surface)`)
  - Text: `var(--accent-primary)`
  - Hover: `var(--accent-primary-light)`
* **Amber / Highlight CTA Button**:
  - Background: `var(--accent-secondary)` (`#C4823F`)
  - Text: `#FFFFFF`

### B. Product / Menu Cards
* Background putih atau cream lembut (`var(--bg-surface)`).
* Border halus (`var(--border-subtle)`), transisi halus saat hover (`translate-y-[-2px]` + bayangan `var(--shadow-md)`).
* Gambar menu berasio `4:3` atau `1:1` dengan sudut melengkung `rounded-xl`.
* Badge kategori menggunakan pill warna matcha muda (`var(--accent-primary-light)`) dengan teks olive (`var(--accent-primary)`).
* Harga ditampilkan tebal dan jelas menggunakan warna charcoal atau amber hangat.

### C. Navbar & Footer
* **Navbar**: Transparan di hero / sticky dengan latar belakang semi-transparan (*glassmorphism* hangat `rgba(250, 247, 242, 0.92)` + `backdrop-blur-md`).
* **Footer**: Elegan berlatar belakang deep night olive (`#1B201A` / `#22261E`) dengan teks cream lembut, tautan cepat, jam operasional, dan kontak WhatsApp resmi.

---

## 6. Photography & Imagery Rules
1. **Pencahayaan Alami**: Gunakan foto dengan cahaya hangat alami (*warm daylight* atau *warm ambient bistro lighting*).
2. **Rasio Konsisten**:
   - Hero Banner: `16:9` atau `21:9`
   - Outlet Ambience: `16:10`
   - Menu Item: `4:3` atau `1:1`
3. **Overlay Halus**: Saat meletakkan teks di atas gambar, gunakan gradien halus `linear-gradient(to top, rgba(20, 23, 19, 0.85), rgba(20, 23, 19, 0.2))` agar teks memiliki kontras yang sempurna dan mudah dibaca.

---
