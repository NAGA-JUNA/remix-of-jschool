
# Core Values Mobile: Centered Vertical Card Layout

## Change
Update the mobile CSS (below 576px) in `php-backend/public/about.php` to display each Core Values card as a centered vertical stack instead of horizontal row:

```
      [Icon]
    Excellence
We strive for the highest...
```

## Implementation

**CSS changes only** (lines ~205-222 in the mobile media query):

- Change `.values-scroll .value-card` from `flex-direction: row` to `flex-direction: column` with `align-items: center` and `text-align: center`
- Remove the `border-left` colored accents (they don't suit centered layout) -- optionally add a subtle bottom border or keep the shadow only
- Center the icon (`margin: 0 auto`)
- Adjust title and description to be centered
- Keep comfortable padding and card shadow/rounded corners

**No HTML changes needed. Desktop layout stays unchanged.**

## Result
Each mobile card becomes a clean centered column: icon on top, bold title below, description underneath -- matching the user's requested layout.
