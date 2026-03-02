<?php
// Header include — requires auth.php already loaded
$schoolName = getSetting('school_name', 'JNV School');
$schoolLogo = getSetting('school_logo', '');
$adminLogo = getSetting('admin_logo', '');
$schoolTagline = getSetting('school_tagline', 'Excellence in Education');
$schoolShortName = getSetting('school_short_name', 'JNV');
$_logoVer = getSetting('logo_updated_at', '0');
$_adminLogoVer = getSetting('admin_logo_updated_at', '0');
$primaryColor = getSetting('primary_color', '#1e40af');
$brandPrimary = getSetting('brand_primary', '#1e40af');
$brandSecondary = getSetting('brand_secondary', '#6366f1');
$brandAccent = getSetting('brand_accent', '#f59e0b');
$pageTitle = $pageTitle ?? 'Dashboard';
$flash = getFlash();

// Determine active nav
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
function navActive(string $path): string {
    global $currentPath;
    return str_contains($currentPath, $path) ? 'active' : '';
}

// User info for profile dropdown
$_currentUser = currentUser();
$_userName = $_currentUser['name'] ?? 'User';
$_userRole = currentRole() ?? 'user';
$_userEmail = $_currentUser['email'] ?? '';
$_userInitials = '';
$_nameParts = explode(' ', $_userName);
$_userInitials .= strtoupper(substr($_nameParts[0] ?? '', 0, 1));
if (count($_nameParts) > 1) $_userInitials .= strtoupper(substr(end($_nameParts), 0, 1));
if (!$_userInitials) $_userInitials = 'U';

$_roleBadgeMap = [
    'super_admin' => ['Super Admin', 'badge-role-super'],
    'admin' => ['Admin', 'badge-role-admin'],
    'office' => ['Office', 'badge-role-office'],
    'teacher' => ['Teacher', 'badge-role-teacher'],
];
$_roleLabel = $_roleBadgeMap[$_userRole][0] ?? ucfirst($_userRole);
$_roleBadgeClass = $_roleBadgeMap[$_userRole][1] ?? 'badge-role-default';

// Notification counts for badges
$_notifCount = 0;
$_admissionCount = 0;
$_recruitmentCount = 0;
try {
    global $pdo;
    if (isset($pdo) && isAdmin()) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE status='pending'");
        $_notifCount = (int)$stmt->fetchColumn();
        $stmt2 = $pdo->query("SELECT COUNT(*) FROM admissions WHERE status IN ('new','contacted')");
        $_admissionCount = (int)$stmt2->fetchColumn();
        // Recruitment: count new applications
        try {
            $stmt3 = $pdo->query("SELECT COUNT(*) FROM teacher_applications WHERE status='new' AND is_deleted=0");
            $_recruitmentCount = (int)$stmt3->fetchColumn();
        } catch (Exception $e) { /* table may not exist yet */ }
    }
} catch (Exception $e) { /* silent */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($schoolName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Theme init (prevent flash) -->
    <script>
    (function(){
        var t=localStorage.getItem('admin_theme')||'light';
        document.documentElement.setAttribute('data-theme',t);
        var c=localStorage.getItem('sidebar_collapsed')==='true';
        if(c) document.documentElement.classList.add('sidebar-is-collapsed');
    })();
    </script>
    <style>
        :root {
            --primary: <?= e($primaryColor) ?>;
            --brand-primary: <?= e($brandPrimary) ?>;
            --brand-secondary: <?= e($brandSecondary) ?>;
            --brand-accent: <?= e($brandAccent) ?>;
            --brand-primary-light: <?= e($brandPrimary) ?>22;
            --brand-secondary-light: <?= e($brandSecondary) ?>22;
            --brand-accent-light: <?= e($brandAccent) ?>22;
            --sidebar-width: 264px;
            --sidebar-collapsed-width: 78px;
            --sidebar-margin: 12px;

            /* Light theme */
            --bg-body: #f4f2ee;
            --bg-card: #ffffff;
            --bg-topbar: #ffffff;
            --text-primary: #1a1a1a;
            --text-secondary: #374151;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);

            /* Sidebar – Light */
            --sidebar-bg: #faf8f5;
            --sidebar-text: #1a1a1a;
            --sidebar-text-muted: #9ca3af;
            --sidebar-hover: rgba(0,0,0,0.04);
            --sidebar-active-bg: var(--brand-primary);
            --sidebar-active-text: #ffffff;
            --sidebar-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.04);
            --sidebar-border: rgba(0,0,0,0.06);
            --sidebar-divider: rgba(0,0,0,0.06);
            --sidebar-profile-bg: rgba(0,0,0,0.02);
            --sidebar-theme-bg: rgba(0,0,0,0.04);
            --sidebar-theme-active: #ffffff;
        }
        html[data-theme="dark"] {
            --brand-primary-light: <?= e($brandPrimary) ?>33;
            --brand-secondary-light: <?= e($brandSecondary) ?>33;
            --brand-accent-light: <?= e($brandAccent) ?>33;
            --bg-body: #111111;
            --bg-card: #1c1c1c;
            --bg-topbar: #1c1c1c;
            --text-primary: #e5e5e5;
            --text-secondary: #d1d5db;
            --text-muted: #6b7280;
            --border-color: rgba(255,255,255,0.08);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.4);

            /* Sidebar – Dark */
            --sidebar-bg: #1a1a1a;
            --sidebar-text: #e5e5e5;
            --sidebar-text-muted: #6b7280;
            --sidebar-hover: rgba(255,255,255,0.05);
            --sidebar-shadow: 0 4px 24px rgba(0,0,0,0.4);
            --sidebar-border: rgba(255,255,255,0.06);
            --sidebar-divider: rgba(255,255,255,0.06);
            --sidebar-profile-bg: rgba(255,255,255,0.04);
            --sidebar-theme-bg: rgba(255,255,255,0.06);
            --sidebar-theme-active: #2a2a2a;
        }

        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-body); min-height: 100vh; color: var(--text-primary); transition: background 0.3s, color 0.3s; }

        /* ========== PREMIUM SIDEBAR ========== */
        .sidebar {
            width: var(--sidebar-width);
            margin: var(--sidebar-margin);
            height: calc(100vh - var(--sidebar-margin) * 2);
            border-radius: 20px;
            background: var(--sidebar-bg);
            box-shadow: var(--sidebar-shadow);
            position: fixed;
            top: 0; left: 0;
            z-index: 1040;
            display: flex;
            flex-direction: column;
            overflow: visible;
            transition: width 0.3s cubic-bezier(.4,0,.2,1), transform 0.3s cubic-bezier(.4,0,.2,1), margin 0.3s;
        }

        /* -- Sidebar Brand Card (Center-Aligned) -- */
        .sidebar-brand-card {
            padding: 20px 16px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            flex-shrink: 0;
            border-bottom: 3px solid transparent;
            border-image: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary), var(--brand-accent)) 1;
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        html[data-theme="dark"] .sidebar-brand-card { box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
        .sidebar-logo {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: #fff;
            object-fit: contain;
            padding: 4px;
            box-shadow: 0 0 0 3px rgba(var(--brand-primary-rgb, 30,64,175), 0.15), 0 2px 12px rgba(0,0,0,0.08);
            flex-shrink: 0;
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
        }
        html[data-theme="dark"] .sidebar-logo { background: #2a2a2a; box-shadow: 0 0 0 3px rgba(99,102,241,0.2), 0 2px 12px rgba(0,0,0,0.3); }
        .sidebar-logo-fallback {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px rgba(var(--brand-primary-rgb, 30,64,175), 0.15);
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
        }
        .sidebar-brand-text {
            margin-top: 10px;
            overflow: hidden;
            transition: opacity 0.25s, max-height 0.3s, margin 0.3s;
            max-height: 60px;
        }
        .sidebar-brand-text h6 { margin: 0; font-size: 0.88rem; font-weight: 700; color: var(--sidebar-text); line-height: 1.3; }
        .sidebar-brand-text .brand-tagline { color: var(--sidebar-text-muted); font-size: 0.68rem; margin-top: 2px; display: block; }

        /* Collapse toggle */
        .sidebar-collapse-btn {
            position: absolute; right: -14px; top: 50%; transform: translateY(-50%);
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--sidebar-bg);
            border: 1px solid var(--sidebar-border);
            color: var(--sidebar-text-muted);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.7rem; z-index: 1050;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .sidebar-collapse-btn:hover { background: var(--sidebar-hover); color: var(--sidebar-text); }

        /* -- Navigation -- */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 4px 0;
            scrollbar-width: thin;
            scrollbar-color: var(--sidebar-divider) transparent;
        }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--sidebar-divider); border-radius: 4px; }

        .nav-group { padding: 4px 0; }
        .nav-group + .nav-group { border-top: 1px solid var(--sidebar-divider); }
        .nav-group-label {
            color: var(--sidebar-text-muted);
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 12px 20px 4px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }
        .nav-group-label:hover { color: var(--sidebar-text); }
        .nav-group-label .nav-chevron {
            font-size: 0.7rem;
            transition: transform 0.25s ease;
        }
        .nav-group.nav-collapsed .nav-chevron { transform: rotate(-90deg); }
        .nav-group-items {
            max-height: 600px;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .nav-group.nav-collapsed .nav-group-items {
            max-height: 0;
        }
        .sidebar .nav-item {
            margin: 1px 10px;
        }
        .sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 9px 12px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 10px;
            transition: all 0.15s ease;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
            font-weight: 450;
            text-decoration: none;
        }
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 0;
            background: var(--brand-primary);
            border-radius: 0 3px 3px 0;
            transition: height 0.2s ease;
        }
        .sidebar .nav-link:hover::before {
            height: 60%;
        }
        .sidebar .nav-link:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-text);
            transform: translateX(2px);
        }
        .sidebar .nav-link i {
            transition: color 0.2s ease;
        }
        .sidebar .nav-link:hover i {
            color: var(--brand-primary);
            opacity: 1;
        }
        .sidebar .nav-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .sidebar .nav-link i {
            font-size: 1.05rem;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
            opacity: 0.8;
        }
        .sidebar .nav-link.active i { opacity: 1; }
        .sidebar .nav-link span { transition: opacity 0.2s; }

        /* Notification badge */
        .nav-badge {
            margin-left: auto;
            background: #ef4444;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            line-height: 1.2;
            flex-shrink: 0;
        }

        /* -- Sidebar Footer (Theme + Profile) -- */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 12px 14px 16px;
            border-top: 1px solid var(--sidebar-divider);
        }

        /* Theme Switcher Pill */
        .theme-pill {
            display: flex;
            background: var(--sidebar-theme-bg);
            border-radius: 12px;
            padding: 3px;
            margin-bottom: 12px;
            position: relative;
        }
        .theme-pill-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 0;
            border: none;
            background: transparent;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--sidebar-text-muted);
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            z-index: 1;
        }
        .theme-pill-btn.active {
            background: var(--sidebar-theme-active);
            color: var(--sidebar-text);
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .theme-pill-btn i { font-size: 0.85rem; }

        /* Profile Card */
        .sidebar-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 8px;
            background: var(--sidebar-profile-bg);
            border-radius: 12px;
            margin-bottom: 6px;
        }
        .sidebar-profile-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.78rem;
            flex-shrink: 0;
            position: relative;
        }
        .sidebar-profile-avatar .online-indicator {
            position: absolute; bottom: -1px; right: -1px;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #22c55e;
            border: 2px solid var(--sidebar-bg);
        }
        .sidebar-profile-info {
            overflow: hidden;
            transition: opacity 0.2s, width 0.2s;
        }
        .sidebar-profile-info h6 {
            margin: 0; font-size: 0.78rem; font-weight: 600;
            color: var(--sidebar-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-profile-info small {
            font-size: 0.65rem;
            color: var(--sidebar-text-muted);
        }
        .badge-role {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-role-super { background: #fde8e8; color: #dc2626; }
        .badge-role-admin { background: var(--brand-primary-light); color: var(--brand-primary); }
        .badge-role-office { background: #dbeafe; color: #2563eb; }
        .badge-role-teacher { background: #dcfce7; color: #16a34a; }
        .badge-role-default { background: #f3f4f6; color: #6b7280; }
        html[data-theme="dark"] .badge-role-super { background: rgba(220,38,38,0.15); }
        html[data-theme="dark"] .badge-role-admin { background: var(--brand-primary-light); }
        html[data-theme="dark"] .badge-role-office { background: rgba(37,99,235,0.15); }
        html[data-theme="dark"] .badge-role-teacher { background: rgba(22,163,74,0.15); }
        html[data-theme="dark"] .badge-role-default { background: rgba(107,114,128,0.15); }

        .sidebar-logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 8px;
            border: none; background: transparent;
            border-radius: 10px;
            color: var(--sidebar-text-muted);
            font-size: 0.78rem; font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }
        .sidebar-logout-btn:hover { background: rgba(239,68,68,0.08); color: #ef4444; }

        /* ========== COLLAPSED STATE ========== */
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar.collapsed .sidebar-brand-card { padding: 16px 0 12px; }
        .sidebar.collapsed .sidebar-logo,
        .sidebar.collapsed .sidebar-logo-fallback { width: 38px; height: 38px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; max-height: 0; margin-top: 0; overflow: hidden; }
        .sidebar.collapsed .nav-group-label { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        .sidebar.collapsed .nav-toggle-all { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        .sidebar.collapsed .nav-item { margin: 2px 8px; }
        .sidebar.collapsed .nav-link { justify-content: center; padding: 10px 0; gap: 0; }
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .nav-badge { opacity: 0; width: 0; position: absolute; overflow: hidden; }
        .sidebar.collapsed .nav-link i { margin: 0; font-size: 1.15rem; }
        .sidebar.collapsed .sidebar-collapse-btn i { transform: rotate(180deg); }
        .sidebar.collapsed .sidebar-collapse-btn { right: -14px; }

        /* Collapsed footer */
        .sidebar.collapsed .sidebar-footer { padding: 8px 6px 12px; }
        .sidebar.collapsed .theme-pill { flex-direction: column; padding: 2px; margin-bottom: 8px; }
        .sidebar.collapsed .theme-pill-btn { padding: 6px 0; }
        .sidebar.collapsed .theme-pill-btn span { display: none; }
        .sidebar.collapsed .sidebar-profile { padding: 6px; justify-content: center; }
        .sidebar.collapsed .sidebar-profile-info { opacity: 0; width: 0; overflow: hidden; }
        .sidebar.collapsed .sidebar-logout-btn span { display: none; }

        /* Tooltip */
        .sidebar.collapsed .nav-link[data-bs-toggle="tooltip"] { overflow: visible; }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: calc(var(--sidebar-width) + var(--sidebar-margin) * 2);
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(.4,0,.2,1);
        }
        .sidebar.collapsed ~ .main-content,
        html.sidebar-is-collapsed .main-content {
            margin-left: calc(var(--sidebar-collapsed-width) + var(--sidebar-margin) * 2);
        }

        /* ========== PREMIUM TOP BAR ========== */
        .top-bar {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: none;
            padding: 1rem 1.75rem;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 1030;
            transition: background 0.3s, border-color 0.3s, box-shadow 0.3s;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .top-bar::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary), var(--brand-accent));
            opacity: 0.7;
        }
        html[data-theme="dark"] .top-bar {
            background: rgba(28,28,28,0.88);
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        html[data-theme="dark"] .top-bar::after {
            opacity: 0.5;
        }

        /* Topbar Highlight Pill */
        .topbar-highlight-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, rgba(var(--brand-primary-rgb, 30,64,175), 0.06), rgba(var(--brand-secondary-rgb, 99,102,241), 0.04));
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 8px 18px 8px 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04), inset 0 1px 0 rgba(255,255,255,0.5);
            border: 1px solid rgba(var(--brand-primary-rgb, 30,64,175), 0.08);
            transition: all 0.3s ease;
        }
        html[data-theme="dark"] .topbar-highlight-pill {
            background: linear-gradient(135deg, rgba(var(--brand-primary-rgb, 30,64,175), 0.12), rgba(var(--brand-secondary-rgb, 99,102,241), 0.08));
            box-shadow: 0 2px 12px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.04);
            border-color: rgba(255,255,255,0.06);
        }
        .topbar-pill-logo {
            width: 28px; height: 28px;
            border-radius: 50%;
            object-fit: contain;
            background: #fff;
            padding: 2px;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        html[data-theme="dark"] .topbar-pill-logo { background: #2a2a2a; }
        .topbar-pill-fallback {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.7rem;
            flex-shrink: 0;
        }

        /* Topbar Pill Brand Section */
        .topbar-pill-brand {
            line-height: 1.25;
            min-width: 0;
        }
        .topbar-pill-brand .pill-school-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .topbar-pill-brand .pill-school-tagline {
            font-size: 0.68rem;
            color: var(--text-muted);
            margin: 1px 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .topbar-pill-divider {
            width: 1px;
            height: 28px;
            background: var(--border-color);
            flex-shrink: 0;
            opacity: 0.6;
        }
        @media (max-width: 767.98px) {
            .topbar-pill-brand,
            .topbar-pill-divider { display: none; }
        }

        /* Greeting & Breadcrumb */
        .topbar-greeting { line-height: 1.3; }
        .topbar-greeting .greeting-line {
            font-size: 1.08rem; font-weight: 700; color: var(--text-primary); margin: 0;
            display: flex; align-items: center; gap: 6px;
        }
        .topbar-greeting .greeting-line .wave-emoji { display: inline-block; animation: wave 2s ease-in-out infinite; transform-origin: 70% 70%; }
        @keyframes wave { 0%,60%,100%{transform:rotate(0)} 10%{transform:rotate(14deg)} 20%{transform:rotate(-8deg)} 30%{transform:rotate(14deg)} 40%{transform:rotate(-4deg)} 50%{transform:rotate(10deg)} }
        .topbar-greeting .breadcrumb-line {
            font-size: 0.72rem; color: var(--text-muted); margin: 2px 0 0; display: flex; align-items: center; gap: 4px;
        }
        .topbar-greeting .breadcrumb-line i { font-size: 0.6rem; }

        /* Search Bar */
        .topbar-search {
            position: relative; max-width: 320px; flex: 1; margin: 0 1.5rem;
        }
        .topbar-search input {
            width: 100%; padding: 0.5rem 0.85rem 0.5rem 2.2rem;
            border: 1px solid var(--border-color); border-radius: 50px;
            background: var(--bg-body); color: var(--text-primary);
            font-size: 0.82rem; outline: none;
            transition: all 0.25s ease;
        }
        .topbar-search input::placeholder { color: var(--text-muted); }
        .topbar-search input:focus {
            border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(30,64,175,0.10);
            background: var(--bg-card);
        }
        html[data-theme="dark"] .topbar-search input:focus { box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        .topbar-search .search-icon {
            position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 0.9rem; pointer-events: none;
        }
        .topbar-search .search-hint {
            position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
            font-size: 0.62rem; color: var(--text-muted);
            background: var(--border-color); padding: 1px 6px; border-radius: 4px; font-weight: 600;
            pointer-events: none;
        }
        .topbar-search .search-results {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 12px; box-shadow: var(--shadow-md);
            max-height: 300px; overflow-y: auto; display: none; z-index: 1060;
        }
        .topbar-search .search-results.show { display: block; animation: dropdownFadeIn 0.2s ease; }
        .topbar-search .search-results a {
            display: flex; align-items: center; gap: 10px;
            padding: 0.6rem 1rem; color: var(--text-secondary);
            text-decoration: none; font-size: 0.82rem; transition: background 0.15s;
        }
        .topbar-search .search-results a:hover { background: rgba(0,0,0,0.04); color: var(--text-primary); }
        html[data-theme="dark"] .topbar-search .search-results a:hover { background: rgba(255,255,255,0.06); }
        .topbar-search .search-results a i { font-size: 1rem; width: 20px; text-align: center; color: var(--text-muted); }
        .topbar-search .search-results .no-results { padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.82rem; }

        /* Notification Bell */
        .topbar-bell {
            position: relative; background: none; border: 1px solid var(--border-color);
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted);
            transition: all 0.2s; font-size: 1.1rem;
        }
        .topbar-bell:hover { background: rgba(0,0,0,0.05); color: var(--text-primary); }
        html[data-theme="dark"] .topbar-bell:hover { background: rgba(255,255,255,0.08); }
        .topbar-bell .bell-badge {
            position: absolute; top: -2px; right: -2px;
            background: #ef4444; color: #fff;
            font-size: 0.55rem; font-weight: 700;
            min-width: 16px; height: 16px;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            padding: 0 4px; border: 2px solid var(--bg-topbar);
            animation: bellPulse 2s ease-in-out infinite;
        }
        @keyframes bellPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.15)} }
        .notif-dropdown {
            position: absolute; top: calc(100% + 8px); right: 0; width: 320px;
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 12px; box-shadow: var(--shadow-md);
            display: none; z-index: 1060; overflow: hidden;
        }
        .notif-dropdown.show { display: block; animation: dropdownFadeIn 0.2s ease; }
        .notif-dropdown .notif-header {
            padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between;
            font-size: 0.85rem; font-weight: 600; color: var(--text-primary);
        }
        .notif-dropdown .notif-item {
            padding: 0.7rem 1rem; display: flex; align-items: flex-start; gap: 10px;
            border-bottom: 1px solid var(--border-color); transition: background 0.15s;
        }
        .notif-dropdown .notif-item:hover { background: rgba(0,0,0,0.03); }
        html[data-theme="dark"] .notif-dropdown .notif-item:hover { background: rgba(255,255,255,0.04); }
        .notif-dropdown .notif-item .notif-icon {
            width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 0.85rem;
        }
        .notif-dropdown .notif-item .notif-text { flex: 1; }
        .notif-dropdown .notif-item .notif-text p { margin: 0; font-size: 0.78rem; color: var(--text-primary); font-weight: 500; }
        .notif-dropdown .notif-item .notif-text small { color: var(--text-muted); font-size: 0.68rem; }
        .notif-dropdown .notif-footer {
            padding: 0.6rem 1rem; text-align: center;
        }
        .notif-dropdown .notif-footer a {
            font-size: 0.78rem; font-weight: 600; color: var(--brand-primary); text-decoration: none;
        }
        .notif-dropdown .notif-footer a:hover { text-decoration: underline; }

        /* Quick Action Buttons */
        .topbar-quick-btn {
            background: none; border: 1px solid var(--border-color);
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted);
            transition: all 0.2s; font-size: 1rem;
            text-decoration: none;
        }
        .topbar-quick-btn:hover { background: rgba(0,0,0,0.05); color: var(--brand-primary); }
        html[data-theme="dark"] .topbar-quick-btn:hover { background: rgba(255,255,255,0.08); }

        .top-bar .user-info { display: flex; align-items: center; gap: 0.6rem; }
        .content-area { padding: 1.5rem; }

        /* Profile Avatar (top bar) */
        .profile-avatar-btn {
            background: none; border: none; cursor: pointer; position: relative;
            display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem; border-radius: 50px;
            transition: background 0.2s;
        }
        .profile-avatar-btn:hover { background: rgba(0,0,0,0.05); }
        html[data-theme="dark"] .profile-avatar-btn:hover { background: rgba(255,255,255,0.08); }
        .avatar-circle {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.8rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border: 2px solid transparent;
            background-clip: padding-box;
            outline: 2px solid var(--brand-primary);
            outline-offset: 1px;
        }
        .online-dot {
            position: absolute; bottom: 2px; right: 2px;
            width: 10px; height: 10px; border-radius: 50%;
            background: #22c55e; border: 2px solid var(--bg-topbar);
        }

        /* Hide search on small screens */
        @media (max-width: 767.98px) {
            .topbar-search { display: none; }
            .topbar-greeting .greeting-line { font-size: 0.95rem; }
        }

        /* Profile Dropdown */
        .profile-dropdown {
            width: 280px; border: 1px solid var(--border-color);
            background: var(--bg-card); border-radius: 12px;
            box-shadow: var(--shadow-md); padding: 0; overflow: hidden;
            animation: dropdownFadeIn 0.2s ease;
        }
        @keyframes dropdownFadeIn { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }
        .profile-dropdown .dropdown-header-custom {
            padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem;
            border-bottom: 1px solid var(--border-color); background: var(--bg-card);
        }
        .profile-dropdown .dropdown-header-custom .avatar-lg {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; flex-shrink: 0;
        }
        .profile-dropdown .dropdown-header-custom .user-meta h6 { margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .profile-dropdown .dropdown-header-custom .user-meta small { color: var(--text-muted); font-size: 0.75rem; }
        .profile-dropdown .dropdown-body { padding: 0.5rem 0; }
        .profile-dropdown .dropdown-item {
            padding: 0.6rem 1.25rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.6rem;
            color: var(--text-secondary); transition: background 0.15s;
        }
        .profile-dropdown .dropdown-item:hover { background: rgba(0,0,0,0.04); color: var(--text-primary); }
        html[data-theme="dark"] .profile-dropdown .dropdown-item:hover { background: rgba(255,255,255,0.06); }
        .profile-dropdown .dropdown-item i { font-size: 1rem; width: 20px; text-align: center; color: var(--text-muted); }
        .profile-dropdown .dropdown-divider { border-color: var(--border-color); margin: 0.25rem 0; }
        .profile-dropdown .dropdown-item.text-danger { color: #ef4444 !important; }
        .profile-dropdown .dropdown-item.text-danger i { color: #ef4444 !important; }

        /* Theme toggle in header */
        .theme-toggle-btn {
            background: none; border: 1px solid var(--border-color); cursor: pointer;
            width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); transition: all 0.2s; font-size: 1.1rem;
        }
        .theme-toggle-btn:hover { background: rgba(0,0,0,0.05); color: var(--text-primary); }
        html[data-theme="dark"] .theme-toggle-btn:hover { background: rgba(255,255,255,0.08); }
        .theme-toggle-btn .bi-sun-fill { display: none; }
        .theme-toggle-btn .bi-moon-fill { display: inline; }
        html[data-theme="dark"] .theme-toggle-btn .bi-sun-fill { display: inline; }
        html[data-theme="dark"] .theme-toggle-btn .bi-moon-fill { display: none; }

        /* Theme switch track in dropdown */
        .theme-switch-item { cursor: pointer; }
        .theme-switch-track {
            width: 40px; height: 22px; border-radius: 11px; position: relative;
            background: #cbd5e1; transition: background 0.3s; flex-shrink: 0; margin-left: auto;
        }
        html[data-theme="dark"] .theme-switch-track { background: var(--brand-primary); }
        .theme-switch-track::after {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 18px; height: 18px; border-radius: 50%; background: #fff;
            transition: transform 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        html[data-theme="dark"] .theme-switch-track::after { transform: translateX(18px); }

        /* ========== MOBILE ========== */
        .sidebar-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--text-primary); }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1035; backdrop-filter: blur(2px); }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(calc(-100% - 24px)); width: var(--sidebar-width) !important; margin: 8px; height: calc(100vh - 16px); }
            .sidebar.collapsed { width: var(--sidebar-width) !important; }
            .sidebar.show { transform: translateX(0); }
            .sidebar-overlay.show { display: block; }
            .main-content, .sidebar.collapsed ~ .main-content, html.sidebar-is-collapsed .main-content { margin-left: 0 !important; }
            .sidebar-toggle { display: inline-block; }
            .sidebar-collapse-btn { display: none !important; }
            /* Restore labels on mobile even if collapsed */
            .sidebar.collapsed .sidebar-brand-text { opacity: 1; width: auto; }
            .sidebar.collapsed .nav-group-label { opacity: 1; height: auto; padding: 12px 20px 4px; }
            .sidebar.collapsed .nav-toggle-all { opacity: 1; height: auto; padding: 8px 16px 4px; overflow: visible; }
            .sidebar.collapsed .nav-link { justify-content: flex-start; padding: 9px 12px; gap: 10px; }
            .sidebar.collapsed .nav-link span { opacity: 1; width: auto; position: static; }
            .sidebar.collapsed .nav-badge { opacity: 1; width: auto; position: static; }
            .sidebar.collapsed .sidebar-header { justify-content: flex-start; padding: 20px 20px 12px; }
            .sidebar.collapsed .sidebar-logo,
            .sidebar.collapsed .sidebar-logo-fallback { width: 48px; height: 48px; }
            .sidebar.collapsed .sidebar-profile-info { opacity: 1; width: auto; }
            .sidebar.collapsed .theme-pill { flex-direction: row; }
            .sidebar.collapsed .theme-pill-btn span { display: inline; }
            .sidebar.collapsed .sidebar-logout-btn span { display: inline; }
        }

        /* ========== PREMIUM CONTENT STYLES ========== */

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s, background 0.3s;
            color: var(--text-primary);
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        .card-body { color: var(--text-primary); }

        /* KPI Cards */
        .kpi-card {
            border: none;
            border-radius: 16px;
            transition: transform 0.3s cubic-bezier(.4,0,.2,1),
                        box-shadow 0.3s cubic-bezier(.4,0,.2,1),
                        background 0.3s ease;
            background: var(--bg-card);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.1), transparent);
            pointer-events: none;
            transition: left 0.5s ease;
        }
        .kpi-card:hover::before { left: 100%; }
        .kpi-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 12px 32px rgba(0,0,0,0.12);
        }
        .kpi-card:active {
            transform: translateY(-4px) scale(0.98);
        }
        .kpi-card .fs-3, .kpi-card .fs-4 { color: var(--text-primary); }
        .kpi-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            transition: transform 0.3s cubic-bezier(.4,0,.2,1), filter 0.3s ease;
        }
        .kpi-card:hover .kpi-icon {
            transform: scale(1.15) rotate(5deg);
            filter: brightness(1.15);
        }
        /* Staggered entrance */
        .kpi-card { animation: fadeInUp 0.5s cubic-bezier(.4,0,.2,1) backwards; }
        .kpi-card:nth-child(1) { animation-delay: 0.05s; }
        .kpi-card:nth-child(2) { animation-delay: 0.1s; }
        .kpi-card:nth-child(3) { animation-delay: 0.15s; }
        .kpi-card:nth-child(4) { animation-delay: 0.2s; }
        .kpi-card:nth-child(5) { animation-delay: 0.25s; }
        .kpi-card:nth-child(6) { animation-delay: 0.3s; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tables */
        .table { color: var(--text-primary); --bs-table-bg: transparent; }
        .table th {
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-muted); font-weight: 600;
            background: rgba(0,0,0,0.02);
            border-color: var(--border-color);
        }
        .table td { border-color: var(--border-color); }
        .table-hover tbody tr {
            transition: background 0.3s cubic-bezier(.4,0,.2,1),
                        transform 0.3s cubic-bezier(.4,0,.2,1),
                        box-shadow 0.3s ease;
        }
        .table-hover tbody tr:hover {
            background: rgba(0,0,0,0.04);
            transform: translateX(3px);
            box-shadow: inset 4px 0 0 0 var(--brand-primary);
        }
        .table-hover tbody tr:active { transform: translateX(1px); }
        html[data-theme="dark"] .table th { background: rgba(255,255,255,0.03); }
        html[data-theme="dark"] .table-hover tbody tr:hover {
            background: rgba(255,255,255,0.04);
            box-shadow: inset 4px 0 0 0 var(--brand-primary);
        }

        /* Forms */
        .form-control, .form-select {
            background: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.25s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }
        .btn::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 0; height: 0;
            background: radial-gradient(circle, rgba(255,255,255,0.3), transparent);
            border-radius: 50%;
            pointer-events: none;
            transform: translate(-50%, -50%);
            transition: width 0.4s, height 0.4s;
        }
        .btn:hover::before { width: 300px; height: 300px; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .btn:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-sm { border-radius: 8px; }
        .btn-outline-primary:hover,
        .btn-outline-success:hover,
        .btn-outline-warning:hover,
        .btn-outline-info:hover,
        .btn-outline-danger:hover { transform: translateY(-2px); }
        /* Quick Action Buttons */
        .card .btn-outline-primary,
        .card .btn-outline-success,
        .card .btn-outline-warning,
        .card .btn-outline-info {
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
        }
        .card .btn-outline-primary:hover,
        .card .btn-outline-success:hover,
        .card .btn-outline-warning:hover,
        .card .btn-outline-info:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        /* Ripple keyframe */
        @keyframes rippleEffect {
            to { transform: scale(4); opacity: 0; }
        }

        /* Modals */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            color: var(--text-primary);
        }
        .modal-header { border-bottom-color: var(--border-color); }
        .modal-footer { border-top-color: var(--border-color); }

        /* Badges & Alerts */
        .badge { font-weight: 500; }
        html[data-theme="dark"] .badge.bg-light { background: rgba(255,255,255,0.08) !important; color: var(--text-primary) !important; }
        html[data-theme="dark"] .bg-primary.bg-opacity-10 { background: var(--brand-primary-light) !important; }

        /* Dark mode overrides */
        html[data-theme="dark"] .card, html[data-theme="dark"] .kpi-card { background: var(--bg-card); border-color: var(--border-color); }
        html[data-theme="dark"] .form-control, html[data-theme="dark"] .form-select {
            background: #111; border-color: var(--border-color); color: var(--text-primary);
        }
        html[data-theme="dark"] .form-control:focus, html[data-theme="dark"] .form-select:focus {
            background: #111; border-color: var(--brand-primary); color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem var(--brand-primary-light);
        }
        html[data-theme="dark"] .modal-content { background: var(--bg-card); color: var(--text-primary); border-color: var(--border-color); }
        html[data-theme="dark"] .alert { border-color: var(--border-color); background: var(--bg-card); }
        html[data-theme="dark"] .list-group-item { background: var(--bg-card); color: var(--text-primary); border-color: var(--border-color); }
        html[data-theme="dark"] .btn-light { background: var(--bg-card); color: var(--text-primary); border-color: var(--border-color); }
        html[data-theme="dark"] .text-muted { color: var(--text-muted) !important; }
        html[data-theme="dark"] .text-dark { color: var(--text-primary) !important; }
        html[data-theme="dark"] .bg-white { background: var(--bg-card) !important; }
        html[data-theme="dark"] .bg-light { background: var(--bg-body) !important; }
        html[data-theme="dark"] .border { border-color: var(--border-color) !important; }
        html[data-theme="dark"] .dropdown-menu { background: var(--bg-card); border-color: var(--border-color); }
        html[data-theme="dark"] .dropdown-item { color: var(--text-secondary); }
        html[data-theme="dark"] .dropdown-item:hover { background: rgba(255,255,255,0.06); color: var(--text-primary); }
        html[data-theme="dark"] h1,html[data-theme="dark"] h2,html[data-theme="dark"] h3,html[data-theme="dark"] h4,html[data-theme="dark"] h5,html[data-theme="dark"] h6 { color: var(--text-primary); }
        html[data-theme="dark"] .nav-tabs .nav-link { color: var(--text-muted); }
        html[data-theme="dark"] .nav-tabs .nav-link.active { color: var(--text-primary); background: var(--bg-card); border-color: var(--border-color); }
        html[data-theme="dark"] .page-link { background: var(--bg-card); border-color: var(--border-color); color: var(--text-primary); }
        html[data-theme="dark"] .breadcrumb-item a { color: var(--text-muted); }
        html[data-theme="dark"] .table-light { background: rgba(255,255,255,0.03) !important; --bs-table-bg: rgba(255,255,255,0.03); }

        /* Brand color overrides */
        .btn-primary { background: var(--brand-primary) !important; border-color: var(--brand-primary) !important; }
        .btn-primary:hover { filter: brightness(1.1); }
        .btn-outline-primary { color: var(--brand-primary) !important; border-color: var(--brand-primary) !important; }
        .btn-outline-primary:hover { background: var(--brand-primary) !important; color: #fff !important; }
        .badge.bg-primary { background: var(--brand-primary) !important; }
        .nav-pills .nav-link.active { background: var(--brand-primary) !important; }
        .form-control:focus, .form-select:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 0.2rem var(--brand-primary-light); }
        a { color: var(--brand-primary); }
        a:hover { color: var(--brand-secondary); }

        /* ========== MOBILE RESPONSIVE ========== */
        @media (max-width: 767.98px) {
            .content-area { padding: 0.75rem; }
            .top-bar { padding: 0.5rem 0.75rem; }
            .top-bar .page-title { font-size: 0.95rem; }
            .kpi-card .card-body { padding: 0.75rem !important; }
            .kpi-card .fs-3 { font-size: 1.5rem !important; }
            .kpi-card .fs-4 { font-size: 1.25rem !important; }
            .kpi-icon { width: 40px; height: 40px; font-size: 1rem; border-radius: 10px; }
            .card { border-radius: 14px; }
            .card-header { padding: 0.75rem 1rem; }
            .card-body { padding: 0.75rem; }
            .table { font-size: 0.8rem; }
            .btn-sm { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
            .modal-dialog { margin: 0.5rem; }
            .modal-content { border-radius: 16px; }
            .d-flex.gap-2 { flex-wrap: wrap; }
        }
        @media (max-width: 575.98px) {
            .content-area { padding: 0.5rem; }
            .kpi-card .card-body { padding: 0.5rem !important; }
            .kpi-icon { width: 36px; height: 36px; font-size: 0.9rem; }
            .top-bar .user-info .theme-toggle-btn { display: none; }
        }
        @media (min-width: 768px) and (max-width: 991.98px) {
            .content-area { padding: 1rem; }
        }
    </style>
</head>
<body>
<?php if (isLoggedIn()): ?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ========== PREMIUM SIDEBAR ========== -->
<nav class="sidebar" id="sidebar">
    <!-- Header / Logo -->
    <div class="sidebar-brand-card">
        <?php
        // Prefer admin_logo if set, otherwise fall back to school_logo
        $_sidebarLogo = !empty($adminLogo) ? $adminLogo : $schoolLogo;
        $_sidebarLogoVer = !empty($adminLogo) ? $_adminLogoVer : $_logoVer;
        if ($_sidebarLogo):
            $_sidebarLogoPath = (strpos($_sidebarLogo, '/uploads/') === 0) ? $_sidebarLogo : (file_exists(__DIR__.'/../uploads/branding/'.$_sidebarLogo) ? '/uploads/branding/'.$_sidebarLogo : '/uploads/logo/'.$_sidebarLogo);
        ?>
            <img src="<?= e($_sidebarLogoPath) ?>?v=<?= e($_sidebarLogoVer) ?>" alt="Logo" class="sidebar-logo">
        <?php else: ?>
            <div class="sidebar-logo-fallback"><?= strtoupper(substr($schoolName, 0, 1)) ?></div>
        <?php endif; ?>
        <div class="sidebar-brand-text">
            <h6><?= e($schoolName) ?></h6>
            <span class="brand-tagline"><?= e($schoolTagline) ?></span>
        </div>
        <button class="sidebar-collapse-btn d-none d-lg-flex" onclick="toggleCollapse()" title="Toggle sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <?php if (isAdmin()): ?>
        <!-- Expand/Collapse All Toggle -->
        <div class="nav-toggle-all" style="padding:8px 16px 4px;display:flex;align-items:center;">
            <button onclick="toggleAllNavGroups()" class="btn-toggle-all" title="Expand/Collapse All" style="background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;color:var(--sidebar-text,#666);display:flex;align-items:center;gap:4px;padding:4px 8px;border-radius:6px;transition:background .2s;">
                <i class="bi bi-arrows-expand" id="toggleAllIcon"></i> <span id="toggleAllLabel">Expand All</span>
            </button>
        </div>
        <!-- ========== MAIN ========== -->
        <div class="nav-group" id="navg-main">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-main')">Main <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <div class="nav-item">
                    <a href="/admin/dashboard.php" class="nav-link <?= navActive('/admin/dashboard') ?>" data-bs-title="Dashboard"><i class="bi bi-grid-1x2"></i> <span>Dashboard</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/students.php" class="nav-link <?= navActive('/admin/students') ?><?= navActive('/admin/student-form') ?>" data-bs-title="Students"><i class="bi bi-mortarboard"></i> <span>Students</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/teachers.php" class="nav-link <?= navActive('/admin/teachers') ?><?= navActive('/admin/teacher-form') ?>" data-bs-title="Teachers"><i class="bi bi-person-badge"></i> <span>Teachers</span></a>
                </div>
                <?php if (isSuperAdmin() || getSetting('feature_admissions', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/admissions.php" class="nav-link <?= navActive('/admin/admissions') ?>" data-bs-title="Admissions"><i class="bi bi-file-earmark-plus"></i> <span>Admissions</span><?php if ($_admissionCount > 0): ?><span class="nav-badge"><?= $_admissionCount > 99 ? '99+' : $_admissionCount ?></span><?php endif; ?></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/seat-capacity.php" class="nav-link <?= navActive('/admin/seat-capacity') ?>" data-bs-title="Seat Capacity"><i class="bi bi-grid-3x3-gap"></i> <span>Seat Capacity</span></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_fee_structure', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/fee-structure.php" class="nav-link <?= navActive('/admin/fee-structure') ?>" data-bs-title="Fee Structure"><i class="bi bi-cash-stack"></i> <span>Fee Structure</span></a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========== RECRUITMENT ========== -->
        <?php if (isSuperAdmin() || getSetting('feature_recruitment', '1') === '1'): ?>
        <div class="nav-group" id="navg-recruitment">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-recruitment')">Recruitment <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <div class="nav-item">
                    <a href="/admin/teacher-applications.php" class="nav-link <?= navActive('/admin/teacher-applications') ?>" data-bs-title="Applications">
                        <i class="bi bi-people"></i> <span>Applications</span>
                        <?php if ($_recruitmentCount > 0): ?><span class="nav-badge"><?= $_recruitmentCount > 99 ? '99+' : $_recruitmentCount ?></span><?php endif; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/recruitment-settings.php" class="nav-link <?= navActive('/admin/recruitment-settings') ?>" data-bs-title="Recruitment Settings">
                        <i class="bi bi-sliders"></i> <span>Recruitment Settings</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== HR MANAGEMENT ========== -->
        <?php if (isSuperAdmin() || getSetting('feature_hr', '1') === '1'): ?>
        <div class="nav-group" id="navg-hr">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-hr')">HR Management <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <div class="nav-item">
                    <a href="/admin/hr/employees.php" class="nav-link <?= navActive('/admin/hr/employees') ?>" data-bs-title="Employees"><i class="bi bi-person-vcard"></i> <span>Employees</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/hr/letters.php?type=appointment" class="nav-link <?= navActive('type=appointment') ?>" data-bs-title="Appointment Letters"><i class="bi bi-envelope-paper"></i> <span>Appointment Letters</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/hr/letters.php?type=joining" class="nav-link <?= navActive('type=joining') ?>" data-bs-title="Joining Letters"><i class="bi bi-person-check"></i> <span>Joining Letters</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/hr/letters.php?type=resignation" class="nav-link <?= navActive('type=resignation') ?>" data-bs-title="Resignation Letters"><i class="bi bi-person-dash"></i> <span>Resignation Letters</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/hr/letters.php?type=hike" class="nav-link <?= navActive('type=hike') ?>" data-bs-title="Salary Hike Letters"><i class="bi bi-graph-up-arrow"></i> <span>Salary Hike Letters</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/hr/payslips.php" class="nav-link <?= navActive('/admin/hr/payslips') ?>" data-bs-title="Payslips"><i class="bi bi-receipt"></i> <span>Payslips</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/hr/templates.php" class="nav-link <?= navActive('/admin/hr/templates') ?>" data-bs-title="Letter Templates"><i class="bi bi-file-earmark-richtext"></i> <span>Letter Templates</span></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== CONTENT & MEDIA ========== -->
        <div class="nav-group" id="navg-content">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-content')">Content & Media <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <?php if (isSuperAdmin() || getSetting('feature_notifications', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/notifications.php" class="nav-link <?= navActive('/admin/notifications') ?>" data-bs-title="Notifications"><i class="bi bi-bell"></i> <span>Notifications</span><?php if ($_notifCount > 0): ?><span class="nav-badge"><?= $_notifCount > 99 ? '99+' : $_notifCount ?></span><?php endif; ?></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_gallery', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/gallery.php" class="nav-link <?= navActive('/admin/gallery') ?>" data-bs-title="Gallery"><i class="bi bi-images"></i> <span>Gallery</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/upload-gallery.php" class="nav-link <?= navActive('/admin/upload-gallery') ?>" data-bs-title="Upload"><i class="bi bi-cloud-arrow-up"></i> <span>Upload Gallery</span></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_events', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/events.php" class="nav-link <?= navActive('/admin/events') ?>" data-bs-title="Events"><i class="bi bi-calendar-event"></i> <span>Events</span></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_certificates', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/certificates.php" class="nav-link <?= navActive('/admin/certificates') ?>" data-bs-title="Certificates"><i class="bi bi-award"></i> <span>Certificates</span></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_feature_cards', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/feature-cards.php" class="nav-link <?= navActive('/admin/feature-cards') ?>" data-bs-title="Feature Cards"><i class="bi bi-grid-1x2-fill"></i> <span>Feature Cards</span></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_core_team', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/core-team.php" class="nav-link <?= navActive('/admin/core-team') ?>" data-bs-title="Core Team"><i class="bi bi-people-fill"></i> <span>Core Team</span></a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========== WEBSITE ========== -->
        <div class="nav-group" id="navg-website">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-website')">Website <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <?php if (isSuperAdmin() || getSetting('feature_slider', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/slider.php" class="nav-link <?= navActive('/admin/slider') ?>" data-bs-title="Slider"><i class="bi bi-collection-play"></i> <span>Home Slider</span></a>
                </div>
                <?php endif; ?>
                <div class="nav-item">
                    <a href="/admin/page-content-manager.php" class="nav-link <?= navActive('/admin/page-content-manager') ?>" data-bs-title="Pages"><i class="bi bi-file-earmark-text"></i> <span>Page Content</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/footer-manager.php" class="nav-link <?= navActive('/admin/footer-manager') ?>" data-bs-title="Footer"><i class="bi bi-layout-three-columns"></i> <span>Footer Manager</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/navigation-settings.php" class="nav-link <?= navActive('/admin/navigation-settings') ?>" data-bs-title="Navigation"><i class="bi bi-menu-button-wide"></i> <span>Navigation</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/quote-highlight.php" class="nav-link <?= navActive('/admin/quote-highlight') ?>" data-bs-title="Quote"><i class="bi bi-quote"></i> <span>Quote Highlight</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/school-location.php" class="nav-link <?= navActive('/admin/school-location') ?>" data-bs-title="Location"><i class="bi bi-geo-alt"></i> <span>School Location</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/popup-ad.php" class="nav-link <?= navActive('/admin/popup-ad') ?>" data-bs-title="Popup Ad"><i class="bi bi-megaphone"></i> <span>Popup Ad</span></a>
                </div>
            </div>
        </div>

        <!-- ========== REPORTS & LOGS ========== -->
        <div class="nav-group" id="navg-reports">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-reports')">Reports & Logs <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <?php if (isSuperAdmin() || getSetting('feature_reports', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/reports.php" class="nav-link <?= navActive('/admin/reports') ?>" data-bs-title="Reports"><i class="bi bi-bar-chart-line"></i> <span>Reports</span></a>
                </div>
                <?php endif; ?>
                <?php if (isSuperAdmin() || getSetting('feature_audit_logs', '1') === '1'): ?>
                <div class="nav-item">
                    <a href="/admin/audit-logs.php" class="nav-link <?= navActive('/admin/audit-logs') ?>" data-bs-title="Audit Logs"><i class="bi bi-clock-history"></i> <span>Audit Logs</span></a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========== SYSTEM ========== -->
        <div class="nav-group" id="navg-system">
            <div class="nav-group-label" onclick="toggleNavGroup('navg-system')">System <i class="bi bi-chevron-down nav-chevron"></i></div>
            <div class="nav-group-items">
                <div class="nav-item">
                    <a href="/admin/enquiries.php" class="nav-link <?= navActive('/admin/enquiries') ?>" data-bs-title="Enquiries"><i class="bi bi-chat-dots"></i> <span>Enquiries</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/settings.php" class="nav-link <?= navActive('/admin/settings') ?>" data-bs-title="Settings"><i class="bi bi-gear"></i> <span>Settings</span></a>
                </div>
                <div class="nav-item">
                    <a href="/admin/support.php" class="nav-link <?= navActive('/admin/support') ?>" data-bs-title="Support"><i class="bi bi-headset"></i> <span>Support</span></a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Teacher Panel -->
        <div class="nav-group">
            <div class="nav-group-label">Teacher Panel</div>
            <div class="nav-item">
                <a href="/teacher/dashboard.php" class="nav-link <?= navActive('/teacher/dashboard') ?>" data-bs-title="Dashboard"><i class="bi bi-grid-1x2"></i> <span>Dashboard</span></a>
            </div>
            <div class="nav-item">
                <a href="/teacher/attendance.php" class="nav-link <?= navActive('/teacher/attendance') ?>" data-bs-title="Attendance"><i class="bi bi-check2-square"></i> <span>Attendance</span></a>
            </div>
            <div class="nav-item">
                <a href="/teacher/exams.php" class="nav-link <?= navActive('/teacher/exams') ?>" data-bs-title="Exams"><i class="bi bi-journal-text"></i> <span>Exam Results</span></a>
            </div>
            <div class="nav-item">
                <a href="/teacher/post-notification.php" class="nav-link <?= navActive('/teacher/post-notification') ?>" data-bs-title="Notify"><i class="bi bi-megaphone"></i> <span>Post Notification</span></a>
            </div>
            <div class="nav-item">
                <a href="/teacher/upload-gallery.php" class="nav-link <?= navActive('/teacher/upload-gallery') ?>" data-bs-title="Gallery"><i class="bi bi-camera"></i> <span>Upload Gallery</span></a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <!-- Theme Switcher Pill -->
        <div class="theme-pill" id="themePill">
            <button class="theme-pill-btn" data-theme="light" onclick="setTheme('light')">
                <i class="bi bi-sun"></i> <span>Light</span>
            </button>
            <button class="theme-pill-btn" data-theme="dark" onclick="setTheme('dark')">
                <i class="bi bi-moon"></i> <span>Dark</span>
            </button>
        </div>

        <!-- Profile Card -->
        <div class="sidebar-profile">
            <div class="sidebar-profile-avatar">
                <?= e($_userInitials) ?>
                <span class="online-indicator"></span>
            </div>
            <div class="sidebar-profile-info">
                <h6><?= e($_userName) ?></h6>
                <small><span class="badge-role <?= e($_roleBadgeClass) ?>"><?= e($_roleLabel) ?></span></small>
            </div>
        </div>

        <!-- Logout -->
        <a href="/logout.php" class="sidebar-logout-btn">
            <i class="bi bi-box-arrow-left"></i> <span>Log out</span>
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <div class="topbar-highlight-pill">
                <?php if ($schoolLogo): ?>
                    <img src="<?= e($_sidebarLogoPath) ?>?v=<?= e($_logoVer) ?>" alt="Logo" class="topbar-pill-logo">
                <?php else: ?>
                    <div class="topbar-pill-fallback"><?= strtoupper(substr($schoolName, 0, 1)) ?></div>
                <?php endif; ?>
                <div class="topbar-greeting">
                    <p class="greeting-line"><span id="greetText">Hello</span>, <?= e(explode(' ', $_userName)[0]) ?> <span class="wave-emoji">👋</span></p>
                    <div class="breadcrumb-line">
                        <i class="bi bi-house-fill"></i> <span><?= e($pageTitle) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="topbar-search d-none d-md-block">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="topbarSearchInput" placeholder="Search pages..." autocomplete="off">
            <span class="search-hint">Ctrl+K</span>
            <div class="search-results" id="searchResults"></div>
        </div>

        <div class="user-info">
            <span class="text-muted d-none d-lg-inline" style="font-size:0.75rem;">
                <i class="bi bi-calendar3 me-1"></i><?= date('d M Y') ?>
                <i class="bi bi-clock ms-2 me-1"></i><span id="headerClock"><?= date('h:i A') ?></span>
            </span>

            <!-- Quick Actions -->
            <?php if (isAdmin()): ?>
            <a href="/admin/student-form.php" class="topbar-quick-btn d-none d-md-flex" title="Add Student"><i class="bi bi-person-plus"></i></a>
            <?php endif; ?>

            <!-- Notification Bell -->
            <?php
            $_totalBadge = $_notifCount + $_admissionCount + $_recruitmentCount;
            ?>
            <div style="position:relative;">
                <button class="topbar-bell" onclick="toggleNotifDropdown(event)" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($_totalBadge > 0): ?>
                    <span class="bell-badge"><?= $_totalBadge > 99 ? '99+' : $_totalBadge ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <span style="font-size:0.7rem;color:var(--text-muted);"><?= $_totalBadge ?> pending</span>
                    </div>
                    <?php if ($_notifCount > 0): ?>
                    <div class="notif-item">
                        <div class="notif-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="bi bi-megaphone"></i></div>
                        <div class="notif-text">
                            <p><?= $_notifCount ?> notification<?= $_notifCount>1?'s':'' ?> pending review</p>
                            <small>Requires approval</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($_admissionCount > 0): ?>
                    <div class="notif-item">
                        <div class="notif-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="bi bi-person-badge"></i></div>
                        <div class="notif-text">
                            <p><?= $_admissionCount ?> admission<?= $_admissionCount>1?'s':'' ?> pending</p>
                            <small>New applications received</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($_recruitmentCount > 0): ?>
                    <div class="notif-item">
                        <div class="notif-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="bi bi-briefcase"></i></div>
                        <div class="notif-text">
                            <p><?= $_recruitmentCount ?> new teacher application<?= $_recruitmentCount>1?'s':'' ?></p>
                            <small><a href="/admin/teacher-applications.php" style="text-decoration:none;">Review now</a></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($_totalBadge == 0): ?>
                    <div class="notif-item" style="justify-content:center;padding:1.5rem;">
                        <span style="color:var(--text-muted);font-size:0.82rem;">All caught up! 🎉</span>
                    </div>
                    <?php endif; ?>
                    <div class="notif-footer">
                        <a href="/admin/notifications.php">View All Notifications →</a>
                    </div>
                </div>
            </div>

            <!-- Fullscreen Toggle -->
            <button class="topbar-quick-btn d-none d-md-flex" onclick="toggleFullScreen()" title="Fullscreen"><i class="bi bi-arrows-fullscreen" id="fsIcon"></i></button>

            <!-- Theme Toggle -->
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Toggle theme">
                <i class="bi bi-moon-fill"></i>
                <i class="bi bi-sun-fill"></i>
            </button>

            <!-- Profile Dropdown -->
            <div class="dropdown">
                <button class="profile-avatar-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar-circle"><?= e($_userInitials) ?></div>
                    <span class="online-dot"></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end profile-dropdown">
                    <div class="dropdown-header-custom">
                        <div class="avatar-lg"><?= e($_userInitials) ?></div>
                        <div class="user-meta">
                            <h6><?= e($_userName) ?></h6>
                            <small><span class="badge <?= e($_roleBadgeClass === 'badge-role-super' ? 'bg-danger' : ($_roleBadgeClass === 'badge-role-admin' ? 'bg-primary' : ($_roleBadgeClass === 'badge-role-teacher' ? 'bg-success' : 'bg-info'))) ?> rounded-pill" style="font-size:0.7rem;"><?= e($_roleLabel) ?></span></small>
                        </div>
                    </div>
                    <div class="dropdown-body">
                        <a class="dropdown-item" href="<?= isAdmin() ? '/admin/settings.php' : '/teacher/dashboard.php' ?>"><i class="bi bi-person"></i> My Profile</a>
                        <a class="dropdown-item" href="<?= isAdmin() ? '/admin/settings.php#security' : '#' ?>"><i class="bi bi-key"></i> Change Password</a>
                        <?php if (isSuperAdmin()): ?>
                        <a class="dropdown-item" href="/admin/settings.php"><i class="bi bi-gear"></i> System Settings</a>
                        <?php endif; ?>
                        <hr class="dropdown-divider">
                        <div class="dropdown-item theme-switch-item" onclick="toggleTheme()">
                            <i class="bi bi-moon-fill"></i> <span>Dark Mode</span>
                            <div class="theme-switch-track"></div>
                        </div>
                        <hr class="dropdown-divider">
                        <a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="content-area">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
<?php else: ?>
<div>
<?php endif; ?>

<script>
function toggleSidebar() {
    document.getElementById('sidebar')?.classList.toggle('show');
    document.getElementById('sidebarOverlay')?.classList.toggle('show');
}

/* ── Theme ─────────────────────────────────────────── */
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('admin_theme', theme);
    // Update sidebar theme pill active states
    document.querySelectorAll('.theme-pill').forEach(function(pill) {
        pill.classList.toggle('active', pill.getAttribute('data-theme') === theme);
    });
    // Update body/root classes for dark mode styling
    document.body.classList.toggle('dark-mode', theme === 'dark');
}

function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') || localStorage.getItem('admin_theme') || 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
}

/* ── Fullscreen ────────────────────────────────────── */
function toggleFullScreen() {
    var btn = document.querySelector('[onclick*="toggleFullScreen"]');
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(function() {
            if (btn) { var i = btn.querySelector('i'); if (i) { i.className = i.className.replace('bi-arrows-fullscreen','bi-fullscreen-exit'); } }
        }).catch(function(){});
    } else {
        document.exitFullscreen().then(function() {
            if (btn) { var i = btn.querySelector('i'); if (i) { i.className = i.className.replace('bi-fullscreen-exit','bi-arrows-fullscreen'); } }
        }).catch(function(){});
    }
}

/* ── Notification Dropdown ─────────────────────────── */
function toggleNotifDropdown(event) {
    if (event) { event.stopPropagation(); }
    var dd = document.getElementById('notifDropdown');
    if (dd) dd.classList.toggle('show');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('notifDropdown');
    if (dd && dd.classList.contains('show')) {
        var bell = document.querySelector('[onclick*="toggleNotifDropdown"]');
        if (bell && !bell.contains(e.target) && !dd.contains(e.target)) {
            dd.classList.remove('show');
        }
    }
});

/* ── Sidebar Collapse ──────────────────────────────── */
function toggleCollapse() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed');
    var collapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
}

/* ── Search ─────────────────────────────────────────── */
(function() {
    var pages = [
        {name:'Dashboard', url:'index.php'},
        {name:'Teachers', url:'teachers.php'},
        {name:'Settings', url:'settings.php'},
        {name:'Feature Cards', url:'feature-cards.php'},
        {name:'Core Team', url:'core-team.php'},
        {name:'Fee Structure', url:'fee-structure.php'},
        {name:'Employees', url:'hr/employees.php'},
        {name:'Payslips', url:'hr/payslips.php'},
        {name:'Letters', url:'hr/letters.php'},
        {name:'Templates', url:'hr/templates.php'}
    ];
    var input = document.getElementById('topbarSearchInput');
    var results = document.getElementById('searchResults');
    if (input && results) {
        input.addEventListener('keyup', function() {
            var q = this.value.toLowerCase().trim();
            if (!q) { results.innerHTML = ''; results.style.display = 'none'; return; }
            var html = pages.filter(function(p){ return p.name.toLowerCase().indexOf(q) > -1; })
                .map(function(p){ return '<a href="'+p.url+'" class="dropdown-item py-2"><i class="bi bi-file-earmark me-2"></i>'+p.name+'</a>'; })
                .join('');
            results.innerHTML = html || '<div class="dropdown-item text-muted py-2">No results found</div>';
            results.style.display = 'block';
        });
        input.addEventListener('blur', function(){ setTimeout(function(){ results.style.display = 'none'; }, 200); });
    }
    // Ctrl+K shortcut
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (input) input.focus();
        }
    });
})();

/* ── Live Clock & Greeting ─────────────────────────── */
function updateClockAndGreeting() {
    var now = new Date();
    var h = now.getHours(), m = now.getMinutes();
    var ampm = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12 || 12;
    var clock = document.getElementById('headerClock');
    if (clock) clock.textContent = h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;

    var greet = document.getElementById('greetText');
    if (greet) {
        var g = h < 12 ? 'Good Morning' : h < 17 ? 'Good Afternoon' : 'Good Evening';
        greet.textContent = g;
    }
}
updateClockAndGreeting();
setInterval(updateClockAndGreeting, 60000);

/* ── Collapsible Nav Groups ─────────────────────────── */
function toggleNavGroup(groupId) {
    var el = document.getElementById(groupId);
    if (!el) return;
    el.classList.toggle('nav-collapsed');
    saveNavGroupStates();
}
function saveNavGroupStates() {
    var groups = document.querySelectorAll('.nav-group[id]');
    var state = {};
    groups.forEach(function(g) {
        state[g.id] = g.classList.contains('nav-collapsed');
    });
    localStorage.setItem('sidebar_groups', JSON.stringify(state));
}
function restoreNavGroupStates() {
    var raw = localStorage.getItem('sidebar_groups');
    var state = raw ? JSON.parse(raw) : null;
    var groups = document.querySelectorAll('.nav-group[id]');
    groups.forEach(function(g) {
        if (state && typeof state[g.id] !== 'undefined') {
            if (state[g.id]) g.classList.add('nav-collapsed');
        } else {
            // Default: Main open, others collapsed
            if (g.id !== 'navg-main') g.classList.add('nav-collapsed');
        }
    });
}
function toggleAllNavGroups() {
    var groups = document.querySelectorAll('.nav-group[id]');
    var allExpanded = true;
    groups.forEach(function(g) { if (g.classList.contains('nav-collapsed')) allExpanded = false; });
    groups.forEach(function(g) {
        if (allExpanded) g.classList.add('nav-collapsed');
        else g.classList.remove('nav-collapsed');
    });
    updateToggleAllButton();
    saveNavGroupStates();
}
function updateToggleAllButton() {
    var groups = document.querySelectorAll('.nav-group[id]');
    var allExpanded = true;
    groups.forEach(function(g) { if (g.classList.contains('nav-collapsed')) allExpanded = false; });
    var icon = document.getElementById('toggleAllIcon');
    var label = document.getElementById('toggleAllLabel');
    if (icon) icon.className = allExpanded ? 'bi bi-arrows-collapse' : 'bi bi-arrows-expand';
    if (label) label.textContent = allExpanded ? 'Collapse All' : 'Expand All';
}

/* ── Init on Load ──────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
    // Restore theme
    var saved = localStorage.getItem('admin_theme') || 'light';
    setTheme(saved);

    // Restore sidebar collapse
    if (localStorage.getItem('sidebar_collapsed') === '1') {
        var sb = document.getElementById('sidebar');
        if (sb) { sb.classList.add('collapsed'); document.documentElement.classList.add('sidebar-collapsed'); }
    }


    // Restore nav group states
    restoreNavGroupStates();
    updateToggleAllButton();
});
</script>