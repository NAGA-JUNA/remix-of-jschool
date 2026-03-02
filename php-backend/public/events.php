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

// Auto-complete past events
$db->exec("UPDATE events SET status='completed' WHERE start_date < CURDATE() AND status='active'");

// Filters
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$tab = $_GET['tab'] ?? 'upcoming';

// Build queries
$baseWhere = "WHERE is_public=1 AND status IN ('active','completed')";
$filterWhere = "";
$filterParams = [];
if ($search) { $filterWhere .= " AND title LIKE ?"; $filterParams[] = "%$search%"; }
if ($filterType) { $filterWhere .= " AND type=?"; $filterParams[] = $filterType; }

// Featured upcoming events
$featuredStmt = $db->prepare("SELECT * FROM events WHERE is_public=1 AND is_featured=1 AND status='active' AND start_date >= CURDATE() ORDER BY start_date ASC LIMIT 3");
$featuredStmt->execute();
$featuredEvents = $featuredStmt->fetchAll();

// Upcoming events
$upStmt = $db->prepare("SELECT * FROM events $baseWhere AND start_date >= CURDATE() $filterWhere ORDER BY start_date ASC LIMIT 50");
$upStmt->execute($filterParams);
$upcomingEvents = $upStmt->fetchAll();

// Past events
$pastStmt = $db->prepare("SELECT * FROM events $baseWhere AND start_date < CURDATE() $filterWhere ORDER BY start_date DESC LIMIT 12");
$pastStmt->execute($filterParams);
$pastEvents = $pastStmt->fetchAll();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Events — <?= e($schoolName) ?></title>
    <?php $favicon = getSetting('school_favicon', ''); if ($favicon): $favVer = getSetting('favicon_updated_at', '0'); $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }
        .hero-banner { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #06b6d4 100%); color: #fff; padding: 3.5rem 0; position: relative; overflow: hidden; }
        .hero-banner::before { content: ''; position: absolute; top: -50%; right: -20%; width: 500px; height: 500px; border-radius: 50%; background: rgba(255,255,255,0.05); }
        .hero-banner::after { content: ''; position: absolute; bottom: -30%; left: -10%; width: 400px; height: 400px; border-radius: 50%; background: rgba(255,255,255,0.03); }

        /* Featured Event Cards */
        .featured-card { border: none; border-radius: 16px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; text-decoration: none; color: inherit; }
        .featured-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.12); color: inherit; }
        .featured-card .poster { height: 200px; object-fit: cover; width: 100%; }
        .featured-card .no-poster { height: 200px; background: linear-gradient(135deg, #1e40af, #3b82f6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 3rem; }

        /* Event Cards */
        .event-card { border: none; border-radius: 14px; transition: transform 0.2s, box-shadow 0.2s; border-left: 4px solid #3b82f6; }
        .event-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        .event-card.past { opacity: 0.7; border-left-color: #94a3b8; }
        .date-box { width: 60px; height: 60px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
        .type-badge { font-size: 0.7rem; padding: 3px 10px; border-radius: 50px; font-weight: 600; }
        .today-badge { background: #22c55e; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 50px; font-weight: 700; }
        .tomorrow-badge { background: #3b82f6; color: #fff; font-size: 0.65rem; padding: 2px 8px; border-radius: 50px; font-weight: 700; }

        .filter-bar { background: #fff; border-radius: 12px; padding: 0.75rem 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .tab-btn { border: none; padding: 0.5rem 1.2rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; background: transparent; color: #64748b; }
        .tab-btn.active { background: #1e40af; color: #fff; }
        .tab-btn:hover:not(.active) { background: #f1f5f9; }

        .wa-share { display: inline-flex; align-items: center; gap: 4px; background: #25D366; color: #fff; border: none; border-radius: 50px; padding: 4px 12px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; }
        .wa-share:hover { background: #1da851; color: #fff; }

        /* Dark Footer */
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

        .whatsapp-float { position: fixed; bottom: 24px; right: 24px; z-index: 9999; width: 60px; height: 60px; border-radius: 50%; background: #25D366; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; text-decoration: none; box-shadow: 0 4px 20px rgba(37,211,102,0.4); transition: transform 0.3s; animation: whatsappPulse 2s infinite; }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }
        @keyframes whatsappPulse { 0%, 100% { box-shadow: 0 4px 20px rgba(37,211,102,0.4); } 50% { box-shadow: 0 4px 30px rgba(37,211,102,0.7); } }

        @media (max-width: 767.98px) {
            .hero-banner { padding: 2.5rem 0; }
            .hero-banner h1 { font-size: 1.4rem; }
            .date-box { width: 48px; height: 48px; }
            .featured-card .poster { height: 160px; }
            .filter-bar { flex-direction: column; }
            .site-footer .row > div { text-align: center; }
            .footer-heading::after { left: 50%; transform: translateX(-50%); }
            .footer-social { justify-content: center; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'events'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero -->
<div class="hero-banner">
    <div class="container position-relative" style="z-index:1;">
        <h1 class="fw-bold mb-2"><i class="bi bi-calendar-event-fill me-2"></i><?= e(getSetting('events_hero_title', 'Events & Activities')) ?></h1>
        <p class="mb-0 opacity-75"><?= e(getSetting('events_hero_subtitle', 'Stay updated with upcoming and past events at ' . $schoolName)) ?></p>
    </div>
</div>

<div class="container py-4">

    <!-- Filter Bar -->
    <div class="filter-bar d-flex flex-wrap align-items-center gap-2 mb-4">
        <div class="d-flex gap-1">
            <a href="?tab=upcoming<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterType ? '&type=' . urlencode($filterType) : '' ?>" class="tab-btn <?= $tab === 'upcoming' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-1"></i>Upcoming</a>
            <a href="?tab=past<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterType ? '&type=' . urlencode($filterType) : '' ?>" class="tab-btn <?= $tab === 'past' ? 'active' : '' ?>"><i class="bi bi-clock-history me-1"></i>Past</a>
        </div>
        <form class="d-flex gap-2 ms-auto flex-wrap" method="GET">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input type="text" name="search" class="form-control form-control-sm" style="width:200px;" placeholder="Search events..." value="<?= e($search) ?>">
            <select name="type" class="form-select form-select-sm" style="width:140px;">
                <option value="">All Types</option>
                <?php foreach (['sports','cultural','exam','holiday','activity','academic','meeting','other'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
            <?php if ($search || $filterType): ?>
            <a href="?tab=<?= e($tab) ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($tab === 'upcoming'): ?>

        <?php
        $typeColors = ['sports'=>'#22c55e','cultural'=>'#a855f7','exam'=>'#f59e0b','holiday'=>'#ef4444','activity'=>'#3b82f6','academic'=>'#06b6d4','meeting'=>'#64748b','other'=>'#94a3b8'];
        ?>

        <!-- Featured Events -->
        <?php if (!empty($featuredEvents) && !$search && !$filterType): ?>
        <h5 class="fw-bold mb-3"><i class="bi bi-star-fill text-warning me-2"></i>Featured Events</h5>
        <div class="row g-3 mb-4">
            <?php foreach ($featuredEvents as $fev): ?>
            <div class="col-md-4">
                <a href="/public/event-view.php?id=<?= $fev['id'] ?>" class="featured-card card">
                    <?php if ($fev['poster']): ?>
                    <img src="/<?= e($fev['poster']) ?>" class="poster" alt="<?= e($fev['title']) ?>">
                    <?php else: ?>
                    <div class="no-poster"><i class="bi bi-calendar-event"></i></div>
                    <?php endif; ?>
                    <div class="card-body">
                        <span class="type-badge mb-2 d-inline-block" style="background:<?= $typeColors[$fev['type']] ?? '#94a3b8' ?>;color:#fff;"><?= ucfirst(e($fev['type'])) ?></span>
                        <?php if ($fev['start_date'] === $today): ?><span class="today-badge ms-1">Today</span><?php endif; ?>
                        <?php if ($fev['start_date'] === $tomorrow): ?><span class="tomorrow-badge ms-1">Tomorrow</span><?php endif; ?>
                        <h6 class="fw-bold mb-1"><?= e($fev['title']) ?></h6>
                        <small class="text-muted">
                            <i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($fev['start_date'])) ?>
                            <?php if ($fev['end_date'] && $fev['end_date'] !== $fev['start_date']): ?> – <?= date('d M Y', strtotime($fev['end_date'])) ?><?php endif; ?>
                        </small>
                        <?php if ($fev['location']): ?><br><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($fev['location']) ?></small><?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Upcoming Events List -->
        <h5 class="fw-bold mb-3"><i class="bi bi-calendar-check me-2 text-success"></i>Upcoming Events</h5>
        <?php if (empty($upcomingEvents)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x display-4 text-muted"></i>
                <p class="text-muted mt-2">No upcoming events scheduled.</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcomingEvents as $ev):
                $isToday = $ev['start_date'] === $today;
                $isTomorrow = $ev['start_date'] === $tomorrow;
                $shareUrl = urlencode("https://{$_SERVER['HTTP_HOST']}/public/event-view.php?id={$ev['id']}");
                $shareText = urlencode("📅 {$ev['title']} — " . date('d M Y', strtotime($ev['start_date'])) . ($ev['location'] ? " at {$ev['location']}" : ''));
            ?>
            <div class="card event-card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex gap-3">
                        <?php if ($ev['poster']): ?>
                        <a href="/public/event-view.php?id=<?= $ev['id'] ?>" class="flex-shrink-0">
                            <img src="/<?= e($ev['poster']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;">
                        </a>
                        <?php else: ?>
                        <div class="date-box bg-primary-subtle text-primary flex-shrink-0">
                            <span style="font-size:1.3rem;line-height:1;"><?= date('d', strtotime($ev['start_date'])) ?></span>
                            <span style="font-size:0.6rem;text-transform:uppercase;"><?= date('M', strtotime($ev['start_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-1">
                                <div>
                                    <a href="/public/event-view.php?id=<?= $ev['id'] ?>" class="text-decoration-none">
                                        <h6 class="fw-bold mb-1 text-dark"><?= e($ev['title']) ?></h6>
                                    </a>
                                    <span class="type-badge" style="background:<?= $typeColors[$ev['type']] ?? '#94a3b8' ?>;color:#fff;"><?= ucfirst(e($ev['type'])) ?></span>
                                    <?php if ($isToday): ?><span class="today-badge ms-1">🔴 Today</span><?php endif; ?>
                                    <?php if ($isTomorrow): ?><span class="tomorrow-badge ms-1">Tomorrow</span><?php endif; ?>
                                </div>
                                <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" class="wa-share"><i class="bi bi-whatsapp"></i>Share</a>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y', strtotime($ev['start_date'])) ?>
                                <?php if ($ev['end_date'] && $ev['end_date'] !== $ev['start_date']): ?> — <?= date('d M Y', strtotime($ev['end_date'])) ?><?php endif; ?>
                                <?php if ($ev['start_time']): ?> <span class="mx-1">•</span> <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($ev['start_time'])) ?><?php if ($ev['end_time']): ?> – <?= date('h:i A', strtotime($ev['end_time'])) ?><?php endif; ?><?php endif; ?>
                                <?php if ($ev['location']): ?> <span class="mx-1">•</span> <i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?><?php endif; ?>
                            </div>
                            <?php if ($ev['description']): ?><p class="mb-0 mt-1 text-muted" style="font-size:0.88rem;"><?= e(mb_strimwidth($ev['description'], 0, 150, '...')) ?></p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>

        <!-- Past Events -->
        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-muted"></i>Past Events</h5>
        <?php if (empty($pastEvents)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x display-4 text-muted"></i>
                <p class="text-muted mt-2">No past events found.</p>
            </div>
        <?php else: ?>
            <?php
            $typeColors = ['sports'=>'#22c55e','cultural'=>'#a855f7','exam'=>'#f59e0b','holiday'=>'#ef4444','activity'=>'#3b82f6','academic'=>'#06b6d4','meeting'=>'#64748b','other'=>'#94a3b8'];
            foreach ($pastEvents as $ev):
                $shareUrl = urlencode("https://{$_SERVER['HTTP_HOST']}/public/event-view.php?id={$ev['id']}");
                $shareText = urlencode("📅 {$ev['title']} — " . date('d M Y', strtotime($ev['start_date'])));
            ?>
            <div class="card event-card past mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex gap-3">
                        <?php if ($ev['poster']): ?>
                        <a href="/public/event-view.php?id=<?= $ev['id'] ?>" class="flex-shrink-0">
                            <img src="/<?= e($ev['poster']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;filter:grayscale(30%);">
                        </a>
                        <?php else: ?>
                        <div class="date-box bg-secondary-subtle text-secondary flex-shrink-0">
                            <span style="font-size:1.3rem;line-height:1;"><?= date('d', strtotime($ev['start_date'])) ?></span>
                            <span style="font-size:0.6rem;text-transform:uppercase;"><?= date('M Y', strtotime($ev['start_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <a href="/public/event-view.php?id=<?= $ev['id'] ?>" class="text-decoration-none">
                                <h6 class="fw-bold mb-1 text-dark"><?= e($ev['title']) ?></h6>
                            </a>
                            <span class="type-badge" style="background:<?= $typeColors[$ev['type']] ?? '#94a3b8' ?>;color:#fff;"><?= ucfirst(e($ev['type'])) ?></span>
                            <span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Completed</span>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($ev['start_date'])) ?>
                                <?php if ($ev['end_date'] && $ev['end_date'] !== $ev['start_date']): ?> — <?= date('d M Y', strtotime($ev['end_date'])) ?><?php endif; ?>
                                <?php if ($ev['location']): ?> <span class="mx-1">•</span> <i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
</body>
</html>