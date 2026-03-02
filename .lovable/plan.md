

# Fix Core Values Mobile Layout

## Problem
The current mobile layout uses a flex-row (icon left, text right) approach that looks cramped and misaligned. The cards lack visual distinction and proper spacing.

## Solution
Redesign the mobile Core Values cards to be cleaner, more polished single-column cards with:

**Card Design (mobile only, below 576px):**
- Each card gets a colored left border (4px solid, matching value color: warning/danger/primary/success)
- White background card with subtle shadow and rounded corners
- Horizontal layout: icon on left (48px circle with colored background), title + description on the right
- Title in bold, description in muted smaller text below
- Comfortable padding (1rem 1.25rem) and gap between cards (1rem)
- Remove the centered text/icon stacking -- keep the horizontal row but fix spacing

**Key CSS Changes (in `@media (max-width: 575.98px)` block):**
- Add `border-left: 4px solid` to each `.value-card` with color inherited from the value's theme color
- Fix icon alignment to vertically center with the title
- Increase gap between icon and text content to 1rem
- Set proper line-height on description text
- Ensure card doesn't have excessive vertical padding
- Add a subtle `border-radius: 12px` and `box-shadow: 0 2px 8px rgba(0,0,0,0.06)`

**HTML Change:**
- Add a `data-color` or inline border-left style to each value card so the left border matches its color (warning=amber, danger=red, primary=blue, success=green)

## File Changed
`php-backend/public/about.php` -- CSS rules in the mobile media query + minor HTML attribute additions for per-card border colors

## Result
Clean, professional-looking value cards on mobile matching the design reference -- colored left accent, icon beside text, proper spacing.
