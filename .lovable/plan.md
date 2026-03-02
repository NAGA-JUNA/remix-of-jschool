

# Fix: Public Footer Color Section Not Visible in Settings

## Root Cause

In `php-backend/admin/settings.php`, the "Live Preview" card (Theme Color section) has broken HTML. At line 897, only one `</div>` closes the `ms-auto` div, but several other elements remain unclosed:

```
Line 888: <div id="colorPreview">         -- NOT CLOSED
Line 890: <div class="preview-navbar">     -- NOT CLOSED  
Line 885: <div class="card-body">          -- NOT CLOSED
Line 885: <div class="card">               -- NOT CLOSED
Line 884: <div class="col-lg-6">           -- NOT CLOSED
Line 840: <div class="row g-3">            -- NOT CLOSED
```

Because these divs are never closed, the **Page Colors section** (line 899+) — which contains all the Public Site footer color pickers — gets nested inside the broken preview card. The browser tries to fix the nesting but the section becomes invisible or misplaced.

## Fix

**File: `php-backend/admin/settings.php` (line 897)**

Replace the single `</div>` at line 897 with the proper closing sequence:

```php
            </div>
          </div>
        </div>
      </div></div>
    </div>
  </div>
</div>
```

This properly closes (in order):
1. `.ms-auto` div
2. `.preview-navbar` div
3. `#colorPreview` div
4. `.card-body` + `.card` divs
5. `.col-lg-6` div
6. `.row.g-3` div (the Appearance tab's row)

After this fix, the Page Colors section with its 6 Public Site color pickers (Navbar BG, Navbar Text, Top Bar BG, **Footer Background**, **Footer CTA Start**, **Footer CTA End**) and 4 Admin Backend pickers will render correctly as a separate card below the Theme Color section.

## Files Changed

| File | Change |
|---|---|
| `php-backend/admin/settings.php` | Fix missing closing `</div>` tags at line 897 to properly close the Live Preview card before the Page Colors section |

