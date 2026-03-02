<?php
/**
 * Maintenance Mode Page
 * Include this from your public entry point (e.g. index.php) when maintenance_mode is enabled.
 * Displays the admin logo (falls back to school logo), school name, and a maintenance message.
 */

require_once __DIR__ . '/db.php';

$schoolName = 'Jawahar Navodaya Vidyalaya';
$schoolTagline = 'Nurturing Talent, Shaping Future';
$schoolLogo = 'uploads/branding/school_logo.png';
$adminLogo = '';
try {
    $db = getDB();
    $s = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('school_name','school_tagline','school_logo','admin_logo')");
    while ($r = $s->fetch()) {
        if ($r['setting_key'] === 'school_name') $schoolName = $r['setting_value'];
        if ($r['setting_key'] === 'school_tagline') $schoolTagline = $r['setting_value'];
        if ($r['setting_key'] === 'school_logo' && $r['setting_value']) $schoolLogo = $r['setting_value'];
        if ($r['setting_key'] === 'admin_logo' && $r['setting_value']) $adminLogo = $r['setting_value'];
    }
} catch (Exception $ex) {}

$schoolLogoPath = (strpos($schoolLogo, '/uploads/') === 0 || strpos($schoolLogo, 'uploads/') === 0)
    ? '/' . ltrim($schoolLogo, '/')
    : '/uploads/branding/' . $schoolLogo;
$adminLogoPath = $adminLogo ? ((strpos($adminLogo, '/uploads/') === 0 || strpos($adminLogo, 'uploads/') === 0) ? '/' . ltrim($adminLogo, '/') : '/uploads/branding/' . $adminLogo) : '';
$logoSrc = $adminLogoPath ? $adminLogoPath : $schoolLogoPath;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance — <?= htmlspecialchars($schoolName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{font-family:'Inter',sans-serif}body{margin:0;min-height:100vh}
        .split-container{display:flex;min-height:100vh}
        .left-panel{flex:1;background:linear-gradient(135deg,#0f172a 0%,#1e40af 50%,#3b82f6 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;padding:3rem;position:relative;overflow:hidden}
        .left-panel::before{content:'';position:absolute;top:-50%;right:-50%;width:100%;height:200%;background:radial-gradient(circle,rgba(255,255,255,0.05) 0%,transparent 60%);animation:float 15s ease-in-out infinite}
        .left-panel::after{content:'';position:absolute;bottom:-30%;left:-30%;width:80%;height:80%;border-radius:50%;background:radial-gradient(circle,rgba(59,130,246,0.3) 0%,transparent 70%);animation:float 20s ease-in-out infinite reverse}
        @keyframes float{0%,100%{transform:translate(0,0)}50%{transform:translate(30px,-30px)}}
        .left-content{position:relative;z-index:1;text-align:center;max-width:400px}
        .school-icon{width:auto;height:auto;background:none;border-radius:0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}
        .school-icon img{max-width:180px;max-height:180px;object-fit:contain}
        .left-content h1{font-size:2rem;font-weight:800;margin-bottom:.5rem}
        .left-content p{font-size:1.05rem;opacity:.85;margin-bottom:2rem}
        .right-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem;background:#fff}
        .maint-box{width:100%;max-width:440px;text-align:center}
        .maint-box h2{font-weight:700;color:#0f172a;margin-bottom:.5rem;font-size:1.75rem}
        .maint-box p{color:#64748b;font-size:1rem;line-height:1.6}
        .maint-icon{font-size:4rem;color:#f59e0b;margin-bottom:1.5rem}
        .btn-login-link{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1.5rem;border-radius:10px;font-weight:600;font-size:.9rem;background:linear-gradient(135deg,#1e40af,#3b82f6);border:none;color:#fff;text-decoration:none;transition:all .3s;margin-top:1.5rem}
        .btn-login-link:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(59,130,246,.4);color:#fff}
        @media(max-width:991.98px){.split-container{flex-direction:column}.left-panel{padding:2rem;min-height:auto}.left-content h1{font-size:1.5rem}}
        @media(max-width:575.98px){.right-panel{padding:1.5rem}.left-panel{padding:1.5rem}.left-content h1{font-size:1.3rem}}
    </style>
</head>
<body>
<div class="split-container">
    <div class="left-panel">
        <div class="left-content">
            <div class="school-icon"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="<?= htmlspecialchars($schoolName) ?> Logo"></div>
            <h1><?= htmlspecialchars($schoolName) ?></h1>
            <p><?= htmlspecialchars($schoolTagline) ?></p>
        </div>
    </div>
    <div class="right-panel">
        <div class="maint-box">
            <div class="maint-icon"><i class="bi bi-gear-wide-connected"></i></div>
            <h2>Under Maintenance</h2>
            <p>We're currently performing scheduled maintenance to improve your experience. The website will be back online shortly.</p>
            <p style="font-size:.9rem;color:#94a3b8;margin-top:1rem">Thank you for your patience.</p>
            <a href="/login.php" class="btn-login-link"><i class="bi bi-box-arrow-in-right"></i> Admin Login</a>
            <div class="text-center mt-4" style="font-size:.8rem;color:#94a3b8">&copy; <?= date('Y') ?> <?= htmlspecialchars($schoolName) ?>. All rights reserved.</div>
        </div>
    </div>
</div>
</body>
</html>