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

$loggedIn = isLoggedIn();
$userId = currentUserId();

// Get notification
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /public/notifications.php'); exit; }

$stmt = $db->prepare("SELECT * FROM notifications WHERE id=? AND status='approved' AND is_public=1 AND is_deleted=0 AND (schedule_at IS NULL OR schedule_at <= NOW()) AND (expires_at IS NULL OR expires_at >= CURDATE())");
$stmt->execute([$id]);
$notif = $stmt->fetch();

if (!$notif) {
    header('Location: /public/notifications.php');
    exit;
}

// Increment view count
$db->prepare("UPDATE notifications SET view_count = view_count + 1 WHERE id=?")->execute([$id]);

// Mark as read for logged-in users
if ($loggedIn && $userId) {
    try {
        $db->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)")->execute([$id, $userId]);
    } catch (Exception $e) {}
}

// Get attachments
$attStmt = $db->prepare("SELECT * FROM notification_attachments WHERE notification_id=? ORDER BY created_at ASC");
$attStmt->execute([$id]);
$attachments = $attStmt->fetchAll();

// Related notifications (same type, exclude current, recent)
$relStmt = $db->prepare("SELECT id, title, type, priority, created_at FROM notifications WHERE type=? AND id!=? AND status='approved' AND is_public=1 AND is_deleted=0 AND (schedule_at IS NULL OR schedule_at <= NOW()) AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY created_at DESC LIMIT 3");
$relStmt->execute([$notif['type'], $id]);
$relatedNotifs = $relStmt->fetchAll();

$typeColors = ['urgent'=>'danger','exam'=>'warning','academic'=>'info','event'=>'success','holiday'=>'purple','general'=>'secondary'];
$color = $typeColors[$notif['type']] ?? 'secondary';
$prColor = ['normal'=>'secondary','important'=>'warning','urgent'=>'danger'];

$shareUrl = urlencode("https://{$_SERVER['HTTP_HOST']}/public/notification-view.php?id={$notif['id']}");
$shareText = urlencode("📢 {$notif['title']} — " . date('d M Y', strtotime($notif['created_at'])));

// File type icon helper
function getFileIcon($type) {
    if (!$type) return 'bi-file-earmark';
    if (str_contains($type, 'pdf')) return 'bi-file-earmark-pdf-fill';
    if (str_contains($type, 'image')) return 'bi-file-earmark-image-fill';
    if (str_contains($type, 'word') || str_contains($type, 'document')) return 'bi-file-earmark-word-fill';
    if (str_contains($type, 'sheet') || str_contains($type, 'excel')) return 'bi-file-earmark-excel-fill';
    return 'bi-file-earmark-fill';
}
function getFileIconColor($type) {
    if (!$type) return '#6b7280';
    if (str_contains($type, 'pdf')) return '#ef4444';
    if (str_contains($type, 'image')) return '#3b82f6';
    if (str_contains($type, 'word')) return '#2563eb';
    if (str_contains($type, 'sheet') || str_contains($type, 'excel')) return '#16a34a';
    return '#6b7280';
}
function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($notif['title']) ?> — <?= e($schoolName) ?></title>
    <meta name="description" content="<?= e(mb_strimwidth($notif['content'] ?? $notif['title'], 0, 155, '...')) ?>">
    <?php $favicon = getSetting('school_favicon', ''); if ($favicon): $favVer = getSetting('favicon_updated_at', '0'); $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }
        .notif-hero { background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%); color: #fff; padding: 2rem 0; }
        .detail-card { border: none; border-radius: 14px; box-shadow: 0 2px 15px rgba(0,0,0,0.06); }
        .info-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; }
        .type-badge { font-size: 0.75rem; padding: 4px 12px; border-radius: 50px; font-weight: 600; }
        .attachment-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: 10px; background: #f8fafc; margin-bottom: 8px; transition: background 0.2s; }
        .attachment-item:hover { background: #f1f5f9; }
        .attachment-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: #fff; flex-shrink: 0; }
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
            .notif-hero { padding: 1.5rem 0; }
            .notif-hero h2 { font-size: 1.3rem; }
            .site-footer .row > div { text-align: center; }
            .footer-heading::after { left: 50%; transform: translateX(-50%); }
            .footer-social { justify-content: center; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'notifications'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero -->
<div class="notif-hero">
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0" style="font-size:0.85rem;">
                <li class="breadcrumb-item"><a href="/" class="text-white-50 text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="/public/notifications.php" class="text-white-50 text-decoration-none">Notifications</a></li>
                <li class="breadcrumb-item active text-white"><?= e(mb_strimwidth($notif['title'], 0, 40, '...')) ?></li>
            </ol>
        </nav>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="badge bg-<?= $color ?> type-badge"><?= ucfirst(e($notif['type'])) ?></span>
            <?php if (($notif['priority'] ?? 'normal') !== 'normal'): ?>
                <span class="badge bg-<?= $prColor[$notif['priority']] ?? 'secondary' ?> type-badge"><?= ucfirst(e($notif['priority'])) ?></span>
            <?php endif; ?>
            <?php if ($notif['is_pinned'] ?? 0): ?>
                <span class="badge bg-warning text-dark type-badge"><i class="bi bi-pin-fill me-1"></i>Pinned</span>
            <?php endif; ?>
        </div>
        <h2 class="fw-bold mb-2"><?= e($notif['title']) ?></h2>
        <div class="d-flex flex-wrap gap-3 opacity-75" style="font-size:0.9rem;">
            <span><i class="bi bi-calendar3 me-1"></i><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></span>
            <span><i class="bi bi-eye me-1"></i><?= number_format(($notif['view_count'] ?? 0) + 1) ?> views</span>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Content Card -->
            <?php if ($notif['content']): ?>
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-text-paragraph me-2"></i>About This Notification</h6>
                    <div style="line-height:1.8;color:#374151;white-space:pre-wrap;"><?= nl2br(e($notif['content'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Legacy single attachment -->
            <?php if ($notif['attachment'] ?? ''): ?>
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-paperclip me-2"></i>Attachment</h6>
                    <div class="attachment-item">
                        <div class="d-flex align-items: center; gap-3">
                            <div class="attachment-icon"><i class="bi bi-file-earmark-fill" style="color:#6b7280"></i></div>
                            <div>
                                <div class="fw-semibold" style="font-size:0.9rem;"><?= e($notif['attachment']) ?></div>
                            </div>
                        </div>
                        <a href="/uploads/documents/<?= e($notif['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Download</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Multi Attachments -->
            <?php if (!empty($attachments)): ?>
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-paperclip me-2"></i>Attachments (<?= count($attachments) ?> file<?= count($attachments) > 1 ? 's' : '' ?>)</h6>
                    <?php foreach ($attachments as $att): ?>
                    <div class="attachment-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="attachment-icon">
                                <i class="bi <?= getFileIcon($att['file_type']) ?>" style="color:<?= getFileIconColor($att['file_type']) ?>"></i>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:0.9rem;"><?= e($att['file_name']) ?></div>
                                <small class="text-muted"><?= formatFileSize($att['file_size']) ?> · <?= e($att['file_type'] ?? 'File') ?></small>
                            </div>
                        </div>
                        <a href="/<?= e($att['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Download</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Share & Views -->
            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" class="wa-share-lg"><i class="bi bi-whatsapp"></i>Share on WhatsApp</a>
                <span class="view-count"><i class="bi bi-eye me-1"></i><?= number_format(($notif['view_count'] ?? 0) + 1) ?> views</span>
            </div>

            <!-- Back link -->
            <a href="/public/notifications.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Notifications</a>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Details Card -->
            <div class="card detail-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i>Notification Details</h6>

                    <div class="info-item">
                        <div class="info-icon bg-primary-subtle text-primary"><i class="bi bi-tag"></i></div>
                        <div>
                            <small class="text-muted d-block">Type</small>
                            <strong><?= ucfirst(e($notif['type'])) ?></strong>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <small class="text-muted d-block">Priority</small>
                            <strong><?= ucfirst(e($notif['priority'] ?? 'Normal')) ?></strong>
                        </div>
                    </div>

                    <?php if ($notif['category'] ?? ''): ?>
                    <div class="info-item">
                        <div class="info-icon bg-info-subtle text-info"><i class="bi bi-folder"></i></div>
                        <div>
                            <small class="text-muted d-block">Category</small>
                            <strong><?= ucfirst(e($notif['category'])) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($notif['tags'] ?? ''): ?>
                    <div class="info-item">
                        <div class="info-icon bg-success-subtle text-success"><i class="bi bi-tags"></i></div>
                        <div>
                            <small class="text-muted d-block">Tags</small>
                            <strong><?= e($notif['tags']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <div class="info-icon bg-warning-subtle text-warning"><i class="bi bi-people"></i></div>
                        <div>
                            <small class="text-muted d-block">Target Audience</small>
                            <strong><?= ucfirst(e($notif['target_audience'] ?? 'All')) ?></strong>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon bg-secondary-subtle text-secondary"><i class="bi bi-calendar3"></i></div>
                        <div>
                            <small class="text-muted d-block">Posted</small>
                            <strong><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></strong>
                        </div>
                    </div>

                    <?php if ($notif['expires_at'] ?? ''): ?>
                    <div class="info-item">
                        <div class="info-icon bg-danger-subtle text-danger"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <small class="text-muted d-block">Expires</small>
                            <strong><?= date('d M Y', strtotime($notif['expires_at'])) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Notifications -->
            <?php if (!empty($relatedNotifs)): ?>
            <div class="card detail-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-bell me-1"></i>Related Notifications</h6>
                    <?php foreach ($relatedNotifs as $rel): ?>
                    <a href="/public/notification-view.php?id=<?= $rel['id'] ?>" class="related-card card mb-2 shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-<?= $typeColors[$rel['type']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= ucfirst(e($rel['type'])) ?></span>
                                <?php if (($rel['priority'] ?? 'normal') !== 'normal'): ?>
                                <span class="badge bg-<?= $prColor[$rel['priority']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= ucfirst(e($rel['priority'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="fw-semibold" style="font-size:0.85rem;"><?= e($rel['title']) ?></div>
                            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($rel['created_at'])) ?></small>
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
