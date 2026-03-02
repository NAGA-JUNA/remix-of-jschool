# JNV School Management System — PHP + MySQL (cPanel Ready)

## 📋 Overview
A complete school management system built with **pure PHP 8+** and **MySQL**. No Node.js, no React, no terminal commands needed. Upload directly to cPanel shared hosting.

**Domain:** `jnvschool.awayindia.com`

---

## 🚀 Deployment Guide (cPanel)

### Step 1: Create Database
1. Log in to **cPanel** → **MySQL® Databases**
2. Create a new database: `yshszsos_jnvschool`
3. Create a database user: `yshszsos_Admin`
4. Add the user to the database with **ALL PRIVILEGES**
5. Note: If the database already exists, skip to Step 2

### Step 2: Import Schema
1. Go to **cPanel** → **phpMyAdmin**
2. Select your database (`yshszsos_jnvschool`)
3. Click the **Import** tab
4. Click **Choose File** → select `schema.sql`
5. Click **Go** to import
6. ✅ This creates all 12 tables + default admin user + school settings

> **⚠️ WARNING:** If re-importing on an existing database, it will **DROP** existing data. Back up first!

### Step 3: Upload Files
1. Go to **cPanel** → **File Manager** → `public_html`
2. **Delete** any existing files (or move to a backup folder)
3. Upload **ALL** files and folders from the `php-backend/` directory
4. Your `public_html` structure should look like:

```
public_html/
├── .htaccess              ← Security rules
├── index.php              ← Public homepage with slider
├── login.php              ← Login page
├── logout.php             ← Logout handler
├── forgot-password.php    ← Password reset request
├── reset-password.php     ← Password reset form
├── config/
│   ├── db.php             ← Database credentials
│   └── mail.php           ← SMTP email config
├── includes/
│   ├── auth.php           ← Session auth, CSRF, roles
│   ├── header.php         ← Admin/teacher layout header
│   └── footer.php         ← Layout footer
├── admin/
│   ├── dashboard.php      ← Admin dashboard with charts
│   ├── students.php       ← Student list (search/filter/paginate)
│   ├── student-form.php   ← Add/edit student with photo
│   ├── teachers.php       ← Teacher list
│   ├── teacher-form.php   ← Add/edit teacher
│   ├── admissions.php     ← Approve/reject admissions
│   ├── notifications.php  ← Approve/reject notifications
│   ├── gallery.php        ← Approve/reject gallery uploads
│   ├── events.php         ← CRUD events
│   ├── slider.php         ← Home slider management
│   ├── reports.php        ← CSV exports
│   ├── audit-logs.php     ← Searchable audit log viewer
│   └── settings.php       ← School settings + user management
├── teacher/
│   ├── dashboard.php      ← Teacher overview
│   ├── attendance.php     ← Mark attendance by class
│   ├── exams.php          ← Enter exam marks
│   ├── post-notification.php ← Submit notification
│   └── upload-gallery.php ← Upload photos/videos
├── public/
│   ├── notifications.php  ← Public notification board
│   ├── gallery.php        ← Public gallery with lightbox
│   ├── events.php         ← Upcoming events
│   └── admission-form.php ← Online admission application
└── uploads/               ← Must be created manually
    ├── photos/            ← Student photos
    ├── gallery/           ← Gallery images
    ├── slider/            ← Slider images
    └── documents/         ← Admission documents
```

### Step 4: Create Upload Directories
**CRITICAL:** You must manually create the upload directories in cPanel File Manager:

1. Navigate to `public_html/`
2. Create folder: `uploads`
3. Inside `uploads/`, create these subfolders:
   - `photos` — Student profile photos
   - `gallery` — Gallery images
   - `slider` — Homepage slider images
   - `documents` — Admission form documents

### Step 5: Set File Permissions
In **cPanel** → **File Manager**, right-click each item → **Change Permissions**:

| Path | Permission | Why |
|------|-----------|-----|
| `uploads/` | **755** | Writable for file uploads |
| `uploads/photos/` | **755** | Writable for student photos |
| `uploads/gallery/` | **755** | Writable for gallery images |
| `uploads/slider/` | **755** | Writable for slider images |
| `uploads/documents/` | **755** | Writable for admission docs |
| `config/` | **755** | Readable by PHP |
| All `.php` files | **644** | Standard PHP permissions |
| `.htaccess` | **644** | Apache config file |

### Step 6: Configure Database Connection
Open `config/db.php` and verify credentials match your cPanel MySQL setup:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yshszsos_jnvschool');
define('DB_USER', 'yshszsos_Admin');
define('DB_PASS', 'your_password_here');
```

### Step 7: Configure Email (Optional)
Open `config/mail.php` and update with your cPanel email:
```php
define('SMTP_HOST', 'mail.jnvschool.awayindia.com');
define('SMTP_USER', 'noreply@jnvschool.awayindia.com');
define('SMTP_PASS', 'your_email_password');
```

### Step 8: Test & Verify
1. Visit `https://jnvschool.awayindia.com` → Should show public homepage
2. Visit `https://jnvschool.awayindia.com/login.php` → Login page
3. Login with default credentials (see below)
4. **⚠️ Immediately change the default admin password!**

---

## 🔑 Default Login Credentials
| Field | Value |
|-------|-------|
| **Email** | `admin@school.com` |
| **Password** | `Admin@123` |
| **Role** | Super Admin |

> **⚠️ SECURITY:** Change this password immediately after first login via Admin → Settings → User Management

---

## 👥 User Roles

| Role | Access Level |
|------|-------------|
| **super_admin** | Full access: all modules + settings + user creation |
| **admin** | Full access to all modules |
| **office** | Same as admin (front-office staff) |
| **teacher** | Dashboard, notifications, gallery, attendance, exams only |

---

## 🔒 Security Features
- ✅ `password_hash()` / `password_verify()` — bcrypt password hashing
- ✅ CSRF tokens on all forms
- ✅ `session_regenerate_id(true)` on login
- ✅ `htmlspecialchars()` output escaping (XSS prevention)
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Role-based access control middleware
- ✅ `.htaccess` blocks direct access to `config/` and `includes/`
- ✅ Audit logging for all admin/teacher actions

---

## 📊 Feature Summary

### Admin Panel
- **Dashboard** — 6 KPI cards, Chart.js monthly trends (admissions + attendance), recent activity feed, quick actions
- **Students** — Full CRUD with search, class/status filters, photo upload, pagination, CSV export
- **Teachers** — Full CRUD with auto user-account creation, search, pagination
- **Admissions** — Status tabs (pending/approved/rejected/waitlisted), approve/reject actions
- **Notifications** — Approve/reject teacher submissions, delete
- **Gallery** — Approve/reject uploads, image preview, delete
- **Events** — Add/edit/delete events with date, time, location
- **Home Slider** — Add/edit/delete/reorder slides with images, badges, headings, CTAs, toggle visibility
- **Reports** — CSV export for students, teachers, admissions, attendance
- **Audit Logs** — Searchable, date-filterable, paginated log of all system actions
- **Settings** — School info, user management, create new users

### Teacher Panel
- **Dashboard** — Personal stats, recent submissions, quick actions
- **Attendance** — Select class + date, bulk mark present/absent/late
- **Exams** — Enter marks by class/exam/subject with auto-grading (A+ to F)
- **Notifications** — Submit for admin approval, view submission history
- **Gallery** — Upload images or YouTube videos for approval

### Public Website
- **Homepage** — Dynamic hero slider, stats bar, latest notifications, upcoming events, contact info
- **Notifications** — Public notification board with type badges
- **Gallery** — Filterable grid with lightbox viewer, YouTube embeds
- **Events** — Upcoming + past events with date cards
- **Admission Form** — Full online application with document upload

---

## 🔧 Troubleshooting

### "500 Internal Server Error"
- Check PHP version: requires **PHP 8.0+**
- Verify `config/db.php` has correct credentials
- Check `.htaccess` is uploaded (enable "Show Hidden Files" in File Manager)

### "Access Denied" on login
- Verify the database was imported correctly
- Check that the `users` table has the default admin record
- Try re-importing `schema.sql`

### File uploads not working
- Verify `uploads/` subdirectories exist with **755** permissions
- Check PHP `upload_max_filesize` in cPanel → **MultiPHP INI Editor** (set to at least 10M)
- Check PHP `post_max_size` (set to at least 12M)

### Pages show blank or broken layout
- Ensure ALL files were uploaded, including `includes/` folder
- Check that `config/db.php` has the right database name
- Verify the database tables were created (check phpMyAdmin)

### Email not sending
- Use cPanel email accounts for SMTP
- Verify SMTP credentials in `config/mail.php`
- Check if your hosting provider blocks port 587/465

---

## 📧 Email Setup (cPanel)

1. **Create email account** in cPanel → Email Accounts (e.g., `noreply@jnvschool.awayindia.com`)
2. Update `config/mail.php` with the credentials
3. For PHPMailer support: download PHPMailer from GitHub, place in `vendor/`, update mail config

---

## 🗄️ Database Schema (v2.0)

12 tables total:
1. `users` — Admin/teacher/office accounts
2. `students` — Student records with photos
3. `teachers` — Teacher records linked to user accounts
4. `admissions` — Online admission applications
5. `notifications` — Notifications with approval workflow
6. `gallery_items` — Gallery uploads with approval
7. `events` — School events/calendar
8. `attendance` — Daily attendance by class
9. `exam_results` — Exam marks with auto-grading
10. `audit_logs` — System action logs
11. `settings` — Key-value school settings
12. `home_slider` — Homepage slider slides

---

*Built for JNV School — jnvschool.awayindia.com*
