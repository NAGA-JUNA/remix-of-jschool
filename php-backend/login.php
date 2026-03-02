<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $error = 'Please enter both email and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user;
                $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                auditLog('login', 'user', $user['id']);
                header('Location: ' . (in_array($user['role'], ['super_admin','admin','office']) ? '/admin/dashboard.php' : '/teacher/dashboard.php'));
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$schoolName = 'Jawahar Navodaya Vidyalaya';
$schoolTagline = 'Nurturing Talent, Shaping Future';
$schoolLogo = 'uploads/branding/school_logo.png';
try {
    $db = getDB();
    $s = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('school_name','school_tagline','school_logo','admin_logo')");
    $adminLogo = '';
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
    <title>Login — <?= htmlspecialchars($schoolName) ?></title>
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
        .feature-list{list-style:none;padding:0;text-align:left}
        .feature-list li{padding:.5rem 0;font-size:.9rem;opacity:.9;display:flex;align-items:center;gap:.75rem}
        .feature-list li i{color:#60a5fa;font-size:1.1rem}
        .right-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem;background:#fff}
        .login-form{width:100%;max-width:400px}
        .login-form h2{font-weight:700;color:#0f172a;margin-bottom:.25rem}
        .login-form p.subtitle{color:#64748b;margin-bottom:2rem}
        .form-control{border-radius:10px;padding:.75rem 1rem;border-color:#e2e8f0}
        .form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
        .form-label{font-weight:500;color:#334155;font-size:.875rem}
        .btn-login{width:100%;padding:.75rem;border-radius:10px;font-weight:600;font-size:1rem;background:linear-gradient(135deg,#1e40af,#3b82f6);border:none;color:#fff;transition:all .3s}
        .btn-login:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(59,130,246,.4);background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff}
        .input-group-text{border-radius:10px 0 0 10px;background:#f8fafc;border-color:#e2e8f0;color:#64748b}
        .input-group .form-control{border-radius:0 10px 10px 0}
        @media(max-width:991.98px){.split-container{flex-direction:column}.left-panel{padding:2rem;min-height:auto}.left-content h1{font-size:1.5rem}.feature-list{display:none}}
        @media(max-width:575.98px){.right-panel{padding:1.5rem}.login-form{max-width:100%}.left-panel{padding:1.5rem}.left-content h1{font-size:1.3rem}.school-icon img{max-width:140px;max-height:140px}.form-control{padding:.6rem .8rem}.btn-login{padding:.6rem}}
    </style>
</head>
<body>
<div class="split-container">
    <div class="left-panel">
        <div class="left-content">
            <div class="school-icon"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="<?= htmlspecialchars($schoolName) ?> Logo"></div>
            <h1><?= htmlspecialchars($schoolName) ?></h1>
            <p><?= htmlspecialchars($schoolTagline) ?></p>
            <ul class="feature-list">
                <li><i class="bi bi-shield-check"></i> Secure Admin & Teacher Portal</li>
                <li><i class="bi bi-people-fill"></i> Student & Staff Management</li>
                <li><i class="bi bi-bar-chart-line-fill"></i> Attendance & Exam Tracking</li>
                <li><i class="bi bi-bell-fill"></i> Notifications & Gallery</li>
                <li><i class="bi bi-calendar-event-fill"></i> Events & Reports</li>
            </ul>
        </div>
    </div>
    <div class="right-panel">
        <div class="login-form">
            <a href="/" class="d-inline-flex align-items-center gap-1 text-decoration-none mb-4" style="color:#64748b;font-size:0.88rem;"><i class="bi bi-arrow-left"></i> Back to Website</a>
            <h2>Welcome back</h2>
            <p class="subtitle">Sign in to your account to continue</p>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 d-flex align-items-center gap-2" style="border-radius:10px"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="on">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="admin@school.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between">Password <a href="/forgot-password.php" class="text-decoration-none" style="font-size:.8rem">Forgot Password?</a></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required id="password">
                        <button type="button" class="input-group-text" onclick="togglePassword()" style="cursor:pointer;border-radius:0 10px 10px 0"><i class="bi bi-eye" id="toggleIcon"></i></button>
                    </div>
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember" style="font-size:.875rem;color:#64748b">Remember me</label>
                </div>
                <button type="submit" class="btn btn-login"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
            </form>
            <div class="text-center mt-4" style="font-size:.8rem;color:#94a3b8">&copy; <?= date('Y') ?> <?= htmlspecialchars($schoolName) ?>. All rights reserved.</div>
        </div>
    </div>
</div>
<script>
function togglePassword(){const p=document.getElementById('password'),i=document.getElementById('toggleIcon');if(p.type==='password'){p.type='text';i.className='bi bi-eye-slash'}else{p.type='password';i.className='bi bi-eye'}}
</script>
</body>
</html>