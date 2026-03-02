<?php
$pageTitle = 'Payslips';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

// Fetch employees for dropdown
$employees = $db->query("SELECT id, employee_id, name, designation, department, salary, email FROM hr_employees WHERE status='active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filters
$filterMonth = $_GET['month'] ?? '';
$filterEmp = (int)($_GET['employee_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';

$where = '1=1';
$params = [];
if ($filterMonth) { $where .= " AND p.pay_month=?"; $params[] = $filterMonth; }
if ($filterEmp) { $where .= " AND p.employee_id=?"; $params[] = $filterEmp; }
if ($filterStatus) { $where .= " AND p.status=?"; $params[] = $filterStatus; }

$stmt = $db->prepare("SELECT p.*, e.name AS emp_name, e.employee_id AS emp_code, e.designation, e.email 
    FROM hr_payslips p JOIN hr_employees e ON p.employee_id=e.id 
    WHERE $where ORDER BY p.created_at DESC");
$stmt->execute($params);
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-receipt me-2"></i>Payslips</h4>
        <p class="text-muted small mb-0">Generate and email monthly payslips for employees</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#payslipModal" onclick="resetPayslipForm()">
        <i class="bi bi-plus-lg me-1"></i> Generate Payslip
    </button>
</div>

<!-- Filters -->
<div class="card mb-4" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Month</label>
                <input type="month" name="month" class="form-control" value="<?= e($filterMonth) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Employee</label>
                <select name="employee_id" class="form-select">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $filterEmp == $emp['id'] ? 'selected' : '' ?>><?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="issued" <?= $filterStatus === 'issued' ? 'selected' : '' ?>>Issued</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/hr/payslips.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Payslips Table -->
<div class="card" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Month</th>
                    <th class="text-end">Earnings</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end">Net Salary</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payslips)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No payslips found. Click "Generate Payslip" to create one.</td></tr>
                <?php else: foreach ($payslips as $ps):
                    $totalEarnings = $ps['basic_salary'] + $ps['hra'] + $ps['da'] + $ps['other_allowances'];
                    $totalDeductions = $ps['pf_deduction'] + $ps['tax_deduction'] + $ps['other_deductions'];
                    $extraData = json_decode($ps['extra_data'] ?? '{}', true);
                    $emailSent = !empty($extraData['email_sent']);
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($ps['emp_name']) ?></div>
                        <small class="text-muted"><?= e($ps['emp_code']) ?> · <?= e($ps['designation'] ?? '') ?></small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark"><?= date('M Y', strtotime($ps['pay_month'] . '-01')) ?></span>
                    </td>
                    <td class="text-end text-success">₹<?= number_format($totalEarnings, 0) ?></td>
                    <td class="text-end text-danger">₹<?= number_format($totalDeductions, 0) ?></td>
                    <td class="text-end fw-bold">₹<?= number_format($ps['net_salary'], 0) ?></td>
                    <td>
                        <span class="badge bg-<?= $ps['status'] === 'issued' ? 'success' : 'warning' ?>"><?= ucfirst($ps['status']) ?></span>
                        <?php if ($emailSent): ?><span class="badge bg-info ms-1"><i class="bi bi-envelope-check"></i></span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="/admin/hr/payslip-preview.php?id=<?= $ps['id'] ?>" target="_blank" class="btn btn-outline-primary" title="Preview"><i class="bi bi-eye"></i></a>
                            <?php if ($ps['email']): ?>
                            <button class="btn btn-outline-info" onclick="emailPayslip(<?= $ps['id'] ?>, '<?= e($ps['email']) ?>')" title="Email"><i class="bi bi-envelope-at"></i></button>
                            <?php endif; ?>
                            <?php if ($ps['status'] === 'draft'): ?>
                            <button class="btn btn-outline-success" onclick="changeStatus(<?= $ps['id'] ?>, 'issued')" title="Mark Issued"><i class="bi bi-check-circle"></i></button>
                            <button class="btn btn-outline-danger" onclick="deletePayslip(<?= $ps['id'] ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generate Payslip Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border-color);">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Generate Payslip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select id="ps_employee" class="form-select" onchange="fillSalary()" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" data-salary="<?= $emp['salary'] ?>"><?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pay Month <span class="text-danger">*</span></label>
                        <input type="month" id="ps_month" class="form-control" value="<?= date('Y-m') ?>" required>
                    </div>

                    <div class="col-12"><hr><h6 class="fw-semibold text-success"><i class="bi bi-plus-circle me-1"></i>Earnings</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small">Basic Salary</label>
                        <input type="number" id="ps_basic" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">HRA</label>
                        <input type="number" id="ps_hra" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">DA</label>
                        <input type="number" id="ps_da" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Other Allowances</label>
                        <input type="number" id="ps_other_allow" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>

                    <div class="col-12"><h6 class="fw-semibold text-danger"><i class="bi bi-dash-circle me-1"></i>Deductions</h6></div>
                    <div class="col-md-4">
                        <label class="form-label small">PF Deduction</label>
                        <input type="number" id="ps_pf" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Tax Deduction</label>
                        <input type="number" id="ps_tax" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Other Deductions</label>
                        <input type="number" id="ps_other_ded" class="form-control ps-calc" step="0.01" min="0" value="0" oninput="calcNet()">
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded-3" style="background:var(--brand-primary-light);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-success fw-semibold">Earnings: ₹<span id="ps_total_earn">0</span></span>
                                    <span class="mx-2">−</span>
                                    <span class="text-danger fw-semibold">Deductions: ₹<span id="ps_total_ded">0</span></span>
                                </div>
                                <div>
                                    <strong style="font-size:1.2rem;">Net: ₹<span id="ps_net">0</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePayslip()"><i class="bi bi-check-lg me-1"></i>Save Payslip</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= csrfToken() ?>';

function fillSalary() {
    const sel = document.getElementById('ps_employee');
    const opt = sel.options[sel.selectedIndex];
    const salary = parseFloat(opt.dataset.salary || 0);
    document.getElementById('ps_basic').value = salary.toFixed(2);
    document.getElementById('ps_hra').value = '0';
    document.getElementById('ps_da').value = '0';
    document.getElementById('ps_other_allow').value = '0';
    document.getElementById('ps_pf').value = '0';
    document.getElementById('ps_tax').value = '0';
    document.getElementById('ps_other_ded').value = '0';
    calcNet();
}

function calcNet() {
    const basic = parseFloat(document.getElementById('ps_basic').value) || 0;
    const hra = parseFloat(document.getElementById('ps_hra').value) || 0;
    const da = parseFloat(document.getElementById('ps_da').value) || 0;
    const otherA = parseFloat(document.getElementById('ps_other_allow').value) || 0;
    const pf = parseFloat(document.getElementById('ps_pf').value) || 0;
    const tax = parseFloat(document.getElementById('ps_tax').value) || 0;
    const otherD = parseFloat(document.getElementById('ps_other_ded').value) || 0;

    const earn = basic + hra + da + otherA;
    const ded = pf + tax + otherD;
    const net = earn - ded;

    document.getElementById('ps_total_earn').textContent = earn.toLocaleString('en-IN');
    document.getElementById('ps_total_ded').textContent = ded.toLocaleString('en-IN');
    document.getElementById('ps_net').textContent = net.toLocaleString('en-IN');
}

function resetPayslipForm() {
    document.getElementById('ps_employee').value = '';
    document.getElementById('ps_month').value = '<?= date('Y-m') ?>';
    document.querySelectorAll('.ps-calc').forEach(el => el.value = '0');
    calcNet();
}

function savePayslip() {
    const empId = document.getElementById('ps_employee').value;
    const month = document.getElementById('ps_month').value;
    if (!empId || !month) { alert('Please select employee and month.'); return; }

    const data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('action', 'create_payslip');
    data.append('employee_id', empId);
    data.append('pay_month', month);
    data.append('basic_salary', document.getElementById('ps_basic').value);
    data.append('hra', document.getElementById('ps_hra').value);
    data.append('da', document.getElementById('ps_da').value);
    data.append('other_allowances', document.getElementById('ps_other_allow').value);
    data.append('pf_deduction', document.getElementById('ps_pf').value);
    data.append('tax_deduction', document.getElementById('ps_tax').value);
    data.append('other_deductions', document.getElementById('ps_other_ded').value);

    fetch('/admin/hr/payslip-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) { location.reload(); }
            else { alert(res.error || 'Failed to save.'); }
        })
        .catch(() => alert('Network error.'));
}

function deletePayslip(id) {
    if (!confirm('Delete this draft payslip?')) return;
    const data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('action', 'delete_payslip');
    data.append('payslip_id', id);
    fetch('/admin/hr/payslip-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => { if (res.success) location.reload(); else alert(res.error || 'Error.'); })
        .catch(() => alert('Network error.'));
}

function changeStatus(id, status) {
    if (!confirm('Mark this payslip as ' + status + '?')) return;
    const data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('action', 'change_status');
    data.append('payslip_id', id);
    data.append('status', status);
    fetch('/admin/hr/payslip-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => { if (res.success) location.reload(); else alert(res.error || 'Error.'); })
        .catch(() => alert('Network error.'));
}

function emailPayslip(id, email) {
    if (!confirm('Send payslip via email to ' + email + '?')) return;
    const data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('action', 'send_email');
    data.append('payslip_id', id);
    fetch('/admin/hr/payslip-actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) { alert('Payslip emailed successfully!'); location.reload(); }
            else { alert(res.error || 'Failed to email.'); }
        })
        .catch(() => alert('Network error.'));
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>