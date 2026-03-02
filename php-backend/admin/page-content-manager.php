<?php
$pageTitle = 'Page Content Manager';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Load current quote from site_quotes table
$currentQuote = null;
try {
    $currentQuote = $db->query("SELECT q.*, u.name as updater_name FROM site_quotes q LEFT JOIN users u ON q.updated_by=u.id WHERE q.is_active=1 ORDER BY q.id DESC LIMIT 1")->fetch();
} catch (Exception $e) {
    // Table may not exist yet
}

// Define all page content settings with defaults
$pageConfigs = [
    'home' => [
        'label' => 'Home Page',
        'icon' => 'bi-house-fill',
        'fields' => [
            ['key' => 'home_marquee_text', 'label' => 'Marquee Text (Top Bar)', 'type' => 'textarea', 'default' => '🎓 Welcome to [school_name] — [tagline]', 'hint' => 'Use [school_name] and [tagline] as placeholders'],
            ['key' => 'home_hero_show', 'label' => 'Show Hero Slider', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'home_stats_show', 'label' => 'Show Stats Bar', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'home_stats_students_label', 'label' => 'Stats: Students Label', 'type' => 'text', 'default' => 'Students'],
            ['key' => 'home_stats_teachers_label', 'label' => 'Stats: Teachers Label', 'type' => 'text', 'default' => 'Teachers'],
            ['key' => 'home_stats_classes_label', 'label' => 'Stats: Classes Label', 'type' => 'text', 'default' => 'Classes'],
            ['key' => 'home_stats_classes_value', 'label' => 'Stats: Classes Value', 'type' => 'text', 'default' => '12'],
            ['key' => 'home_stats_dedication_label', 'label' => 'Stats: Dedication Label', 'type' => 'text', 'default' => 'Dedication'],
            ['key' => 'home_stats_dedication_value', 'label' => 'Stats: Dedication Value', 'type' => 'text', 'default' => '100%'],
            ['key' => 'home_quicklinks_show', 'label' => 'Show Quick Links Section', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'home_cta_admissions_title', 'label' => 'Quick Link: Admissions Title', 'type' => 'text', 'default' => 'Admissions'],
            ['key' => 'home_cta_admissions_desc', 'label' => 'Quick Link: Admissions Description', 'type' => 'textarea', 'default' => 'Apply online for admission to JNV School.'],
            ['key' => 'home_cta_notifications_title', 'label' => 'Quick Link: Notifications Title', 'type' => 'text', 'default' => 'Notifications'],
            ['key' => 'home_cta_notifications_desc', 'label' => 'Quick Link: Notifications Description', 'type' => 'textarea', 'default' => 'Stay updated with latest announcements.'],
            ['key' => 'home_cta_gallery_title', 'label' => 'Quick Link: Gallery Title', 'type' => 'text', 'default' => 'Gallery'],
            ['key' => 'home_cta_gallery_desc', 'label' => 'Quick Link: Gallery Description', 'type' => 'textarea', 'default' => 'Explore photos & videos from school life.'],
            ['key' => 'home_cta_events_title', 'label' => 'Quick Link: Events Title', 'type' => 'text', 'default' => 'Events'],
            ['key' => 'home_cta_events_desc', 'label' => 'Quick Link: Events Description', 'type' => 'textarea', 'default' => 'Check upcoming school events & dates.'],
            ['key' => 'home_core_team_show', 'label' => 'Show Core Team Section', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'home_core_team_title', 'label' => 'Core Team Section Title', 'type' => 'text', 'default' => 'Our Core Team'],
            ['key' => 'home_core_team_subtitle', 'label' => 'Core Team Subtitle', 'type' => 'textarea', 'default' => 'Meet the dedicated leaders guiding our school\'s vision and mission.'],
            ['key' => 'home_contact_show', 'label' => 'Show Contact Section', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'home_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'home_footer_cta_title', 'label' => 'Footer CTA Title', 'type' => 'text', 'default' => 'Become a Part of [school_name]'],
            ['key' => 'home_footer_cta_desc', 'label' => 'Footer CTA Description', 'type' => 'textarea', 'default' => 'Give your child the gift of quality education. Contact us today to learn more about admissions.'],
            ['key' => 'home_footer_cta_btn_text', 'label' => 'Footer CTA Button Text', 'type' => 'text', 'default' => 'Get In Touch'],
        ],
    ],
    'about' => [
        'label' => 'About Us',
        'icon' => 'bi-info-circle-fill',
        'fields' => [
            ['key' => 'about_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'About Us'],
            ['key' => 'about_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'default' => 'Discover our story, vision, and the values that drive us to provide exceptional education.'],
            ['key' => 'about_hero_badge', 'label' => 'Hero Badge Text', 'type' => 'text', 'default' => 'About Our School'],
            ['key' => 'about_history_show', 'label' => 'Show History Section', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'about_vision_mission_show', 'label' => 'Show Vision & Mission', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'about_core_values_show', 'label' => 'Show Core Values', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'about_leadership_show', 'label' => 'Show Leadership Section', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'about_leadership_title', 'label' => 'Leadership Section Title', 'type' => 'text', 'default' => 'Meet Our Leadership'],
            ['key' => 'about_leadership_subtitle', 'label' => 'Leadership Subtitle / Quote', 'type' => 'textarea', 'default' => 'With dedication and passion, our team creates an environment where every student thrives.'],
            ['key' => 'about_quote_show', 'label' => 'Show Inspirational Quote', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'about_quote_text', 'label' => 'Quote Message', 'type' => 'textarea', 'default' => '', 'source' => 'site_quotes', 'hint' => 'The inspirational quote displayed on the About Us page'],
            ['key' => 'about_quote_author', 'label' => 'Quote Author Name', 'type' => 'text', 'default' => '', 'source' => 'site_quotes', 'hint' => 'Optional — who said this quote'],
            ['key' => 'about_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
        ],
    ],
    'teachers' => [
        'label' => 'Our Teachers',
        'icon' => 'bi-person-badge-fill',
        'fields' => [
            ['key' => 'teachers_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'Our Teachers'],
            ['key' => 'teachers_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'default' => 'Meet our dedicated team of qualified educators who inspire, guide, and shape the future of every student.'],
            ['key' => 'teachers_hero_badge', 'label' => 'Hero Badge Text', 'type' => 'text', 'default' => 'Our Educators'],
            ['key' => 'teachers_core_team_show', 'label' => 'Show Principal Section', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'teachers_grid_title', 'label' => 'Faculty Grid Title', 'type' => 'text', 'default' => 'Meet Our Faculty'],
            ['key' => 'teachers_grid_subtitle', 'label' => 'Faculty Grid Subtitle', 'type' => 'text', 'default' => 'Hover on a card to learn more about each teacher'],
            ['key' => 'teachers_all_show', 'label' => 'Show All Teachers Grid', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'teachers_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
        ],
    ],
    'gallery' => [
        'label' => 'Gallery',
        'icon' => 'bi-images',
        'fields' => [
            ['key' => 'gallery_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'Photo Gallery'],
            ['key' => 'gallery_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'default' => 'Explore moments from [school_name]'],
            ['key' => 'gallery_hero_icon', 'label' => 'Hero Icon (Bootstrap Icons class)', 'type' => 'text', 'default' => 'bi-images'],
            ['key' => 'gallery_layout_style', 'label' => 'Gallery Layout Style', 'type' => 'select', 'options' => ['premium' => 'Premium Dark', 'classic' => 'Classic Grid'], 'default' => 'premium'],
            ['key' => 'gallery_bg_style', 'label' => 'Background Style', 'type' => 'select', 'options' => ['dark' => 'Dark Gradient', 'light' => 'Light'], 'default' => 'dark'],
            ['key' => 'gallery_particles_show', 'label' => 'Show Particle Effects', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'gallery_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
        ],
    ],
    'events' => [
        'label' => 'Events',
        'icon' => 'bi-calendar-event-fill',
        'fields' => [
            ['key' => 'events_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'Events'],
            ['key' => 'events_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'default' => 'Upcoming and past events at [school_name]'],
            ['key' => 'events_hero_icon', 'label' => 'Hero Icon', 'type' => 'text', 'default' => 'bi-calendar-event-fill'],
            ['key' => 'events_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
        ],
    ],
    'notifications' => [
        'label' => 'Notifications',
        'icon' => 'bi-bell-fill',
        'fields' => [
            ['key' => 'notifications_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'Notifications'],
            ['key' => 'notifications_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'default' => 'Stay updated with the latest announcements from [school_name]'],
            ['key' => 'notifications_hero_icon', 'label' => 'Hero Icon', 'type' => 'text', 'default' => 'bi-bell-fill'],
            ['key' => 'notifications_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
        ],
    ],
    'admission' => [
        'label' => 'Admission Form',
        'icon' => 'bi-file-earmark-plus-fill',
        'fields' => [
            ['key' => 'admission_hero_title', 'label' => 'Hero Title', 'type' => 'text', 'default' => 'Apply for Admission'],
            ['key' => 'admission_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'default' => 'Submit your application to [school_name]'],
            ['key' => 'admission_hero_icon', 'label' => 'Hero Icon', 'type' => 'text', 'default' => 'bi-file-earmark-plus-fill'],
            ['key' => 'admission_footer_cta_show', 'label' => 'Show Footer CTA', 'type' => 'toggle', 'default' => '1'],
        ],
    ],
    'global' => [
        'label' => 'Global Elements',
        'icon' => 'bi-globe2',
        'fields' => [
            ['key' => 'global_navbar_show_top_bar', 'label' => 'Show Top Bar (Marquee)', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'global_navbar_show_login', 'label' => 'Show Login Button', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'global_navbar_show_notif_bell', 'label' => 'Show Notification Bell', 'type' => 'toggle', 'default' => '1'],
            ['key' => 'global_footer_cta_title', 'label' => 'Default Footer CTA Title (all pages)', 'type' => 'text', 'default' => 'Become a Part of [school_name]'],
            ['key' => 'global_footer_cta_desc', 'label' => 'Default Footer CTA Description', 'type' => 'textarea', 'default' => 'Give your child the gift of quality education. Contact us today to learn more about admissions.'],
            ['key' => 'global_footer_cta_btn_text', 'label' => 'Default Footer CTA Button Text', 'type' => 'text', 'default' => 'Get In Touch'],
        ],
    ],
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'save_page_content') {
    if (!verifyCsrf()) { setFlash('error', 'Invalid CSRF token.'); header('Location: /admin/page-content-manager.php?page=' . e($_POST['page_key'] ?? 'home')); exit; }
    
    $pageKey = $_POST['page_key'] ?? 'home';
    if (!isset($pageConfigs[$pageKey])) { setFlash('error', 'Invalid page.'); header('Location: /admin/page-content-manager.php'); exit; }
    
    $updated = 0;
    $quoteText = null;
    $quoteAuthor = null;
    foreach ($pageConfigs[$pageKey]['fields'] as $field) {
        $key = $field['key'];
        
        // Handle site_quotes fields separately
        if (isset($field['source']) && $field['source'] === 'site_quotes') {
            if ($key === 'about_quote_text') $quoteText = trim($_POST[$key] ?? '');
            if ($key === 'about_quote_author') $quoteAuthor = trim($_POST[$key] ?? '');
            continue;
        }
        
        if ($field['type'] === 'toggle') {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = trim($_POST[$key] ?? '');
            // Validate length
            if (strlen($value) > 2000) $value = substr($value, 0, 2000);
        }
        
        // Upsert setting
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
        $updated++;
    }
    
    // Save quote to site_quotes table if quote fields were present
    if ($quoteText !== null) {
        if ($quoteText !== '') {
            $existing = $db->query("SELECT id FROM site_quotes WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
            if ($existing) {
                $db->prepare("UPDATE site_quotes SET quote_text=?, author_name=?, updated_by=?, updated_at=NOW() WHERE id=?")
                   ->execute([$quoteText, $quoteAuthor ?: null, currentUserId(), $existing['id']]);
            } else {
                $db->prepare("INSERT INTO site_quotes (quote_text, author_name, updated_by) VALUES (?, ?, ?)")
                   ->execute([$quoteText, $quoteAuthor ?: null, currentUserId()]);
            }
            $updated += 2;
        }
    }
    
    auditLog('page_content_update', 'page_content', null, "Updated {$updated} settings for page: {$pageConfigs[$pageKey]['label']}");
    setFlash('success', "✅ {$pageConfigs[$pageKey]['label']} content updated successfully! ({$updated} fields saved)");
    header('Location: /admin/page-content-manager.php?page=' . $pageKey);
    exit;
}

// Handle reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'reset_defaults') {
    if (!verifyCsrf()) { setFlash('error', 'Invalid CSRF token.'); header('Location: /admin/page-content-manager.php'); exit; }
    
    $pageKey = $_POST['page_key'] ?? 'home';
    if (!isset($pageConfigs[$pageKey])) { setFlash('error', 'Invalid page.'); header('Location: /admin/page-content-manager.php'); exit; }
    
    foreach ($pageConfigs[$pageKey]['fields'] as $field) {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$field['key'], $field['default']]);
    }
    
    auditLog('page_content_reset', 'page_content', null, "Reset defaults for page: {$pageConfigs[$pageKey]['label']}");
    setFlash('success', "🔄 {$pageConfigs[$pageKey]['label']} content reset to defaults.");
    header('Location: /admin/page-content-manager.php?page=' . $pageKey);
    exit;
}

$activePage = $_GET['page'] ?? 'home';
if (!isset($pageConfigs[$activePage])) $activePage = 'home';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.page-tab { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; color: #64748b; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; border: 1px solid transparent; }
.page-tab:hover { color: #1e293b; background: #f1f5f9; }
.page-tab.active { color: #fff; background: var(--primary, #1e40af); border-color: var(--primary, #1e40af); }
.field-group { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 0.75rem; transition: box-shadow 0.2s; }
.field-group:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.field-group label { font-size: 0.82rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem; }
.field-hint { font-size: 0.72rem; color: #94a3b8; margin-top: 0.2rem; }
.toggle-switch { display: flex; align-items: center; gap: 0.75rem; }
.section-divider { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700; padding: 0.5rem 0; margin-top: 0.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 0.75rem; }
</style>

<!-- Page Tabs -->
<div class="card border-0 rounded-3 mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($pageConfigs as $key => $config): ?>
            <a href="/admin/page-content-manager.php?page=<?= $key ?>" class="page-tab <?= $activePage === $key ? 'active' : '' ?>">
                <i class="bi <?= $config['icon'] ?>"></i> <?= e($config['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Content Editor -->
<div class="card border-0 rounded-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi <?= $pageConfigs[$activePage]['icon'] ?> me-2"></i><?= e($pageConfigs[$activePage]['label']) ?> — Content Settings</h5>
            <small class="text-muted">Edit text, headings, and toggle section visibility for this page</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $activePage === 'home' ? '/' : '/public/' . ($activePage === 'admission' ? 'admission-form' : $activePage) . '.php' ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Preview Page
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="save_page_content">
            <input type="hidden" name="page_key" value="<?= e($activePage) ?>">
            
            <?php
            $lastCategory = '';
            foreach ($pageConfigs[$activePage]['fields'] as $field):
                // Auto-detect category from key prefix
                $parts = explode('_', str_replace($activePage . '_', '', $field['key']), 2);
                $category = $parts[0] ?? '';
                
                // For site_quotes fields, get value from the quote record
                if (isset($field['source']) && $field['source'] === 'site_quotes') {
                    if ($field['key'] === 'about_quote_text') {
                        $currentValue = $currentQuote['quote_text'] ?? '';
                    } elseif ($field['key'] === 'about_quote_author') {
                        $currentValue = $currentQuote['author_name'] ?? '';
                    }
                } else {
                    $currentValue = getSetting($field['key'], $field['default']);
                }
            ?>
            
            <div class="field-group">
                <?php if ($field['type'] === 'toggle'): ?>
                <div class="toggle-switch">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="<?= e($field['key']) ?>" id="<?= e($field['key']) ?>" <?= $currentValue === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="<?= e($field['key']) ?>" style="font-size:0.85rem;"><?= e($field['label']) ?></label>
                    </div>
                </div>
                <?php elseif ($field['type'] === 'select'): ?>
                <label for="<?= e($field['key']) ?>"><?= e($field['label']) ?></label>
                <select class="form-select form-select-sm" name="<?= e($field['key']) ?>" id="<?= e($field['key']) ?>">
                    <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                    <option value="<?= e($optVal) ?>" <?= $currentValue === $optVal ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php elseif ($field['type'] === 'textarea'): ?>
                <label for="<?= e($field['key']) ?>"><?= e($field['label']) ?></label>
                <textarea class="form-control form-control-sm" name="<?= e($field['key']) ?>" id="<?= e($field['key']) ?>" rows="2" maxlength="2000"><?= e($currentValue) ?></textarea>
                <?php if (isset($field['hint'])): ?><div class="field-hint"><?= e($field['hint']) ?></div><?php endif; ?>
                <?php else: ?>
                <label for="<?= e($field['key']) ?>"><?= e($field['label']) ?></label>
                <input type="text" class="form-control form-control-sm" name="<?= e($field['key']) ?>" id="<?= e($field['key']) ?>" value="<?= e($currentValue) ?>" maxlength="500">
                <?php if (isset($field['hint'])): ?><div class="field-hint"><?= e($field['hint']) ?></div><?php endif; ?>
                <?php endif; ?>
                <?php // Show "Last updated" info after the quote author field ?>
                <?php if (isset($field['source']) && $field['source'] === 'site_quotes' && $field['key'] === 'about_quote_author' && $currentQuote && $currentQuote['updated_at']): ?>
                <div class="bg-light rounded-3 p-2 mt-2">
                    <small class="text-muted" style="font-size:.72rem">
                        <i class="bi bi-clock me-1"></i>Quote last updated: <?= date('d M Y, h:i A', strtotime($currentQuote['updated_at'])) ?>
                        <?php if ($currentQuote['updater_name']): ?> by <strong><?= e($currentQuote['updater_name']) ?></strong><?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-dark px-4"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default</button>
            </div>
        </form>

        <?php if ($activePage === 'teachers'): ?>
        <!-- ═══════════ PRINCIPAL PROFILE EDITOR ═══════════ -->
        <div class="mt-4">
            <div class="card border rounded-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#principalPanel">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Principal Profile Editor</h6>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="collapse show" id="principalPanel">
                    <div class="card-body" id="principalEditorBody">
                        <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ TEACHERS GRID MANAGER ═══════════ -->
        <div class="mt-3">
            <div class="card border rounded-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#teachersGridPanel">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i>Manage Teachers</h6>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="collapse show" id="teachersGridPanel">
                    <div class="card-body" id="teachersGridBody">
                        <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teacher Add/Edit Modal -->
        <div class="modal fade" id="teacherModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="teacherModalTitle">Add Teacher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="teacherForm" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_teacher">
                            <input type="hidden" name="id" id="tf_id" value="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" id="tf_name" required maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Designation</label>
                                    <input type="text" class="form-control" name="designation" id="tf_designation" value="Teacher" maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Subject / Role</label>
                                    <input type="text" class="form-control" name="subject" id="tf_subject" maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Qualification</label>
                                    <input type="text" class="form-control" name="qualification" id="tf_qualification" maxlength="100">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Short Bio</label>
                                    <textarea class="form-control" name="bio" id="tf_bio" rows="2" maxlength="1000"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Profile Photo</label>
                                    <input type="file" class="form-control" name="photo" id="tf_photo" accept="image/*">
                                    <div id="tf_photo_preview" class="mt-2"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Visible</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" name="is_visible" id="tf_is_visible" value="1" checked>
                                        <label class="form-check-label" for="tf_is_visible">Show on public page</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Featured</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" name="is_featured" id="tf_is_featured" value="1">
                                        <label class="form-check-label" for="tf_is_featured">Featured badge</label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveTeacherBtn"><i class="bi bi-check-lg me-1"></i>Save Teacher</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .teacher-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; background: #fff; transition: box-shadow 0.2s; }
        .teacher-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .teacher-row .drag-handle { cursor: grab; color: #94a3b8; font-size: 1.1rem; }
        .teacher-row .drag-handle:active { cursor: grabbing; }
        .teacher-row.dragging { opacity: 0.5; box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .teacher-row .t-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .teacher-row .t-thumb img { width: 100%; height: 100%; border-radius: 8px; object-fit: cover; }
        .teacher-row .t-info { flex: 1; min-width: 0; }
        .teacher-row .t-info strong { font-size: 0.88rem; }
        .teacher-row .t-info small { color: #64748b; font-size: 0.78rem; }
        .teacher-row .t-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .principal-photo-preview { width: 120px; height: 150px; border-radius: 12px; object-fit: cover; border: 2px solid #e2e8f0; }
        </style>

        <script>
        const CSRF = '<?= csrfToken() ?>';
        const AJAX_URL = '/admin/ajax/teacher-actions.php';

        function showToast(msg, type='success') {
            const t = document.createElement('div');
            t.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow-sm`;
            t.style.zIndex = '9999';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        // ══════ PRINCIPAL EDITOR ══════
        async function loadPrincipal() {
            const body = document.getElementById('principalEditorBody');
            try {
                const res = await fetch(AJAX_URL + '?action=get_principal');
                const data = await res.json();
                const p = data.principal;
                const photoSrc = p && p.photo ? (p.photo.startsWith('/uploads/') ? p.photo : '/uploads/photos/' + p.photo) : '';
                body.innerHTML = `
                    <form id="principalForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="${CSRF}">
                        <input type="hidden" name="action" value="save_principal">
                        <input type="hidden" name="id" value="${p ? p.id : 0}">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-3 text-center">
                                <div id="princPhotoPreview">
                                    ${photoSrc ? `<img src="${photoSrc}" class="principal-photo-preview">` : `<div class="principal-photo-preview d-flex align-items-center justify-content-center bg-light mx-auto"><i class="bi bi-person-fill" style="font-size:2.5rem;color:#94a3b8;"></i></div>`}
                                </div>
                                <input type="file" name="photo" id="princPhoto" accept="image/*" class="form-control form-control-sm mt-2">
                                ${photoSrc ? `<button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="document.getElementById('princRemovePhoto').value='1';this.previousElementSibling.previousElementSibling.querySelector('img,div').style.opacity='0.3';this.textContent='Will be removed'"><i class="bi bi-trash me-1"></i>Remove</button>` : ''}
                                <input type="hidden" name="remove_photo" id="princRemovePhoto" value="0">
                            </div>
                            <div class="col-md-9">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="name" value="${p ? escHtml(p.name) : ''}" required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Designation</label>
                                        <input type="text" class="form-control form-control-sm" name="designation" value="${p ? escHtml(p.designation || 'Principal') : 'Principal'}" maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Qualification</label>
                                        <input type="text" class="form-control form-control-sm" name="qualification" value="${p ? escHtml(p.qualification || '') : ''}" maxlength="100">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Message / Quote</label>
                                        <textarea class="form-control form-control-sm" name="bio" rows="4" maxlength="2000">${p ? escHtml(p.bio || '') : ''}</textarea>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm mt-3" onclick="savePrincipal()"><i class="bi bi-check-lg me-1"></i>Save Principal</button>
                            </div>
                        </div>
                    </form>`;

                // Image preview
                document.getElementById('princPhoto').addEventListener('change', function(e) {
                    if (e.target.files[0]) {
                        const reader = new FileReader();
                        reader.onload = ev => {
                            document.getElementById('princPhotoPreview').innerHTML = `<img src="${ev.target.result}" class="principal-photo-preview">`;
                            document.getElementById('princRemovePhoto').value = '0';
                        };
                        reader.readAsDataURL(e.target.files[0]);
                    }
                });
            } catch(err) {
                body.innerHTML = '<div class="text-danger">Failed to load principal data.</div>';
            }
        }

        async function savePrincipal() {
            const form = document.getElementById('principalForm');
            const fd = new FormData(form);
            try {
                const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) { showToast('Principal saved successfully!'); loadPrincipal(); }
                else showToast(data.message || 'Error saving', 'danger');
            } catch(err) { showToast('Network error', 'danger'); }
        }

        // ══════ TEACHERS GRID MANAGER ══════
        let teachersList = [];
        async function loadTeachers(search='') {
            const body = document.getElementById('teachersGridBody');
            try {
                const res = await fetch(AJAX_URL + '?action=list_teachers&search=' + encodeURIComponent(search));
                const data = await res.json();
                teachersList = data.teachers || [];
                renderTeachersList();
            } catch(err) {
                body.innerHTML = '<div class="text-danger">Failed to load teachers.</div>';
            }
        }

        function renderTeachersList() {
            const body = document.getElementById('teachersGridBody');
            let html = `<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="input-group" style="max-width:300px;">
                    <input type="text" class="form-control form-control-sm" placeholder="Search teachers..." id="teacherSearch" oninput="debounceSearch(this.value)">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openTeacherModal()"><i class="bi bi-plus-lg me-1"></i>Add Teacher</button>
            </div>`;

            if (teachersList.length === 0) {
                html += '<div class="text-center text-muted py-4"><i class="bi bi-people" style="font-size:2rem;"></i><p class="mt-2">No teachers found.</p></div>';
            } else {
                html += '<div id="teacherSortableList">';
                teachersList.forEach((t, i) => {
                    const photo = t.photo ? (t.photo.startsWith('/uploads/') ? t.photo : '/uploads/photos/' + t.photo) : '';
                    html += `<div class="teacher-row" draggable="true" data-id="${t.id}" ondragstart="dragStart(event)" ondragover="dragOver(event)" ondrop="dropRow(event)" ondragend="dragEnd(event)">
                        <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                        <div class="t-thumb">
                            ${photo ? `<img src="${photo}" alt="">` : `<i class="bi bi-person-fill text-muted"></i>`}
                        </div>
                        <div class="t-info">
                            <strong>${escHtml(t.name)}</strong>
                            ${t.is_featured == 1 ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Featured</span>' : ''}
                            <br><small>${escHtml(t.subject || t.designation || 'Teacher')}</small>
                        </div>
                        <div class="t-actions">
                            <div class="form-check form-switch" title="Visible on public page">
                                <input class="form-check-input" type="checkbox" ${t.is_visible == 1 ? 'checked' : ''} onchange="toggleVis(${t.id})">
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick='editTeacher(${JSON.stringify(t).replace(/'/g, "\\'")})'><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTeacher(${t.id}, '${escHtml(t.name)}')"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>`;
                });
                html += '</div>';
            }
            body.innerHTML = html;
        }

        // Search debounce
        let searchTimer;
        function debounceSearch(val) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadTeachers(val), 300);
        }

        // ── Drag & Drop ──
        let draggedEl = null;
        function dragStart(e) { draggedEl = e.currentTarget; e.currentTarget.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
        function dragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; const target = e.currentTarget; if (target !== draggedEl && target.classList.contains('teacher-row')) { const rect = target.getBoundingClientRect(); const mid = rect.top + rect.height / 2; target.parentNode.insertBefore(draggedEl, e.clientY < mid ? target : target.nextSibling); } }
        function dropRow(e) { e.preventDefault(); saveOrder(); }
        function dragEnd(e) { e.currentTarget.classList.remove('dragging'); draggedEl = null; }

        async function saveOrder() {
            const rows = document.querySelectorAll('#teacherSortableList .teacher-row');
            const order = Array.from(rows).map(r => r.dataset.id);
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('action', 'reorder');
            fd.append('order', JSON.stringify(order));
            await fetch(AJAX_URL, { method: 'POST', body: fd });
        }

        // ── Toggle Visibility ──
        async function toggleVis(id) {
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('action', 'toggle_visibility');
            fd.append('id', id);
            await fetch(AJAX_URL, { method: 'POST', body: fd });
        }

        // ── Modal ──
        function openTeacherModal(teacher = null) {
            document.getElementById('teacherModalTitle').textContent = teacher ? 'Edit Teacher' : 'Add Teacher';
            document.getElementById('tf_id').value = teacher ? teacher.id : 0;
            document.getElementById('tf_name').value = teacher ? teacher.name : '';
            document.getElementById('tf_designation').value = teacher ? (teacher.designation || 'Teacher') : 'Teacher';
            document.getElementById('tf_subject').value = teacher ? (teacher.subject || '') : '';
            document.getElementById('tf_qualification').value = teacher ? (teacher.qualification || '') : '';
            document.getElementById('tf_bio').value = teacher ? (teacher.bio || '') : '';
            document.getElementById('tf_is_visible').checked = teacher ? teacher.is_visible == 1 : true;
            document.getElementById('tf_is_featured').checked = teacher ? teacher.is_featured == 1 : false;
            document.getElementById('tf_photo').value = '';
            const preview = document.getElementById('tf_photo_preview');
            if (teacher && teacher.photo) {
                const src = teacher.photo.startsWith('/uploads/') ? teacher.photo : '/uploads/photos/' + teacher.photo;
                preview.innerHTML = `<img src="${src}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">`;
            } else {
                preview.innerHTML = '';
            }
            new bootstrap.Modal(document.getElementById('teacherModal')).show();
        }

        function editTeacher(t) { openTeacherModal(t); }

        document.getElementById('saveTeacherBtn').addEventListener('click', async () => {
            const form = document.getElementById('teacherForm');
            if (!document.getElementById('tf_name').value.trim()) { showToast('Name is required', 'warning'); return; }
            const fd = new FormData(form);
            fd.set('is_visible', document.getElementById('tf_is_visible').checked ? '1' : '0');
            fd.set('is_featured', document.getElementById('tf_is_featured').checked ? '1' : '0');
            try {
                const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast('Teacher saved!');
                    bootstrap.Modal.getInstance(document.getElementById('teacherModal')).hide();
                    loadTeachers();
                } else showToast(data.message || 'Error', 'danger');
            } catch(err) { showToast('Network error', 'danger'); }
        });

        // Image preview in modal
        document.getElementById('tf_photo').addEventListener('change', function(e) {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = ev => {
                    document.getElementById('tf_photo_preview').innerHTML = `<img src="${ev.target.result}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">`;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        async function deleteTeacher(id, name) {
            if (!confirm(`Delete teacher "${name}"? This cannot be undone.`)) return;
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('action', 'delete_teacher');
            fd.append('id', id);
            try {
                const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) { showToast('Teacher deleted'); loadTeachers(); }
                else showToast(data.message || 'Error', 'danger');
            } catch(err) { showToast('Network error', 'danger'); }
        }

        function escHtml(str) {
            if (!str) return '';
            const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
        }

        // Init
        loadPrincipal();
        loadTeachers();
        </script>
        <?php endif; ?>

        <?php if ($activePage === 'gallery'): ?>
        <!-- ═══════════ GALLERY CATEGORIES MANAGER ═══════════ -->
        <div class="mt-4">
            <div class="card border rounded-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#galleryCatPanel">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Gallery Categories Manager</h6>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="collapse show" id="galleryCatPanel">
                    <div class="card-body" id="galleryCatBody">
                        <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Add/Edit Modal -->
        <div class="modal fade" id="catModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="catModalTitle">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="catForm" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_category">
                            <input type="hidden" name="id" id="cf_id" value="0">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="cf_name" required maxlength="100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cover Image</label>
                                <input type="file" class="form-control" name="cover_image" id="cf_cover" accept="image/*">
                                <div id="cf_cover_preview" class="mt-2"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" id="cf_desc" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select" name="status" id="cf_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveCatBtn"><i class="bi bi-check-lg me-1"></i>Save Category</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Album Add/Edit Modal -->
        <div class="modal fade" id="albumModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="albumModalTitle">Add Album</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="albumForm" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_album">
                            <input type="hidden" name="id" id="af_id" value="0">
                            <input type="hidden" name="category_id" id="af_cat_id" value="0">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" id="af_title" required maxlength="200">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cover Image</label>
                                <input type="file" class="form-control" name="cover_image" id="af_cover" accept="image/*">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Event Date</label>
                                    <input type="date" class="form-control" name="event_date" id="af_date">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Year</label>
                                    <input type="text" class="form-control" name="year" id="af_year" maxlength="10" placeholder="e.g. 2026">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select" name="status" id="af_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveAlbumBtn"><i class="bi bi-check-lg me-1"></i>Save Album</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .cat-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; background: #fff; transition: box-shadow 0.2s; }
        .cat-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .cat-row .drag-handle { cursor: grab; color: #94a3b8; font-size: 1.1rem; }
        .cat-row.dragging { opacity: 0.5; }
        .cat-row .c-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .cat-row .c-thumb img { width: 100%; height: 100%; border-radius: 8px; object-fit: cover; }
        .cat-row .c-info { flex: 1; min-width: 0; }
        .cat-row .c-info strong { font-size: 0.88rem; }
        .cat-row .c-info small { color: #64748b; font-size: 0.78rem; }
        .cat-row .c-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .album-sub { margin-left: 2rem; margin-top: 0.25rem; }
        .album-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.6rem; border: 1px dashed #e2e8f0; border-radius: 6px; margin-bottom: 0.25rem; background: #fafbfc; font-size: 0.82rem; }
        .album-row .a-info { flex: 1; }
        </style>

        <script>
        const G_CSRF = '<?= csrfToken() ?>';
        const G_AJAX = '/admin/ajax/gallery-actions.php';

        function gToast(msg, type='success') {
            const t = document.createElement('div');
            t.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow-sm`;
            t.style.zIndex = '9999'; t.textContent = msg;
            document.body.appendChild(t); setTimeout(() => t.remove(), 3000);
        }
        function gEsc(s) { if(!s)return''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

        let catList = [];
        async function loadCategories() {
            const body = document.getElementById('galleryCatBody');
            try {
                const res = await fetch(G_AJAX + '?action=list_categories');
                const data = await res.json();
                catList = data.categories || [];
                renderCategories();
            } catch(e) { body.innerHTML = '<div class="text-danger">Failed to load.</div>'; }
        }

        async function loadAlbumsForCat(catId) {
            try {
                const res = await fetch(G_AJAX + '?action=list_albums&category_id=' + catId);
                const data = await res.json();
                return data.albums || [];
            } catch(e) { return []; }
        }

        async function renderCategories() {
            const body = document.getElementById('galleryCatBody');
            let html = `<div class="d-flex justify-content-between align-items-center mb-3">
                <small class="text-muted">${catList.length} categories</small>
                <button class="btn btn-primary btn-sm" onclick="openCatModal()"><i class="bi bi-plus-lg me-1"></i>Add Category</button>
            </div><div id="catSortableList">`;

            for (const c of catList) {
                const cover = c.cover_image ? '/' + c.cover_image : '';
                const isActive = c.status === 'active';
                html += `<div class="cat-row" draggable="true" data-id="${c.id}" ondragstart="catDragStart(event)" ondragover="catDragOver(event)" ondrop="catDropRow(event)" ondragend="catDragEnd(event)">
                    <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                    <div class="c-thumb">${cover ? `<img src="${cover}">` : `<i class="bi bi-images text-muted"></i>`}</div>
                    <div class="c-info">
                        <strong>${gEsc(c.name)}</strong>
                        ${!isActive ? '<span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Inactive</span>' : ''}
                        <br><small>${gEsc(c.slug)}</small>
                    </div>
                    <div class="c-actions">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" ${isActive?'checked':''} onchange="toggleCatStatus(${c.id})"></div>
                        <button class="btn btn-sm btn-outline-info" onclick="toggleAlbums(${c.id})" title="Albums"><i class="bi bi-folder2-open"></i></button>
                        <button class="btn btn-sm btn-outline-primary" onclick='editCat(${JSON.stringify(c).replace(/'/g,"\\'")})'><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCat(${c.id},'${gEsc(c.name)}')"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="album-sub d-none" id="albums-${c.id}"><div class="text-center py-2"><small class="text-muted">Loading albums...</small></div></div>`;
            }
            html += '</div>';
            if (!catList.length) html = '<div class="text-center text-muted py-4"><i class="bi bi-grid-3x3-gap" style="font-size:2rem;"></i><p class="mt-2">No categories yet.</p><button class="btn btn-primary btn-sm" onclick="openCatModal()"><i class="bi bi-plus-lg me-1"></i>Add Category</button></div>';
            body.innerHTML = html;
        }

        async function toggleAlbums(catId) {
            const el = document.getElementById('albums-' + catId);
            if (!el.classList.contains('d-none')) { el.classList.add('d-none'); return; }
            el.classList.remove('d-none');
            const albums = await loadAlbumsForCat(catId);
            let html = `<div class="d-flex justify-content-between mb-2"><small class="text-muted">${albums.length} album(s)</small><button class="btn btn-sm btn-outline-primary" onclick="openAlbumModal(${catId})"><i class="bi bi-plus me-1"></i>Add Album</button></div>`;
            albums.forEach(a => {
                html += `<div class="album-row">
                    <div class="a-info"><strong>${gEsc(a.title)}</strong> <small class="text-muted ms-1">${a.status === 'inactive' ? '(Hidden)' : ''}</small></div>
                    <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick='editAlbum(${JSON.stringify(a).replace(/'/g,"\\'")})'><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteAlbum(${a.id},'${gEsc(a.title)}')"><i class="bi bi-trash"></i></button>
                </div>`;
            });
            if (!albums.length) html += '<small class="text-muted">No albums in this category.</small>';
            el.innerHTML = html;
        }

        // Drag & Drop
        let catDraggedEl = null;
        function catDragStart(e) { catDraggedEl = e.currentTarget; e.currentTarget.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
        function catDragOver(e) { e.preventDefault(); const t = e.currentTarget; if (t !== catDraggedEl && t.classList.contains('cat-row')) { const r = t.getBoundingClientRect(); t.parentNode.insertBefore(catDraggedEl, e.clientY < r.top + r.height/2 ? t : t.nextSibling); } }
        function catDropRow(e) { e.preventDefault(); saveCatOrder(); }
        function catDragEnd(e) { e.currentTarget.classList.remove('dragging'); catDraggedEl = null; }

        async function saveCatOrder() {
            const rows = document.querySelectorAll('#catSortableList .cat-row');
            const order = Array.from(rows).map(r => r.dataset.id);
            const fd = new FormData();
            fd.append('csrf_token', G_CSRF); fd.append('action', 'reorder_categories'); fd.append('order', JSON.stringify(order));
            await fetch(G_AJAX, { method: 'POST', body: fd });
        }

        async function toggleCatStatus(id) {
            const fd = new FormData();
            fd.append('csrf_token', G_CSRF); fd.append('action', 'toggle_category_status'); fd.append('id', id);
            await fetch(G_AJAX, { method: 'POST', body: fd });
            loadCategories();
        }

        // Category Modal
        function openCatModal(cat = null) {
            document.getElementById('catModalTitle').textContent = cat ? 'Edit Category' : 'Add Category';
            document.getElementById('cf_id').value = cat ? cat.id : 0;
            document.getElementById('cf_name').value = cat ? cat.name : '';
            document.getElementById('cf_desc').value = cat ? (cat.description || '') : '';
            document.getElementById('cf_status').value = cat ? cat.status : 'active';
            document.getElementById('cf_cover').value = '';
            const preview = document.getElementById('cf_cover_preview');
            if (cat && cat.cover_image) preview.innerHTML = `<img src="/${cat.cover_image}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">`;
            else preview.innerHTML = '';
            new bootstrap.Modal(document.getElementById('catModal')).show();
        }
        function editCat(c) { openCatModal(c); }

        document.getElementById('saveCatBtn').addEventListener('click', async () => {
            if (!document.getElementById('cf_name').value.trim()) { gToast('Name required', 'warning'); return; }
            const fd = new FormData(document.getElementById('catForm'));
            const res = await fetch(G_AJAX, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { gToast('Category saved!'); bootstrap.Modal.getInstance(document.getElementById('catModal')).hide(); loadCategories(); }
            else gToast(data.message || 'Error', 'danger');
        });

        async function deleteCat(id, name) {
            if (!confirm(`Delete category "${name}"? Albums inside will also be removed.`)) return;
            const fd = new FormData();
            fd.append('csrf_token', G_CSRF); fd.append('action', 'delete_category'); fd.append('id', id);
            const res = await fetch(G_AJAX, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { gToast('Deleted'); loadCategories(); }
            else gToast(data.message || 'Error', 'danger');
        }

        // Album Modal
        function openAlbumModal(catId, album = null) {
            document.getElementById('albumModalTitle').textContent = album ? 'Edit Album' : 'Add Album';
            document.getElementById('af_id').value = album ? album.id : 0;
            document.getElementById('af_cat_id').value = album ? album.category_id : catId;
            document.getElementById('af_title').value = album ? album.title : '';
            document.getElementById('af_date').value = album ? (album.event_date || '') : '';
            document.getElementById('af_year').value = album ? (album.year || '') : '';
            document.getElementById('af_status').value = album ? album.status : 'active';
            document.getElementById('af_cover').value = '';
            new bootstrap.Modal(document.getElementById('albumModal')).show();
        }
        function editAlbum(a) { openAlbumModal(a.category_id, a); }

        document.getElementById('saveAlbumBtn').addEventListener('click', async () => {
            if (!document.getElementById('af_title').value.trim()) { gToast('Title required', 'warning'); return; }
            const fd = new FormData(document.getElementById('albumForm'));
            const res = await fetch(G_AJAX, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { gToast('Album saved!'); bootstrap.Modal.getInstance(document.getElementById('albumModal')).hide(); toggleAlbums(parseInt(document.getElementById('af_cat_id').value)); }
            else gToast(data.message || 'Error', 'danger');
        });

        async function deleteAlbum(id, name) {
            if (!confirm(`Delete album "${name}"?`)) return;
            const fd = new FormData();
            fd.append('csrf_token', G_CSRF); fd.append('action', 'delete_album'); fd.append('id', id);
            const res = await fetch(G_AJAX, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { gToast('Deleted'); loadCategories(); }
            else gToast(data.message || 'Error', 'danger');
        }

        // Cover image preview
        document.getElementById('cf_cover').addEventListener('change', function(e) {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = ev => { document.getElementById('cf_cover_preview').innerHTML = `<img src="${ev.target.result}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;">`; };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        loadCategories();
        </script>
        <?php endif; ?>

        <?php if ($activePage === 'about'): ?>
        <!-- ═══════════ LEADERSHIP PROFILES MANAGER ═══════════ -->
        <div class="mt-4">
            <div class="card border rounded-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#leadershipPanel">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i>Leadership Profiles Manager</h6>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="collapse show" id="leadershipPanel">
                    <div class="card-body" id="leadershipGridBody">
                        <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leader Add/Edit Modal -->
        <div class="modal fade" id="leaderModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="leaderModalTitle">Add Leader</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="leaderForm" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_leader">
                            <input type="hidden" name="id" id="lf_id" value="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" id="lf_name" required maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Designation</label>
                                    <input type="text" class="form-control" name="designation" id="lf_designation" maxlength="100" placeholder="e.g., Correspondent, Director, Principal">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Short Bio (optional)</label>
                                    <textarea class="form-control" name="bio" id="lf_bio" rows="2" maxlength="1000"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Profile Photo</label>
                                    <input type="file" class="form-control" name="photo" id="lf_photo" accept="image/*">
                                    <div id="lf_photo_preview" class="mt-2"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select class="form-select" name="status" id="lf_status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveLeaderBtn"><i class="bi bi-check-lg me-1"></i>Save Leader</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .leader-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; background: #fff; transition: box-shadow 0.2s; }
        .leader-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .leader-row .drag-handle { cursor: grab; color: #94a3b8; font-size: 1.1rem; }
        .leader-row .drag-handle:active { cursor: grabbing; }
        .leader-row.dragging { opacity: 0.5; box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .leader-row .l-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .leader-row .l-thumb img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .leader-row .l-info { flex: 1; min-width: 0; }
        .leader-row .l-info strong { font-size: 0.88rem; }
        .leader-row .l-info small { color: #64748b; font-size: 0.78rem; }
        .leader-row .l-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        </style>

        <script>
        const L_CSRF = '<?= csrfToken() ?>';
        const L_AJAX_URL = '/admin/ajax/leadership-actions.php';

        function showLeaderToast(msg, type='success') {
            const t = document.createElement('div');
            t.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow-sm`;
            t.style.zIndex = '9999';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        function escLeaderHtml(str) {
            if (!str) return '';
            const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
        }

        // ══════ LEADERSHIP GRID MANAGER ══════
        let leadersList = [];
        async function loadLeaders(search='') {
            const body = document.getElementById('leadershipGridBody');
            try {
                const res = await fetch(L_AJAX_URL + '?action=list_leaders&search=' + encodeURIComponent(search));
                const data = await res.json();
                leadersList = data.leaders || [];
                renderLeadersList();
            } catch(err) {
                body.innerHTML = '<div class="text-danger">Failed to load leadership profiles.</div>';
            }
        }

        function renderLeadersList() {
            const body = document.getElementById('leadershipGridBody');
            let html = `<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="input-group" style="max-width:300px;">
                    <input type="text" class="form-control form-control-sm" placeholder="Search leaders..." id="leaderSearch" oninput="debounceLeaderSearch(this.value)">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openLeaderModal()"><i class="bi bi-plus-lg me-1"></i>Add Leader</button>
            </div>`;

            if (leadersList.length === 0) {
                html += '<div class="text-center text-muted py-4"><i class="bi bi-people" style="font-size:2rem;"></i><p class="mt-2">No leadership profiles found.</p></div>';
            } else {
                html += '<div id="leaderSortableList">';
                leadersList.forEach((l, i) => {
                    const photo = l.photo ? (l.photo.startsWith('/uploads/') ? l.photo : '/uploads/photos/' + l.photo) : '';
                    const isActive = l.status === 'active';
                    html += `<div class="leader-row" draggable="true" data-id="${l.id}" ondragstart="leaderDragStart(event)" ondragover="leaderDragOver(event)" ondrop="leaderDropRow(event)" ondragend="leaderDragEnd(event)">
                        <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                        <div class="l-thumb">
                            ${photo ? `<img src="${photo}" alt="">` : `<i class="bi bi-person-fill text-muted"></i>`}
                        </div>
                        <div class="l-info">
                            <strong>${escLeaderHtml(l.name)}</strong>
                            ${!isActive ? '<span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Inactive</span>' : ''}
                            <br><small>${escLeaderHtml(l.designation || 'Leader')}</small>
                        </div>
                        <div class="l-actions">
                            <div class="form-check form-switch" title="Active / Inactive">
                                <input class="form-check-input" type="checkbox" ${isActive ? 'checked' : ''} onchange="toggleLeaderStatus(${l.id})">
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick='editLeader(${JSON.stringify(l).replace(/'/g, "\\'").replace(/"/g, "&quot;")})'><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteLeader(${l.id}, '${escLeaderHtml(l.name)}')"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>`;
                });
                html += '</div>';
            }
            body.innerHTML = html;
        }

        let leaderSearchTimer;
        function debounceLeaderSearch(val) {
            clearTimeout(leaderSearchTimer);
            leaderSearchTimer = setTimeout(() => loadLeaders(val), 300);
        }

        // ── Drag & Drop ──
        let leaderDraggedEl = null;
        function leaderDragStart(e) { leaderDraggedEl = e.currentTarget; e.currentTarget.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
        function leaderDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; const target = e.currentTarget; if (target !== leaderDraggedEl && target.classList.contains('leader-row')) { const rect = target.getBoundingClientRect(); const mid = rect.top + rect.height / 2; target.parentNode.insertBefore(leaderDraggedEl, e.clientY < mid ? target : target.nextSibling); } }
        function leaderDropRow(e) { e.preventDefault(); saveLeaderOrder(); }
        function leaderDragEnd(e) { e.currentTarget.classList.remove('dragging'); leaderDraggedEl = null; }

        async function saveLeaderOrder() {
            const rows = document.querySelectorAll('#leaderSortableList .leader-row');
            const order = Array.from(rows).map(r => r.dataset.id);
            const fd = new FormData();
            fd.append('csrf_token', L_CSRF);
            fd.append('action', 'reorder');
            fd.append('order', JSON.stringify(order));
            await fetch(L_AJAX_URL, { method: 'POST', body: fd });
        }

        async function toggleLeaderStatus(id) {
            const fd = new FormData();
            fd.append('csrf_token', L_CSRF);
            fd.append('action', 'toggle_status');
            fd.append('id', id);
            const res = await fetch(L_AJAX_URL, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) loadLeaders();
        }

        // ── Modal ──
        function openLeaderModal(leader = null) {
            document.getElementById('leaderModalTitle').textContent = leader ? 'Edit Leader' : 'Add Leader';
            document.getElementById('lf_id').value = leader ? leader.id : 0;
            document.getElementById('lf_name').value = leader ? leader.name : '';
            document.getElementById('lf_designation').value = leader ? (leader.designation || '') : '';
            document.getElementById('lf_bio').value = leader ? (leader.bio || '') : '';
            document.getElementById('lf_status').value = leader ? leader.status : 'active';
            document.getElementById('lf_photo').value = '';
            const preview = document.getElementById('lf_photo_preview');
            if (leader && leader.photo) {
                const src = leader.photo.startsWith('/uploads/') ? leader.photo : '/uploads/photos/' + leader.photo;
                preview.innerHTML = `<img src="${src}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">`;
            } else {
                preview.innerHTML = '';
            }
            new bootstrap.Modal(document.getElementById('leaderModal')).show();
        }

        function editLeader(l) { openLeaderModal(l); }

        document.getElementById('saveLeaderBtn').addEventListener('click', async () => {
            const form = document.getElementById('leaderForm');
            if (!document.getElementById('lf_name').value.trim()) { showLeaderToast('Name is required', 'warning'); return; }
            const fd = new FormData(form);
            try {
                const res = await fetch(L_AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showLeaderToast('Leader saved!');
                    bootstrap.Modal.getInstance(document.getElementById('leaderModal')).hide();
                    loadLeaders();
                } else showLeaderToast(data.message || 'Error', 'danger');
            } catch(err) { showLeaderToast('Network error', 'danger'); }
        });

        document.getElementById('lf_photo').addEventListener('change', function(e) {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = ev => {
                    document.getElementById('lf_photo_preview').innerHTML = `<img src="${ev.target.result}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">`;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        async function deleteLeader(id, name) {
            if (!confirm(`Delete leader "${name}"? This cannot be undone.`)) return;
            const fd = new FormData();
            fd.append('csrf_token', L_CSRF);
            fd.append('action', 'delete_leader');
            fd.append('id', id);
            try {
                const res = await fetch(L_AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) { showLeaderToast('Leader deleted'); loadLeaders(); }
                else showLeaderToast(data.message || 'Error', 'danger');
            } catch(err) { showLeaderToast('Network error', 'danger'); }
        }

        // Init
        loadLeaders();
        </script>
        <?php endif; ?>

    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size:3rem;"></i>
                <h6 class="fw-bold mt-3">Reset to Defaults?</h6>
                <p class="text-muted small">This will overwrite all current values for <strong><?= e($pageConfigs[$activePage]['label']) ?></strong> with factory defaults.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="reset_defaults">
                    <input type="hidden" name="page_key" value="<?= e($activePage) ?>">
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-warning px-3"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>