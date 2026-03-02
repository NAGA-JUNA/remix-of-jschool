

# Testimonials Slider + Mobile-Friendly Core Values & Leadership

## Overview
Three improvements to the About page in a single file (`php-backend/public/about.php`):

1. Convert the Testimonials grid into a touch-friendly auto-playing carousel/slider
2. Make Core Values section scroll horizontally on mobile instead of stacking vertically
3. Make Leadership section more compact and polished on mobile

---

## 1. Testimonials: Grid to Slider/Carousel

Replace the current `row g-4` grid with a CSS-only horizontal slider (no extra JS library needed).

**How it works:**
- A horizontally scrollable container with `scroll-snap-type: x mandatory`
- Each card is a fixed-width snap point (`scroll-snap-align: start`)
- On desktop: shows 3 cards side by side (same as current)
- On tablet: shows 2 cards
- On mobile: shows 1 card at a time, swipeable left/right
- Auto-play via a small JS `setInterval` that scrolls to the next card every 4 seconds, pausing on hover/touch
- Left/right arrow buttons for manual navigation
- Dot indicators showing current position

**CSS additions:**
- `.testimonial-slider` -- horizontal scroll container with hidden scrollbar
- `.testimonial-slide` -- each card with snap alignment
- `.slider-arrows` -- prev/next buttons
- `.slider-dots` -- active dot indicator

**JS additions (~30 lines):**
- Auto-scroll timer with pause on hover
- Arrow click handlers
- Dot sync on scroll

---

## 2. Core Values: Horizontal Scroll on Mobile

Currently 4 value cards stack into a 2x2 grid on mobile, taking up a lot of vertical space.

**Change:**
- On screens below 576px, switch to a horizontal scrollable row
- Each value card becomes a compact fixed-width card (250px)
- Users swipe left/right to see all 4 values
- Add a subtle scroll hint gradient on the right edge
- On tablet and desktop, keep the current grid layout unchanged

**CSS additions:**
- `@media (max-width: 575.98px)` rules for `.values-scroll` container with `overflow-x: auto`, `flex-wrap: nowrap`
- Each `.value-card` gets `min-width: 250px` on mobile

---

## 3. Leadership: Compact Mobile Layout

Currently leadership photos are 200x200px circles stacked vertically on mobile -- very tall.

**Changes on mobile (below 576px):**
- Reduce photo size from 200px to 120px
- Switch from single-column stack to a 2-column grid (`col-6`) on mobile
- Reduce spacing between cards
- On tablet and desktop, keep the current layout unchanged

**CSS additions:**
- `@media (max-width: 575.98px)` rules for smaller photos and tighter spacing
- Leadership cards get `col-6` on mobile for a 2-up grid

---

## Technical Summary

| Section | Desktop | Tablet | Mobile |
|---------|---------|--------|--------|
| Testimonials | 3 visible in slider | 2 visible | 1 at a time, swipeable |
| Core Values | 4-column grid | 2-column grid | Horizontal scroll row |
| Leadership | 3-column grid | 2-column grid | 2-column grid, smaller photos |

**File changed:** `php-backend/public/about.php` only

- Add ~40 lines of CSS for slider, mobile scroll, and leadership sizing
- Add ~30 lines of JS for slider auto-play and arrow/dot navigation
- Modify the HTML structure of all three sections (testimonials wrapper, values row classes, leadership column classes)

