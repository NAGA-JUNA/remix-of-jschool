<?php
require_once __DIR__.'/../includes/auth.php';
checkMaintenance();
$db = getDB();
$schoolName = getSetting('school_name', 'JNV School');
$schoolTagline = getSetting('school_tagline', 'Nurturing Talent, Shaping Future');
$schoolEmail = getSetting('school_email', '');
$schoolPhone = getSetting('school_phone', '');
$schoolAddress = getSetting('school_address', '');
$whatsappNumber = getSetting('whatsapp_api_number', '');
$navLogo = getSetting('school_logo', '');
$logoVersion = getSetting('logo_updated_at', '0');
$logoPath = '';
if ($navLogo) { $logoPath = (strpos($navLogo, '/uploads/') === 0) ? $navLogo : (file_exists(__DIR__.'/../uploads/branding/'.$navLogo) ? '/uploads/branding/'.$navLogo : '/uploads/logo/'.$navLogo); $logoPath .= '?v=' . $logoVersion; }

// Social links
$socialFacebook = getSetting('social_facebook', '');
$socialTwitter = getSetting('social_twitter', '');
$socialInstagram = getSetting('social_instagram', '');
$socialYoutube = getSetting('social_youtube', '');
$socialLinkedin = getSetting('social_linkedin', '');

// Bell notifications
$bellNotifs = $db->query("SELECT title, type, created_at FROM notifications WHERE status='approved' AND is_public=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
$notifCount = $db->query("SELECT COUNT(*) FROM notifications WHERE status='approved' AND is_public=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Get event
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /public/events.php'); exit; }

$stmt = $db->prepare("SELECT * FROM events WHERE id=? AND is_public=1 AND status IN ('active','completed')");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: /public/events.php');
    exit;
}

// Increment view count
$db->prepare("UPDATE events SET views = views + 1 WHERE id = ?")->execute([$id]);

// Related events (same type, upcoming, exclude current)
$relStmt = $db->prepare("SELECT * FROM events WHERE type=? AND id!=? AND is_public=1 AND status='active' AND start_date >= CURDATE() ORDER BY start_date ASC LIMIT 3");
$relStmt->execute([$event['type'], $id]);
$relatedEvents = $relStmt->fetchAll();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$isToday = $event['start_date'] === $today;
$isTomorrow = $event['start_date'] === $tomorrow;
$isPast = $event['start_date'] < $today;

$typeColors = ['sports'=>'#22c55e','cultural'=>'#a855f7','exam'=>'#f59e0b','holiday'=>'#ef4444','activity'=>'#3b82f6','academic'=>'#06b6d4','meeting'=>'#64748b','other'=>'#94a3b8'];

$shareUrl = urlencode("https://{$_SERVER['HTTP_HOST']}/public/event-view.php?id={$event['id']}");
$shareText = urlencode("📅 {$event['title']} — " . date('d M Y', strtotime($event['start_date'])) . ($event['location'] ? " at {$event['location']}" : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($event['title']) ?> — <?= e($schoolName) ?></title>
    <meta name="description" content="<?= e(mb_strimwidth($event['description'] ?? $event['title'], 0, 155, '...')) ?>">
    <?php $favicon = getSetting('school_favicon', ''); if ($favicon): $favVer = getSetting('favicon_updated_at', '0'); $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }
        .event-hero { border-radius: 16px; overflow: hidden; margin-bottom: 1.5rem; }
        .event-hero img { width: 100%; max-height: 400px; object-fit: cover; }
        .event-hero .no-poster { height: 250px; background: linear-gradient(135deg, #1e40af, #3b82f6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 5rem; }
        .detail-card { border: none; border-radius: 14px; box-shadow: 0 2px 15px rgba(0,0,0,0.06); }
        .info-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; }
        .type-badge { font-size: 0.75rem; padding: 4px 12px; border-radius: 50px; font-weight: 600; }
        .related-card { border: none; border-radius: 12px; transition: transform 0.2s; text-decoration: none; color: inherit; }
        .related-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); color: inherit; }
        .wa-share-lg { display: inline-flex; align-items: center; gap: 6px; background: #25D366; color: #fff; border: none; border-radius: 50px; padding: 8px 20px; font-size: 0.9rem; font-weight: 600; text-decoration: none; transition: background 0.2s; }
        .wa-share-lg:hover { background: #1da851; color: #fff; }
        .view-count { color: #94a3b8; font-size: 0.8rem; }

        .site-footer { background: #1a1a2e; color: #fff; }
        .footer-cta { background: #0f2557; padding: 4rem 0; text-align: center; }
        .footer-cta h2 { font-weight: 700; font-size: 2.2rem; color: #fff; margin-bottom: 1rem; }
        .footer-cta p { color: rgba(255,255,255,0.7); max-width: 600px; margin: 0 auto 1.5rem; }
        .footer-heading { text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; color: #fff; }
        .footer-heading::after { content: ''; position: absolute; bottom: 0; left: 0; width: 30px; height: 2px; background: var(--theme-primary, #1e40af); }
        .footer-link { color: rgba(255,255,255,0.65); text-decoration: none; transition: color 0.2s; font-size: 0.9rem; }
        .footer-link:hover { color: #fff; }
        .footer-social a { width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.3); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; font-size: 0.9rem; }
        .footer-social a:hover { background: var(--theme-primary, #1e40af); border-color: var(--theme-primary, #1e40af); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); }
        .whatsapp-float { position: fixed; bottom: 24px; right: 24px; z-index: 9999; width: 60px; height: 60px; border-radius: 50%; background: #25D366; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; text-decoration: none; box-shadow: 0 4px 20px rgba(37,211,102,0.4); transition: transform 0.3s; }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }

        @media (max-width: 767.98px) {
            .event-hero img { max-height: 250px; }
            .event-hero .no-poster { height: 180px; font-size: 3rem; }
            .site-footer .row > div { text-align: center; }
            .footer-heading::after { left: 50%; transform: translateX(-50%); }
            .footer-social { justify-content: center; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'events'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="/public/events.php" class="text-decoration-none">Events</a></li>
            <li class="breadcrumb-item active"><?= e(mb_strimwidth($event['title'], 0, 40, '...')) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Poster -->
            <div class="event-hero">
                <?php if ($event['poster']): ?>
                <img src="/<?= e($event['poster']) ?>" alt="<?= e($event['title']) ?>">
                <?php else: ?>
                <div class="no-poster"><i class="bi bi-calendar-event"></i></div>
                <?php endif; ?>
            </div>

            <!-- Title & Badges -->
            <div class="mb-3">
                <span class="type-badge" style="background:<?= $typeColors[$event['type']] ?? '#94a3b8' ?>;color:#fff;"><?= ucfirst(e($event['type'])) ?></span>
                <?php if ($isToday): ?><span class="badge bg-success ms-1">🔴 Today</span><?php endif; ?>
                <?php if ($isTomorrow): ?><span class="badge bg-info ms-1">Tomorrow</span><?php endif; ?>
                <?php if ($isPast): ?><span class="badge bg-secondary ms-1">Completed</span><?php endif; ?>
                <?php if ($event['is_featured']): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill me-1"></i>Featured</span><?php endif; ?>
            </div>

            <h2 class="fw-bold mb-3"><?= e($event['title']) ?></h2>

            <!-- Description -->
            <?php if ($event['description']): ?>
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-text-paragraph me-1"></i>About This Event</h6>
                    <div style="line-height:1.8;color:#374151;"><?= nl2br(e($event['description'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Share & Views -->
            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" class="wa-share-lg"><i class="bi bi-whatsapp"></i>Share on WhatsApp</a>
                <span class="view-count"><i class="bi bi-eye me-1"></i><?= number_format($event['views'] + 1) ?> views</span>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Event Details Card -->
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i>Event Details</h6>

                    <div class="info-item">
                        <div class="info-icon bg-primary-subtle text-primary"><i class="bi bi-calendar3"></i></div>
                        <div>
                            <small class="text-muted d-block">Date</small>
                            <strong><?= date('l, d F Y', strtotime($event['start_date'])) ?></strong>
                            <?php if ($event['end_date'] && $event['end_date'] !== $event['start_date']): ?>
                            <br><small class="text-muted">to <?= date('l, d F Y', strtotime($event['end_date'])) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($event['start_time']): ?>
                    <div class="info-item">
                        <div class="info-icon bg-success-subtle text-success"><i class="bi bi-clock"></i></div>
                        <div>
                            <small class="text-muted d-block">Time</small>
                            <strong><?= date('h:i A', strtotime($event['start_time'])) ?></strong>
                            <?php if ($event['end_time']): ?> <span class="text-muted">to</span> <strong><?= date('h:i A', strtotime($event['end_time'])) ?></strong><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($event['location']): ?>
                    <div class="info-item">
                        <div class="info-icon bg-danger-subtle text-danger"><i class="bi bi-geo-alt"></i></div>
                        <div>
                            <small class="text-muted d-block">Location</small>
                            <strong><?= e($event['location']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <div class="info-icon bg-warning-subtle text-warning"><i class="bi bi-tag"></i></div>
                        <div>
                            <small class="text-muted d-block">Category</small>
                            <strong><?= ucfirst(e($event['type'])) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Events -->
            <?php if (!empty($relatedEvents)): ?>
            <div class="card detail-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-calendar2-event me-1"></i>Related Events</h6>
                    <?php foreach ($relatedEvents as $rel): ?>
                    <a href="/public/event-view.php?id=<?= $rel['id'] ?>" class="related-card card mb-2 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="fw-semibold" style="font-size:0.85rem;"><?= e($rel['title']) ?></div>
                            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($rel['start_date'])) ?></small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
</body>
</html>