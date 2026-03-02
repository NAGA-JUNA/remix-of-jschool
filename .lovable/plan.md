

# Add Clickable Links to Admin Bell Dropdown Items

## What Changes
Make each notification item in the admin bell dropdown clickable, navigating to the relevant admin page.

## File: `php-backend/includes/header.php`

### Edit 1: Notifications item (lines 1421-1427)
Wrap the `notif-item` div in an anchor tag linking to `/admin/notifications.php`:
```php
<a href="/admin/notifications.php" style="text-decoration:none;color:inherit;">
  <div class="notif-item"> ... </div>
</a>
```

### Edit 2: Admissions item (lines 1430-1436)
Wrap the `notif-item` div in an anchor tag linking to `/admin/admissions.php`:
```php
<a href="/admin/admissions.php" style="text-decoration:none;color:inherit;">
  <div class="notif-item"> ... </div>
</a>
```

### Edit 3: Recruitment item (lines 1439-1445)
Wrap the `notif-item` div in an anchor tag linking to `/admin/teacher-applications.php` (the "Review now" link already points here, this just makes the whole row clickable):
```php
<a href="/admin/teacher-applications.php" style="text-decoration:none;color:inherit;">
  <div class="notif-item"> ... </div>
</a>
```

### Edit 4: Add hover style for clickable items
Add a CSS rule so items highlight on hover:
```css
.notif-dropdown a .notif-item:hover {
    background: var(--sidebar-hover, rgba(0,0,0,0.04));
}
```

Three small edits, no structural changes. Each dropdown item becomes a full clickable link to its admin page.

