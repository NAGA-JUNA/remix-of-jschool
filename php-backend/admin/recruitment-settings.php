<?php
ob_start();

// Temporary debug mode -- set to true to see errors on screen
$debugMode = true;
if ($debugMode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$pageTitle = 'Recruitment Settings';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Fallback: define getSetting/setSetting if not provided by auth.php
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('setSetting')) {
    function setSetting(string $key, string $value): void {
        global $db;
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
}

// Handle POST actions
try {
    $csrfOk = verifyCsrf();
} catch (Exception $e) {
    $csrfOk = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrfOk) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'toggle_recruitment') {
            $enabled = !empty($_POST['recruitment_enabled']) ? '1' : '0';
            setSetting('recruitment_enabled', $enabled);
            auditLog('recruitment_toggled', 'settings', 0, "Recruitment " . ($enabled === '1' ? 'enabled' : 'disabled'));
            setFlash('success', 'Recruitment ' . ($enabled === '1' ? 'enabled' : 'disabled') . '.');
        }

        if ($action === 'save_templates') {
            $waTpl = trim($_POST['whatsapp_template'] ?? '');
            $emailTpl = trim($_POST['email_template'] ?? '');
            if ($waTpl) setSetting('whatsapp_recruitment_template', $waTpl);
            if ($emailTpl) setSetting('email_recruitment_template', $emailTpl);
            auditLog('recruitment_templates_updated', 'settings', 0);
            setFlash('success', 'Message templates updated.');
        }

        if ($action === 'add_job') {
            $title = trim($_POST['title'] ?? '');
            $dept = trim($_POST['department'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $reqs = trim($_POST['requirements'] ?? '');
            $type = $_POST['employment_type'] ?? 'full-time';
            $loc = trim($_POST['location'] ?? '');
            $salary = trim($_POST['salary_range'] ?? '');
            $order = (int)($_POST['sort_order'] ?? 0);

            if ($title) {
                $db->prepare("INSERT INTO job_openings (title, department, description, requirements, employment_type, location, salary_range, sort_order, created_by) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$title, $dept, $desc, $reqs, $type, $loc, $salary, $order, currentUserId()]);
                auditLog('job_opening_created', 'job_openings', (int)$db->lastInsertId());
                setFlash('success', 'Job opening added.');
            }
        }

        if ($action === 'edit_job') {
            $id = (int)($_POST['job_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if ($id && $title) {
                $db->prepare("UPDATE job_openings SET title=?, department=?, description=?, requirements=?, employment_type=?, location=?, salary_range=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?")
                    ->execute([
                        $title,
                        trim($_POST['department'] ?? ''),
                        trim($_POST['description'] ?? ''),
                        trim($_POST['requirements'] ?? ''),
                        $_POST['employment_type'] ?? 'full-time',
                        trim($_POST['location'] ?? ''),
                        trim($_POST['salary_range'] ?? ''),
                        (int)($_POST['sort_order'] ?? 0),
                        !empty($_POST['is_active']) ? 1 : 0,
                        $id
                    ]);
                auditLog('job_opening_updated', 'job_openings', $id);
                setFlash('success', 'Job opening updated.');
            }
        }

        if ($action === 'delete_job') {
            $id = (int)($_POST['job_id'] ?? 0);
            if ($id) {
                $db->prepare("DELETE FROM job_openings WHERE id=?")->execute([$id]);
                auditLog('job_opening_deleted', 'job_openings', $id);
                setFlash('success', 'Job opening deleted.');
            }
        }
    } catch (Exception $e) {
        error_log("Recruitment settings error: " . $e->getMessage());
        setFlash('danger', 'An error occurred: ' . $e->getMessage());
    }

    header('Location: recruitment-settings.php');
    exit;
}

// Fetch data
try {
    $recruitmentEnabled = getSetting('recruitment_enabled', '0') === '1';
    $waTpl = getSetting('whatsapp_recruitment_template', 'Hello {name}, regarding your application ({app_id}) for {position}...');
    $emailTpl = getSetting('email_recruitment_template', '');
} catch (Exception $e) {
    $recruitmentEnabled = false;
    $waTpl = '';
    $emailTpl = '';
    error_log("Settings fetch error: " . $e->getMessage());
}
try {
    $jobs = $db->query("SELECT * FROM job_openings ORDER BY sort_order ASC, created_at DESC")->fetchAll();
} catch (Exception $e) {
    $jobs = [];
    error_log("job_openings table may not exist: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .settings-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        padding: 1.75rem;
        margin-bottom: 1.5rem;
    }
    .settings-card:hover { box-shadow: 0 8px 32px rgba(26,86,219,0.1); }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    .job-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        margin-bottom: 0.5rem;
        transition: background 0.15s;
    }
    .job-row:hover { background: var(--bs-tertiary-bg); }
    .toggle-card {
        background: var(--bs-body-bg);
        border: 2px solid var(--bs-border-color);
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        margin-bottom: 1.5rem;
        transition: border-color 0.2s;
    }
    .toggle-card.active { border-color: #22c55e; }
    .toggle-card.inactive { border-color: #ef4444; }
    .placeholder-tag {
        display: inline-block;
        background: #e0f2fe;
        color: #0369a1;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-family: monospace;
        margin: 2px;
        cursor: pointer;
    }
    [data-bs-theme="dark"] .placeholder-tag { background: #1e3a5f; color: #7dd3fc; }
    [data-bs-theme="dark"] .settings-card { box-shadow: 0 4px 24px rgba(0,0,0,0.25); }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Recruitment Settings</h4>
        <p class="text-muted mb-0" style="font-size:.85rem">Manage job openings, templates, and recruitment status</p>
    </div>
    <a href="teacher-applications.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>View Applications</a>
</div>

<!-- Toggle -->
<div class="toggle-card <?= $recruitmentEnabled ? 'active' : 'inactive' ?>">
    <form method="POST" class="d-inline">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="toggle_recruitment">
        <div class="d-flex align-items-center justify-content-center gap-3">
            <i class="bi <?= $recruitmentEnabled ? 'bi-toggle-on text-success' : 'bi-toggle-off text-danger' ?>" style="font-size:2.5rem"></i>
            <div class="text-start">
                <h5 class="fw-bold mb-0"><?= $recruitmentEnabled ? 'Recruitment is ACTIVE' : 'Recruitment is DISABLED' ?></h5>
                <small class="text-muted"><?= $recruitmentEnabled ? 'Public join-us page is accepting applications' : 'Join-us page shows "Not hiring" message' ?></small>
            </div>
            <input type="hidden" name="recruitment_enabled" value="<?= $recruitmentEnabled ? '' : '1' ?>">
            <button type="submit" class="btn <?= $recruitmentEnabled ? 'btn-outline-danger' : 'btn-success' ?> btn-sm ms-3">
                <i class="bi <?= $recruitmentEnabled ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i>
                <?= $recruitmentEnabled ? 'Disable' : 'Enable' ?>
            </button>
        </div>
    </form>
</div>

<div class="row g-4">
    <!-- Job Openings -->
    <div class="col-lg-7">
        <div class="settings-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="section-title mb-0"><i class="bi bi-briefcase me-2"></i>Job Openings</h6>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addJobModal"><i class="bi bi-plus-lg me-1"></i>Add Job</button>
            </div>

            <?php if (empty($jobs)): ?>
                <p class="text-muted text-center py-3"><i class="bi bi-inbox me-1"></i>No job openings yet</p>
            <?php else: foreach ($jobs as $j): ?>
                <div class="job-row">
                    <div>
                        <strong><?= htmlspecialchars($j['title']) ?></strong>
                        <?php if ($j['department']): ?><small class="text-muted ms-2"><?= htmlspecialchars($j['department']) ?></small><?php endif; ?>
                        <div>
                            <span class="badge bg-<?= $j['is_active'] ? 'success' : 'secondary' ?>-subtle text-<?= $j['is_active'] ? 'success' : 'secondary' ?>" style="font-size:.7rem"><?= $j['is_active'] ? 'Active' : 'Inactive' ?></span>
                            <span class="badge bg-info-subtle text-info" style="font-size:.7rem"><?= ucfirst(str_replace('-',' ',$j['employment_type'])) ?></span>
                            <?php if ($j['location']): ?><small class="text-muted ms-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($j['location']) ?></small><?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-outline-primary btn-sm" onclick="editJob(<?= htmlspecialchars(json_encode($j)) ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this job opening?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_job">
                            <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Message Templates -->
    <div class="col-lg-5">
        <div class="settings-card">
            <h6 class="section-title"><i class="bi bi-chat-square-text me-2"></i>Message Templates</h6>
            <p class="text-muted mb-2" style="font-size:.8rem">Available placeholders:</p>
            <div class="mb-3">
                <span class="placeholder-tag" onclick="insertPlaceholder('{name}')">{name}</span>
                <span class="placeholder-tag" onclick="insertPlaceholder('{app_id}')">{app_id}</span>
                <span class="placeholder-tag" onclick="insertPlaceholder('{position}')">{position}</span>
                <span class="placeholder-tag" onclick="insertPlaceholder('{school_name}')">{school_name}</span>
            </div>

            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_templates">

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem"><i class="bi bi-whatsapp text-success me-1"></i>WhatsApp Template</label>
                    <textarea name="whatsapp_template" class="form-control form-control-sm" rows="4" id="waTemplate"><?= htmlspecialchars($waTpl) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem"><i class="bi bi-envelope me-1"></i>Email Template (HTML)</label>
                    <textarea name="email_template" class="form-control form-control-sm" rows="6" id="emailTemplate"><?= htmlspecialchars($emailTpl) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-save me-1"></i>Save Templates</button>
            </form>
        </div>
    </div>
</div>

<!-- Add Job Modal -->
<div class="modal fade" id="addJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_job">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Job Opening</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8"><label class="form-label fw-semibold">Title *</label><input type="text" name="title" class="form-control" required maxlength="255"></div>
                        <div class="col-4"><label class="form-label fw-semibold">Type</label>
                            <select name="employment_type" class="form-select">
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label fw-semibold">Department</label><input type="text" name="department" class="form-control" maxlength="100"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Location</label><input type="text" name="location" class="form-control" maxlength="150"></div>
                        <div class="col-8"><label class="form-label fw-semibold">Salary Range</label><input type="text" name="salary_range" class="form-control" maxlength="100" placeholder="e.g. ₹25,000 - ₹40,000"></div>
                        <div class="col-4"><label class="form-label fw-semibold">Sort Order</label><input type="number" name="sort_order" class="form-control" value="0" min="0"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                        <div class="col-12"><label class="form-label fw-semibold">Requirements</label><textarea name="requirements" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Job Modal -->
<div class="modal fade" id="editJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_job">
                <input type="hidden" name="job_id" id="editJobId">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1"></i>Edit Job Opening</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8"><label class="form-label fw-semibold">Title *</label><input type="text" name="title" id="editTitle" class="form-control" required maxlength="255"></div>
                        <div class="col-4"><label class="form-label fw-semibold">Type</label>
                            <select name="employment_type" id="editType" class="form-select">
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label fw-semibold">Department</label><input type="text" name="department" id="editDept" class="form-control" maxlength="100"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Location</label><input type="text" name="location" id="editLocation" class="form-control" maxlength="150"></div>
                        <div class="col-8"><label class="form-label fw-semibold">Salary Range</label><input type="text" name="salary_range" id="editSalary" class="form-control" maxlength="100"></div>
                        <div class="col-4"><label class="form-label fw-semibold">Sort Order</label><input type="number" name="sort_order" id="editOrder" class="form-control" min="0"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" id="editDesc" class="form-control" rows="3"></textarea></div>
                        <div class="col-12"><label class="form-label fw-semibold">Requirements</label><textarea name="requirements" id="editReqs" class="form-control" rows="3"></textarea></div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editActive" value="1">
                                <label class="form-check-label fw-semibold" for="editActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editJob(job) {
    document.getElementById('editJobId').value = job.id;
    document.getElementById('editTitle').value = job.title;
    document.getElementById('editDept').value = job.department || '';
    document.getElementById('editType').value = job.employment_type;
    document.getElementById('editLocation').value = job.location || '';
    document.getElementById('editSalary').value = job.salary_range || '';
    document.getElementById('editOrder').value = job.sort_order || 0;
    document.getElementById('editDesc').value = job.description || '';
    document.getElementById('editReqs').value = job.requirements || '';
    document.getElementById('editActive').checked = job.is_active == 1;
    new bootstrap.Modal(document.getElementById('editJobModal')).show();
}

function insertPlaceholder(tag) {
    const el = document.activeElement;
    if (el && (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT')) {
        const start = el.selectionStart;
        el.value = el.value.substring(0, start) + tag + el.value.substring(el.selectionEnd);
        el.selectionStart = el.selectionEnd = start + tag.length;
        el.focus();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>