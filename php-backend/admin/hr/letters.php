<?php
$letterType = $_GET['type'] ?? 'appointment';
if (!in_array($letterType, ['appointment','joining','resignation','hike'])) $letterType = 'appointment';

$typeTitles = [
    'appointment' => 'Appointment Letters',
    'joining' => 'Joining Confirmation Letters',
    'resignation' => 'Resignation Acceptance Letters',
    'hike' => 'Salary Hike Letters',
];
$pageTitle = $typeTitles[$letterType];

require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

// Seed templates if empty
require_once __DIR__ . '/seed-templates.php';
seedLetterTemplates($db);

// Fetch letters
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$where = "l.letter_type=?";
$params = [$letterType];
if ($search) {
    $where .= " AND (e.name LIKE ? OR l.reference_no LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
}
if ($filterStatus) {
    $where .= " AND l.status=?";
    $params[] = $filterStatus;
}

$letters = $db->prepare("SELECT l.*, e.name AS emp_name, e.employee_id AS emp_code, e.designation, e.department, e.email AS emp_email, e.salary AS current_salary FROM hr_letters l JOIN hr_employees e ON l.employee_id = e.id WHERE $where ORDER BY l.created_at DESC");
$letters->execute($params);
$letters = $letters->fetchAll(PDO::FETCH_ASSOC);

// Fetch active employees for dropdown
$employees = $db->query("SELECT id, employee_id, name, designation, department, salary, date_of_joining, probation_months, reporting_to FROM hr_employees WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <?php
            $typeIcons = ['appointment'=>'bi-envelope-paper','joining'=>'bi-person-check','resignation'=>'bi-person-dash','hike'=>'bi-graph-up-arrow'];
            ?>
            <i class="bi <?= $typeIcons[$letterType] ?> me-2"></i><?= e($pageTitle) ?>
        </h4>
        <p class="text-muted small mb-0"><?= count($letters) ?> letter(s) found</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#letterModal" onclick="resetLetterForm()">
        <i class="bi bi-plus-lg me-1"></i> Generate Letter
    </button>
</div>

<!-- Search & Filter -->
<div class="card mb-4" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?= e($letterType) ?>">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by employee or reference..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="issued" <?= $filterStatus === 'issued' ? 'selected' : '' ?>>Issued</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Filter</button></div>
            <div class="col-md-2"><a href="?type=<?= e($letterType) ?>" class="btn btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
</div>

<!-- Letters Table -->
<div class="card" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Employee</th>
                    <th>Issue Date</th>
                    <?php if ($letterType === 'hike'): ?><th>Old Salary</th><th>New Salary</th><th>Hike %</th><?php endif; ?>
                    <?php if ($letterType === 'resignation'): ?><th>Last Working Date</th><?php endif; ?>
                    <?php if ($letterType === 'joining' || $letterType === 'hike'): ?><th>Effective Date</th><?php endif; ?>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($letters)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No letters found. Click "Generate Letter" to create one.</td></tr>
                <?php else: foreach ($letters as $lt):
                    $extraData = json_decode($lt['extra_data'] ?? '{}', true);
                    $emailSent = !empty($extraData['email_sent']);
                    $hasEmail = !empty($lt['emp_email']) && filter_var($lt['emp_email'], FILTER_VALIDATE_EMAIL);
                ?>
                <tr>
                    <td><code><?= e($lt['reference_no']) ?></code></td>
                    <td>
                        <strong><?= e($lt['emp_name']) ?></strong>
                        <br><small class="text-muted"><?= e($lt['designation'] ?? '') ?></small>
                    </td>
                    <td><?= date('d M Y', strtotime($lt['issue_date'])) ?></td>
                    <?php if ($letterType === 'hike'): ?>
                    <td>₹<?= number_format($lt['salary_old'] ?? 0, 0) ?></td>
                    <td class="text-success fw-bold">₹<?= number_format($lt['salary_new'] ?? 0, 0) ?></td>
                    <td><span class="badge bg-info"><?= $lt['increment_pct'] ?>%</span></td>
                    <?php endif; ?>
                    <?php if ($letterType === 'resignation'): ?>
                    <td><?= $lt['last_working_date'] ? date('d M Y', strtotime($lt['last_working_date'])) : '—' ?></td>
                    <?php endif; ?>
                    <?php if ($letterType === 'joining' || $letterType === 'hike'): ?>
                    <td><?= $lt['effective_date'] ? date('d M Y', strtotime($lt['effective_date'])) : '—' ?></td>
                    <?php endif; ?>
                    <td>
                        <span class="badge bg-<?= $lt['status'] === 'issued' ? 'success' : 'warning' ?>"><?= ucfirst($lt['status']) ?></span>
                        <?php if ($emailSent): ?><span class="badge bg-purple" style="background:#8b5cf6;" title="Email sent to <?= e($lt['emp_email']) ?>"><i class="bi bi-envelope-check"></i></span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/hr/letter-preview.php?id=<?= $lt['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Preview & Print" target="_blank">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($hasEmail && $lt['status'] === 'issued'): ?>
                        <button class="btn btn-sm btn-outline-purple me-1" style="border-color:#8b5cf6;color:#8b5cf6;" onclick="sendEmailFromList(<?= $lt['id'] ?>, '<?= e($lt['emp_email']) ?>', this)" title="<?= $emailSent ? 'Resend' : 'Send' ?> Email to <?= e($lt['emp_email']) ?>">
                            <i class="bi bi-envelope-at"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($lt['status'] === 'draft'): ?>
                        <button class="btn btn-sm btn-outline-success me-1" onclick="issueConfirm(<?= $lt['id'] ?>, '<?= e($lt['reference_no']) ?>')" title="Mark as Issued">
                            <i class="bi bi-check-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteLetter(<?= $lt['id'] ?>, '<?= e($lt['reference_no']) ?>')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generate Letter Modal -->
<div class="modal fade" id="letterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border-color);">
            <form id="letterForm" onsubmit="return submitLetter(event)">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="create_letter">
                <input type="hidden" name="letter_type" value="<?= e($letterType) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Generate <?= e($typeTitles[$letterType]) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Common Fields -->
                        <div class="col-md-6">
                            <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" id="sel_employee" class="form-select" required onchange="fillEmployeeDetails()">
                                <option value="">-- Select --</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-salary="<?= $emp['salary'] ?>" data-doj="<?= $emp['date_of_joining'] ?>" data-designation="<?= e($emp['designation']) ?>" data-department="<?= e($emp['department']) ?>" data-probation="<?= $emp['probation_months'] ?>" data-reporting="<?= e($emp['reporting_to']) ?>">
                                    <?= e($emp['employee_id']) ?> — <?= e($emp['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft">Draft</option>
                                <option value="issued">Issue Now</option>
                            </select>
                        </div>

                        <!-- Employee Info (read-only) -->
                        <div class="col-12" id="empInfoBox" style="display:none;">
                            <div class="alert alert-info py-2 mb-0">
                                <small><strong>Designation:</strong> <span id="info_designation">—</span> | <strong>Dept:</strong> <span id="info_department">—</span> | <strong>Current Salary:</strong> ₹<span id="info_salary">—</span></small>
                            </div>
                        </div>

                        <?php if ($letterType === 'appointment'): ?>
                        <div class="col-md-4">
                            <label class="form-label">Offered Salary (₹/month)</label>
                            <input type="number" name="salary_new" id="f_salary_new" class="form-control" step="0.01">
                        </div>
                        <?php endif; ?>

                        <?php if ($letterType === 'joining'): ?>
                        <div class="col-md-4">
                            <label class="form-label">Confirmation Date</label>
                            <input type="date" name="effective_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Revised Salary (₹/month)</label>
                            <input type="number" name="salary_new" id="f_salary_new" class="form-control" step="0.01">
                        </div>
                        <?php endif; ?>

                        <?php if ($letterType === 'resignation'): ?>
                        <div class="col-md-4">
                            <label class="form-label">Last Working Date</label>
                            <input type="date" name="last_working_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notice Period</label>
                            <input type="text" name="notice_period" class="form-control" placeholder="e.g. 30 days" maxlength="50">
                        </div>
                        <?php endif; ?>

                        <?php if ($letterType === 'hike'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Old Salary (₹)</label>
                            <input type="number" name="salary_old" id="f_salary_old" class="form-control" step="0.01" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">New Salary (₹)</label>
                            <input type="number" name="salary_new" id="f_salary_new" class="form-control" step="0.01" required oninput="calcHike()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Increment %</label>
                            <input type="number" name="increment_pct" id="f_increment_pct" class="form-control" step="0.01" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Effective Date</label>
                            <input type="date" name="effective_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reason</label>
                            <select name="reason" class="form-select">
                                <option value="Annual Review">Annual Review</option>
                                <option value="Performance Based">Performance Based</option>
                                <option value="Promotion">Promotion</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit"><i class="bi bi-file-earmark-plus me-1"></i>Generate Letter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetLetterForm() {
    document.getElementById('letterForm').reset();
    document.getElementById('empInfoBox').style.display = 'none';
}

function fillEmployeeDetails() {
    const sel = document.getElementById('sel_employee');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) {
        document.getElementById('empInfoBox').style.display = 'none';
        return;
    }
    document.getElementById('empInfoBox').style.display = 'block';
    document.getElementById('info_designation').textContent = opt.dataset.designation || '—';
    document.getElementById('info_department').textContent = opt.dataset.department || '—';
    document.getElementById('info_salary').textContent = Number(opt.dataset.salary || 0).toLocaleString('en-IN');

    const salaryNew = document.getElementById('f_salary_new');
    const salaryOld = document.getElementById('f_salary_old');
    if (salaryNew) salaryNew.value = opt.dataset.salary || '';
    if (salaryOld) salaryOld.value = opt.dataset.salary || '';
}

<?php if ($letterType === 'hike'): ?>
function calcHike() {
    const oldSal = parseFloat(document.getElementById('f_salary_old').value) || 0;
    const newSal = parseFloat(document.getElementById('f_salary_new').value) || 0;
    const pct = oldSal > 0 ? (((newSal - oldSal) / oldSal) * 100).toFixed(2) : 0;
    document.getElementById('f_increment_pct').value = pct;
}
<?php endif; ?>

function submitLetter(e) {
    e.preventDefault();
    const form = document.getElementById('letterForm');
    const data = new FormData(form);
    document.getElementById('btnSubmit').disabled = true;

    fetch('/admin/hr/letter-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Failed to generate letter.');
                document.getElementById('btnSubmit').disabled = false;
            }
        })
        .catch(() => {
            alert('Network error.');
            document.getElementById('btnSubmit').disabled = false;
        });
}

function issueConfirm(id, ref) {
    if (!confirm('Mark letter ' + ref + ' as ISSUED? This cannot be undone.')) return;
    const data = new FormData();
    data.append('csrf_token', '<?= csrfToken() ?>');
    data.append('action', 'change_status');
    data.append('id', id);
    data.append('new_status', 'issued');
    fetch('/admin/hr/letter-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => { if (res.success) location.reload(); else alert(res.error || 'Failed.'); });
}

function deleteLetter(id, ref) {
    if (!confirm('Delete draft letter ' + ref + '?')) return;
    const data = new FormData();
    data.append('csrf_token', '<?= csrfToken() ?>');
    data.append('action', 'delete_letter');
    data.append('id', id);
    fetch('/admin/hr/letter-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => { if (res.success) location.reload(); else alert(res.error || 'Failed.'); });
}

function sendEmailFromList(letterId, email, btn) {
    if (!confirm('Send this letter via email to ' + email + '?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

    const data = new FormData();
    data.append('csrf_token', '<?= csrfToken() ?>');
    data.append('action', 'send_email');
    data.append('letter_id', letterId);

    fetch('/admin/hr/letter-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.innerHTML = '<i class="bi bi-envelope-check"></i>';
                btn.style.borderColor = '#22c55e';
                btn.style.color = '#22c55e';
                setTimeout(() => location.reload(), 1500);
            } else {
                alert(res.error || 'Failed to send email.');
                btn.innerHTML = '<i class="bi bi-envelope-at"></i>';
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error.');
            btn.innerHTML = '<i class="bi bi-envelope-at"></i>';
            btn.disabled = false;
        });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>