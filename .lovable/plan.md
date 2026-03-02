
# Add Notification Detail Page (like Event View)

## What We're Building
A new dedicated page `php-backend/public/notification-view.php` that opens when you click on a notification, similar to how `event-view.php` works for events. This gives each notification its own full page with complete content, attachments, sharing options, and related notifications.

## Changes

### 1. Create new file: `php-backend/public/notification-view.php`

A full detail page modeled after `event-view.php`, including:

- **Breadcrumb**: Home > Notifications > [Notification Title]
- **Hero/Header area**: Type badge, priority badge, pinned indicator, date, view count
- **Full content**: Complete notification text displayed in a clean card
- **Attachments section**: List all attachments (from `notification_attachments` table) with download links, showing file type icons (PDF, image, document)
- **Sidebar**: Notification details card (type, priority, category, tags, posted date, target audience)
- **Related notifications**: 3 recent notifications of the same type
- **Share button**: WhatsApp share link (matching existing pattern)
- **Mark as Read**: Auto-mark as read for logged-in users when they visit the page
- **View count**: Auto-increment on page visit

### 2. Update `php-backend/public/notifications.php` (list page)

- Make each notification title clickable, linking to `/public/notification-view.php?id=ID`
- Add a "View Details" button alongside the existing expand chevron
- The link will also pass `view_id` to increment the view count

## How It Will Look

```text
+------------------------------------------+
| Home > Notifications > [Title]           |
+------------------------------------------+
|                                          |
| [Urgent] [Academic]        [Pinned]      |
| Notification Title Here                  |
| 12 Mar 2025, 10:30 AM  |  45 views      |
|                                          |
| +--------------------------------------+ |
| | About This Notification              | |
| | Full content text displayed here...  | |
| +--------------------------------------+ |
|                                          |
| +--------------------------------------+ |  +------------------+
| | Attachments (2 files)                | |  | Details          |
| | [PDF] Circular.pdf     [Download]    | |  | Type: Academic   |
| | [IMG] Notice.jpg       [Download]    | |  | Priority: Urgent |
| +--------------------------------------+ |  | Category: Exam   |
|                                          |  | Tags: mid-term   |
| [WhatsApp Share]         234 views       |  | Posted: 12 Mar   |
|                                          |  +------------------+
|                                          |  | Related          |
|                                          |  | - Notif 1        |
|                                          |  | - Notif 2        |
|                                          |  +------------------+
+------------------------------------------+
```

## Technical Details

### New file: `php-backend/public/notification-view.php`
- Accepts `?id=N` parameter
- Queries `notifications` table for the notification (with same filters as list: approved, public, not deleted, not expired, schedule check)
- Queries `notification_attachments` table for file attachments
- Queries related notifications (same type, recent, limit 3)
- Auto-increments `view_count`
- Auto-inserts into `notification_reads` for logged-in users
- Redirects to `/public/notifications.php` if notification not found
- Uses same navbar, footer, styles as other public pages

### Modified file: `php-backend/public/notifications.php`
- Wrap notification title in an `<a>` tag linking to `notification-view.php?id=ID`
- Add a "View" button next to the expand chevron

### No database changes required
All needed tables (`notifications`, `notification_attachments`, `notification_reads`) already exist.
