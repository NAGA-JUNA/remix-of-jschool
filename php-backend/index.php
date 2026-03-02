<?php
require_once __DIR__ . '/includes/auth.php';
checkMaintenance();
$db = getDB();
$schoolName = getSetting('school_name', 'JNV School');
$schoolTagline = getSetting('school_tagline', 'Nurturing Talent, Shaping Future');
$schoolEmail = getSetting('school_email', '');
$schoolPhone = getSetting('school_phone', '');
$schoolAddress = getSetting('school_address', '');
$admissionOpen = getSetting('admission_open', '0');
$whatsappNumber = getSetting('whatsapp_api_number', '');
$primaryColor = getSetting('primary_color', '#1e40af');

// Handle enquiry form submission
$enquirySuccess = false;
$enquiryError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enquiry_submit'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $enquiryError = 'Security verification failed. Please try again.';
    } else {
        $enqName = trim($_POST['enq_name'] ?? '');
        $enqPhone = trim($_POST['enq_phone'] ?? '');
        $enqEmail = trim($_POST['enq_email'] ?? '');
        $enqMsg = trim($_POST['enq_message'] ?? '');
        if ($enqName === '' || $enqPhone === '') {
            $enquiryError = 'Name and Phone are required.';
        } elseif (strlen($enqName) > 100 || strlen($enqPhone) > 20 || strlen($enqEmail) > 255) {
            $enquiryError = 'Input too long.';
        } elseif ($enqEmail !== '' && !filter_var($enqEmail, FILTER_VALIDATE_EMAIL)) {
            $enquiryError = 'Invalid email address.';
        } else {
            $db->prepare("INSERT INTO enquiries (name, phone, email, message) VALUES (?, ?, ?, ?)")
               ->execute([$enqName, $enqPhone, $enqEmail ?: null, $enqMsg ?: null]);
            $enquirySuccess = true;
        }
    }
}

// Social links
$socialFacebook = getSetting('social_facebook', '');
$socialTwitter = getSetting('social_twitter', '');
$socialInstagram = getSetting('social_instagram', '');
$socialYoutube = getSetting('social_youtube', '');
$socialLinkedin = getSetting('social_linkedin', '');

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
    exit;
}

// Get active slides
$slides = $db->query("SELECT * FROM home_slider WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();

// Get core team members (from independent core_team table)
$coreTeam = [];
try {
    $coreTeam = $db->query("SELECT * FROM core_team WHERE is_visible=1 ORDER BY display_order ASC, name ASC")->fetchAll();
} catch (Exception $e) {
    // Fallback to teachers table if core_team table doesn't exist yet
    $coreTeam = $db->query("SELECT * FROM teachers WHERE status='active' AND is_core_team=1 ORDER BY FIELD(designation,'Principal','Director','Correspondent','Vice Principal','Teacher'), name ASC")->fetchAll();
}

// Get upcoming events (next 3)
$events = $db->query("SELECT title, start_date, location FROM events WHERE is_public=1 AND start_date >= CURDATE() ORDER BY start_date ASC LIMIT 3")->fetchAll();

// Get latest notifications (3 for section)
$notifs = $db->query("SELECT title, type, created_at FROM notifications WHERE status='approved' AND is_public=1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Get latest 5 notifications for bell popup
$bellNotifs = $db->query("SELECT title, type, created_at FROM notifications WHERE status='approved' AND is_public=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Notification count (last 7 days)
$notifCount = $db->query("SELECT COUNT(*) FROM notifications WHERE status='approved' AND is_public=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Stats
$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn();

// Ad popup from popup_ads table
$popupAd = null;
try {
    $popupAd = $db->query("SELECT * FROM popup_ads WHERE id=1 AND is_enabled=1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())")->fetch();
    if ($popupAd && !$popupAd['image_path']) $popupAd = null;
} catch (Exception $e) {
    // Fallback to old settings if table doesn't exist yet
    $popupAdActive = getSetting('popup_ad_active', '0');
    $popupAdImage = getSetting('popup_ad_image', '');
    if ($popupAdActive === '1' && $popupAdImage) {
        $popupAd = ['image_path' => 'uploads/ads/' . $popupAdImage, 'redirect_url' => '', 'button_text' => '', 'show_once_per_day' => 1, 'disable_on_mobile' => 0, 'show_on_home' => 1];
    }
}

// Nav logo with cache-busting
$navLogo = getSetting('school_logo', '');
$logoVersion = getSetting('logo_updated_at', '0');
$logoPath = '';
if ($navLogo) {
    $logoPath = (strpos($navLogo, '/uploads/') === 0) ? $navLogo : (file_exists(__DIR__.'/uploads/branding/'.$navLogo) ? '/uploads/branding/'.$navLogo : '/uploads/logo/'.$navLogo);
    $logoPath .= '?v=' . $logoVersion;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($schoolName) ?> — <?= e($schoolTagline) ?></title>
    <meta name="description" content="<?= e($schoolName) ?> — <?= e($schoolTagline) ?>. Official school website for admissions, notifications, gallery, and events.">
    <?php $favicon = getSetting('school_favicon', ''); $favVer = getSetting('favicon_updated_at', '0'); if ($favicon): $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-primary: <?= e($primaryColor) ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }



        /* Hero Slider */
        .hero-slider { position: relative; overflow: hidden; height: 520px; }
        .hero-slide {
            position: absolute; inset: 0; opacity: 0;
            transition: opacity 1s ease-in-out;
            background-size: cover; background-position: center;
        }
        .hero-slide.active { opacity: 1; }
        .hero-slide.anim-slide-left { transform: translateX(100%); opacity: 1; transition: transform 0.8s ease, opacity 0.5s ease; }
        .hero-slide.anim-slide-left.active { transform: translateX(0); opacity: 1; }
        .hero-slide.anim-slide-up { transform: translateY(30px); transition: transform 0.8s ease, opacity 0.6s ease; }
        .hero-slide.anim-slide-up.active { transform: translateY(0); opacity: 1; }
        .hero-slide.anim-zoom-in { transform: scale(0.95); transition: transform 1s ease, opacity 0.8s ease; }
        .hero-slide.anim-zoom-in.active { transform: scale(1); opacity: 1; }
        .hero-slide.anim-zoom-out { transform: scale(1.1); transition: transform 1s ease, opacity 0.8s ease; }
        .hero-slide.anim-zoom-out.active { transform: scale(1); opacity: 1; }
        @keyframes kenBurns { 0% { transform: scale(1); } 100% { transform: scale(1.08); } }
        .hero-slide.anim-ken-burns.active { animation: kenBurns 8s ease forwards; opacity: 1; }
        .hero-slide .content {
            position: relative; z-index: 2; color: #fff; height: 100%;
            display: flex; flex-direction: column; justify-content: center;
            padding: 0 2rem; max-width: 700px;
        }
        .hero-slide .badge-text {
            display: inline-block; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.25); padding: 0.35rem 1rem;
            border-radius: 50px; font-size: 0.75rem; letter-spacing: 1px;
            text-transform: uppercase; font-weight: 600; margin-bottom: 1rem; width: fit-content;
        }
        .hero-slide h1 { font-size: 2.8rem; font-weight: 800; line-height: 1.15; margin-bottom: 1rem; }
        .hero-slide p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 1.5rem; }
        .hero-slide .cta-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: #fff; color: #1e40af; padding: 0.7rem 1.8rem;
            border-radius: 50px; font-weight: 600; text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s; width: fit-content;
        }
        .hero-slide .cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
        .slider-dots {
            position: absolute; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
            z-index: 10; display: flex; gap: 0.5rem;
        }
        .slider-dots .dot {
            width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.4);
            cursor: pointer; transition: all 0.3s; border: none;
        }
        .slider-dots .dot.active { background: #fff; transform: scale(1.2); }
        .slider-arrow {
            position: absolute; top: 50%; transform: translateY(-50%); z-index: 10;
            background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2); color: #fff;
            width: 48px; height: 48px; border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; transition: all 0.3s;
        }
        .slider-arrow:hover { background: rgba(255,255,255,0.3); }
        .slider-arrow.prev { left: 1.5rem; }
        .slider-arrow.next { right: 1.5rem; }
        .hero-fallback {
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 100%);
            color: #fff; padding: 5rem 0; text-align: center;
        }

        /* Stats bar */
        .stats-bar { background: #0f172a; color: #fff; padding: 1rem 0; }
        .stat-item { text-align: center; }
        .stat-item .num { font-size: 1.5rem; font-weight: 700; }
        .stat-item .label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; }

        /* Section styling */
        .section-title { font-weight: 700; position: relative; display: inline-block; margin-bottom: 1.5rem; }
        .section-title::after {
            content: ''; position: absolute; bottom: -6px; left: 0;
            width: 50px; height: 3px; background: var(--theme-primary); border-radius: 2px;
        }
        .feature-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .info-card { border: none; border-radius: 14px; transition: transform 0.2s, box-shadow 0.2s; }
        .info-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }

        /* WhatsApp floating button */
        .whatsapp-float {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            width: 60px; height: 60px; border-radius: 50%; background: #25D366;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.8rem; text-decoration: none;
            box-shadow: 0 4px 20px rgba(37,211,102,0.4); transition: transform 0.3s;
            animation: whatsappPulse 2s infinite;
        }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }
        @keyframes whatsappPulse {
            0%, 100% { box-shadow: 0 4px 20px rgba(37,211,102,0.4); }
            50% { box-shadow: 0 4px 30px rgba(37,211,102,0.7); }
        }

        /* Ad popup */
        .ad-popup-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000;
            display: flex; align-items: center; justify-content: center;
        }
        .ad-popup-content { position: relative; max-width: 550px; width: 90%; animation: adPopupIn 0.4s ease-out; }
        .ad-popup-content img { width: 100%; max-height: 80vh; object-fit: contain; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        @keyframes adPopupIn { 0% { opacity: 0; transform: scale(0.85); } 100% { opacity: 1; transform: scale(1); } }
        .ad-popup-close {
            position: absolute; top: -12px; right: -12px; width: 36px; height: 36px;
            border-radius: 50%; background: #dc3545; color: #fff; border: 3px solid #fff;
            font-size: 1.1rem; cursor: pointer; display: flex; align-items: center;
            justify-content: center; z-index: 10001; transition: transform 0.2s;
        }
        .ad-popup-close:hover { transform: scale(1.1); }

        /* Dark Footer - Aryan Style */
        .site-footer { background: #1a1a2e; color: #fff; margin-top: 0; border-radius: 0; }
        .footer-cta { background: #0f2557; padding: 4rem 0; text-align: center; }
        .footer-cta h2 { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 2.2rem; color: #fff; margin-bottom: 1rem; }
        .footer-cta p { color: rgba(255,255,255,0.7); max-width: 600px; margin: 0 auto 1.5rem; }
        .footer-heading { text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; color: #fff; }
        .footer-heading::after { content: ''; position: absolute; bottom: 0; left: 0; width: 30px; height: 2px; background: var(--theme-primary); }
        .footer-link { color: rgba(255,255,255,0.65); text-decoration: none; transition: color 0.2s; font-size: 0.9rem; }
        .footer-link:hover { color: #fff; }
        .footer-social a { width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.3); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; font-size: 0.9rem; }
        .footer-social a:hover { background: var(--theme-primary); border-color: var(--theme-primary); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); }

        @media (max-width: 767.98px) {
            .hero-slider { height: 400px; }
            .hero-slide h1 { font-size: 1.8rem; }
            .slider-arrow { display: none; }
            .top-bar .d-flex { flex-direction: column; gap: 0.3rem; text-align: center; }
            .stat-item .num { font-size: 1.2rem; }
            .info-card { padding: 1.5rem !important; }
        }
        @media (max-width: 575.98px) {
            .navbar-brand { }
            .navbar-brand img { width: 120px !important; height: auto !important; }
            .navbar-collapse .d-flex { flex-direction: column; width: 100%; gap: 0.5rem; margin-top: 0.75rem; }
            .notif-bell-btn, .login-nav-btn { width: 100%; text-align: center; display: block; }
            .top-bar .d-flex.gap-3 { font-size: 0.7rem; gap: 0.4rem !important; }
            .hero-slider { height: 320px; }
            .hero-slide h1 { font-size: 1.5rem; }
            .hero-slide p { font-size: 0.9rem; }
            .hero-slide .content { padding: 0 1rem; }
            .hero-slide .badge-text { font-size: 0.65rem; padding: 0.25rem 0.8rem; }
            .hero-slide .cta-btn { padding: 0.5rem 1.2rem; font-size: 0.85rem; }
            .stat-item .num { font-size: 1.1rem; }
            .stat-item .label { font-size: 0.65rem; }
            .stats-bar { padding: 0.6rem 0; }
            .section-title { font-size: 1.1rem; }
            .site-footer .row > div { text-align: center; }
            .footer-heading::after { left: 50%; transform: translateX(-50%); }
            .footer-social { justify-content: center; }
            .site-footer { border-radius: 20px 20px 0 0; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
            .ad-popup-close { width: 44px; height: 44px; font-size: 1.3rem; top: -8px; right: -8px; }
            .card img[style*="height:280px"] { height: 200px !important; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'home'; include __DIR__ . '/includes/public-navbar.php'; ?>

<!-- Ad Popup -->
<?php if ($popupAd): ?>
<div class="ad-popup-overlay" id="adPopup" style="display:none;">
    <div class="ad-popup-content">
        <button class="ad-popup-close" onclick="closeAdPopup()"><i class="bi bi-x-lg"></i></button>
        <?php if ($popupAd['redirect_url']): ?>
        <a href="<?= e($popupAd['redirect_url']) ?>" target="_blank" rel="noopener" id="adPopupLink" onclick="trackPopupClick()">
            <img src="/<?= e($popupAd['image_path']) ?>" alt="Advertisement">
        </a>
        <?php else: ?>
        <img src="/<?= e($popupAd['image_path']) ?>" alt="Advertisement">
        <?php endif; ?>
        <?php if ($popupAd['button_text'] && $popupAd['redirect_url']): ?>
        <div style="text-align:center;margin-top:10px">
            <a href="<?= e($popupAd['redirect_url']) ?>" target="_blank" rel="noopener" class="btn btn-light btn-sm rounded-pill px-4 fw-semibold" onclick="trackPopupClick()" style="box-shadow:0 2px 10px rgba(0,0,0,0.2)"><?= e($popupAd['button_text']) ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    var oncePerDay = <?= $popupAd['show_once_per_day'] ? 'true' : 'false' ?>;
    var disableMobile = <?= $popupAd['disable_on_mobile'] ? 'true' : 'false' ?>;
    var key = 'popup_ad_dismissed_' + new Date().toISOString().slice(0,10);

    // Mobile check
    if (disableMobile && window.innerWidth <= 768) return;

    // Once per day check
    if (oncePerDay && localStorage.getItem(key)) return;

    setTimeout(function(){
        document.getElementById('adPopup').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Track view
        fetch('/admin/ajax/popup-analytics.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=view&popup_id=1'
        }).catch(function(){});
    }, 1000);

    window.closeAdPopup = function(){
        document.getElementById('adPopup').style.display = 'none';
        document.body.style.overflow = '';
        if (oncePerDay) localStorage.setItem(key, '1');
    };

    window.trackPopupClick = function(){
        fetch('/admin/ajax/popup-analytics.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=click&popup_id=1'
        }).catch(function(){});
    };
})();
</script>
<?php endif; ?>

<!-- Hero Slider -->
<?php if (!empty($slides)): ?>
<div class="hero-slider" id="heroSlider">
    <?php foreach ($slides as $i => $slide): ?>
    <?php
        $anim = $slide['animation_type'] ?? 'fade';
        $overlay = $slide['overlay_style'] ?? 'gradient-dark';
        $textPos = $slide['text_position'] ?? 'left';
        $opacity = ($slide['overlay_opacity'] ?? 70) / 100;
        $overlayMap = [
            'gradient-dark' => "linear-gradient(135deg, rgba(15,23,42,{$opacity}) 0%, rgba(30,64,175," . ($opacity * 0.7) . ") 100%)",
            'gradient-blue' => "linear-gradient(135deg, rgba(30,64,175,{$opacity}) 0%, rgba(59,130,246," . ($opacity * 0.7) . ") 100%)",
            'gradient-warm' => "linear-gradient(135deg, rgba(180,83,9,{$opacity}) 0%, rgba(234,88,12," . ($opacity * 0.7) . ") 100%)",
            'solid-dark' => "rgba(15,23,42,{$opacity})",
            'none' => 'transparent',
        ];
        $overlayBg = $overlayMap[$overlay] ?? $overlayMap['gradient-dark'];
        $alignMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
        $textAlign = $textPos === 'center' ? 'text-align:center;' : ($textPos === 'right' ? 'text-align:right;' : '');
        $contentAlign = $alignMap[$textPos] ?? 'flex-start';
    ?>
    <div class="hero-slide <?= $i === 0 ? 'active' : '' ?> anim-<?= e($anim) ?>" style="background-image: url('/<?= e($slide['image_path']) ?>');" data-index="<?= $i ?>">
        <div style="position:absolute;inset:0;background:<?= $overlayBg ?>;"></div>
        <div class="container h-100">
            <div class="content" style="align-items:<?= $contentAlign ?>;<?= $textAlign ?>">
                <?php if ($slide['badge_text']): ?>
                    <div class="badge-text"><?= e($slide['badge_text']) ?></div>
                <?php endif; ?>
                <?php if ($slide['title']): ?>
                    <h1><?= e($slide['title']) ?></h1>
                <?php endif; ?>
                <?php if ($slide['subtitle']): ?>
                    <p><?= e($slide['subtitle']) ?></p>
                <?php endif; ?>
                <?php if ($slide['cta_text'] && $slide['link_url']): ?>
                    <a href="<?= e($slide['link_url']) ?>" class="cta-btn"><?= e($slide['cta_text']) ?> <i class="bi bi-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($slides) > 1): ?>
    <button class="slider-arrow prev" onclick="changeSlide(-1)"><i class="bi bi-chevron-left"></i></button>
    <button class="slider-arrow next" onclick="changeSlide(1)"><i class="bi bi-chevron-right"></i></button>
    <div class="slider-dots">
        <?php for ($i = 0; $i < count($slides); $i++): ?>
            <button class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="hero-fallback">
    <div class="container">
        <h1 class="display-4 fw-bold mb-3"><?= e($schoolName) ?></h1>
        <p class="lead opacity-75 mb-4"><?= e($schoolTagline) ?></p>
        <?php if ($admissionOpen === '1'): ?>
            <a href="/public/admission-form.php" class="btn btn-light btn-lg rounded-pill px-4 fw-semibold">Apply for Admission <i class="bi bi-arrow-right ms-1"></i></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (getSetting('home_stats_show', '1') === '1'): ?>
<!-- Stats Bar -->
<div class="stats-bar">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num"><?= $totalStudents ?>+</div><div class="label"><?= e(getSetting('home_stats_students_label', 'Students')) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num"><?= $totalTeachers ?>+</div><div class="label"><?= e(getSetting('home_stats_teachers_label', 'Teachers')) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num"><?= e(getSetting('home_stats_classes_value', '12')) ?></div><div class="label"><?= e(getSetting('home_stats_classes_label', 'Classes')) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-item"><div class="num"><?= e(getSetting('home_stats_dedication_value', '100%')) ?></div><div class="label"><?= e(getSetting('home_stats_dedication_label', 'Dedication')) ?></div></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (getSetting('home_quicklinks_show', '1') === '1'):
// Fetch feature cards from DB (fallback to defaults if table doesn't exist)
$featureCards = [];
try {
    $featureCards = $db->query("SELECT * FROM feature_cards WHERE is_visible=1 ORDER BY sort_order ASC")->fetchAll();
} catch (Exception $e) { /* table may not exist yet */ }

// Live stats
$galleryCount = 0;
$nextEvent = null;
try {
    $galleryCount = (int)$db->query("SELECT COUNT(*) FROM gallery_items WHERE status='approved'")->fetchColumn();
    $nextEvent = $db->query("SELECT title, start_date FROM events WHERE is_public=1 AND start_date >= CURDATE() ORDER BY start_date ASC LIMIT 1")->fetch();
} catch (Exception $e) {}

// Build stats map
$cardStats = [
    'admissions' => $admissionOpen === '1' ? 'Admissions Open' : 'Admissions Closed',
    'notifications' => $notifCount . ' new this week',
    'gallery' => $galleryCount . ' photos & videos',
    'events' => $nextEvent ? e($nextEvent['title']) . ' — ' . date('d M', strtotime($nextEvent['start_date'])) : 'No upcoming events',
];
$cardStatsIcon = [
    'admissions' => $admissionOpen === '1' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger',
    'notifications' => 'bi-envelope-open-fill',
    'gallery' => 'bi-camera-fill',
    'events' => 'bi-clock-fill',
];

// Dynamic badge overrides
if ($notifCount > 0) {
    foreach ($featureCards as &$fc) {
        if ($fc['slug'] === 'notifications') $fc['badge_text'] = $notifCount . ' New';
    }
    unset($fc);
}
?>

<style>
/* ===== Feature Cards — Glassmorphism ===== */
.fcard-section { padding: 4rem 0; }
.fcard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.fcard {
    position: relative; background: rgba(255,255,255,0.65); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.4); border-radius: 1.25rem; padding: 2rem 1.5rem 1.5rem;
    text-align: center; transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.4s ease;
    overflow: hidden; cursor: default;
}
.fcard::before {
    content: ''; position: absolute; inset: -1px; border-radius: 1.25rem; padding: 1px;
    background: linear-gradient(135deg, rgba(255,255,255,0.5), transparent 60%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none;
}
.fcard:hover { transform: translateY(-10px); box-shadow: 0 20px 50px rgba(0,0,0,0.12); }
.fcard.featured { box-shadow: 0 0 0 2px var(--card-accent, var(--theme-primary)), 0 8px 30px rgba(0,0,0,0.1); }
.fcard.featured::after {
    content: ''; position: absolute; inset: -2px; border-radius: 1.25rem;
    background: conic-gradient(from 0deg, transparent, var(--card-accent, var(--theme-primary)), transparent 30%);
    opacity: 0.3; animation: fcardGlow 4s linear infinite; pointer-events: none; z-index: -1;
}
@keyframes fcardGlow { 100% { transform: rotate(360deg); } }

.fcard-icon {
    width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 1.25rem;
    display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
    background: linear-gradient(135deg, var(--card-accent, var(--theme-primary)), color-mix(in srgb, var(--card-accent, var(--theme-primary)) 70%, white));
    color: #fff; transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1);
    box-shadow: 0 8px 24px color-mix(in srgb, var(--card-accent, var(--theme-primary)) 30%, transparent);
}
.fcard:hover .fcard-icon { transform: scale(1.12) rotate(-8deg); }

.fcard-badge {
    position: absolute; top: 1rem; right: 1rem; font-size: 0.65rem; font-weight: 700;
    padding: 0.25rem 0.65rem; border-radius: 50px; color: #fff; letter-spacing: 0.5px;
    text-transform: uppercase; animation: fcardBadgePulse 2s ease-in-out infinite;
}
@keyframes fcardBadgePulse { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }

.fcard h5 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.5rem; color: #1a1a2e; }
.fcard p { font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem; line-height: 1.5; }

.fcard-stats {
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
    font-size: 0.75rem; color: #9ca3af; margin-bottom: 1rem; font-weight: 500;
}
.fcard-stats i { font-size: 0.8rem; }

.fcard .btn-fcard {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1.4rem;
    border-radius: 50px; font-size: 0.8rem; font-weight: 600; text-decoration: none;
    border: 1.5px solid var(--card-accent, var(--theme-primary));
    color: var(--card-accent, var(--theme-primary)); background: transparent;
    transition: all 0.3s ease; position: relative; overflow: hidden;
}
.fcard .btn-fcard:hover {
    background: var(--card-accent, var(--theme-primary)); color: #fff;
    box-shadow: 0 4px 16px color-mix(in srgb, var(--card-accent, var(--theme-primary)) 30%, transparent);
}

/* Ripple */
.fcard .ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.4); transform: scale(0); animation: fcardRipple 0.6s ease-out; pointer-events: none; }
@keyframes fcardRipple { to { transform: scale(4); opacity: 0; } }

/* Mobile carousel */
@media (max-width: 991.98px) { .fcard-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px) {
    .fcard-grid {
        display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 1rem;
        -ms-overflow-style: none; scrollbar-width: none; padding: 0 0 0.5rem 0;
        margin: 0;
    }
    .fcard-grid::-webkit-scrollbar { display: none; }
    .fcard { min-width: 260px; scroll-snap-align: start; flex-shrink: 0; }
    .fcard-section { padding: 1.5rem 0; }
}

/* Skeleton */
.fcard-skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: fcardShimmer 1.5s infinite; border-radius: 1.25rem; height: 280px; }
@keyframes fcardShimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Fade-in on scroll */
.fcard-animate { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
.fcard-animate.visible { opacity: 1; transform: translateY(0); }
</style>

<!-- Feature Cards Section -->
<section class="fcard-section">
    <div class="container">
        <?php if (!empty($featureCards)): ?>
        <div class="fcard-grid">
            <?php foreach ($featureCards as $i => $card):
                $accent = ($card['accent_color'] === 'auto' || empty($card['accent_color'])) ? $primaryColor : $card['accent_color'];
                $stat = $cardStats[$card['slug']] ?? '';
                $statIcon = $cardStatsIcon[$card['slug']] ?? 'bi-info-circle';
            ?>
            <div class="fcard fcard-animate <?= $card['is_featured'] ? 'featured' : '' ?>" style="--card-accent:<?= e($accent) ?>; transition-delay: <?= $i * 0.1 ?>s;" role="article" aria-label="<?= e($card['title']) ?>" onclick="trackCardClick('<?= e($card['slug']) ?>')">
                <?php if (!empty($card['badge_text'])): ?>
                    <span class="fcard-badge" style="background:<?= e($card['badge_color'] ?: '#ef4444') ?>"><?= e($card['badge_text']) ?></span>
                <?php endif; ?>
                <div class="fcard-icon"><i class="bi <?= e($card['icon_class']) ?>"></i></div>
                <h5><?= e($card['title']) ?></h5>
                <p><?= e($card['description']) ?></p>
                <?php if ($card['show_stats'] && $stat): ?>
                    <div class="fcard-stats"><i class="bi <?= e($statIcon) ?>"></i> <?= $stat ?></div>
                <?php endif; ?>
                <a href="<?= e($card['btn_link']) ?>" class="btn-fcard" aria-label="<?= e($card['btn_text']) ?> — <?= e($card['title']) ?>"><?= e($card['btn_text']) ?> <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Fallback to settings-based cards -->
        <div class="fcard-grid">
            <div class="fcard fcard-animate featured" style="--card-accent:#3b82f6">
                <span class="fcard-badge" style="background:#22c55e"><?= $admissionOpen === '1' ? 'Open' : 'Closed' ?></span>
                <div class="fcard-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <h5><?= e(getSetting('home_cta_admissions_title', 'Admissions')) ?></h5>
                <p><?= e(getSetting('home_cta_admissions_desc', 'Apply online for admission.')) ?></p>
                <div class="fcard-stats"><i class="bi <?= $admissionOpen === '1' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i> <?= $admissionOpen === '1' ? 'Admissions Open' : 'Admissions Closed' ?></div>
                <a href="/public/admission-form.php" class="btn-fcard">Apply Now <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="fcard fcard-animate" style="--card-accent:#f59e0b; transition-delay:0.1s">
                <?php if ($notifCount > 0): ?><span class="fcard-badge" style="background:#ef4444"><?= $notifCount ?> New</span><?php endif; ?>
                <div class="fcard-icon"><i class="bi bi-bell-fill"></i></div>
                <h5><?= e(getSetting('home_cta_notifications_title', 'Notifications')) ?></h5>
                <p><?= e(getSetting('home_cta_notifications_desc', 'Stay updated with latest announcements.')) ?></p>
                <div class="fcard-stats"><i class="bi bi-envelope-open-fill"></i> <?= $notifCount ?> new this week</div>
                <a href="/public/notifications.php" class="btn-fcard">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="fcard fcard-animate" style="--card-accent:#10b981; transition-delay:0.2s">
                <div class="fcard-icon"><i class="bi bi-images"></i></div>
                <h5><?= e(getSetting('home_cta_gallery_title', 'Gallery')) ?></h5>
                <p><?= e(getSetting('home_cta_gallery_desc', 'Explore photos & videos from school life.')) ?></p>
                <div class="fcard-stats"><i class="bi bi-camera-fill"></i> <?= $galleryCount ?> photos & videos</div>
                <a href="/public/gallery.php" class="btn-fcard">Browse <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="fcard fcard-animate" style="--card-accent:#ef4444; transition-delay:0.3s">
                <div class="fcard-icon"><i class="bi bi-calendar-event-fill"></i></div>
                <h5><?= e(getSetting('home_cta_events_title', 'Events')) ?></h5>
                <p><?= e(getSetting('home_cta_events_desc', 'Check upcoming school events & dates.')) ?></p>
                <div class="fcard-stats"><i class="bi bi-clock-fill"></i> <?= $nextEvent ? e($nextEvent['title']) . ' — ' . date('d M', strtotime($nextEvent['start_date'])) : 'No upcoming events' ?></div>
                <a href="/public/events.php" class="btn-fcard">View Events <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Intersection Observer for fade-in
(function(){
    var cards = document.querySelectorAll('.fcard-animate');
    if ('IntersectionObserver' in window) {
        var obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
        }, { threshold: 0.15 });
        cards.forEach(function(c) { obs.observe(c); });
    } else { cards.forEach(function(c) { c.classList.add('visible'); }); }
})();

// Click ripple
document.querySelectorAll('.fcard').forEach(function(card) {
    card.addEventListener('click', function(e) {
        var r = document.createElement('span');
        r.className = 'ripple';
        var rect = card.getBoundingClientRect();
        var sz = Math.max(rect.width, rect.height);
        r.style.width = r.style.height = sz + 'px';
        r.style.left = (e.clientX - rect.left - sz / 2) + 'px';
        r.style.top = (e.clientY - rect.top - sz / 2) + 'px';
        card.appendChild(r);
        setTimeout(function() { r.remove(); }, 600);
    });
});

// Track card clicks
function trackCardClick(slug) {
    fetch('/admin/ajax/feature-card-actions.php?action=track_click&slug=' + encodeURIComponent(slug), { method: 'POST' }).catch(function(){});
}
</script>
<?php endif; ?>

<!-- Latest Notifications & Upcoming Events -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <h4 class="section-title">Latest Notifications</h4>
                <?php if (empty($notifs)): ?>
                    <p class="text-muted">No recent notifications.</p>
                <?php else: ?>
                    <?php foreach ($notifs as $n):
                        $typeColors = ['urgent' => 'danger', 'exam' => 'warning', 'academic' => 'info', 'event' => 'success'];
                        $color = $typeColors[$n['type']] ?? 'secondary';
                    ?>
                    <div class="card info-card mb-3">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="fw-semibold mb-1"><?= e($n['title']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($n['created_at'])) ?></small>
                                </div>
                                <span class="badge bg-<?= $color ?>"><?= e(ucfirst($n['type'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <a href="/public/notifications.php" class="btn btn-sm btn-outline-primary">View All Notifications →</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <h4 class="section-title">Upcoming Events</h4>
                <?php if (empty($events)): ?>
                    <p class="text-muted">No upcoming events.</p>
                <?php else: ?>
                    <?php foreach ($events as $ev): ?>
                    <div class="card info-card mb-3">
                        <div class="card-body py-3 d-flex gap-3 align-items-center">
                            <div class="text-center flex-shrink-0" style="width:50px;">
                                <div class="fw-bold text-primary" style="font-size:1.3rem;line-height:1;"><?= date('d', strtotime($ev['start_date'])) ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;"><?= date('M', strtotime($ev['start_date'])) ?></small>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0"><?= e($ev['title']) ?></h6>
                                <?php if ($ev['location']): ?><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?></small><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <a href="/public/events.php" class="btn btn-sm btn-outline-primary">View All Events →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Our Core Team — Centered Flip-Card Grid -->
<?php if (!empty($coreTeam) && getSetting('home_core_team_show', '1') === '1'): ?>
<section class="py-5" style="background:#f8fafc;">
    <div class="container">
        <div class="text-center mb-4">
            <h4 style="font-family:'Playfair Display',serif;font-style:italic;font-size:2rem;font-weight:700;color:#1a1a2e;"><?= e(getSetting('home_core_team_title', 'Our Core Team')) ?></h4>
            <p class="text-muted mt-2"><?= e(getSetting('home_core_team_subtitle', 'Meet the dedicated leaders guiding our school\'s vision and mission.')) ?></p>
        </div>
        <div class="row g-4 justify-content-center core-team-slider" id="coreTeamSlider">
            <?php foreach ($coreTeam as $ct):
                $ctPhoto = $ct['photo'] ? (str_starts_with($ct['photo'], '/uploads/') ? $ct['photo'] : '/uploads/photos/'.$ct['photo']) : '';
            ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="core-flip-card" onclick="this.classList.toggle('flipped')">
                    <div class="core-flip-inner">
                        <div class="core-flip-front">
                            <?php if ($ctPhoto): ?>
                                <img src="<?= e($ctPhoto) ?>" alt="<?= e($ct['name']) ?>" class="core-flip-img">
                            <?php else: ?>
                                <div class="core-flip-img d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#e2e8f0,#cbd5e1);">
                                    <i class="bi bi-person-fill" style="font-size:4rem;color:#94a3b8;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="core-flip-overlay">
                                <h6 class="mb-0 fw-bold text-white"><?= e($ct['name']) ?></h6>
                                <small class="text-white-50"><?= e($ct['designation'] ?? 'Team Member') ?></small>
                            </div>
                        </div>
                        <div class="core-flip-back">
                            <h6 class="fw-bold mb-2"><?= e($ct['name']) ?></h6>
                            <small class="d-block mb-2" style="opacity:.85;"><?= e($ct['designation'] ?? 'Team Member') ?></small>
                            <?php if (!empty($ct['qualification'])): ?>
                                <div class="mb-1"><i class="bi bi-mortarboard me-1"></i><small><?= e($ct['qualification']) ?></small></div>
                            <?php endif; ?>
                            <?php if (!empty($ct['subject'])): ?>
                                <div class="mb-1"><i class="bi bi-book me-1"></i><small><?= e($ct['subject']) ?></small></div>
                            <?php endif; ?>
                            <?php if (!empty($ct['experience_years']) && $ct['experience_years'] > 0): ?>
                                <span class="badge bg-white bg-opacity-25 mt-1 mb-2"><?= (int)$ct['experience_years'] ?> Yrs Experience</span>
                            <?php endif; ?>
                            <?php if (!empty($ct['bio'])): ?>
                                <p class="small mt-2 mb-0" style="opacity:.9;line-height:1.4;"><?= e(mb_strimwidth($ct['bio'], 0, 120, '...')) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="core-team-dots" id="coreTeamDots"></div>
        <script>
        (function(){
            var slider = document.getElementById('coreTeamSlider');
            var dotsC = document.getElementById('coreTeamDots');
            if (!slider || window.innerWidth >= 576) return;
            var cards = slider.querySelectorAll('.col-sm-6');
            if (cards.length < 2) return;
            cards.forEach(function(_, i) {
                var d = document.createElement('button');
                d.className = 'ct-dot' + (i === 0 ? ' active' : '');
                d.onclick = function() { cards[i].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' }); };
                dotsC.appendChild(d);
            });
            var dots = dotsC.querySelectorAll('.ct-dot');
            slider.addEventListener('scroll', function() {
                var sl = slider.scrollLeft, sw = slider.scrollWidth - slider.clientWidth;
                var idx = Math.round((sl / sw) * (cards.length - 1));
                dots.forEach(function(d, i) { d.classList.toggle('active', i === idx); });
            });
        })();
        </script>
        <div class="text-center mt-4">
            <a href="/public/teachers.php" class="btn fw-bold px-4 rounded-1 text-uppercase" style="font-size:0.8rem;letter-spacing:1px;background:var(--theme-primary);color:#fff;">View Our Teachers</a>
        </div>
    </div>
</section>
<style>
.core-flip-card{perspective:1000px;height:320px;cursor:pointer}
.core-flip-inner{position:relative;width:100%;height:100%;transition:transform .6s cubic-bezier(.4,0,.2,1);transform-style:preserve-3d}
.core-flip-card:hover .core-flip-inner,.core-flip-card.flipped .core-flip-inner{transform:rotateY(180deg)}
.core-flip-front,.core-flip-back{position:absolute;width:100%;height:100%;backface-visibility:hidden;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
.core-flip-front{background:#fff}
.core-flip-img{width:100%;height:100%;object-fit:cover}
.core-flip-overlay{position:absolute;bottom:0;left:0;right:0;padding:16px;background:linear-gradient(transparent,rgba(0,0,0,.7))}
.core-flip-back{background:linear-gradient(135deg,var(--theme-primary,#1e40af),#3b82f6);color:#fff;transform:rotateY(180deg);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center}
@media(hover:none){.core-flip-card:hover .core-flip-inner{transform:none}.core-flip-card.flipped .core-flip-inner{transform:rotateY(180deg)}}

/* Core Team Mobile Swipe Slider */
@media (max-width: 575.98px) {
    .core-team-slider {
        display: flex !important;
        flex-wrap: nowrap !important;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        -ms-overflow-style: none;
        scrollbar-width: none;
        gap: 1rem;
        padding-bottom: 1rem;
    }
    .core-team-slider::-webkit-scrollbar { display: none; }
    .core-team-slider > .col-sm-6 {
        min-width: 270px;
        max-width: 270px;
        flex: 0 0 270px;
        scroll-snap-align: center;
    }
    .core-team-dots {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 8px;
    }
    .core-team-dots .ct-dot {
        width: 10px; height: 10px; border-radius: 50%;
        background: #cbd5e1; border: none; padding: 0; cursor: pointer;
        transition: background 0.3s, transform 0.3s;
    }
    .core-team-dots .ct-dot.active {
        background: var(--theme-primary, #1e40af);
        transform: scale(1.3);
    }
}
@media (min-width: 576px) {
    .core-team-dots { display: none !important; }
}
</style>
<?php endif; ?>

<?php
// ── Certificates & Accreditations Section ──
if (getSetting('home_certificates_show', '1') === '1'):
    $certMax = (int)getSetting('home_certificates_max', '6');
    $featuredCerts = $db->query("SELECT * FROM certificates WHERE is_active=1 AND is_deleted=0 AND is_featured=1 ORDER BY display_order ASC LIMIT $certMax")->fetchAll();
    if (!empty($featuredCerts)):
        $certCategories = ['govt_approval'=>'Govt Approved','board_affiliation'=>'CBSE Affiliation','recognition'=>'Recognition','awards'=>'Award'];
        $certBadgeColors = ['govt_approval'=>'success','board_affiliation'=>'primary','recognition'=>'info','awards'=>'warning'];
?>
<section class="py-5" style="background:linear-gradient(135deg,#f0f4ff 0%,#e8f0fe 100%);">
    <div class="container">
        <div class="text-center mb-4">
            <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-2" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;"><i class="bi bi-award me-1"></i>Our Credentials</div>
            <h4 style="font-family:'Playfair Display',serif;font-weight:700;font-size:2rem;color:#1a1a2e;">Our Certifications & Accreditations</h4>
            <p class="text-muted mt-2" style="max-width:600px;margin:0 auto;">Recognized and accredited by leading educational bodies and government authorities.</p>
        </div>
        <!-- Certificate Slider -->
        <style>
        .cert-slider{position:relative;overflow:hidden;padding:0 50px;}
        .cert-slider-track{display:flex;transition:transform .5s cubic-bezier(.4,0,.2,1);}
        .cert-slider-card{flex:0 0 33.333%;padding:0 12px;box-sizing:border-box;}
        .cert-slider-arrow{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:none;background:var(--theme-primary,#16a34a);color:#fff;font-size:1.2rem;cursor:pointer;z-index:5;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 15px rgba(0,0,0,.15);transition:background .3s,transform .3s;}
        .cert-slider-arrow:hover{background:var(--theme-primary-dark,#15803d);transform:translateY(-50%) scale(1.1);}
        .cert-slider-arrow.prev{left:0;}
        .cert-slider-arrow.next{right:0;}
        .cert-dots{display:flex;justify-content:center;gap:8px;margin-top:1.2rem;}
        .cert-dots .dot{width:10px;height:10px;border-radius:50%;background:#ccc;border:none;cursor:pointer;transition:background .3s,transform .3s;padding:0;}
        .cert-dots .dot.active{background:var(--theme-primary,#16a34a);transform:scale(1.3);}
        @media(max-width:991px){.cert-slider-card{flex:0 0 50%;}}
        @media(max-width:575px){.cert-slider-card{flex:0 0 100%;}.cert-slider{padding:0 40px;}.cert-slider-arrow{width:36px;height:36px;font-size:1rem;}}
        </style>
        <div class="cert-slider" id="certSlider" onmouseenter="clearInterval(certAutoPlay)" onmouseleave="certStartAuto()">
            <button class="cert-slider-arrow prev" onclick="certSlidePrev()" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
            <div class="cert-slider-track" id="certTrack">
                <?php foreach ($featuredCerts as $fc):
                    $fcThumb = $fc['thumb_path'] ? '/' . $fc['thumb_path'] : ($fc['file_type']==='pdf' ? '' : '/' . $fc['file_path']);
                    $fcCatLabel = $certCategories[$fc['category']] ?? ucfirst($fc['category']);
                    $fcCatColor = $certBadgeColors[$fc['category']] ?? 'secondary';
                ?>
                <div class="cert-slider-card">
                    <div class="card border-0 shadow-sm h-100 text-center" style="border-radius:16px;overflow:hidden;transition:transform .3s,box-shadow .3s;cursor:pointer;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 12px 30px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow=''" onclick="window.certLightbox('<?= e('/' . $fc['file_path']) ?>','<?= $fc['file_type'] ?>')">
                        <?php if ($fc['file_type'] === 'pdf'): ?>
                            <div class="d-flex align-items-center justify-content-center" style="height:180px;background:linear-gradient(135deg,#fef2f2,#fee2e2);"><i class="bi bi-file-earmark-pdf text-danger" style="font-size:3rem"></i></div>
                        <?php elseif ($fcThumb): ?>
                            <img src="<?= e($fcThumb) ?>" alt="<?= e($fc['title']) ?>" style="width:100%;height:180px;object-fit:cover;" loading="lazy">
                        <?php endif; ?>
                        <div class="card-body p-3">
                            <span class="badge bg-<?= $fcCatColor ?>-subtle text-<?= $fcCatColor ?> mb-1" style="font-size:.65rem;border-radius:50px;"><?= e($fcCatLabel) ?></span>
                            <h6 class="fw-semibold mb-0" style="font-size:.82rem;line-height:1.3;"><?= e($fc['title']) ?></h6>
                            <?php if ($fc['year']): ?><small class="text-muted" style="font-size:.68rem"><?= $fc['year'] ?></small><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="cert-slider-arrow next" onclick="certSlideNext()" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="cert-dots" id="certDots"></div>
        <script>
        (function(){
            var track=document.getElementById('certTrack'),cards=track.querySelectorAll('.cert-slider-card'),total=cards.length,idx=0;
            function getVisible(){return window.innerWidth<=575?1:window.innerWidth<=991?2:3;}
            function maxIdx(){return Math.max(0,total-getVisible());}
            function update(){
                var v=getVisible(),pct=100/v;
                track.style.transform='translateX(-'+(idx*(100/total))+'%)';
                // dots
                var dots=document.getElementById('certDots'),m=maxIdx()+1;
                dots.innerHTML='';
                for(var i=0;i<m;i++){var d=document.createElement('button');d.className='dot'+(i===idx?' active':'');d.setAttribute('aria-label','Slide '+(i+1));d.onclick=(function(n){return function(){idx=n;update();};})(i);dots.appendChild(d);}
            }
            window.certSlideNext=function(){idx=idx>=maxIdx()?0:idx+1;update();};
            window.certSlidePrev=function(){idx=idx<=0?maxIdx():idx-1;update();};
            update();
            // Auto-play
            window.certAutoPlay=setInterval(window.certSlideNext,4000);
            window.certStartAuto=function(){clearInterval(window.certAutoPlay);window.certAutoPlay=setInterval(window.certSlideNext,4000);};
            // Touch/swipe
            var sx=0;track.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;},{passive:true});
            track.addEventListener('touchend',function(e){var dx=e.changedTouches[0].clientX-sx;if(Math.abs(dx)>40){dx<0?window.certSlideNext():window.certSlidePrev();}},{passive:true});
            // Resize
            window.addEventListener('resize',function(){if(idx>maxIdx())idx=maxIdx();update();});
        })();
        </script>
        <?php if (getSetting('certificates_page_enabled', '1') === '1'): ?>
        <div class="text-center mt-4">
            <a href="/public/certificates.php" class="btn fw-bold px-4 rounded-pill text-uppercase" style="font-size:.8rem;letter-spacing:1px;background:var(--theme-primary);color:#fff;">View All Certificates <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Certificate Lightbox (Home) -->
<div id="homeCertLightbox" style="position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:10000;display:none;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this){this.style.display='none';document.body.style.overflow=''}">
    <button onclick="document.getElementById('homeCertLightbox').style.display='none';document.body.style.overflow=''" style="position:absolute;top:1rem;right:1.5rem;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1.5rem;width:44px;height:44px;border-radius:50%;cursor:pointer;z-index:10001;"><i class="bi bi-x-lg"></i></button>
    <div id="homeCertLightboxContent"></div>
</div>
<script>
window.certLightbox = function(src, type) {
    var el = document.getElementById('homeCertLightbox');
    var content = document.getElementById('homeCertLightboxContent');
    if (type === 'pdf') content.innerHTML = '<iframe src="'+src+'" style="width:90vw;height:85vh;border:none;border-radius:12px;background:#fff"></iframe>';
    else content.innerHTML = '<img src="'+src+'" alt="Certificate" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:12px">';
    el.style.display = 'flex'; document.body.style.overflow = 'hidden';
};
</script>
<?php endif; endif; ?>

<?php if (getSetting('home_contact_show', '1') === '1'): ?>
<section class="py-5">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <h4 class="section-title">Contact Us</h4>
                <p class="text-muted mb-4">Have questions? Reach out to us anytime.</p>
                <div class="d-flex flex-column gap-3">
                    <?php if ($schoolAddress): ?>
                    <div class="d-flex gap-3"><div class="feature-icon bg-primary-subtle text-primary flex-shrink-0" style="width:40px;height:40px;border-radius:10px;font-size:1rem;"><i class="bi bi-geo-alt-fill"></i></div><div><strong>Address</strong><br><span class="text-muted"><?= e($schoolAddress) ?></span></div></div>
                    <?php endif; ?>
                    <?php if ($schoolPhone): ?>
                    <div class="d-flex gap-3"><div class="feature-icon bg-success-subtle text-success flex-shrink-0" style="width:40px;height:40px;border-radius:10px;font-size:1rem;"><i class="bi bi-telephone-fill"></i></div><div><strong>Phone</strong><br><a href="tel:<?= e($schoolPhone) ?>" class="text-muted text-decoration-none"><?= e($schoolPhone) ?></a></div></div>
                    <?php endif; ?>
                    <?php if ($schoolEmail): ?>
                    <div class="d-flex gap-3"><div class="feature-icon bg-warning-subtle text-warning flex-shrink-0" style="width:40px;height:40px;border-radius:10px;font-size:1rem;"><i class="bi bi-envelope-fill"></i></div><div><strong>Email</strong><br><a href="mailto:<?= e($schoolEmail) ?>" class="text-muted text-decoration-none"><?= e($schoolEmail) ?></a></div></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-lg" style="border-radius:20px;background:linear-gradient(135deg,#ffffff 0%,#f8faff 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="feature-icon bg-primary-subtle text-primary flex-shrink-0" style="width:40px;height:40px;border-radius:12px;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-chat-dots-fill"></i>
                            </div>
                            <h5 class="fw-bold mb-0">Send an Enquiry</h5>
                        </div>

                        <?php if ($enquirySuccess): ?>
                        <div class="text-center py-3">
                            <div style="width:60px;height:60px;border-radius:50%;background:#d1fae5;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                                <i class="bi bi-check-lg text-success" style="font-size:1.8rem;"></i>
                            </div>
                            <h6 class="fw-bold text-success">Enquiry Sent!</h6>
                            <p class="text-muted small mb-0">We'll get back to you shortly.</p>
                        </div>
                        <?php else: ?>

                        <?php if ($enquiryError): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= e($enquiryError) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="#contact-section">
                            <input type="hidden" name="enquiry_submit" value="1">
                            <?= csrfField() ?>
                            <div class="mb-2">
                                <input type="text" name="enq_name" class="form-control form-control-sm" placeholder="Parent Name *" required maxlength="100" value="<?= e($_POST['enq_name'] ?? '') ?>" style="border-radius:10px;">
                            </div>
                            <div class="mb-2">
                                <input type="tel" name="enq_phone" class="form-control form-control-sm" placeholder="Phone Number *" required maxlength="20" value="<?= e($_POST['enq_phone'] ?? '') ?>" style="border-radius:10px;">
                            </div>
                            <div class="mb-2">
                                <input type="email" name="enq_email" class="form-control form-control-sm" placeholder="Email (optional)" maxlength="255" value="<?= e($_POST['enq_email'] ?? '') ?>" style="border-radius:10px;">
                            </div>
                            <div class="mb-3">
                                <textarea name="enq_message" class="form-control form-control-sm" placeholder="Message / Feedback (optional)" rows="2" maxlength="1000" style="border-radius:10px;"><?= e($_POST['enq_message'] ?? '') ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 flex-grow-1">
                                    <i class="bi bi-send-fill me-1"></i>Send Enquiry
                                </button>
                                <?php if ($whatsappNumber): ?>
                                <a href="https://wa.me/<?= e($whatsappNumber) ?>?text=<?= urlencode('Hi, I have an enquiry about ' . $schoolName) ?>" target="_blank" rel="noopener" class="btn btn-success rounded-pill px-3" title="Chat on WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <p class="text-muted text-center mt-2 mb-0" style="font-size:.7rem;"><i class="bi bi-shield-check me-1"></i>We respect your privacy. No spam.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
// === School Location / Map Section ===
$mapEnabled = getSetting('school_map_enabled', '0');
if ($mapEnabled === '1'):
    $mapEmbedUrl  = getSetting('school_map_embed_url', '');
    $mapLat       = getSetting('school_latitude', '');
    $mapLng       = getSetting('school_longitude', '');
    $mapLandmark  = getSetting('school_landmark', '');
    if ($mapEmbedUrl):
?>
<section class="py-5" style="background:linear-gradient(135deg,#f8fafc 0%,#f0f4ff 100%);">
    <div class="container">
        <div class="text-center mb-4">
            <h4 class="fw-bold"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Our Location</h4>
            <p class="text-muted">Find us on the map and visit our campus</p>
        </div>
        <div class="map-card-hover" style="border-radius:20px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.1);transition:transform .3s ease,box-shadow .3s ease;">
            <iframe src="<?= e($mapEmbedUrl) ?>" width="100%" height="450" style="border:0;display:block;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</section>
<style>
.map-card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.15) !important; }
</style>
<?php
    endif;
endif;
?>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
<?php if (count($slides) > 1): ?>
<script>
let currentSlide = 0;
const slides = document.querySelectorAll('.hero-slide');
const dots = document.querySelectorAll('.slider-dots .dot');
const totalSlides = slides.length;
let autoPlay;

function goToSlide(n) {
    slides[currentSlide].classList.remove('active');
    if (dots.length) dots[currentSlide].classList.remove('active');
    currentSlide = (n + totalSlides) % totalSlides;
    slides[currentSlide].classList.add('active');
    if (dots.length) dots[currentSlide].classList.add('active');
}

function changeSlide(dir) {
    goToSlide(currentSlide + dir);
    resetAutoPlay();
}

function resetAutoPlay() {
    clearInterval(autoPlay);
    autoPlay = setInterval(() => changeSlide(1), 5000);
}

autoPlay = setInterval(() => changeSlide(1), 5000);
</script>
<?php endif; ?>
</body>
</html>