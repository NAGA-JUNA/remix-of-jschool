<?php
$pageTitle = 'HR Employees';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

// POST: Add/Edit employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $id = (int)($_POST['id'] ?? 0);
    $employee_id = trim($_POST['employee_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_joining = $_POST['date_of_joining'] ?? null;
    $salary = (float)($_POST['salary'] ?? 0);
    $probation_months = (int)($_POST['probation_months'] ?? 6);
    $reporting_to = trim($_POST['reporting_to'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($name && $employee_id) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE hr_employees SET employee_id=?, name=?, designation=?, department=?, email=?, phone=?, date_of_joining=?, salary=?, probation_months=?, reporting_to=?, status=? WHERE id=?");
            $stmt->execute([$employee_id, $name, $designation, $department, $email, $phone, $date_of_joining ?: null, $salary, $probation_months, $reporting_to, $status, $id]);
            setFlash('success', 'Employee updated successfully.');
        } else {
            $stmt = $db->prepare("INSERT INTO hr_employees (employee_id, name, designation, department, email, phone, date_of_joining, salary, probation_months, reporting_to, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $name, $designation, $department, $email, $phone, $date_of_joining ?: null, $salary, $probation_months, $reporting_to, $status]);
            setFlash('success', 'Employee added successfully.');
        }
    } else {
        setFlash('danger', 'Employee ID and Name are required.');
    }
    header('Location: /admin/hr/employees.php');
    exit;
}

// DELETE via GET
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
        $delId = (int)$_GET['delete'];
        if ($delId > 0) {
            // Check if employee has letters
            $chk = $db->prepare("SELECT COUNT(*) FROM hr_letters WHERE employee_id=?");
            $chk->execute([$delId]);
            if ((int)$chk->fetchColumn() > 0) {
                setFlash('danger', 'Cannot delete employee with existing letters.');
            } else {
                $db->prepare("DELETE FROM hr_employees WHERE id=?")->execute([$delId]);
                setFlash('success', 'Employee deleted.');
            }
        }
    }
    header('Location: /admin/hr/employees.php');
    exit;
}

// Fetch employees
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR employee_id LIKE ? OR designation LIKE ? OR department LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s];
}
if ($filterStatus) {
    $where .= " AND status=?";
    $params[] = $filterStatus;
}
$employees = $db->prepare("SELECT * FROM hr_employees WHERE $where ORDER BY name ASC");
$employees->execute($params);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-person-vcard me-2"></i>HR Employees</h4>
        <p class="text-muted small mb-0"><?= count($employees) ?> employee(s) found</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empModal" onclick="resetForm()">
        <i class="bi bi-plus-lg me-1"></i> Add Employee
    </button>
</div>

<!-- Search & Filter -->
<div class="card mb-4" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name, ID, designation..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="resigned" <?= $filterStatus === 'resigned' ? 'selected' : '' ?>>Resigned</option>
                    <option value="terminated" <?= $filterStatus === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/hr/employees.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Employees Table -->
<div class="card" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Salary</th>
                    <th>Joining</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
                <?php else: foreach ($employees as $emp): ?>
                <tr>
                    <td><code><?= e($emp['employee_id']) ?></code></td>
                    <td class="fw-semibold"><?= e($emp['name']) ?></td>
                    <td><?= e($emp['designation'] ?: '—') ?></td>
                    <td><?= e($emp['department'] ?: '—') ?></td>
                    <td>₹<?= number_format($emp['salary'], 0) ?></td>
                    <td><?= $emp['date_of_joining'] ? date('d M Y', strtotime($emp['date_of_joining'])) : '—' ?></td>
                    <td>
                        <?php
                        $statusColors = ['active' => 'success', 'resigned' => 'warning', 'terminated' => 'danger'];
                        $sc = $statusColors[$emp['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $sc ?>"><?= ucfirst($emp['status']) ?></span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editEmployee(<?= json_encode($emp) ?>)' title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="/admin/hr/employees.php?delete=<?= $emp['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete <?= e($emp['name']) ?>?')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="empModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border-color);">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="emp_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" id="f_employee_id" class="form-control" required maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f_name" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Designation</label>
                            <input type="text" name="designation" id="f_designation" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" id="f_department" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="f_email" class="form-control" maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="f_phone" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" name="date_of_joining" id="f_doj" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Monthly Salary (₹)</label>
                            <input type="number" name="salary" id="f_salary" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Probation (months)</label>
                            <input type="number" name="probation_months" id="f_probation" class="form-control" value="6" min="0" max="24">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporting To</label>
                            <input type="text" name="reporting_to" id="f_reporting" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="f_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="resigned">Resigned</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('empModalTitle').textContent = 'Add Employee';
    document.getElementById('emp_id').value = 0;
    ['f_employee_id','f_name','f_designation','f_department','f_email','f_phone','f_doj','f_reporting'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('f_salary').value = '';
    document.getElementById('f_probation').value = 6;
    document.getElementById('f_status').value = 'active';
}

function editEmployee(emp) {
    document.getElementById('empModalTitle').textContent = 'Edit Employee';
    document.getElementById('emp_id').value = emp.id;
    document.getElementById('f_employee_id').value = emp.employee_id;
    document.getElementById('f_name').value = emp.name;
    document.getElementById('f_designation').value = emp.designation || '';
    document.getElementById('f_department').value = emp.department || '';
    document.getElementById('f_email').value = emp.email || '';
    document.getElementById('f_phone').value = emp.phone || '';
    document.getElementById('f_doj').value = emp.date_of_joining || '';
    document.getElementById('f_salary').value = emp.salary || '';
    document.getElementById('f_probation').value = emp.probation_months || 6;
    document.getElementById('f_reporting').value = emp.reporting_to || '';
    document.getElementById('f_status').value = emp.status || 'active';
    new bootstrap.Modal(document.getElementById('empModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>