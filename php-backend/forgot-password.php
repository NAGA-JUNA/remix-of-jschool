<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/mail.php';
if (isLoggedIn()) { header('Location: /'); exit; }

$success = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { $error = 'Invalid request.'; }
    else {
        $email = trim($_POST['email'] ?? '');
        if (!$email) { $error = 'Please enter your email.'; }
        else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
                $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$token, $expires, $user['id']]);
                $resetUrl = 'https://jnvschool.awayindia.com/reset-password.php?token=' . $token;
                $body = "<h2>Password Reset</h2><p>Hi {$user['name']},</p><p>Click below to reset your password:</p><p><a href='{$resetUrl}' style='padding:10px 24px;background:#1e40af;color:#fff;text-decoration:none;border-radius:8px;'>Reset Password</a></p><p>This link expires in 1 hour.</p>";
                sendMail($email, 'Password Reset — JNV School', $body);
            }
            $success = 'If that email exists, a reset link has been sent.';
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
    <title>Forgot Password — <?= htmlspecialchars($schoolName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{font-family:'Inter',sans-serif}body{margin:0;min-height:100vh}.split-container{display:flex;min-height:100vh}.left-panel{flex:1;background:linear-gradient(135deg,#0f172a,#1e40af,#3b82f6);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;padding:3rem;position:relative;overflow:hidden}.left-panel::before{content:'';position:absolute;top:-50%;right:-50%;width:100%;height:200%;background:radial-gradient(circle,rgba(255,255,255,0.05),transparent 60%);animation:float 15s ease-in-out infinite}@keyframes float{0%,100%{transform:translate(0,0)}50%{transform:translate(30px,-30px)}}.left-content{position:relative;z-index:1;text-align:center;max-width:400px}.school-icon{width:auto;height:auto;background:none;border-radius:0;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}.school-icon img{max-width:180px;max-height:180px;object-fit:contain}.right-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem;background:#fff}.form-box{width:100%;max-width:400px}.form-box h2{font-weight:700;color:#0f172a;margin-bottom:.25rem}.form-box p.subtitle{color:#64748b;margin-bottom:2rem}.form-control{border-radius:10px;padding:.75rem 1rem;border-color:#e2e8f0}.form-control:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}.btn-submit{width:100%;padding:.75rem;border-radius:10px;font-weight:600;background:linear-gradient(135deg,#1e40af,#3b82f6);border:none;color:#fff;transition:all .3s}.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(59,130,246,.4);color:#fff}.input-group-text{border-radius:10px 0 0 10px;background:#f8fafc;border-color:#e2e8f0;color:#64748b}.input-group .form-control{border-radius:0 10px 10px 0}@media(max-width:991.98px){.split-container{flex-direction:column}.left-panel{padding:2rem;min-height:auto}}@media(max-width:575.98px){.right-panel{padding:1.5rem}.form-box{max-width:100%}.left-panel{padding:1.5rem}.school-icon img{max-width:140px;max-height:140px}.form-control{padding:.6rem .8rem}.btn-submit{padding:.6rem}}
    </style>
</head>
<body>
<div class="split-container">
    <div class="left-panel"><div class="left-content"><div class="school-icon"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="<?= htmlspecialchars($schoolName) ?> Logo"></div><h1 style="font-size:2rem;font-weight:800"><?= htmlspecialchars($schoolName) ?></h1><p style="opacity:.85">Reset your account password securely</p></div></div>
    <div class="right-panel">
        <div class="form-box">
            <h2>Forgot Password?</h2>
            <p class="subtitle">Enter your email and we'll send you a reset link</p>
            <?php if ($success): ?><div class="alert alert-success py-2" style="border-radius:10px"><i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger py-2" style="border-radius:10px"><i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-medium" style="font-size:.875rem;color:#334155">Email Address</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="email" name="email" class="form-control" placeholder="your@email.com" required></div>
                </div>
                <button type="submit" class="btn btn-submit"><i class="bi bi-send me-2"></i>Send Reset Link</button>
            </form>
            <div class="text-center mt-3"><a href="/login.php" class="text-decoration-none" style="font-size:.875rem"><i class="bi bi-arrow-left me-1"></i>Back to Login</a></div>
        </div>
    </div>
</div>
</body>
</html>