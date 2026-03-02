

# Expanded Brand Colors System — Full Control for Public + Admin Pages

## Current State
- **3 brand colors** (Primary, Secondary, Accent) — auto-extracted from logo or manually set
- **1 theme color** (primary_color) — applied to public navbar/buttons via preset swatches
- **All hardcoded**: Public navbar uses `#0f172a`, footer uses `#1a1a2e`, `#0f2557`, sidebar uses `#faf8f5` / `#1a1a1a` — none of these are configurable

## What Will Change

### New Color Groups in Settings (Appearance Tab)

We will add **3 new collapsible color sections** below the existing Brand Colors card, organized by area:

**A. Public Site Colors (6 new settings)**

| Setting Key | Label | Default | What It Controls |
|---|---|---|---|
| `color_navbar_bg` | Navbar Background | `#0f172a` | `.premium-navbar` background |
| `color_navbar_text` | Navbar Link Color | `rgba(255,255,255,0.75)` | `.pn-nav-link` color |
| `color_topbar_bg` | Top Bar Background | `#060a12` | `.pn-top-bar` background |
| `color_footer_bg` | Footer Background | `#1a1a2e` | `.site-footer` background |
| `color_footer_cta_bg` | Footer CTA Start | `#0f2557` | `.footer-cta` gradient start |
| `color_footer_cta_end` | Footer CTA End | `#1a3a7a` | `.footer-cta` gradient end |

**B. Admin Backend Colors (4 new settings)**

| Setting Key | Label | Default Light | Default Dark | What It Controls |
|---|---|---|---|---|
| `color_sidebar_bg` | Sidebar Background | `#faf8f5` | `#1a1a1a` | `--sidebar-bg` |
| `color_sidebar_bg_dark` | Sidebar BG (Dark) | `#1a1a1a` | — | Dark mode sidebar |
| `color_body_bg` | Body Background | `#f4f2ee` | `#111111` | `--bg-body` |
| `color_body_bg_dark` | Body BG (Dark) | `#111111` | — | Dark mode body |

**C. Preset Color Schemes (Quick Apply)**

A row of 5-6 pre-built schemes the user can click to instantly fill all colors:

| Scheme | Navbar | Footer | CTA | Sidebar |
|---|---|---|---|---|
| Classic Navy | `#0f172a` | `#1a1a2e` | `#0f2557` | `#faf8f5` |
| Ocean Blue | `#1e3a5f` | `#0c2340` | `#1a4a7a` | `#f0f7ff` |
| Forest Green | `#1a3c34` | `#0d2818` | `#14532d` | `#f0fdf4` |
| Royal Purple | `#2d1b69` | `#1a1040` | `#3b1f8e` | `#faf5ff` |
| Warm Earth | `#3d2b1f` | `#2c1810` | `#5c3d2e` | `#fef7ed` |
| Minimal Light | `#ffffff` | `#f8fafc` | `#1e40af` | `#ffffff` |

Clicking a scheme populates all color pickers. User can then fine-tune individual colors before saving.

### Files Changed

**1. `php-backend/admin/settings.php`**
- Add `page_colors_manual` form handler (lines ~147) — validates and saves all 10 new color keys
- Add new UI section in the Appearance tab (after Brand Colors card, ~line 795) with:
  - Preset Schemes row (clickable cards)
  - Public Site Colors group (6 color pickers with hex inputs)
  - Admin Backend Colors group (4 color pickers — light + dark pairs)
  - Live mini-preview showing navbar strip, footer strip, and sidebar swatch
  - JavaScript to sync pickers, apply presets, and update preview in real-time
  - "Reset to Defaults" button

**2. `php-backend/includes/header.php`**
- Read `color_sidebar_bg`, `color_sidebar_bg_dark`, `color_body_bg`, `color_body_bg_dark` from settings at the top (~line 10)
- Replace hardcoded values in `:root` CSS variables (line 95-96: `--bg-body`, `--sidebar-bg`) and dark mode block (lines 123, 134) with the dynamic values

**3. `php-backend/includes/public-navbar.php`**
- Read `color_navbar_bg`, `color_navbar_text`, `color_topbar_bg` from settings at the top
- Replace hardcoded colors in CSS:
  - Line 113: `.pn-top-bar` background `#060a12` -> dynamic
  - Line 123: `.premium-navbar` background `rgba(15,23,42,0.92)` -> dynamic with alpha
  - Line 131: `.premium-navbar.scrolled` -> dynamic with higher alpha
  - Line 158: `.pn-nav-link` color -> dynamic
  - Line 164: `.pn-nav-link::after` gradient -> use brand primary/secondary
  - Line 150: `.pn-logo-fallback` gradient -> brand primary/secondary
  - Line 182: `.pn-dropdown-menu` background -> dynamic with alpha
  - Line 201: `.pn-dropdown-item:hover` -> dynamic highlight

**4. `php-backend/includes/public-footer.php`**
- Read `color_footer_bg`, `color_footer_cta_bg`, `color_footer_cta_end` from settings at top
- Replace hardcoded colors in CSS:
  - Line 24: `.footer-cta` gradient -> dynamic start/end
  - Line 59: `.site-footer` background -> dynamic
  - Line 105: `.footer-heading::after` gradient -> brand primary/secondary
  - Line 126: `.footer-contact-item i` color -> brand primary
  - Line 138: `.footer-social a:hover` background -> brand primary

### How It Works
- All new settings use `getSetting('color_*', '<default>')` so existing installs won't break
- Color validation uses the same `preg_match('/^#[0-9a-fA-F]{6}$/')` pattern already used for brand colors
- No database schema changes — uses existing `settings` key-value table
- Preset schemes fill the form via JavaScript (no server call needed), user still clicks Save
- The live preview updates instantly via JavaScript `oninput` events on color pickers

