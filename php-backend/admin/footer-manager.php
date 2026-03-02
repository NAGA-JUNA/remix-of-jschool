<?php
$pageTitle = 'Footer Manager';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

$schoolName = getSetting('school_name', 'JNV School');

// Default data
$defaultQuickLinks = [
    ['label' => 'About Us', 'url' => '/public/about.php'],
    ['label' => 'Our Teachers', 'url' => '/public/teachers.php'],
    ['label' => 'Admissions', 'url' => '/public/admission-form.php'],
    ['label' => 'Gallery', 'url' => '/public/gallery.php'],
    ['label' => 'Events', 'url' => '/public/events.php'],
    ['label' => 'Admin Login', 'url' => '/login.php'],
];
$defaultPrograms = [
    ['label' => 'Pre-Primary (LKG & UKG)'],
    ['label' => 'Primary School (1-5)'],
    ['label' => 'Upper Primary (6-8)'],
    ['label' => 'Co-Curricular Activities'],
    ['label' => 'Sports Programs'],
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    if (!verifyCsrf()) { setFlash('error', 'Invalid CSRF token.'); header('Location: /admin/footer-manager.php'); exit; }
    
    if ($_POST['form_action'] === 'save_footer') {
        // Footer description
        $desc = trim(substr($_POST['footer_description'] ?? '', 0, 2000));
        $upsert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $upsert->execute(['footer_description', $desc]);
        
        // Contact info
        $contactFields = ['footer_contact_address', 'footer_contact_phone', 'footer_contact_email', 'footer_contact_hours'];
        foreach ($contactFields as $cf) {
            $val = trim(substr($_POST[$cf] ?? '', 0, 500));
            $upsert->execute([$cf, $val]);
        }
        
        // Social links
        $socialFields = ['footer_social_facebook', 'footer_social_twitter', 'footer_social_instagram', 'footer_social_youtube', 'footer_social_linkedin'];
        foreach ($socialFields as $sf) {
            $val = trim($_POST[$sf] ?? '');
            if ($val && !filter_var($val, FILTER_VALIDATE_URL)) $val = '';
            $upsert->execute([$sf, substr($val, 0, 500)]);
        }
        
        // Quick Links (JSON)
        $quickLinks = [];
        $qlLabels = $_POST['ql_label'] ?? [];
        $qlUrls = $_POST['ql_url'] ?? [];
        for ($i = 0; $i < count($qlLabels); $i++) {
            $label = trim($qlLabels[$i] ?? '');
            $url = trim($qlUrls[$i] ?? '');
            if ($label && $url) {
                $quickLinks[] = ['label' => substr($label, 0, 100), 'url' => substr($url, 0, 255)];
            }
        }
        $upsert->execute(['footer_quick_links', json_encode($quickLinks)]);
        
        // Programs (JSON)
        $programs = [];
        $pgLabels = $_POST['pg_label'] ?? [];
        foreach ($pgLabels as $pl) {
            $pl = trim($pl);
            if ($pl) $programs[] = ['label' => substr($pl, 0, 200)];
        }
        $upsert->execute(['footer_programs', json_encode($programs)]);
        
        auditLog('footer_update', 'footer', null, 'Updated footer content');
        setFlash('success', '✅ Footer updated successfully!');
        header('Location: /admin/footer-manager.php');
        exit;
    }
    
    if ($_POST['form_action'] === 'reset_footer') {
        $upsert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $upsert->execute(['footer_description', 'A professional and modern school with years of experience in nurturing children with senior teachers and a clean environment.']);
        $upsert->execute(['footer_quick_links', json_encode($defaultQuickLinks)]);
        $upsert->execute(['footer_programs', json_encode($defaultPrograms)]);
        $upsert->execute(['footer_contact_address', '']);
        $upsert->execute(['footer_contact_phone', '']);
        $upsert->execute(['footer_contact_email', '']);
        $upsert->execute(['footer_contact_hours', 'Mon - Sat: 8:00 AM - 5:00 PM']);
        $upsert->execute(['footer_social_facebook', '']);
        $upsert->execute(['footer_social_twitter', '']);
        $upsert->execute(['footer_social_instagram', '']);
        $upsert->execute(['footer_social_youtube', '']);
        $upsert->execute(['footer_social_linkedin', '']);
        
        auditLog('footer_reset', 'footer', null, 'Reset footer to defaults');
        setFlash('success', '🔄 Footer reset to defaults.');
        header('Location: /admin/footer-manager.php');
        exit;
    }
}

// Load current values
$footerDesc = getSetting('footer_description', 'A professional and modern school with years of experience in nurturing children with senior teachers and a clean environment.');
$quickLinks = json_decode(getSetting('footer_quick_links', ''), true) ?: $defaultQuickLinks;
$programs = json_decode(getSetting('footer_programs', ''), true) ?: $defaultPrograms;
$contactAddress = getSetting('footer_contact_address', getSetting('school_address', ''));
$contactPhone = getSetting('footer_contact_phone', getSetting('school_phone', ''));
$contactEmail = getSetting('footer_contact_email', getSetting('school_email', ''));
$contactHours = getSetting('footer_contact_hours', 'Mon - Sat: 8:00 AM - 5:00 PM');

// Social links - fallback to main social settings
$fSocialFb = getSetting('footer_social_facebook', getSetting('social_facebook', ''));
$fSocialTw = getSetting('footer_social_twitter', getSetting('social_twitter', ''));
$fSocialIg = getSetting('footer_social_instagram', getSetting('social_instagram', ''));
$fSocialYt = getSetting('footer_social_youtube', getSetting('social_youtube', ''));
$fSocialLi = getSetting('footer_social_linkedin', getSetting('social_linkedin', ''));

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.footer-col-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
.footer-col-card h6 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; }
.link-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.5rem; border-radius: 6px; margin-bottom: 0.3rem; background: #f8fafc; }
.link-item:hover { background: #f1f5f9; }
.link-item .drag-handle { cursor: grab; color: #94a3b8; }
.link-item input { border: none; background: transparent; font-size: 0.85rem; flex: 1; }
.link-item input:focus { outline: none; background: #fff; border-radius: 4px; padding: 0 0.3rem; }
.btn-add-item { border: 1.5px dashed #cbd5e1; background: transparent; color: #64748b; width: 100%; padding: 0.5rem; border-radius: 8px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
.btn-add-item:hover { border-color: var(--primary, #1e40af); color: var(--primary, #1e40af); background: #f0f9ff; }
.preview-footer { background: #1a1a2e; color: #fff; border-radius: 12px; padding: 1.5rem; font-size: 0.8rem; }
.preview-footer h6 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; margin-bottom: 0.5rem; color: #fff; }
.preview-footer .link { color: rgba(255,255,255,0.6); font-size: 0.78rem; display: block; margin-bottom: 0.3rem; }
</style>

<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="save_footer">
    
    <div class="row g-3">
        <!-- Column 1: Logo & Description -->
        <div class="col-lg-6">
            <div class="footer-col-card">
                <h6><i class="bi bi-card-text me-2"></i>Column 1: Description & Social</h6>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Footer Description</label>
                    <textarea class="form-control form-control-sm" name="footer_description" rows="3" maxlength="2000"><?= e($footerDesc) ?></textarea>
                </div>
                <label class="form-label small fw-semibold">Social Links</label>
                <div class="row g-2">
                    <div class="col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-facebook"></i></span>
                            <input type="url" class="form-control" name="footer_social_facebook" placeholder="Facebook URL" value="<?= e($fSocialFb) ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-twitter-x"></i></span>
                            <input type="url" class="form-control" name="footer_social_twitter" placeholder="Twitter/X URL" value="<?= e($fSocialTw) ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-instagram"></i></span>
                            <input type="url" class="form-control" name="footer_social_instagram" placeholder="Instagram URL" value="<?= e($fSocialIg) ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-youtube"></i></span>
                            <input type="url" class="form-control" name="footer_social_youtube" placeholder="YouTube URL" value="<?= e($fSocialYt) ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                            <input type="url" class="form-control" name="footer_social_linkedin" placeholder="LinkedIn URL" value="<?= e($fSocialLi) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Column 4: Contact Info -->
        <div class="col-lg-6">
            <div class="footer-col-card">
                <h6><i class="bi bi-geo-alt me-2"></i>Column 4: Contact Info</h6>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Address</label>
                    <textarea class="form-control form-control-sm" name="footer_contact_address" rows="2" maxlength="500"><?= e($contactAddress) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Phone</label>
                    <input type="text" class="form-control form-control-sm" name="footer_contact_phone" value="<?= e($contactPhone) ?>" maxlength="30">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" class="form-control form-control-sm" name="footer_contact_email" value="<?= e($contactEmail) ?>" maxlength="100">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Operating Hours</label>
                    <input type="text" class="form-control form-control-sm" name="footer_contact_hours" value="<?= e($contactHours) ?>" maxlength="100">
                </div>
            </div>
        </div>
        
        <!-- Column 2: Quick Links -->
        <div class="col-lg-6">
            <div class="footer-col-card">
                <h6><i class="bi bi-link-45deg me-2"></i>Column 2: Quick Links</h6>
                <div id="quickLinksContainer">
                    <?php foreach ($quickLinks as $i => $link): ?>
                    <div class="link-item" data-type="quicklink">
                        <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                        <input type="text" name="ql_label[]" value="<?= e($link['label']) ?>" placeholder="Label" style="max-width:40%;">
                        <input type="text" name="ql_url[]" value="<?= e($link['url']) ?>" placeholder="/page-url">
                        <button type="button" class="btn btn-sm text-danger p-0 border-0 bg-transparent" onclick="this.closest('.link-item').remove()"><i class="bi bi-trash"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-item mt-2" onclick="addQuickLink()"><i class="bi bi-plus me-1"></i>Add Link</button>
            </div>
        </div>
        
        <!-- Column 3: Programs -->
        <div class="col-lg-6">
            <div class="footer-col-card">
                <h6><i class="bi bi-bookmark me-2"></i>Column 3: Programs</h6>
                <div id="programsContainer">
                    <?php foreach ($programs as $i => $pg): ?>
                    <div class="link-item" data-type="program">
                        <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                        <input type="text" name="pg_label[]" value="<?= e($pg['label']) ?>" placeholder="Program name">
                        <button type="button" class="btn btn-sm text-danger p-0 border-0 bg-transparent" onclick="this.closest('.link-item').remove()"><i class="bi bi-trash"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-item mt-2" onclick="addProgram()"><i class="bi bi-plus me-1"></i>Add Program</button>
            </div>
        </div>
    </div>
    
    <!-- Live Preview -->
    <div class="card border-0 rounded-3 mt-3">
        <div class="card-header bg-white border-0">
            <h6 class="fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Live Preview</h6>
        </div>
        <div class="card-body p-3">
            <div class="preview-footer" id="footerPreview">
                <div class="row g-3">
                    <div class="col-3">
                        <h6><?= e($schoolName) ?></h6>
                        <span class="link" id="prevDesc"><?= e(substr($footerDesc, 0, 100)) ?>...</span>
                    </div>
                    <div class="col-3">
                        <h6>Quick Links</h6>
                        <div id="prevLinks">
                            <?php foreach ($quickLinks as $l): ?><span class="link"><?= e($l['label']) ?></span><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <h6>Programs</h6>
                        <div id="prevPrograms">
                            <?php foreach ($programs as $p): ?><span class="link"><?= e($p['label']) ?></span><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <h6>Contact Info</h6>
                        <span class="link" id="prevAddr"><?= e($contactAddress ?: 'Address') ?></span>
                        <span class="link" id="prevPhone"><?= e($contactPhone ?: 'Phone') ?></span>
                        <span class="link" id="prevEmail"><?= e($contactEmail ?: 'Email') ?></span>
                        <span class="link" id="prevHours"><?= e($contactHours) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-dark px-4"><i class="bi bi-check-lg me-1"></i>Save Footer Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetFooterModal"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default</button>
    </div>
</form>

<!-- Reset Modal -->
<div class="modal fade" id="resetFooterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size:3rem;"></i>
                <h6 class="fw-bold mt-3">Reset Footer?</h6>
                <p class="text-muted small">All footer content will be restored to factory defaults.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="reset_footer">
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-warning px-3"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function addQuickLink() {
    const c = document.getElementById('quickLinksContainer');
    const div = document.createElement('div');
    div.className = 'link-item';
    div.dataset.type = 'quicklink';
    div.innerHTML = '<span class="drag-handle"><i class="bi bi-grip-vertical"></i></span><input type="text" name="ql_label[]" placeholder="Label" style="max-width:40%;"><input type="text" name="ql_url[]" placeholder="/page-url"><button type="button" class="btn btn-sm text-danger p-0 border-0 bg-transparent" onclick="this.closest(\'.link-item\').remove()"><i class="bi bi-trash"></i></button>';
    c.appendChild(div);
}

function addProgram() {
    const c = document.getElementById('programsContainer');
    const div = document.createElement('div');
    div.className = 'link-item';
    div.dataset.type = 'program';
    div.innerHTML = '<span class="drag-handle"><i class="bi bi-grip-vertical"></i></span><input type="text" name="pg_label[]" placeholder="Program name"><button type="button" class="btn btn-sm text-danger p-0 border-0 bg-transparent" onclick="this.closest(\'.link-item\').remove()"><i class="bi bi-trash"></i></button>';
    c.appendChild(div);
}

// Simple drag-to-reorder
['quickLinksContainer', 'programsContainer'].forEach(containerId => {
    const container = document.getElementById(containerId);
    let dragEl = null;
    
    container.addEventListener('dragstart', e => {
        dragEl = e.target.closest('.link-item');
        if (dragEl) { dragEl.style.opacity = '0.4'; e.dataTransfer.effectAllowed = 'move'; }
    });
    container.addEventListener('dragend', e => {
        if (dragEl) dragEl.style.opacity = '1';
        dragEl = null;
    });
    container.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
    container.addEventListener('drop', e => {
        e.preventDefault();
        const target = e.target.closest('.link-item');
        if (dragEl && target && dragEl !== target) {
            const items = [...container.querySelectorAll('.link-item')];
            const dragIdx = items.indexOf(dragEl);
            const targetIdx = items.indexOf(target);
            if (dragIdx < targetIdx) target.after(dragEl);
            else target.before(dragEl);
        }
    });
    
    // Make items draggable
    container.querySelectorAll('.link-item').forEach(item => item.draggable = true);
    new MutationObserver(() => {
        container.querySelectorAll('.link-item').forEach(item => item.draggable = true);
    }).observe(container, { childList: true });
});

// Live preview update
document.querySelectorAll('input, textarea').forEach(el => {
    el.addEventListener('input', updatePreview);
});

function updatePreview() {
    // Description
    const desc = document.querySelector('[name="footer_description"]');
    if (desc) document.getElementById('prevDesc').textContent = desc.value.substring(0, 100) + '...';
    
    // Contact
    const addr = document.querySelector('[name="footer_contact_address"]');
    if (addr) document.getElementById('prevAddr').textContent = addr.value || 'Address';
    const phone = document.querySelector('[name="footer_contact_phone"]');
    if (phone) document.getElementById('prevPhone').textContent = phone.value || 'Phone';
    const email = document.querySelector('[name="footer_contact_email"]');
    if (email) document.getElementById('prevEmail').textContent = email.value || 'Email';
    const hours = document.querySelector('[name="footer_contact_hours"]');
    if (hours) document.getElementById('prevHours').textContent = hours.value || 'Hours';
    
    // Quick Links
    const linkLabels = document.querySelectorAll('[name="ql_label[]"]');
    let linksHtml = '';
    linkLabels.forEach(l => { if (l.value) linksHtml += '<span class="link">' + l.value + '</span>'; });
    document.getElementById('prevLinks').innerHTML = linksHtml;
    
    // Programs
    const pgLabels = document.querySelectorAll('[name="pg_label[]"]');
    let pgHtml = '';
    pgLabels.forEach(p => { if (p.value) pgHtml += '<span class="link">' + p.value + '</span>'; });
    document.getElementById('prevPrograms').innerHTML = pgHtml;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>