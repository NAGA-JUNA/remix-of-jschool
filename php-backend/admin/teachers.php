<?php
$pageTitle = 'Manage Teachers';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Handle form submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_name'])) {
    $id = (int)($_POST['teacher_id'] ?? 0);
    $data = [
        'employee_id' => trim($_POST['employee_id'] ?? ''),
        'name' => trim($_POST['teacher_name'] ?? ''),
        'designation' => trim($_POST['designation'] ?? 'Teacher'),
        'gender' => $_POST['gender'] ?? null,
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'qualification' => trim($_POST['qualification'] ?? ''),
        'experience_years' => (int)($_POST['experience_years'] ?? 0),
        'dob' => $_POST['dob'] ?: null,
        'joining_date' => $_POST['joining_date'] ?: null,
        'status' => $_POST['status'] ?? 'active',
        'address' => trim($_POST['address'] ?? ''),
        'bio' => trim($_POST['bio'] ?? ''),
        'display_order' => (int)($_POST['display_order'] ?? 0),
        'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    ];

    // Handle photo upload
    $photoPath = $_POST['existing_photo'] ?? '';
    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/photos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
            $filename = 'teacher_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                if ($photoPath && $id > 0) {
                    $oldPath = __DIR__ . '/../' . ltrim($photoPath, '/');
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $photoPath = '/uploads/photos/' . $filename;
            }
        }
    }

    if (!$data['employee_id'] || !$data['name']) {
        setFlash('error', 'Employee ID and Name are required.');
    } else {
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE teachers SET employee_id=?, name=?, designation=?, gender=?, email=?, phone=?, subject=?, qualification=?, experience_years=?, dob=?, joining_date=?, status=?, address=?, bio=?, display_order=?, is_visible=?, is_featured=?, photo=? WHERE id=?");
                $stmt->execute([
                    $data['employee_id'], $data['name'], $data['designation'], $data['gender'],
                    $data['email'], $data['phone'], $data['subject'], $data['qualification'],
                    $data['experience_years'], $data['dob'], $data['joining_date'], $data['status'],
                    $data['address'], $data['bio'], $data['display_order'], $data['is_visible'],
                    $data['is_featured'], $photoPath, $id
                ]);
                auditLog('update_teacher', 'teacher', $id);
                setFlash('success', 'Teacher updated successfully!');
            } else {
                $stmt = $db->prepare("INSERT INTO teachers (employee_id, name, designation, gender, email, phone, subject, qualification, experience_years, dob, joining_date, status, address, bio, display_order, is_visible, is_featured, photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $data['employee_id'], $data['name'], $data['designation'], $data['gender'],
                    $data['email'], $data['phone'], $data['subject'], $data['qualification'],
                    $data['experience_years'], $data['dob'], $data['joining_date'], $data['status'],
                    $data['address'], $data['bio'], $data['display_order'], $data['is_visible'],
                    $data['is_featured'], $photoPath
                ]);
                $newId = (int)$db->lastInsertId();
                auditLog('create_teacher', 'teacher', $newId);
                setFlash('success', 'Teacher added successfully!');
                // Create login account if email provided
                if ($data['email']) {
                    try {
                        $db->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'teacher')")
                           ->execute([$data['name'], $data['email'], password_hash('Teacher@123', PASSWORD_DEFAULT)]);
                    } catch (Exception $e) {}
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) setFlash('error', 'Employee ID already exists.');
            else setFlash('error', $e->getMessage());
        }
    }
    header('Location: /admin/teachers.php');
    exit;
}

// Fetch teachers with search/filter
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active';
$page = max(1, (int)($_GET['page'] ?? 1));
$where = []; $params = [];
if ($search) { $where[] = "(name LIKE ? OR employee_id LIKE ? OR subject LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($statusFilter) { $where[] = "status=?"; $params[] = $statusFilter; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = $db->prepare("SELECT COUNT(*) FROM teachers $w"); $total->execute($params); $total = $total->fetchColumn();
$p = paginate($total, 24, $page);
$stmt = $db->prepare("SELECT * FROM teachers $w ORDER BY display_order ASC, name ASC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params);
$teachers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manage Teachers</h4>
        <p class="text-muted mb-0 small">Manage teachers displayed on the Our Teachers page.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary"><?= $total ?> Teachers</span>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importTeacherModal">
            <i class="bi bi-upload me-1"></i>Import
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="bi bi-plus-lg me-1"></i>Add Teacher
        </button>
    </div>
</div>

<!-- Search/Filter -->
<div class="card border-0 rounded-3 mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-5"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, ID, subject..." value="<?= e($search) ?>"></div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['active','inactive','resigned','retired'] as $st): ?>
                    <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-dark w-100">Filter</button></div>
            <div class="col-md-2"><a href="/admin/teachers.php" class="btn btn-sm btn-outline-secondary w-100">Clear</a></div>
        </form>
    </div>
</div>

<!-- Teachers Grid -->
<div class="row g-3" id="teachersGrid">
    <?php foreach ($teachers as $t):
        $tPhoto = $t['photo'] ? (str_starts_with($t['photo'], '/uploads/') ? $t['photo'] : '/uploads/photos/'.$t['photo']) : '';
    ?>
    <div class="col-md-6 col-xl-3" data-id="<?= $t['id'] ?>" draggable="true">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:1rem;<?= !$t['is_visible'] ? 'opacity:0.5;' : '' ?>">
            <div class="card-body text-center p-3">
                <!-- Quick toggles -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" title="Toggle Visibility" onclick="toggleTeacher(<?= $t['id'] ?>, 'toggle_visibility')">
                            <i class="bi <?= $t['is_visible'] ? 'bi-eye-fill' : 'bi-eye-slash' ?>"></i>
                        </button>
                        <button class="btn btn-sm <?= $t['is_featured'] ? 'btn-warning' : 'btn-outline-secondary' ?>" title="Toggle Featured" onclick="toggleTeacher(<?= $t['id'] ?>, 'toggle_featured')">
                            <i class="bi bi-star-fill"></i>
                        </button>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <span class="badge bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>" style="font-size:.65rem"><?= ucfirst($t['status']) ?></span>
                        <span class="badge bg-light text-dark" title="Display Order"><i class="bi bi-sort-numeric-up me-1"></i>#<?= (int)$t['display_order'] ?></span>
                    </div>
                </div>

                <!-- Photo -->
                <?php if ($tPhoto): ?>
                    <img src="<?= e($tPhoto) ?>" alt="<?= e($t['name']) ?>" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width:80px;height:80px;background:linear-gradient(135deg,#e2e8f0,#cbd5e1);">
                        <i class="bi bi-person-fill" style="font-size:2rem;color:#94a3b8;"></i>
                    </div>
                <?php endif; ?>

                <h6 class="fw-bold mb-0"><?= e($t['name']) ?></h6>
                <small class="text-muted d-block"><?= e($t['designation'] ?? 'Teacher') ?></small>
                <?php if ($t['subject']): ?><small class="text-primary" style="font-size:.75rem"><?= e($t['subject']) ?></small><?php endif; ?>

                <!-- Actions -->
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-info flex-fill btn-view-teacher" data-bs-toggle="modal" data-bs-target="#teacherViewModal"
                        data-id="<?= $t['id'] ?>"
                        data-name="<?= e($t['name']) ?>"
                        data-employee_id="<?= e($t['employee_id']) ?>"
                        data-photo="<?= e($tPhoto) ?>"
                        data-status="<?= e($t['status']) ?>"
                        data-subject="<?= e($t['subject'] ?? '') ?>"
                        data-qualification="<?= e($t['qualification'] ?? '') ?>"
                        data-experience_years="<?= e($t['experience_years'] ?? '') ?>"
                        data-joining_date="<?= e($t['joining_date'] ?? '') ?>"
                        data-dob="<?= e($t['dob'] ?? '') ?>"
                        data-gender="<?= e($t['gender'] ?? '') ?>"
                        data-phone="<?= e($t['phone'] ?? '') ?>"
                        data-email="<?= e($t['email'] ?? '') ?>"
                        data-address="<?= e($t['address'] ?? '') ?>"
                    ><i class="bi bi-eye me-1"></i>View</button>
                    <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#editTeacherModal<?= $t['id'] ?>">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTeacher(<?= $t['id'] ?>, '<?= e(addslashes($t['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-footer bg-transparent text-center py-2 border-0">
                <small class="text-muted"><i class="bi bi-grip-horizontal"></i> Drag to reorder</small>
            </div>
        </div>
    </div>

    <!-- Edit Modal for each teacher -->
    <div class="modal fade" id="editTeacherModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:1rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="existing_photo" value="<?= e($t['photo'] ?? '') ?>">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit: <?= e($t['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Employee ID *</label>
                                <input type="text" name="employee_id" class="form-control" value="<?= e($t['employee_id']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Full Name *</label>
                                <input type="text" name="teacher_name" class="form-control" value="<?= e($t['name']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Designation</label>
                                <input type="text" name="designation" class="form-control" value="<?= e($t['designation'] ?? 'Teacher') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach (['male','female','other'] as $g): ?>
                                    <option value="<?= $g ?>" <?= ($t['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= e($t['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?= e($t['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Subject</label>
                                <input type="text" name="subject" class="form-control" value="<?= e($t['subject'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Qualification</label>
                                <input type="text" name="qualification" class="form-control" value="<?= e($t['qualification'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Experience (Years)</label>
                                <input type="number" name="experience_years" class="form-control" value="<?= (int)($t['experience_years'] ?? 0) ?>" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">DOB</label>
                                <input type="date" name="dob" class="form-control" value="<?= e($t['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Joining Date</label>
                                <input type="date" name="joining_date" class="form-control" value="<?= e($t['joining_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach (['active','inactive','resigned','retired'] as $st): ?>
                                    <option value="<?= $st ?>" <?= ($t['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?= (int)($t['display_order'] ?? 0) ?>" min="0" title="1=first, 2=second, etc.">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Photo</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                <?php if ($tPhoto): ?><small class="text-muted">Current: <?= basename($t['photo']) ?></small><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?= e($t['address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Bio</label>
                                <textarea name="bio" class="form-control" rows="2"><?= e($t['bio'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 d-flex gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="tVis<?= $t['id'] ?>" <?= $t['is_visible'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="tVis<?= $t['id'] ?>">Visible</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="tFeat<?= $t['id'] ?>" <?= $t['is_featured'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="tFeat<?= $t['id'] ?>">Featured</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($teachers)): ?>
<div class="text-center py-5">
    <i class="bi bi-people" style="font-size:3rem;color:#cbd5e1;"></i>
    <p class="text-muted mt-2">No teachers found. Click "Add Teacher" to get started.</p>
</div>
<?php endif; ?>

<!-- Pagination -->
<?= paginationHtml($p, '/admin/teachers.php?' . http_build_query(array_filter(['search' => $search, 'status' => $statusFilter]))) ?>

<!-- Teacher View Modal -->
<div class="modal fade" id="teacherViewModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0 rounded-3">
  <div class="modal-header border-0 bg-primary bg-opacity-10">
    <div class="d-flex align-items-center gap-3">
      <div>
        <i class="bi bi-person-circle text-primary" style="font-size:3rem" id="tm-avatar-icon"></i>
        <img id="tm-avatar-img" class="rounded-circle d-none" style="width:64px;height:64px;object-fit:cover" alt="">
      </div>
      <div>
        <h5 class="mb-0 fw-bold" id="tm-name"></h5>
        <small class="text-muted" id="tm-employee_id"></small>
        <span class="badge ms-2" id="tm-status"></span>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="row g-4">
      <div class="col-md-6">
        <h6 class="fw-semibold text-primary mb-3"><i class="bi bi-briefcase me-2"></i>Professional Info</h6>
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted" style="width:40%">Subject</td><td class="fw-medium" id="tm-subject"></td></tr>
          <tr><td class="text-muted">Qualification</td><td class="fw-medium" id="tm-qualification"></td></tr>
          <tr><td class="text-muted">Experience</td><td class="fw-medium" id="tm-experience_years"></td></tr>
          <tr><td class="text-muted">Joining Date</td><td class="fw-medium" id="tm-joining_date"></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <h6 class="fw-semibold text-primary mb-3"><i class="bi bi-person me-2"></i>Personal Info</h6>
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted" style="width:40%">DOB</td><td class="fw-medium" id="tm-dob"></td></tr>
          <tr><td class="text-muted">Gender</td><td class="fw-medium" id="tm-gender"></td></tr>
          <tr><td class="text-muted">Phone</td><td class="fw-medium" id="tm-phone"></td></tr>
          <tr><td class="text-muted">Email</td><td class="fw-medium" id="tm-email"></td></tr>
          <tr><td class="text-muted">Address</td><td class="fw-medium" id="tm-address"></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="modal-footer border-0">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
  </div>
</div></div></div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:1rem;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="teacher_id" value="0">
                <input type="hidden" name="existing_photo" value="">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Add Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Employee ID *</label>
                            <input type="text" name="employee_id" class="form-control" required placeholder="e.g. TCH001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" name="teacher_name" class="form-control" required placeholder="Full name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control" value="Teacher" placeholder="e.g. Teacher, HOD">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Mathematics">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Ed, B.Sc">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Experience (Years)</label>
                            <input type="number" name="experience_years" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">DOB</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Joining Date</label>
                            <input type="date" name="joining_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="resigned">Resigned</option>
                                <option value="retired">Retired</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Display Order</label>
                            <input type="number" name="display_order" class="form-control" value="<?= $total + 1 ?>" min="0" title="1=first, 2=second, etc.">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Address..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Bio</label>
                            <textarea name="bio" class="form-control" rows="2" placeholder="Short bio..."></textarea>
                        </div>
                        <div class="col-12 d-flex gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" id="addTVis" checked>
                                <label class="form-check-label small" for="addTVis">Visible</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="addTFeat">
                                <label class="form-check-label small" for="addTFeat">Featured</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Teacher Modal -->
<div class="modal fade" id="importTeacherModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-0 rounded-3">
  <div class="modal-header border-0">
    <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i>Import Teachers</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div id="t-import-step1">
      <div class="alert alert-info py-2" style="font-size:.85rem">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Instructions:</strong>
        <ul class="mb-0 mt-1 ps-3">
          <li>CSV format only</li>
          <li>First row must be column headers</li>
          <li><strong>employee_id</strong> &amp; <strong>name</strong> are required</li>
          <li>If email is provided, a login account will be created (password: Teacher@123)</li>
          <li>Duplicate employee IDs will be skipped</li>
        </ul>
      </div>
      <a href="/admin/sample-teachers-csv.php" class="btn btn-outline-success btn-sm mb-3"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Download Sample CSV</a>
      <div class="mb-3">
        <label class="form-label fw-medium">Select CSV File</label>
        <input type="file" class="form-control" id="tImportFile" accept=".csv">
      </div>
    </div>
    <div id="t-import-step2" class="d-none text-center py-4">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <h6 class="fw-semibold">Processing...</h6>
      <div class="progress mt-3" style="height:8px"><div class="progress-bar progress-bar-striped progress-bar-animated" id="tImportProgress" style="width:10%"></div></div>
      <small class="text-muted mt-2 d-block" id="tImportStatusText">Uploading file...</small>
    </div>
    <div id="t-import-step3" class="d-none">
      <div class="text-center mb-3">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h5 class="fw-bold mt-2">Import Complete</h5>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-4"><div class="card border-0 bg-success bg-opacity-10 text-center p-2"><h4 class="mb-0 text-success" id="t-res-added">0</h4><small class="text-muted">Added</small></div></div>
        <div class="col-4"><div class="card border-0 bg-warning bg-opacity-10 text-center p-2"><h4 class="mb-0 text-warning" id="t-res-skipped">0</h4><small class="text-muted">Skipped</small></div></div>
        <div class="col-4"><div class="card border-0 bg-danger bg-opacity-10 text-center p-2"><h4 class="mb-0 text-danger" id="t-res-failed">0</h4><small class="text-muted">Failed</small></div></div>
      </div>
      <div id="t-res-errors" class="d-none">
        <h6 class="fw-semibold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Errors</h6>
        <div class="border rounded p-2" style="max-height:150px;overflow-y:auto;font-size:.8rem" id="t-res-errors-list"></div>
      </div>
    </div>
  </div>
  <div class="modal-footer border-0">
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="tImportCancelBtn">Cancel</button>
    <button type="button" class="btn btn-primary btn-sm" id="tImportUploadBtn" disabled><i class="bi bi-upload me-1"></i>Upload &amp; Process</button>
    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="tImportMoreBtn">Import More</button>
  </div>
</div></div></div>

<script>
// View modal population
document.querySelectorAll('.btn-view-teacher').forEach(btn => {
  btn.addEventListener('click', function() {
    const d = this.dataset;
    document.getElementById('tm-name').textContent = d.name;
    document.getElementById('tm-employee_id').textContent = d.employee_id;
    const statusEl = document.getElementById('tm-status');
    statusEl.textContent = d.status.charAt(0).toUpperCase() + d.status.slice(1);
    statusEl.className = 'badge ms-2 bg-'+(d.status==='active'?'success':'secondary')+'-subtle text-'+(d.status==='active'?'success':'secondary');
    if (d.photo) { document.getElementById('tm-avatar-img').src = d.photo; document.getElementById('tm-avatar-img').classList.remove('d-none'); document.getElementById('tm-avatar-icon').classList.add('d-none'); }
    else { document.getElementById('tm-avatar-img').classList.add('d-none'); document.getElementById('tm-avatar-icon').classList.remove('d-none'); }
    ['subject','qualification','joining_date','dob','gender','phone','email','address'].forEach(k => {
      const el = document.getElementById('tm-'+k);
      if(el) el.textContent = d[k] || '-';
    });
    document.getElementById('tm-experience_years').textContent = d.experience_years ? d.experience_years+' years' : '-';
  });
});

// Toggle actions via AJAX
function toggleTeacher(id, action) {
    fetch('/admin/ajax/teacher-actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=' + action + '&id=' + id + '&csrf_token=' + (document.querySelector('[name=csrf_token]')?.value || '')
    }).then(function(){ location.reload(); });
}

// Delete teacher
function deleteTeacher(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    fetch('/admin/ajax/teacher-actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=delete_teacher&id=' + id + '&csrf_token=' + (document.querySelector('[name=csrf_token]')?.value || '')
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success) location.reload();
        else alert('Delete failed: ' + (data.message || 'Unknown error'));
    })
    .catch(function(err){ alert('Delete failed: ' + err.message); });
}

// Drag & Drop reorder
(function(){
    var grid = document.getElementById('teachersGrid');
    var dragged = null;
    grid.addEventListener('dragstart', function(e) {
        dragged = e.target.closest('[data-id]');
        if (dragged) dragged.style.opacity = '0.4';
    });
    grid.addEventListener('dragend', function(e) {
        if (dragged) dragged.style.opacity = '1';
        dragged = null;
    });
    grid.addEventListener('dragover', function(e) { e.preventDefault(); });
    grid.addEventListener('drop', function(e) {
        e.preventDefault();
        var target = e.target.closest('[data-id]');
        if (target && dragged && target !== dragged) {
            var items = Array.from(grid.querySelectorAll('[data-id]'));
            var dragIdx = items.indexOf(dragged);
            var dropIdx = items.indexOf(target);
            if (dragIdx < dropIdx) target.after(dragged); else target.before(dragged);
            var order = Array.from(grid.querySelectorAll('[data-id]')).map(function(el){ return el.dataset.id; });
            fetch('/admin/ajax/teacher-actions.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(order)) + '&csrf_token=' + (document.querySelector('[name=csrf_token]')?.value || '')
            });
        }
    });
})();

// Import CSV
(function(){
  const fileInput = document.getElementById('tImportFile');
  const uploadBtn = document.getElementById('tImportUploadBtn');
  const cancelBtn = document.getElementById('tImportCancelBtn');
  const moreBtn = document.getElementById('tImportMoreBtn');
  const step1 = document.getElementById('t-import-step1');
  const step2 = document.getElementById('t-import-step2');
  const step3 = document.getElementById('t-import-step3');
  const progress = document.getElementById('tImportProgress');

  fileInput.addEventListener('change', () => { uploadBtn.disabled = !fileInput.files.length; });

  function resetModal() {
    step1.classList.remove('d-none'); step2.classList.add('d-none'); step3.classList.add('d-none');
    uploadBtn.classList.remove('d-none'); uploadBtn.disabled = true; moreBtn.classList.add('d-none');
    cancelBtn.textContent = 'Cancel'; fileInput.value = '';
    progress.style.width = '10%';
  }

  document.getElementById('importTeacherModal').addEventListener('hidden.bs.modal', resetModal);
  moreBtn.addEventListener('click', resetModal);

  uploadBtn.addEventListener('click', function() {
    if (!fileInput.files.length) return;
    step1.classList.add('d-none'); step2.classList.remove('d-none');
    uploadBtn.classList.add('d-none');

    const fd = new FormData();
    fd.append('csv_file', fileInput.files[0]);

    progress.style.width = '30%';
    document.getElementById('tImportStatusText').textContent = 'Processing records...';

    let prog = 30;
    const iv = setInterval(() => { if (prog < 85) { prog += 5; progress.style.width = prog+'%'; } }, 300);

    fetch('/admin/import-teachers.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        clearInterval(iv);
        progress.style.width = '100%';
        setTimeout(() => {
          step2.classList.add('d-none'); step3.classList.remove('d-none');
          moreBtn.classList.remove('d-none'); cancelBtn.textContent = 'Close';
          document.getElementById('t-res-added').textContent = data.added || 0;
          document.getElementById('t-res-skipped').textContent = data.skipped || 0;
          document.getElementById('t-res-failed').textContent = data.failed || 0;
          if (data.errors && data.errors.length) {
            document.getElementById('t-res-errors').classList.remove('d-none');
            document.getElementById('t-res-errors-list').innerHTML = data.errors.map(e => '<div class="text-danger">'+e+'</div>').join('');
          } else {
            document.getElementById('t-res-errors').classList.add('d-none');
          }
        }, 500);
      })
      .catch(err => {
        clearInterval(iv);
        step2.classList.add('d-none'); step1.classList.remove('d-none');
        uploadBtn.classList.remove('d-none');
        alert('Import failed: ' + err.message);
      });
  });
})();
</script>

<style>
@media print {
  .sidebar, .sidebar-overlay, .top-bar, .content-area > *:not(#teacherViewModal) { display: none !important; }
  .main-content { margin-left: 0 !important; }
  .modal { position: static !important; display: block !important; }
  .modal-dialog { max-width: 100% !important; margin: 0 !important; }
  .modal-content { border: none !important; box-shadow: none !important; }
  .modal-footer { display: none !important; }
  .modal-backdrop { display: none !important; }
  body { background: #fff !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>