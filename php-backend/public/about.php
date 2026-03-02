<?php
require_once __DIR__ . '/../includes/auth.php';
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

// About content
$aboutHistory = getSetting('about_history', 'Our school was established with a vision to provide quality education to students from diverse backgrounds. Over the years, we have grown into a premier educational institution known for academic excellence and holistic development.');
$aboutVision = getSetting('about_vision', 'To be a center of excellence in education, nurturing future leaders who are academically proficient, morally upright, and socially responsible.');
$aboutMission = getSetting('about_mission', 'To provide quality education through innovative teaching methods, foster critical thinking, and develop well-rounded individuals who contribute positively to society.');

// Core Values
$coreValues = [];
$defaultCoreValues = [
    1 => ['Excellence', 'We strive for the highest standards in academics, character, and personal growth.', 'bi-trophy', 'warning'],
    2 => ['Integrity', 'We foster honesty, transparency, and ethical behavior in all our actions.', 'bi-shield-check', 'danger'],
    3 => ['Innovation', 'We embrace creativity and modern teaching methods to inspire learning.', 'bi-lightbulb', 'primary'],
    4 => ['Community', 'We build a supportive, inclusive environment where everyone belongs.', 'bi-people', 'success'],
];
for ($i = 1; $i <= 4; $i++) {
    $coreValues[$i] = [
        'title' => getSetting("core_value_{$i}_title", $defaultCoreValues[$i][0]),
        'desc' => getSetting("core_value_{$i}_desc", $defaultCoreValues[$i][1]),
        'icon' => $defaultCoreValues[$i][2],
        'color' => $defaultCoreValues[$i][3],
    ];
}

// Inspirational Quote
$siteQuote = null;
try {
    $siteQuote = $db->query("SELECT quote_text, author_name, updated_at FROM site_quotes WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
} catch (Exception $e) {}

// Favicon
$favicon = getSetting('school_favicon', '');

// Bell notifications
$bellNotifs = $db->query("SELECT title, type, created_at FROM notifications WHERE status='approved' AND is_public=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
$notifCount = $db->query("SELECT COUNT(*) FROM notifications WHERE status='approved' AND is_public=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About Us — <?= e($schoolName) ?></title>
    <meta name="description" content="Learn about <?= e($schoolName) ?> — our history, vision, mission, and core values. <?= e($schoolTagline) ?>">
    <?php if ($favicon): $favVer = getSetting('favicon_updated_at', '0'); $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }



        /* Hero */
        .about-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e40af 100%);
            color: #fff; padding: 5rem 0 4rem; position: relative; overflow: hidden;
        }
        .about-hero::before {
            content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%); border-radius: 50%;
        }
        .about-hero::after {
            content: ''; position: absolute; bottom: -30%; left: -10%; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(139,92,246,0.1) 0%, transparent 70%); border-radius: 50%;
        }
        .hero-badge {
            display: inline-block; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2); padding: 0.4rem 1.2rem;
            border-radius: 50px; font-size: 0.75rem; letter-spacing: 2px;
            text-transform: uppercase; font-weight: 600; margin-bottom: 1.5rem;
        }

        /* Content cards */
        .about-card {
            border: none; border-radius: 16px; overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .about-card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); }
        .about-icon {
            width: 60px; height: 60px; border-radius: 14px; display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .value-card {
            border: none; border-radius: 16px; text-align: center; padding: 2rem 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .value-card:hover { transform: translateY(-6px); box-shadow: 0 15px 40px rgba(0,0,0,0.1); }
        .value-icon {
            width: 70px; height: 70px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1rem;
        }
        .section-heading {
            font-weight: 800; position: relative; display: inline-block; margin-bottom: 0.5rem;
        }
        .section-heading::after {
            content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            width: 60px; height: 4px; background: linear-gradient(90deg, #1e40af, #3b82f6); border-radius: 2px;
        }

        /* WhatsApp */
        .whatsapp-float { position: fixed; bottom: 24px; right: 24px; z-index: 9999; width: 60px; height: 60px; border-radius: 50%; background: #25D366; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; text-decoration: none; box-shadow: 0 4px 20px rgba(37,211,102,0.4); transition: transform 0.3s; animation: whatsappPulse 2s infinite; }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }
        @keyframes whatsappPulse { 0%, 100% { box-shadow: 0 4px 20px rgba(37,211,102,0.4); } 50% { box-shadow: 0 4px 30px rgba(37,211,102,0.7); } }

        /* Dark Footer */
        .site-footer { background: #1a1a2e; color: #fff; margin-top: 0; }
        .footer-cta { background: #0f2557; padding: 4rem 0; text-align: center; }
        .footer-cta h2 { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 2.2rem; color: #fff; margin-bottom: 1rem; }
        .footer-cta p { color: rgba(255,255,255,0.7); max-width: 600px; margin: 0 auto 1.5rem; }
        .footer-heading { text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; color: #fff; }
        .footer-heading::after { content: ''; position: absolute; bottom: 0; left: 0; width: 30px; height: 2px; background: var(--theme-primary, #1e40af); }
        .footer-link { color: rgba(255,255,255,0.65); text-decoration: none; transition: color 0.2s; font-size: 0.9rem; }
        .footer-link:hover { color: #fff; }
        .footer-social a { width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.3); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; font-size: 0.9rem; }
        .footer-social a:hover { background: var(--theme-primary, #1e40af); border-color: var(--theme-primary, #1e40af); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); }

        @media (max-width: 767.98px) {
            .about-hero { padding: 3rem 0 2.5rem; }
            .about-hero h1 { font-size: 2rem; }
            .top-bar .d-flex { flex-direction: column; gap: 0.3rem; text-align: center; }
        }
        @media (max-width: 575.98px) {
            .navbar-brand { }
            .navbar-brand img { width: 120px !important; height: auto !important; }
            .navbar-collapse .d-flex { flex-direction: column; width: 100%; gap: 0.5rem; margin-top: 0.75rem; }
            .notif-bell-btn, .login-nav-btn { width: 100%; text-align: center; display: block; }
            .top-bar .d-flex.gap-3 { font-size: 0.7rem; gap: 0.4rem !important; }
            .about-hero { padding: 2.5rem 0 2rem; }
            .about-hero h1 { font-size: 1.8rem; }
            .about-icon { width: 48px; height: 48px; font-size: 1.2rem; }
            .value-card { padding: 1.2rem 1rem; }
            .value-icon { width: 56px; height: 56px; font-size: 1.4rem; }
            .value-card h5 { font-size: 1rem; }
            .col-lg-5.col-md-6 { flex: 0 0 100%; max-width: 100%; }
            .site-footer .row > div { text-align: center; }
            .footer-heading::after { left: 50%; transform: translateX(-50%); }
            .footer-social { justify-content: center; }
            .site-footer { border-radius: 20px 20px 0 0; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'about'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero Section -->
<section class="about-hero">
    <div class="container position-relative text-center" style="z-index:2;">
        <div class="hero-badge"><i class="bi bi-info-circle me-2"></i>About Our School</div>
        <h1 class="display-4 mb-3" style="font-weight:900;"><?= e(getSetting('about_hero_title', 'About Us')) ?></h1>
        <p class="lead opacity-75 mx-auto" style="max-width:600px;"><?= e(getSetting('about_hero_subtitle', 'Discover our story, vision, and the values that drive us to provide exceptional education.')) ?></p>
    </div>
</section>

<!-- History Section -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card about-card shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-start gap-4 flex-column flex-md-row">
                            <div class="about-icon bg-primary-subtle text-primary flex-shrink-0">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-3">Our History</h3>
                                <p class="text-muted mb-0" style="line-height:1.8;"><?= nl2br(e($aboutHistory)) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vision & Mission -->
<section class="py-5" style="background:#f1f5f9;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading">Our Purpose</h2>
            <p class="text-muted mt-3">Guided by our vision and driven by our mission</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-5 col-md-6">
                <div class="card about-card shadow-sm h-100">
                    <div class="card-body p-4 text-center">
                        <div class="about-icon bg-info-subtle text-info mx-auto mb-3">
                            <i class="bi bi-eye"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Our Vision</h4>
                        <p class="text-muted mb-0" style="line-height:1.8;"><?= nl2br(e($aboutVision)) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 col-md-6">
                <div class="card about-card shadow-sm h-100">
                    <div class="card-body p-4 text-center">
                        <div class="about-icon bg-success-subtle text-success mx-auto mb-3">
                            <i class="bi bi-bullseye"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Our Mission</h4>
                        <p class="text-muted mb-0" style="line-height:1.8;"><?= nl2br(e($aboutMission)) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading">Our Core Values</h2>
            <p class="text-muted mt-3">The principles that guide everything we do</p>
        </div>
        <div class="row g-4">
            <?php foreach ($coreValues as $cv): ?>
            <div class="col-lg-3 col-md-6">
                <div class="card value-card shadow-sm h-100">
                    <div class="value-icon bg-<?= $cv['color'] ?>-subtle text-<?= $cv['color'] ?>"><i class="bi <?= $cv['icon'] ?>"></i></div>
                    <h5 class="fw-bold"><?= e($cv['title']) ?></h5>
                    <p class="text-muted small mb-0"><?= e($cv['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php
// Leadership Section
$leadershipShow = getSetting('about_leadership_show', '1');
$leadershipTitle = getSetting('about_leadership_title', 'Meet Our Leadership');
$leadershipSubtitle = getSetting('about_leadership_subtitle', 'With dedication and passion, our team creates an environment where every student thrives.');
$leaders = [];
if ($leadershipShow === '1') {
    try {
        $leaders = $db->query("SELECT * FROM leadership_profiles WHERE status='active' ORDER BY display_order ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>
<?php if ($leadershipShow === '1' && !empty($leaders)): ?>
<!-- Meet Our Leadership -->
<section class="py-5" style="background:#f8f6f3;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 style="font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:2.2rem;color:#1e293b;"><?= e($leadershipTitle) ?></h2>
            <?php if ($leadershipSubtitle): ?>
            <p style="font-style:italic;color:#64748b;max-width:600px;margin:1rem auto 0;font-size:1.05rem;line-height:1.7;">"<?= e($leadershipSubtitle) ?>"</p>
            <?php endif; ?>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($leaders as $leader):
                $leaderPhoto = $leader['photo'] ? ($leader['photo'] && strpos($leader['photo'], '/uploads/') === 0 ? $leader['photo'] : '/uploads/photos/' . $leader['photo']) : '';
            ?>
            <div class="col-lg-4 col-md-6 col-12 text-center">
                <div style="margin-bottom:1rem;">
                    <?php if ($leaderPhoto): ?>
                    <img src="<?= e($leaderPhoto) ?>" alt="<?= e($leader['name']) ?>" loading="lazy" style="width:200px;height:200px;border-radius:50%;object-fit:cover;border:4px solid rgba(220,180,180,0.4);margin:0 auto;">
                    <?php else: ?>
                    <div style="width:200px;height:200px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;margin:0 auto;border:4px solid rgba(220,180,180,0.4);">
                        <i class="bi bi-person-fill" style="font-size:4rem;color:#94a3b8;"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <h5 style="font-weight:700;color:#1e293b;margin-bottom:0.25rem;"><?= e($leader['name']) ?></h5>
                <?php if ($leader['designation']): ?>
                <p style="color:#dc2626;font-weight:600;font-size:0.95rem;margin-bottom:0;"><?= e($leader['designation']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Inspirational Quote Banner -->
<?php if ($siteQuote): ?>
<section class="py-5" style="background:#f1f5f9;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="quote-banner" style="background:#fff;border-radius:16px;padding:3rem 2.5rem;text-align:center;border-left:5px solid #1e40af;position:relative;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.06);opacity:0;transform:translateY(30px);transition:opacity 0.8s ease,transform 0.8s ease;">
                    <div style="position:absolute;top:-15px;left:25px;font-size:7rem;color:rgba(30,64,175,0.06);font-family:Georgia,serif;line-height:1;">"</div>
                    <p style="font-size:1.25rem;font-style:italic;color:#1e293b;line-height:1.8;margin-bottom:1.2rem;position:relative;z-index:1;font-family:'Playfair Display',Georgia,serif;">
                        "<?= e($siteQuote['quote_text']) ?>"
                    </p>
                    <?php if ($siteQuote['author_name']): ?>
                    <div style="font-size:.95rem;color:#475569;font-weight:600;">— <?= e($siteQuote['author_name']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:.7rem;color:#94a3b8;margin-top:.6rem;">
                        <i class="bi bi-clock me-1"></i>Last updated: <?= date('d M Y, h:i A', strtotime($siteQuote['updated_at'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
<script>
// Scroll animation for quote banner
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.style.opacity = '1';
            e.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.2 });
document.querySelectorAll('.quote-banner').forEach(el => observer.observe(el));
</script>
</body>
</html>