<?php
require_once __DIR__ . '/../includes/auth.php';
checkMaintenance();
$db = getDB();
$schoolName = getSetting('school_name', 'JNV School');
$schoolTagline = getSetting('school_tagline', 'Nurturing Talent, Shaping Future');
$schoolEmail = getSetting('school_email', '');
$schoolPhone = getSetting('school_phone', '');
$navLogo = getSetting('school_logo', '');
$logoVersion = getSetting('logo_updated_at', '0');
$logoPath = '';
if ($navLogo) { $logoPath = (strpos($navLogo, '/uploads/') === 0) ? $navLogo : (file_exists(__DIR__.'/../uploads/branding/'.$navLogo) ? '/uploads/branding/'.$navLogo : '/uploads/logo/'.$navLogo); $logoPath .= '?v=' . $logoVersion; }
$whatsappNumber = getSetting('whatsapp_api_number', '');
$schoolAddress = getSetting('school_address', '');
$primaryColor = getSetting('primary_color', '#1e40af');

// Social links
$socialFacebook = getSetting('social_facebook', '');
$socialTwitter = getSetting('social_twitter', '');
$socialInstagram = getSetting('social_instagram', '');
$socialYoutube = getSetting('social_youtube', '');
$socialLinkedin = getSetting('social_linkedin', '');

// If logged in, redirect
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
    exit;
}

// Get all active teachers
$teachers = $db->query("SELECT * FROM teachers WHERE status='active' AND is_visible=1 ORDER BY display_order ASC, name ASC")->fetchAll();
$totalTeachers = count($teachers);

// Get principal for message section
$principal = $db->prepare("SELECT * FROM teachers WHERE status='active' AND designation='Principal' AND bio IS NOT NULL AND bio != '' LIMIT 1");
$principal->execute();
$principal = $principal->fetch();

// Notification count for bell
$notifCount = $db->query("SELECT COUNT(*) FROM notifications WHERE status='approved' AND is_public=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$bellNotifs = $db->query("SELECT title, type, created_at FROM notifications WHERE status='approved' AND is_public=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Our Teachers — <?= e($schoolName) ?></title>
    <meta name="description" content="Meet the dedicated educators at <?= e($schoolName) ?>. Our qualified and experienced teachers are committed to academic excellence.">
    <?php $favicon = getSetting('school_favicon', ''); if ($favicon): $favVer = getSetting('favicon_updated_at', '0'); $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-primary: <?= e($primaryColor) ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }



        /* Hero Section */
        .teachers-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e40af 100%);
            color: #fff; padding: 5rem 0 4rem; position: relative; overflow: hidden;
        }
        .teachers-hero::before {
            content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%); border-radius: 50%;
        }
        .teachers-hero::after {
            content: ''; position: absolute; bottom: -30%; left: -10%; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(139,92,246,0.1) 0%, transparent 70%); border-radius: 50%;
        }
        .hero-badge {
            display: inline-block; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2); padding: 0.4rem 1.2rem;
            border-radius: 50px; font-size: 0.75rem; letter-spacing: 2px;
            text-transform: uppercase; font-weight: 600; margin-bottom: 1.5rem;
        }
        .hero-stat-card {
            background: rgba(255,255,255,0.08); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.12); border-radius: 16px;
            padding: 1.5rem; text-align: center; transition: transform 0.3s;
        }
        .hero-stat-card:hover { transform: translateY(-4px); }
        .hero-stat-card .num { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .hero-stat-card .label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-top: 0.3rem; }

        /* Principal Message */
        .principal-section { background: #fff; padding: 4rem 0; }
        .principal-photo {
            width: 100%; max-width: 350px; aspect-ratio: 3/4; object-fit: cover;
            border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        }
        .quote-box {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-left: 4px solid #1e40af; border-radius: 0 16px 16px 0;
            padding: 2rem; position: relative;
        }
        .quote-box::before {
            content: '"'; position: absolute; top: -10px; left: 15px;
            font-size: 5rem; color: #1e40af; opacity: 0.15; font-family: Georgia, serif; line-height: 1;
        }

        /* Teacher Cards */
        .teachers-grid { padding: 4rem 0; }
        .teacher-card { perspective: 1000px; height: 380px; cursor: pointer; }
        .teacher-card-inner {
            position: relative; width: 100%; height: 100%;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); transform-style: preserve-3d;
        }
        .teacher-card:hover .teacher-card-inner { transform: rotateY(180deg); }
        .teacher-card-front, .teacher-card-back {
            position: absolute; width: 100%; height: 100%;
            backface-visibility: hidden; border-radius: 16px; overflow: hidden;
        }
        .teacher-card-front { background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .teacher-card-front img { width: 100%; height: 260px; object-fit: cover; }
        .teacher-card-front .no-photo {
            width: 100%; height: 260px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #64748b; font-size: 4rem;
        }
        .teacher-card-front .info { padding: 1rem 1.2rem; text-align: center; }
        .teacher-card-front .info h6 { font-weight: 700; margin-bottom: 0.2rem; font-size: 1rem; }
        .teacher-card-front .info small { color: #64748b; font-size: 0.85rem; }
        .teacher-card-back {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff; transform: rotateY(180deg);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 2rem; text-align: center;
        }
        .teacher-card-back .back-icon {
            width: 70px; height: 70px; border-radius: 50%;
            background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center;
            font-size: 2rem; margin-bottom: 1rem;
        }
        .teacher-card-back h6 { font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .teacher-card-back .detail { opacity: 0.85; font-size: 0.85rem; margin-bottom: 0.3rem; }
        .teacher-card-back .badge-pill {
            background: rgba(255,255,255,0.2); padding: 0.3rem 1rem;
            border-radius: 50px; font-size: 0.75rem; margin-top: 0.5rem; letter-spacing: 0.5px;
        }
        .section-heading { font-weight: 800; position: relative; display: inline-block; margin-bottom: 0.5rem; }
        .section-heading::after {
            content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            width: 60px; height: 4px; background: linear-gradient(90deg, #1e40af, #3b82f6); border-radius: 2px;
        }

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

        /* Dark Footer */
        .site-footer { background: #1a1a2e; color: #fff; margin-top: 0; }
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

        /* Tap to flip for touch */
        .teacher-card.flipped .teacher-card-inner { transform: rotateY(180deg); }

        @media (max-width: 767.98px) {
            .teachers-hero { padding: 3rem 0 2.5rem; }
            .teachers-hero h1 { font-size: 2rem; }
            .teacher-card { height: 350px; }
            .teacher-card-front img, .teacher-card-front .no-photo { height: 230px; }
            .principal-photo { max-width: 250px; margin: 0 auto; }
            .top-bar .d-flex { flex-direction: column; gap: 0.3rem; text-align: center; }
            .hero-stat-card .num { font-size: 2rem; }
        }
        @media (max-width: 575.98px) {
            .navbar-brand { }
            .navbar-brand img { width: 120px !important; height: auto !important; }
            .navbar-collapse .d-flex { flex-direction: column; width: 100%; gap: 0.5rem; margin-top: 0.75rem; }
            .notif-bell-btn, .login-nav-btn { width: 100%; text-align: center; display: block; }
            .top-bar .d-flex.gap-3 { font-size: 0.7rem; gap: 0.4rem !important; }
            .teachers-hero { padding: 2.5rem 0 2rem; }
            .teachers-hero h1 { font-size: 1.8rem; }
            .teacher-card { height: 300px; }
            .teacher-card-front img, .teacher-card-front .no-photo { height: 200px; }
            .teacher-card-back { padding: 1rem; }
            .teacher-card-back .back-icon { width: 50px; height: 50px; font-size: 1.5rem; margin-bottom: 0.5rem; }
            .teacher-card-back h6 { font-size: 0.95rem; }
            .teacher-card-back .detail { font-size: 0.75rem; }
            .hero-stat-card { padding: 1rem; }
            .hero-stat-card .num { font-size: 1.5rem; }
            .hero-stat-card .label { font-size: 0.7rem; }
            .principal-photo { max-width: 200px; }
            .quote-box { padding: 1.2rem; }
            .site-footer .row > div { text-align: center; }
            .footer-heading::after { left: 50%; transform: translateX(-50%); }
            .footer-social { justify-content: center; }
            .site-footer { border-radius: 20px 20px 0 0; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'teachers'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero Section -->
<section class="teachers-hero">
    <div class="container position-relative" style="z-index:2;">
        <div class="text-center mb-4">
            <div class="hero-badge"><i class="bi bi-people-fill me-2"></i>Our Educators</div>
            <h1 class="mb-3" style="font-family:'Playfair Display',serif;font-style:italic;font-weight:700;font-size:3rem;"><?= e(getSetting('teachers_hero_title', 'Our Teachers')) ?></h1>
            <p class="lead opacity-75 mx-auto" style="max-width:600px;"><?= e(getSetting('teachers_hero_subtitle', 'Meet our dedicated team of qualified educators who inspire, guide, and shape the future of every student.')) ?></p>
        </div>
        <div class="row g-3 justify-content-center mt-4">
            <div class="col-6 col-md-4"><div class="hero-stat-card"><div class="num"><?= $totalTeachers ?>+</div><div class="label">Expert Teachers</div></div></div>
            <div class="col-6 col-md-4"><div class="hero-stat-card"><div class="num">15+</div><div class="label">Years Experience</div></div></div>
        </div>
    </div>
</section>

<!-- Principal's Message -->
<?php if ($principal): ?>
<section class="principal-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 mb-3" style="font-size:0.75rem;letter-spacing:1px;text-transform:uppercase;">Principal's Message</span>
            <h2 class="section-heading" style="font-family:'Playfair Display',serif;">From the Principal's Desk</h2>
        </div>
        <div class="row g-4 align-items-center justify-content-center">
            <div class="col-md-4 text-center">
                <?php
                    $pPhoto = $principal['photo'] ? (str_starts_with($principal['photo'], '/uploads/') ? $principal['photo'] : '/uploads/photos/'.$principal['photo']) : '';
                ?>
                <?php if ($pPhoto): ?>
                    <img src="<?= e($pPhoto) ?>" alt="<?= e($principal['name']) ?>" class="principal-photo">
                <?php else: ?>
                    <div class="principal-photo d-flex align-items-center justify-content-center mx-auto" style="background:linear-gradient(135deg,#e2e8f0,#cbd5e1);">
                        <i class="bi bi-person-fill" style="font-size:6rem;color:#94a3b8;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-7">
                <div class="quote-box">
                    <p class="mb-3" style="font-size:1.05rem;line-height:1.8;color:#334155;">
                        <?= nl2br(e($principal['bio'])) ?>
                    </p>
                    <div class="d-flex align-items-center gap-3 mt-3 pt-3" style="border-top:1px solid rgba(30,64,175,0.1);">
                        <div>
                            <h6 class="fw-bold mb-0" style="color:#1e40af;"><?= e($principal['name']) ?></h6>
                            <small class="text-muted"><?= e($principal['designation'] ?? 'Principal') ?></small>
                            <?php if ($principal['qualification']): ?>
                                <small class="text-muted d-block"><?= e($principal['qualification']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Teachers Grid -->
<section class="teachers-grid" style="background:#f1f5f9;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading">Meet Our Faculty</h2>
            <p class="text-muted mt-3">Hover on a card to learn more about each teacher</p>
        </div>
        <div class="row g-4">
            <?php foreach ($teachers as $t):
                $tPhoto = $t['photo'] ? (str_starts_with($t['photo'], '/uploads/') ? $t['photo'] : '/uploads/photos/'.$t['photo']) : '';
            ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="teacher-card">
                    <div class="teacher-card-inner">
                        <div class="teacher-card-front">
                            <?php if ($tPhoto): ?>
                                <img src="<?= e($tPhoto) ?>" alt="<?= e($t['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="no-photo"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>
                            <div class="info">
                                <h6><?= e($t['name']) ?> <?php if (!empty($t['is_featured'])): ?><span class="badge bg-warning text-dark" style="font-size:0.6rem;vertical-align:middle;">★ Featured</span><?php endif; ?></h6>
                                <small><?= e($t['designation'] ?? 'Teacher') ?></small>
                            </div>
                        </div>
                        <div class="teacher-card-back">
                            <div class="back-icon"><i class="bi bi-mortarboard-fill"></i></div>
                            <h6><?= e($t['name']) ?></h6>
                            <?php if ($t['designation']): ?>
                                <div class="detail"><i class="bi bi-briefcase me-1"></i><?= e($t['designation']) ?></div>
                            <?php endif; ?>
                            <?php if ($t['qualification']): ?>
                                <div class="detail"><i class="bi bi-award me-1"></i><?= e($t['qualification']) ?></div>
                            <?php endif; ?>
                            <?php if ($t['subject']): ?>
                                <div class="detail"><i class="bi bi-book me-1"></i><?= e($t['subject']) ?></div>
                            <?php endif; ?>
                            <?php if ($t['experience_years']): ?>
                                <div class="badge-pill"><?= e($t['experience_years']) ?> Years Experience</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($teachers)): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-people text-muted" style="font-size:3rem;"></i>
                    <h5 class="text-muted mt-3">No teachers available</h5>
                    <p class="text-muted">Teacher profiles will appear here once added by the admin.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
<script>
// Tap-to-flip for touch devices
if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
    document.querySelectorAll('.teacher-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            document.querySelectorAll('.teacher-card.flipped').forEach(function(c) {
                if (c !== card) c.classList.remove('flipped');
            });
            card.classList.toggle('flipped');
        });
    });
}
</script>
</body>
</html>