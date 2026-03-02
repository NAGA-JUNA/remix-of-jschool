

# Fix Core Values Mobile View + Leadership Slider

## Changes (1 file: `php-backend/public/about.php`)

### 1. Core Values: Fix Mobile Layout

The current horizontal scroll approach isn't working well on mobile. Replace it with a cleaner vertical stack with smaller, more compact cards:

- Remove the `values-scroll` horizontal scroll CSS on mobile
- Instead, display as a clean single-column list with each card as a horizontal row (icon left, text right) rather than centered tall cards
- Each card becomes a compact flex row: icon (48px) + title/description side by side
- This reduces vertical space while looking polished on mobile
- Desktop/tablet layout stays unchanged (4-column / 2-column grid)

**Mobile card layout:**
```
[icon]  Excellence
        We strive for the highest standards...
```

### 2. Leadership: Convert to Slider (Same Pattern as Testimonials)

Replace the grid/compact layout with a full slider matching the Testimonials carousel:

- Reuse the exact same slider CSS pattern (`.leadership-slider`, `.leadership-slide`)
- Add prev/next arrows and dot indicators
- Auto-play with 5-second interval, pause on hover/touch
- Desktop: 3 leaders visible, Tablet: 2, Mobile: 1 at a time (swipeable)
- Each slide shows the leader photo (circle), name, and designation centered
- Add a second JS block for the leadership slider (independent from testimonials)

**CSS additions:**
- `.leadership-slider` -- same flex/scroll-snap rules as `.testimonial-slider`
- `.leadership-slide` -- same responsive widths as `.testimonial-slide`
- `.leader-slider-nav` -- arrows and dots (reuse `.slider-arrow` / `.slider-dot` styles)

**JS additions (~25 lines):**
- Duplicate the testimonial slider logic for `#leadershipSlider`
- Independent auto-play timer, arrows, and dot sync

**HTML changes:**
- Wrap leader cards in `.leadership-slider` container instead of `.row`
- Each leader becomes a `.leadership-slide` div
- Add nav controls below the slider

### Summary

| Section | Desktop | Tablet | Mobile |
|---------|---------|--------|--------|
| Core Values | 4-column grid (unchanged) | 2-column grid (unchanged) | Compact horizontal rows (icon + text) |
| Leadership | 3 visible in slider | 2 visible | 1 at a time, swipeable |

