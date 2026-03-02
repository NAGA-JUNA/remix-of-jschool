

# Testimonials / Reviews Section

Build a complete testimonials system: database table, admin CRUD page with approval workflow, feature access toggle, sidebar link, and a public display section on the About page.

---

## 1. Database Table

Create `testimonials` table in the MySQL database:

```sql
CREATE TABLE testimonials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Parent',
    message TEXT NOT NULL,
    rating TINYINT UNSIGNED DEFAULT 5,
    photo VARCHAR(255) DEFAULT NULL,
    is_approved TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Add this to `php-backend/schema.sql` and run it on the server.

---

## 2. Admin Page: `php-backend/admin/testimonials.php`

Full CRUD admin page following existing patterns (like certificates.php / gallery.php):

- **Add Testimonial Form**: Name, Role (dropdown: Parent / Student / Alumni / Teacher / Other), Message (textarea), Rating (1-5 star selector), Photo upload (optional, compressed to webp)
- **Testimonials List**: Card grid showing all testimonials with status badges (Pending / Approved / Rejected)
- **Actions**: Approve, Reject, Edit, Delete with CSRF protection
- **Photo handling**: Upload to `uploads/photos/`, compress to webp using existing image compression pattern
- **Audit logging**: All actions logged via `auditLog()`
- **Flash messages**: Success/error feedback

---

## 3. Sidebar Link in `php-backend/includes/header.php`

Add a "Testimonials" nav item under the **Website** nav group (after "Quote Highlight"), guarded by feature access:

```php
<?php if (isSuperAdmin() || getSetting('feature_testimonials', '1') === '1'): ?>
<div class="nav-item">
    <a href="/admin/testimonials.php" class="nav-link" data-bs-title="Testimonials">
        <i class="bi bi-chat-quote"></i> <span>Testimonials</span>
    </a>
</div>
<?php endif; ?>
```

---

## 4. Feature Access Toggle in `php-backend/admin/settings.php`

Add `feature_testimonials` to the feature access control list so super admins can enable/disable the module:

- Add to the `$features` array in the POST handler (line 286)
- Add to the `$featureList` display array (line 1314) with icon `bi-chat-quote-fill` and description "Parent & student testimonials"

---

## 5. Public Display on About Page (`php-backend/public/about.php`)

Add a testimonials carousel/grid section before the Inspirational Quote Banner (line 309):

- Query approved testimonials: `SELECT * FROM testimonials WHERE is_approved=1 ORDER BY created_at DESC LIMIT 6`
- Display as a responsive card grid with:
  - Photo (or initials fallback)
  - Name and role
  - Star rating (filled/empty stars)
  - Message text (truncated if long)
- Scroll-reveal animation matching existing page style
- Only renders if there are approved testimonials

---

## Files Changed Summary

| File | Change |
|------|--------|
| `php-backend/schema.sql` | Add `testimonials` table definition |
| `php-backend/admin/testimonials.php` | **New file** -- full admin CRUD page |
| `php-backend/includes/header.php` | Add sidebar nav link under Website group |
| `php-backend/admin/settings.php` | Add `feature_testimonials` to feature access control |
| `php-backend/public/about.php` | Add public testimonials section |

