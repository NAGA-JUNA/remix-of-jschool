<?php
/**
 * Premium Glassmorphism Navbar (Shared Include)
 * 
 * Expected variables from parent page:
 *   $navLogo, $logoPath, $schoolName, $bellNotifs, $notifCount, $currentPage
 *   Optional: $schoolTagline (for top bar marquee)
 */

$_navCurrentPage = $currentPage ?? '';
$_navShowTopBar = getSetting('global_navbar_show_top_bar', '1');
$_navShowLogin = getSetting('global_navbar_show_login', '1');
$_navShowBell = getSetting('global_navbar_show_notif_bell', '1');
$_navSchoolTagline = $schoolTagline ?? getSetting('school_tagline', 'Nurturing Talent, Shaping Future');
$_navMarquee = getSetting('home_marquee_text', '');

// Dynamic color settings
$_clrNavbarBg = getSetting('color_navbar_bg', '#0f172a');
$_clrNavbarText = getSetting('color_navbar_text', '#ffffff');
$_clrTopbarBg = getSetting('color_topbar_bg', '#060a12');
$_clrBrandPrimary = getSetting('brand_primary', '#1e40af');
$_clrBrandSecondary = getSetting('brand_secondary', '#6366f1');

// Ensure parent_id column exists (migration for existing installs)
try {
    $db->exec("ALTER TABLE nav_menu_items ADD COLUMN parent_id INT DEFAULT NULL");
} catch (Exception $e) {
    // Column already exists — ignore
}

// Load menu items from database (with fallback)
$_navMenuItems = [];
try {
    $_navMenuItems = $db->query("SELECT * FROM nav_menu_items WHERE is_visible=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {
    // Table doesn't exist yet — use defaults
}

if (empty($_navMenuItems)) {
    $_navMenuItems = [
        ['label'=>'Home','url'=>'/','icon'=>'bi-house-fill','is_cta'=>0,'link_type'=>'internal','parent_id'=>null],
        ['label'=>'About Us','url'=>'/public/about.php','icon'=>'bi-info-circle','is_cta'=>0,'link_type'=>'internal','parent_id'=>null,'id'=>'about_default'],
        ['label'=>'Fee Structure','url'=>'/public/fee-structure.php','icon'=>'bi-cash-stack','is_cta'=>0,'link_type'=>'internal','parent_id'=>'about_default'],
        ['label'=>'Our Teachers','url'=>'/public/teachers.php','icon'=>'bi-person-badge','is_cta'=>0,'link_type'=>'internal','parent_id'=>'about_default'],
        ['label'=>'Certificates','url'=>'/public/certificates.php','icon'=>'bi-award','is_cta'=>0,'link_type'=>'internal','parent_id'=>'about_default'],
        ['label'=>'Notifications','url'=>'/public/notifications.php','icon'=>'bi-bell','is_cta'=>0,'link_type'=>'internal','parent_id'=>null],
        ['label'=>'Gallery','url'=>'/public/gallery.php','icon'=>'bi-images','is_cta'=>0,'link_type'=>'internal','parent_id'=>null],
        ['label'=>'Events','url'=>'/public/events.php','icon'=>'bi-calendar-event','is_cta'=>0,'link_type'=>'internal','parent_id'=>null],
        ['label'=>'Apply Now','url'=>'/public/admission-form.php','icon'=>'bi-pencil-square','is_cta'=>1,'link_type'=>'internal','parent_id'=>null],
    ];
    // Conditionally add "Join Us" under About Us when recruitment is enabled
    if (getSetting('recruitment_enabled', '0') === '1') {
        // Insert after "Our Teachers" (index 3) — before Certificates
        array_splice($_navMenuItems, 4, 0, [
            ['label'=>'Join Us','url'=>'/join-us.php','icon'=>'bi-briefcase-fill','is_cta'=>0,'link_type'=>'internal','parent_id'=>'about_default']
        ]);
    }
}

// Build hierarchical structure: group children under their parent
$_navTopLevel = [];
$_navChildren = [];
foreach ($_navMenuItems as $item) {
    $pid = $item['parent_id'] ?? null;
    if ($pid) {
        $_navChildren[$pid][] = $item;
    } else {
        $_navTopLevel[] = $item;
    }
}
// Attach children to parents
foreach ($_navTopLevel as &$_tlItem) {
    $itemId = $_tlItem['id'] ?? null;
    if ($itemId && isset($_navChildren[$itemId])) {
        $_tlItem['children'] = $_navChildren[$itemId];
    }
}
unset($_tlItem);

// Separate CTA from regular items
$_navCta = null;
$_navRegularItems = [];
foreach ($_navTopLevel as $item) {
    if ($item['is_cta']) {
        $_navCta = $item;
    } else {
        $_navRegularItems[] = $item;
    }
}

// Determine active page by URL match
function _navIsActive($url, $currentPage) {
    $map = [
        '/' => 'home',
        '/public/about.php' => 'about',
        '/public/teachers.php' => 'teachers',
        '/public/notifications.php' => 'notifications',
        '/public/gallery.php' => 'gallery',
        '/public/events.php' => 'events',
        '/public/fee-structure.php' => 'fee-structure',
        '/join-us.php' => 'join-us',
        '/public/admission-form.php' => 'apply',
        '/public/certificates.php' => 'certificates',
    ];
    return ($map[$url] ?? '') === $currentPage;
}

// Check if any child is active (for parent highlight)
function _navHasActiveChild($children, $currentPage) {
    foreach ($children as $child) {
        if (_navIsActive($child['url'], $currentPage)) return true;
    }
    return false;
}
?>

<!-- Premium Navbar Styles -->
<style>
/* ── Top Bar ── */
.pn-top-bar { background: <?= e($_clrTopbarBg) ?>; color: #fff; padding: 0.35rem 0; font-size: 0.75rem; z-index: 1051; position: relative; }
.pn-top-bar a { color: rgba(255,255,255,0.75); text-decoration: none; transition: color 0.2s; }
.pn-top-bar a:hover { color: #fff; }
.pn-marquee { white-space: nowrap; overflow: hidden; }
.pn-marquee span { display: inline-block; animation: pnMarquee 22s linear infinite; }
@keyframes pnMarquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

/* ── Premium Navbar ── */
.premium-navbar {
    position: sticky; top: 0; z-index: 1050;
    background: <?= e($_clrNavbarBg) ?>ee;
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    padding: 0.7rem 0;
    transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
}
.premium-navbar.scrolled {
    padding: 0.35rem 0;
    background: <?= e($_clrNavbarBg) ?>f8;
    box-shadow: 0 4px 30px rgba(0,0,0,0.3);
}
/* Only hide navbar on scroll for desktop */
@media (min-width: 992px) {
  .premium-navbar.nav-hidden { transform: translateY(-100%); }
}

/* Logo */
.pn-logo-wrap { display: flex; align-items: center; text-decoration: none; transition: transform 0.3s; }
.pn-logo-wrap:hover { transform: scale(1.03); }
.pn-logo-wrap img {
    height: 52px; width: auto; max-width: 200px; border-radius: 0; object-fit: contain;
    background: transparent; padding: 0; border: none; box-shadow: none;
    transition: opacity 0.3s;
}
.pn-logo-wrap:hover img { opacity: 0.85; }
.pn-logo-fallback {
    width: 42px; height: 42px; border-radius: 10px;
    background: linear-gradient(135deg, <?= e($_clrBrandPrimary) ?>, <?= e($_clrBrandSecondary) ?>);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.3rem;
}

/* Nav Links */
.pn-nav { display: flex; align-items: center; gap: 0.25rem; list-style: none; margin: 0; padding: 0; }
.pn-nav-link {
    color: <?= e($_clrNavbarText) ?>cc; font-weight: 500; font-size: 0.88rem;
    padding: 0.5rem 0.85rem; text-decoration: none; position: relative;
    transition: color 0.2s; border-radius: 8px;
}
.pn-nav-link::after {
    content: ''; position: absolute; bottom: 2px; left: 50%; width: 0; height: 2px;
    background: linear-gradient(90deg, <?= e($_clrBrandPrimary) ?>, <?= e($_clrBrandSecondary) ?>);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1); transform: translateX(-50%); border-radius: 2px;
}
.pn-nav-link:hover { color: #fff; }
.pn-nav-link:hover::after { width: 60%; }
.pn-nav-link.active { color: #fff; }
.pn-nav-link.active::after { width: 60%; }

/* ── Dropdown ── */
.pn-dropdown { position: relative; list-style: none; }
.pn-dropdown-toggle { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }
.pn-dropdown-toggle .pn-chevron {
    font-size: 0.65rem; transition: transform 0.25s ease; display: inline-block;
}
.pn-dropdown:hover .pn-chevron { transform: rotate(180deg); }
.pn-dropdown-menu {
    position: absolute; top: 100%; left: 50%; transform: translateX(-50%) translateY(8px);
    min-width: 200px; padding: 8px 0;
    background: <?= e($_clrNavbarBg) ?>f2;
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
    z-index: 100;
}
.pn-dropdown:hover .pn-dropdown-menu {
    opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0);
}
.pn-dropdown-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 16px; color: rgba(255,255,255,0.8);
    text-decoration: none; font-size: 0.84rem; font-weight: 500;
    transition: all 0.15s;
}
.pn-dropdown-item:hover {
    background: <?= e($_clrBrandPrimary) ?>1f; color: #fff;
}
.pn-dropdown-item i { font-size: 0.95rem; width: 18px; text-align: center; opacity: 0.7; }
.pn-dropdown-item:hover i { opacity: 1; color: #60a5fa; }

/* Bell Icon */
.pn-bell {
    position: relative; background: none; border: none; color: rgba(255,255,255,0.8);
    font-size: 1.2rem; cursor: pointer; padding: 0.4rem; transition: color 0.2s;
}
.pn-bell:hover { color: #fff; }
.pn-bell .pn-bell-badge {
    position: absolute; top: -2px; right: -4px; background: #ef4444; color: #fff;
    font-size: 0.6rem; font-weight: 700; min-width: 18px; height: 18px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    border: 2px solid rgba(15,23,42,0.9);
}
@keyframes pnBellShake {
    0%,100% { transform: rotate(0); }
    15% { transform: rotate(12deg); }
    30% { transform: rotate(-10deg); }
    45% { transform: rotate(6deg); }
    60% { transform: rotate(-4deg); }
    75% { transform: rotate(2deg); }
}
.pn-bell.has-notifs i { animation: pnBellShake 1.5s ease-in-out 2s 3; }

/* Login Button */
.pn-login-btn {
    background: transparent; border: 1.5px solid rgba(255,255,255,0.35); color: #fff;
    border-radius: 50px; padding: 0.38rem 1.1rem; font-size: 0.82rem; font-weight: 600;
    text-decoration: none; transition: all 0.25s; white-space: nowrap;
}
.pn-login-btn:hover { background: #fff; color: #0f172a; border-color: #fff; }

/* CTA Button */
.pn-cta-btn {
    background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff;
    border: none; border-radius: 50px; padding: 0.45rem 1.4rem;
    font-size: 0.85rem; font-weight: 600; text-decoration: none;
    box-shadow: 0 4px 15px rgba(239,68,68,0.35);
    transition: all 0.3s; white-space: nowrap;
    animation: pnCtaGlow 2.5s ease-in-out infinite alternate;
}
.pn-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(239,68,68,0.5); color: #fff; }
@keyframes pnCtaGlow {
    0% { box-shadow: 0 4px 15px rgba(239,68,68,0.3); }
    100% { box-shadow: 0 4px 25px rgba(239,68,68,0.55); }
}

/* Right section */
.pn-right { display: flex; align-items: center; gap: 0.6rem; }

/* Hamburger */
.pn-hamburger {
    display: none; background: none; border: none; color: #fff; font-size: 1.6rem;
    cursor: pointer; padding: 0.3rem; line-height: 1;
}

/* ── Mobile Drawer ── */
.pn-drawer-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1060;
    opacity: 0; visibility: hidden; transition: all 0.3s;
}
.pn-drawer-overlay.show { opacity: 1; visibility: visible; }
.pn-drawer {
    position: fixed; top: 0; right: 0; bottom: 0; width: 300px; max-width: 85vw;
    background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
    z-index: 1061; transform: translateX(100%); transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
    display: flex; flex-direction: column;
}
.pn-drawer.show { transform: translateX(0); }
.pn-drawer-header {
    padding: 1.2rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.pn-drawer-close { background: none; border: none; color: rgba(255,255,255,0.7); font-size: 1.5rem; cursor: pointer; }
.pn-drawer-close:hover { color: #fff; }
.pn-drawer-nav { flex: 1; overflow-y: auto; padding: 1rem 0; }
.pn-drawer-link {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1.5rem;
    color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.95rem; font-weight: 500;
    transition: all 0.2s; border-left: 3px solid transparent;
}
.pn-drawer-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
.pn-drawer-link.active { color: #fff; border-left-color: #3b82f6; background: rgba(59,130,246,0.08); }
.pn-drawer-link i { font-size: 1.1rem; width: 22px; text-align: center; }
.pn-drawer-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); }
.pn-drawer-cta {
    display: block; width: 100%; text-align: center;
    background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff;
    border: none; border-radius: 50px; padding: 0.75rem;
    font-size: 0.95rem; font-weight: 600; text-decoration: none;
    box-shadow: 0 4px 15px rgba(239,68,68,0.35);
}

/* Mobile Drawer — Accordion submenu */
.pn-drawer-parent { cursor: pointer; }
.pn-drawer-parent .pn-drawer-chevron {
    margin-left: auto; font-size: 0.7rem; transition: transform 0.25s;
}
.pn-drawer-parent.open .pn-drawer-chevron { transform: rotate(180deg); }
.pn-drawer-submenu {
    max-height: 0; overflow: hidden; transition: max-height 0.3s ease;
}
.pn-drawer-submenu.open { max-height: 300px; }
.pn-drawer-submenu .pn-drawer-link {
    padding-left: 3rem; font-size: 0.88rem;
    color: rgba(255,255,255,0.65);
}
.pn-drawer-submenu .pn-drawer-link:hover { color: #fff; }

/* Mobile bottom CTA */
.pn-mobile-bottom-cta {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1049;
    padding: 0.6rem 1rem; background: rgba(15,23,42,0.95);
    backdrop-filter: blur(10px); border-top: 1px solid rgba(255,255,255,0.08);
}
.pn-mobile-bottom-cta a {
    display: block; width: 100%; text-align: center;
    background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff;
    border-radius: 50px; padding: 0.65rem; font-weight: 600; font-size: 0.9rem;
    text-decoration: none; box-shadow: 0 4px 15px rgba(239,68,68,0.3);
}

/* ── Responsive ── */
@media (max-width: 991.98px) {
    .pn-hamburger { display: inline-block; }
    .pn-desktop-nav { display: none !important; }
    .pn-mobile-bottom-cta { display: block; }
    .pn-logo-wrap img { height: 44px !important; width: auto !important; }
    body { padding-bottom: 60px; }
}
@media (min-width: 992px) {
    .pn-drawer, .pn-drawer-overlay { display: none !important; }
}
</style>

<?php if ($_navShowTopBar === '1'): ?>
<!-- Top Bar -->
<div class="pn-top-bar d-none d-lg-block">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div class="pn-marquee flex-grow-1 me-3">
                <span><?php echo e($_navMarquee ?: "🎓 Welcome to {$schoolName} — {$_navSchoolTagline}"); ?></span>
            </div>
            <div class="d-flex gap-3 flex-shrink-0">
                <a href="/public/admission-form.php"><i class="bi bi-mortarboard me-1"></i>Admissions</a>
                <a href="/public/gallery.php"><i class="bi bi-images me-1"></i>Gallery</a>
                <a href="/public/events.php"><i class="bi bi-calendar-event me-1"></i>Events</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Premium Navbar -->
<nav class="premium-navbar" id="premiumNavbar" role="navigation" aria-label="Main navigation">
    <div class="container d-flex align-items-center justify-content-between">
        <!-- Logo -->
        <a href="/" class="pn-logo-wrap" aria-label="Home">
            <?php if ($navLogo): ?>
                <img src="<?= e($logoPath) ?>" alt="<?= e($schoolName) ?> Logo">
            <?php else: ?>
                <div class="pn-logo-fallback"><i class="bi bi-mortarboard-fill"></i></div>
            <?php endif; ?>
        </a>

        <!-- Desktop Nav -->
        <ul class="pn-nav pn-desktop-nav">
            <?php foreach ($_navRegularItems as $item): ?>
                <?php if (!empty($item['children'])): ?>
                <li class="pn-dropdown">
                    <a class="pn-nav-link pn-dropdown-toggle <?= _navIsActive($item['url'], $_navCurrentPage) || _navHasActiveChild($item['children'], $_navCurrentPage) ? 'active' : '' ?>"
                       href="<?= e($item['url']) ?>">
                        <?= e($item['label']) ?>
                        <i class="bi bi-chevron-down pn-chevron"></i>
                    </a>
                    <div class="pn-dropdown-menu">
                        <?php foreach ($item['children'] as $child): ?>
                        <a class="pn-dropdown-item" href="<?= e($child['url']) ?>"
                           <?= ($child['link_type'] ?? '') === 'external' ? 'target="_blank" rel="noopener"' : '' ?>>
                            <i class="bi <?= e($child['icon'] ?? 'bi-circle') ?>"></i>
                            <?= e($child['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </li>
                <?php else: ?>
                <li>
                    <a class="pn-nav-link <?= _navIsActive($item['url'], $_navCurrentPage) ? 'active' : '' ?>"
                       href="<?= e($item['url']) ?>"
                       <?= ($item['link_type'] ?? '') === 'external' ? 'target="_blank" rel="noopener"' : '' ?>>
                        <?= e($item['label']) ?>
                    </a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <!-- Right Actions -->
        <div class="pn-right">
            <?php if ($_navShowBell === '1'): ?>
            <button class="pn-bell <?= $notifCount > 0 ? 'has-notifs' : '' ?>" data-bs-toggle="modal" data-bs-target="#pnNotifModal" aria-label="Notifications">
                <i class="bi bi-bell-fill"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="pn-bell-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                <?php endif; ?>
            </button>
            <?php endif; ?>

            <?php if ($_navShowLogin === '1'): ?>
            <a href="/login.php" class="pn-login-btn d-none d-lg-inline-flex"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
            <?php endif; ?>

            <?php if ($_navCta): ?>
            <a href="<?= e($_navCta['url']) ?>" class="pn-cta-btn d-none d-lg-inline-flex"><?= e($_navCta['label']) ?> <i class="bi bi-arrow-right ms-1"></i></a>
            <?php endif; ?>

            <button class="pn-hamburger" onclick="pnToggleDrawer()" aria-label="Open menu" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Drawer -->
<div class="pn-drawer-overlay" id="pnDrawerOverlay" onclick="pnCloseDrawer()"></div>
<div class="pn-drawer" id="pnDrawer" role="dialog" aria-label="Navigation menu">
    <div class="pn-drawer-header">
        <span class="text-white fw-bold"><?= e($schoolName) ?></span>
        <button class="pn-drawer-close" onclick="pnCloseDrawer()" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="pn-drawer-nav">
        <?php foreach ($_navRegularItems as $idx => $item): ?>
            <?php if (!empty($item['children'])): ?>
                <!-- Parent with submenu -->
                <div class="pn-drawer-link pn-drawer-parent <?= _navHasActiveChild($item['children'], $_navCurrentPage) ? 'active open' : '' ?>"
                     data-href="<?= e($item['url']) ?>">
                    <i class="bi <?= e($item['icon'] ?? 'bi-circle') ?>"></i>
                    <?= e($item['label']) ?>
                    <i class="bi bi-chevron-down pn-drawer-chevron"></i>
                </div>
                <div class="pn-drawer-submenu <?= _navHasActiveChild($item['children'], $_navCurrentPage) ? 'open' : '' ?>" id="pnSubmenu<?= $idx ?>">
                    <?php foreach ($item['children'] as $child): ?>
                    <a class="pn-drawer-link <?= _navIsActive($child['url'], $_navCurrentPage) ? 'active' : '' ?>"
                       href="<?= e($child['url']) ?>"
                       <?= ($child['link_type'] ?? '') === 'external' ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="bi <?= e($child['icon'] ?? 'bi-circle') ?>"></i>
                        <?= e($child['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <a class="pn-drawer-link <?= _navIsActive($item['url'], $_navCurrentPage) ? 'active' : '' ?>"
                   href="<?= e($item['url']) ?>"
                   <?= ($item['link_type'] ?? '') === 'external' ? 'target="_blank" rel="noopener"' : '' ?>>
                    <i class="bi <?= e($item['icon'] ?? 'bi-circle') ?>"></i>
                    <?= e($item['label']) ?>
                    <?php if ($item['url'] === '/public/notifications.php' && $notifCount > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>


        <?php if ($_navShowLogin === '1'): ?>
        <a class="pn-drawer-link" href="/login.php">
            <i class="bi bi-box-arrow-in-right"></i> Login
        </a>
        <?php endif; ?>
    </nav>
</div>

<!-- Mobile Bottom CTA -->
<?php if ($_navCta): ?>
<div class="pn-mobile-bottom-cta">
    <a href="<?= e($_navCta['url']) ?>"><?= e($_navCta['label']) ?> <i class="bi bi-arrow-right ms-1"></i></a>
</div>
<?php endif; ?>

<!-- Notification Bell Modal -->
<div class="modal fade" id="pnNotifModal" tabindex="-1" aria-label="Notifications">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-bell-fill text-danger me-2"></i>Latest Notifications</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <?php if (empty($bellNotifs)): ?>
                    <p class="text-muted text-center py-3">No recent notifications.</p>
                <?php else: ?>
                    <?php foreach ($bellNotifs as $_bn):
                        $_bnColors = ['urgent'=>'danger','exam'=>'warning','academic'=>'info','event'=>'success'];
                        $_bnColor = $_bnColors[$_bn['type']] ?? 'secondary';
                    ?>
                    <div class="d-flex justify-content-between align-items-start p-2 rounded-3 mb-2" style="background:#f8fafc;">
                        <div>
                            <div class="fw-semibold" style="font-size:0.88rem;"><?= e($_bn['title']) ?></div>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d M Y', strtotime($_bn['created_at'])) ?></small>
                        </div>
                        <span class="badge bg-<?= $_bnColor ?>" style="font-size:0.7rem;"><?= e(ucfirst($_bn['type'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0 pt-0">
                <a href="/public/notifications.php" class="btn btn-primary btn-sm w-100 rounded-3"><i class="bi bi-list-ul me-1"></i>View All Notifications</a>
            </div>
        </div>
    </div>
</div>

<!-- Navbar JS -->
<script>
(function(){
    var nav = document.getElementById('premiumNavbar');
    var lastScroll = 0, ticking = false;
    window.addEventListener('scroll', function(){
        if (!ticking) {
            window.requestAnimationFrame(function(){
                var scroll = window.scrollY;
                nav.classList.toggle('scrolled', scroll > 50);
                if (scroll > lastScroll && scroll > 200 && window.innerWidth >= 992) nav.classList.add('nav-hidden');
                else nav.classList.remove('nav-hidden');
                lastScroll = scroll;
                ticking = false;
            });
            ticking = true;
        }
    });
})();

function pnToggleDrawer() {
    document.getElementById('pnDrawer').classList.toggle('show');
    document.getElementById('pnDrawerOverlay').classList.toggle('show');
    document.body.style.overflow = document.getElementById('pnDrawer').classList.contains('show') ? 'hidden' : '';
}
function pnCloseDrawer() {
    document.getElementById('pnDrawer').classList.remove('show');
    document.getElementById('pnDrawerOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
function pnToggleSubmenu(el) {
    el.classList.toggle('open');
    var sub = el.nextElementSibling;
    if (sub) sub.classList.toggle('open');
}

// Event-delegated handler for submenu toggles + parent navigation
document.addEventListener('DOMContentLoaded', function() {
    var drawer = document.getElementById('pnDrawer');
    if (drawer) {
        drawer.addEventListener('click', function(e) {
            var chevron = e.target.closest('.pn-drawer-chevron');
            var parent = e.target.closest('.pn-drawer-parent');
            if (!parent) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            if (chevron) {
                pnToggleSubmenu(parent);
            } else {
                if (parent.classList.contains('open')) {
                    var href = parent.getAttribute('data-href');
                    if (href) window.location.href = href;
                } else {
                    pnToggleSubmenu(parent);
                }
            }
        });
    }
});
</script>