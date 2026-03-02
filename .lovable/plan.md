

# Fix: Remove Duplicate Logo and "Apply Now" in Mobile Drawer

## Problems
1. The school logo shows at the top of the mobile drawer, duplicating the main navbar logo visible behind it.
2. The "Apply Now" CTA appears twice on mobile: once inside the drawer footer and once as a persistent fixed bottom bar.

## Changes (1 file: `php-backend/includes/public-navbar.php`)

### Edit 1: Simplify drawer header — remove logo, show school name only
Replace the logo image in the drawer header (lines 437-443) with just the school name text. This avoids the visual repetition while still branding the drawer.

```php
<div class="pn-drawer-header">
    <span class="text-white fw-bold"><?= e($schoolName) ?></span>
    <button class="pn-drawer-close" ...>...</button>
</div>
```

### Edit 2: Remove CTA from inside the drawer (lines 485-489)
Delete the drawer footer CTA block. The fixed bottom "Apply Now" bar (lines 492-497) already handles this on mobile, so having it inside the drawer is redundant.

## Result
- Drawer shows a clean text header (school name) instead of a repeated logo
- "Apply Now" appears only once as the fixed bottom bar, not duplicated inside the drawer
- No impact on desktop layout
