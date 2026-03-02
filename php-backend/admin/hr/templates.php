<?php
$pageTitle = 'Letter Templates';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

// Seed defaults if empty
require_once __DIR__ . '/seed-templates.php';
seedLetterTemplates($db);

// Handle HR asset uploads (logo / digital signature)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_hr_assets']) && verifyCsrf()) {
    $uploadDir = __DIR__ . '/../../uploads/hr/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // HR Logo
    if (!empty($_FILES['hr_logo']['name']) && $_FILES['hr_logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['hr_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
            $filename = 'hr_logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['hr_logo']['tmp_name'], $uploadDir . $filename)) {
                $path = '/uploads/hr/' . $filename;
                $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hr_logo', ?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$path, $path]);
                setFlash('success', 'HR Logo uploaded successfully.');
            }
        } else {
            setFlash('danger', 'Invalid logo file type. Use JPG, PNG, GIF, WebP or SVG.');
        }
    }

    // Digital Signature
    if (!empty($_FILES['hr_digital_signature']['name']) && $_FILES['hr_digital_signature']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['hr_digital_signature']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
            $filename = 'signature_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['hr_digital_signature']['tmp_name'], $uploadDir . $filename)) {
                $path = '/uploads/hr/' . $filename;
                $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hr_digital_signature', ?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$path, $path]);
                setFlash('success', 'Digital Signature uploaded successfully.');
            }
        } else {
            setFlash('danger', 'Invalid signature file type. Use JPG, PNG, GIF, WebP or SVG.');
        }
    }

    // Remove logo
    if (!empty($_POST['remove_hr_logo'])) {
        $db->prepare("UPDATE settings SET setting_value='' WHERE setting_key='hr_logo'")->execute();
        setFlash('success', 'HR Logo removed. School logo will be used as fallback.');
    }

    // Remove signature
    if (!empty($_POST['remove_hr_signature'])) {
        $db->prepare("UPDATE settings SET setting_value='' WHERE setting_key='hr_digital_signature'")->execute();
        setFlash('success', 'Digital Signature removed.');
    }

    header('Location: /admin/hr/templates.php');
    exit;
}

// POST: Reset template to default
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_template_id']) && verifyCsrf()) {
    $resetId = (int)$_POST['reset_template_id'];
    $resetType = $_POST['reset_type'] ?? '';
    $defaults = getDefaultTemplates();

    if ($resetId > 0 && isset($defaults[$resetType])) {
        $db->prepare("UPDATE letter_templates SET template_content=?, status='active' WHERE id=?")->execute([$defaults[$resetType], $resetId]);
        setFlash('success', ucfirst($resetType) . ' template has been reset to default.');
    } else {
        setFlash('danger', 'Invalid template for reset.');
    }
    header('Location: /admin/hr/templates.php');
    exit;
}

// POST: Update template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template_id']) && verifyCsrf()) {
    $tplId = (int)($_POST['template_id'] ?? 0);
    $content = $_POST['template_content'] ?? '';
    $status = $_POST['tpl_status'] ?? 'active';

    if ($tplId > 0 && $content) {
        $db->prepare("UPDATE letter_templates SET template_content=?, status=? WHERE id=?")->execute([$content, $status, $tplId]);
        setFlash('success', 'Template updated successfully.');
    } else {
        setFlash('danger', 'Template content cannot be empty.');
    }
    header('Location: /admin/hr/templates.php');
    exit;
}

// Fetch all templates
$templates = $db->query("SELECT * FROM letter_templates ORDER BY FIELD(letter_type,'appointment','joining','resignation','hike')")->fetchAll(PDO::FETCH_ASSOC);

$typeLabels = [
    'appointment' => ['Appointment Letter', 'bi-envelope-paper', 'primary'],
    'joining' => ['Joining Confirmation', 'bi-person-check', 'success'],
    'resignation' => ['Resignation Acceptance', 'bi-person-dash', 'warning'],
    'hike' => ['Salary Hike / Increment', 'bi-graph-up-arrow', 'info'],
];

$placeholders = [
    '{{school_name}}', '{{hr_logo}}', '{{school_address}}',
    '{{employee_name}}', '{{employee_id}}', '{{designation}}', '{{department}}',
    '{{date_of_joining}}', '{{salary_old}}', '{{salary_new}}', '{{increment_pct}}',
    '{{effective_date}}', '{{issue_date}}', '{{reference_no}}',
    '{{last_working_date}}', '{{notice_period}}', '{{reporting_to}}', '{{reason}}', '{{probation_months}}',
    '{{digital_signature}}'
];

// Get current HR assets
$hrLogo = getSetting('hr_logo', '');
$hrSignature = getSetting('hr_digital_signature', '');
$schoolLogo = getSetting('school_logo', '');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-richtext me-2"></i>Letter Templates</h4>
        <p class="text-muted small mb-0">Customize HTML templates for all letter types</p>
    </div>
</div>

<!-- HR Branding Settings -->
<div class="card mb-4" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="card-header py-3" style="background:transparent;border-bottom:1px solid var(--border-color);">
        <h6 class="fw-semibold mb-0"><i class="bi bi-image me-2"></i>HR Branding — Logo & Digital Signature</h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="upload_hr_assets" value="1">
            <div class="row g-4">
                <!-- HR Logo -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">HR Logo</label>
                    <p class="text-muted small mb-2">Used in letter headers. If not uploaded, school logo is used as fallback.</p>
                    <?php if ($hrLogo): ?>
                    <div class="mb-2 p-3 text-center" style="background:#f8f9fa;border-radius:8px;border:1px dashed #dee2e6;">
                        <img src="<?= htmlspecialchars($hrLogo) ?>" style="max-height:80px;max-width:200px;" alt="HR Logo">
                        <div class="mt-2">
                            <button type="submit" name="remove_hr_logo" value="1" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Remove</button>
                        </div>
                    </div>
                    <?php elseif ($schoolLogo): ?>
                    <div class="mb-2 p-3 text-center" style="background:#f0f4ff;border-radius:8px;border:1px dashed #93c5fd;">
                        <img src="<?= htmlspecialchars($schoolLogo) ?>" style="max-height:60px;max-width:200px;" alt="School Logo">
                        <div class="mt-1"><small class="text-muted">Using school logo (fallback)</small></div>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="hr_logo" class="form-control" accept="image/*">
                </div>

                <!-- Digital Signature -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Digital Signature</label>
                    <p class="text-muted small mb-2">Appears in the signature area of all letters. Use a transparent PNG for best results.</p>
                    <?php if ($hrSignature): ?>
                    <div class="mb-2 p-3 text-center" style="background:#f8f9fa;border-radius:8px;border:1px dashed #dee2e6;">
                        <img src="<?= htmlspecialchars($hrSignature) ?>" style="max-height:60px;max-width:200px;" alt="Signature">
                        <div class="mt-2">
                            <button type="submit" name="remove_hr_signature" value="1" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Remove</button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-2 p-3 text-center" style="background:#fff3cd;border-radius:8px;border:1px dashed #ffc107;">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>No signature uploaded. Letters will show a blank signature area.</small>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="hr_digital_signature" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload & Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Placeholders Reference -->
<div class="card mb-4" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="card-body">
        <h6 class="fw-semibold mb-2"><i class="bi bi-code-square me-1"></i>Available Placeholders</h6>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($placeholders as $ph): ?>
            <code class="px-2 py-1" style="background:var(--brand-primary-light);border-radius:6px;font-size:0.78rem;cursor:pointer;" onclick="navigator.clipboard.writeText('<?= $ph ?>')" title="Click to copy"><?= $ph ?></code>
            <?php endforeach; ?>
        </div>
        <small class="text-muted mt-2 d-block">Click any placeholder to copy. These will be replaced with actual data when generating letters.</small>
    </div>
</div>

<!-- Templates Accordion -->
<div class="accordion" id="tplAccordion">
    <?php foreach ($templates as $i => $tpl):
        $meta = $typeLabels[$tpl['letter_type']] ?? ['Unknown', 'bi-file', 'secondary'];
    ?>
    <div class="accordion-item mb-3" style="border:1px solid var(--border-color);border-radius:12px;overflow:hidden;background:var(--bg-card);">
        <h2 class="accordion-header">
            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#tpl_<?= $tpl['id'] ?>" style="background:var(--bg-card);color:var(--text-primary);">
                <i class="bi <?= $meta[1] ?> me-2 text-<?= $meta[2] ?>"></i>
                <strong><?= $meta[0] ?></strong>
                <span class="badge bg-<?= $tpl['status'] === 'active' ? 'success' : 'secondary' ?> ms-2"><?= ucfirst($tpl['status']) ?></span>
            </button>
        </h2>
        <div id="tpl_<?= $tpl['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#tplAccordion">
            <div class="accordion-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Template Status</label>
                        <select name="tpl_status" class="form-select" style="max-width:200px;">
                            <option value="active" <?= $tpl['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $tpl['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">HTML Content</label>
                        <textarea name="template_content" class="form-control font-monospace" rows="18" style="font-size:0.82rem;"><?= htmlspecialchars($tpl['template_content']) ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Template</button>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <small class="text-muted">Last updated: <?= date('d M Y H:i', strtotime($tpl['updated_at'])) ?></small>
                        </div>
                    </div>
                </form>
                <!-- Reset to Default (separate form) -->
                <form method="POST" class="mt-2" onsubmit="return confirm('Are you sure you want to reset the <?= $meta[0] ?> template to its default content? Your current customizations will be lost.');">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="reset_template_id" value="<?= $tpl['id'] ?>">
                    <input type="hidden" name="reset_type" value="<?= $tpl['letter_type'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>