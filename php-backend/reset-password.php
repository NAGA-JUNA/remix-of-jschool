<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: /'); exit; }

$error = ''; $success = ''; $validToken = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($token) {
    $db = getDB();
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > ? AND is_active = 1");
    $stmt->execute([$token, $now]);
    $user = $stmt->fetch();
    if ($user) $validToken = true;
    else $error = 'Invalid or expired reset link. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!verifyCsrf()) { $error = 'Invalid request.'; }
    else {
        $pass = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($pass) < 6) { $error = 'Password must be at least 6 characters.'; }
        elseif ($pass !== $confirm) { $error = 'Passwords do not match.'; }
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$hash, $user['id']]);
            auditLog('password_reset', 'user', $user['id']);
            $success = 'Password reset successfully! You can now login.';
            $validToken = false;
        }
    }
}
$schoolName = 'Jawahar Navodaya Vidyalaya';
$schoolTagline = 'Nurturing Talent, Shaping Future';
$schoolLogo = 'uploads/branding/school_logo.png';
try {
    $s = getDB()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('school_name','school_tagline','school_logo','admin_logo')");
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
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reset Password — <?= htmlspecialchars($schoolName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{font-family:'Inter',sans-serif}body{margin:0;min-height:100vh}.split-container{display:flex;min-height:100vh}.left-panel{flex:1;background:linear-gradient(135deg,#0f172a,#1e40af,#3b82f6);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;padding:3rem;position:relative;overflow:hidden}.left-panel::before{content:'';position:absolute;top:-50%;right:-50%;width:100%;height:200%;background:radial-gradient(circle,rgba(255,255,255,0.05),transparent 60%);animation:float 15s ease-in-out infinite}@keyframes float{0%,100%{transform:translate(0,0)}50%{transform:translate(30px,-30px)}}.left-content{position:relative;z-index:1;text-align:center;max-width:400px}.school-icon{width:auto;height:auto;background:none;border-radius:0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}.school-icon img{max-width:180px;max-height:180px;object-fit:contain}.right-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem;background:#fff}.form-box{width:100%;max-width:400px}.form-box h2{font-weight:700;color:#0f172a;margin-bottom:.25rem}.form-box p.subtitle{color:#64748b;margin-bottom:2rem}.form-control{border-radius:10px;padding:.75rem 1rem;border-color:#e2e8f0}.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}.btn-submit{width:100%;padding:.75rem;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#1e40af,#3b82f6);border:none;color:#fff;transition:all .3s}.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(59,130,246,.4);color:#fff}.input-group-text{border-radius:10px 0 0 10px;background:#f8fafc;border-color:#e2e8f0;color:#64748b}.input-group .form-control{border-radius:0 10px 10px 0}@media(max-width:991.98px){.split-container{flex-direction:column}.left-panel{padding:2rem;min-height:auto}}@media(max-width:575.98px){.right-panel{padding:1.5rem}.form-box{max-width:100%}.left-panel{padding:1.5rem}.school-icon img{max-width:140px;max-height:140px}.form-control{padding:.6rem .8rem}.btn-submit{padding:.6rem}}
    </style>
</head>
<body>
<div class="split-container">
    <div class="left-panel"><div class="left-content"><div class="school-icon"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="<?= htmlspecialchars($schoolName) ?> Logo"></div><h1 style="font-size:2rem;font-weight:800"><?= htmlspecialchars($schoolName) ?></h1><p style="opacity:.85">Create a new secure password</p></div></div>
    <div class="right-panel">
        <div class="form-box">
            <h2>Reset Password</h2>
            <p class="subtitle">Enter your new password below</p>
            <?php if ($success): ?><div class="alert alert-success py-2" style="border-radius:10px"><i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?></div><a href="/login.php" class="btn btn-submit mt-2"><i class="bi bi-box-arrow-in-right me-2"></i>Go to Login</a><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger py-2" style="border-radius:10px"><i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?></div><?php if(!$validToken): ?><a href="/forgot-password.php" class="btn btn-submit mt-2">Request New Link</a><?php endif; endif; ?>
            <?php if ($validToken): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                    <label class="form-label fw-medium" style="font-size:.875rem;color:#334155">New Password</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium" style="font-size:.875rem;color:#334155">Confirm Password</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-lock-fill"></i></span><input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required minlength="6"></div>
                </div>
                <button type="submit" class="btn btn-submit"><i class="bi bi-check-lg me-2"></i>Reset Password</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>