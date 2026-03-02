

# Fix: Duplicate "Notifications" in Mobile Menu

## Problem
The mobile drawer shows "Notifications" twice:
1. Once from the regular nav menu items loop (without badge)
2. Once from a separate hardcoded bell notification link (with badge count)

## Fix (1 file)

**File: `php-backend/includes/public-navbar.php`**

### Edit 1: Add badge to the regular Notifications link in the drawer loop (lines 466-471)

Update the drawer link rendering for regular (non-parent) items to detect when the item is "Notifications" and append the notification count badge:

```php
<a class="pn-drawer-link ..." href="...">
    <i class="bi ..."></i>
    <?= e($item['label']) ?>
    <?php if ($item['url'] === '/public/notifications.php' && $notifCount > 0): ?>
        <span class="badge bg-danger rounded-pill ms-auto"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
    <?php endif; ?>
</a>
```

### Edit 2: Remove the duplicate bell notification link (lines 475-483)

Delete the entire block that adds a second "Notifications" link to the drawer:

```php
// REMOVE THIS:
<?php if ($_navShowBell === '1'): ?>
<a class="pn-drawer-link" href="/public/notifications.php">
    <i class="bi bi-bell-fill"></i>
    Notifications
    <?php if ($notifCount > 0): ?>
        <span class="badge bg-danger rounded-pill ms-auto">...</span>
    <?php endif; ?>
</a>
<?php endif; ?>
```

## Result
- Mobile menu shows "Notifications" only once
- The single entry still displays the red badge with unread count
- Desktop nav and bell icon modal are unaffected
