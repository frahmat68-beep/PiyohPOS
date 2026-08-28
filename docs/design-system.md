# Piyoh Kopi Design System & Typography Scale

Dokumen ini merupakan panduan resmi standar desain, tipografi, warna, dan responsivitas untuk **PiyohWeb** dan **PiyohPOS**.

---

## 1. Skala Tipografi Resmi (Typography Scale)

| Elemen | Mobile (< 768px) | Tablet (768px - 1024px) | Desktop (>= 1024px) | Font Family | Font Weight | Line Height |
|---|---|---|---|---|---|---|
| **H1 (Hero / Page Title)** | **40px** (`text-4xl`) | **56px** (`text-5xl`) | **64px – 72px** (`text-6xl` / `text-7xl`) | *Playfair Display* | Bold (700) | Tight (1.05 - 1.1) |
| **H2 (Section Heading)** | **28px** (`text-2xl`/`text-3xl`) | **32px** (`text-3xl`) | **40px – 48px** (`text-4xl` / `text-5xl`) | *Playfair Display* | Bold (700) | Snug (1.2) |
| **H3 (Card / Subsection)** | **20px** (`text-xl`) | **22px** (`text-xl`) | **24px** (`text-2xl`) | *Playfair Display* | SemiBold (600) | Normal (1.3) |
| **Body Text (Utama)** | **16px** (`text-base`) | **16px** (`text-base`) | **16px – 18px** (`text-base` / `text-lg`) | *Plus Jakarta Sans* | Light (300) / Regular (400) | Relaxed (1.6) |
| **Body Text (Secondary)** | **16px** (`text-base`) | **16px** (`text-base`) | **16px** (`text-base`) | *Plus Jakarta Sans* | Regular (400) | Normal (1.5) |
| **Caption / Small / Label** | **13px – 14px** (`text-xs`/`text-sm`) | **13px – 14px** (`text-xs`/`text-sm`) | **14px** (`text-sm`) | *Plus Jakarta Sans* | Medium (500) / SemiBold (600) | Normal (1.4) |

> [!IMPORTANT]
> **Standar Keterbacaan**: Body text **TIDAK BOLEH** berada di bawah **16px** di semua breakpoint. Caption dan label tombol minimal **13px**.

---

## 2. Palet Warna Resmi (Brand Colors)

```css
:root {
    /* Backgrounds */
    --bg-primary: #FAF7F2;      /* Warm Ivory Linen */
    --bg-surface: #FFFFFF;      /* Clean White Card */
    --bg-secondary: #F3ECE1;    /* Warm Sand */
    --bg-night: #161A14;        /* Dark Espresso Charcoal */

    /* Brand Accents */
    --accent-primary: #475638;        /* Deep Forest Olive */
    --accent-primary-hover: #36422A;  /* Darker Olive */
    --accent-primary-light: #EBF0E6;  /* Soft Tint Olive */
    --accent-secondary: #C4823F;      /* Warm Roasted Amber */
    --accent-secondary-light: #FBF2E8;/* Soft Tint Amber */

    /* Typography */
    --text-primary: #22261E;    /* Deep Charcoal Charcoal */
    --text-secondary: #575E50;  /* Olive Muted Slate */
    --text-muted: #889180;      /* Gentle Soft Slate */

    /* Borders */
    --border-subtle: #EBE4D8;
    --border-default: #DDD4C5;
}
```

---

## 3. Standar Interaksi & Touch Target

1. **Touch Target**: Minimal **44x44px** untuk seluruh tombol, tautan CTA, quantity stepper, dan dropdown selector pada layar mobile dan tablet.
2. **Animasi Easing**: Easing tenang `cubic-bezier(0.16, 1, 0.3, 1)` dengan durasi 300ms – 500ms (slowbar calming mood).
3. **Card Micro-Interactions**: Hover elevation `transform: translateY(-2px)` dengan `box-shadow` lembut 300ms.
4. **Layout Grid Responsif**:
   - Mobile: 1-2 kolom terstruktur
   - Tablet: 2-3 kolom
   - Desktop (1440px): 3-4 kolom
   - Desktop Lebar (1920px+): Max container `1600px` dengan padding seimbang `px-6 lg:px-10 2xl:px-16`.
