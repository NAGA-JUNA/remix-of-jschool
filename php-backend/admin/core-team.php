<?php
$pageTitle = 'Core Team Manager';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Handle form submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_name']) && verifyCsrf()) {
    $id = (int)($_POST['member_id'] ?? 0);
    $data = [
        'name' => trim($_POST['member_name'] ?? ''),
        'designation' => trim($_POST['designation'] ?? ''),
        'qualification' => trim($_POST['qualification'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'experience_years' => (int)($_POST['experience_years'] ?? 0),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
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
            $filename = 'core_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                // Delete old photo if replacing
                if ($photoPath && $id > 0) {
                    $oldPath = __DIR__ . '/../' . ltrim($photoPath, '/');
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $photoPath = '/uploads/photos/' . $filename;
            }
        }
    }

    if ($data['name']) {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE core_team SET name=?, designation=?, qualification=?, subject=?, experience_years=?, email=?, phone=?, bio=?, display_order=?, is_visible=?, is_featured=?, photo=? WHERE id=?");
            $stmt->execute([
                $data['name'], $data['designation'], $data['qualification'], $data['subject'],
                $data['experience_years'], $data['email'], $data['phone'], $data['bio'],
                $data['display_order'], $data['is_visible'], $data['is_featured'], $photoPath, $id
            ]);
            auditLog('core_team_update', 'core_team', $id, 'Updated: ' . $data['name']);
            setFlash('success', 'Member updated successfully!');
        } else {
            $stmt = $db->prepare("INSERT INTO core_team (name, designation, qualification, subject, experience_years, email, phone, bio, display_order, is_visible, is_featured, photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['name'], $data['designation'], $data['qualification'], $data['subject'],
                $data['experience_years'], $data['email'], $data['phone'], $data['bio'],
                $data['display_order'], $data['is_visible'], $data['is_featured'], $photoPath
            ]);
            $newId = $db->lastInsertId();
            auditLog('core_team_add', 'core_team', $newId, 'Added: ' . $data['name']);
            setFlash('success', 'Member added successfully!');
        }
    }

    header('Location: /admin/core-team.php');
    exit;
}

// Fetch all members
$members = $db->query("SELECT * FROM core_team ORDER BY display_order ASC, name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<input type="hidden" id="csrf_token" name="csrf_token" value="<?= csrfToken() ?>">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Core Team Manager</h4>
        <p class="text-muted mb-0 small">Manage independent core team members displayed on the homepage.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary"><?= count($members) ?> Members</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
            <i class="bi bi-plus-lg me-1"></i> Add Member
        </button>
    </div>
</div>

<!-- Members Grid -->
<div class="row g-3" id="membersGrid">
    <?php foreach ($members as $m):
        $mPhoto = $m['photo'] ? (str_starts_with($m['photo'], '/uploads/') ? $m['photo'] : '/uploads/photos/'.$m['photo']) : '';
    ?>
    <div class="col-md-6 col-xl-3" data-id="<?= $m['id'] ?>" draggable="true">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:1rem;<?= !$m['is_visible'] ? 'opacity:0.5;' : '' ?>">
            <div class="card-body text-center p-3">
                <!-- Quick toggles -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" title="Toggle Visibility" onclick="toggleMember(<?= $m['id'] ?>, 'toggle_visibility')">
                            <i class="bi <?= $m['is_visible'] ? 'bi-eye-fill' : 'bi-eye-slash' ?>"></i>
                        </button>
                        <button class="btn btn-sm <?= $m['is_featured'] ? 'btn-warning' : 'btn-outline-secondary' ?>" title="Toggle Featured" onclick="toggleMember(<?= $m['id'] ?>, 'toggle_featured')">
                            <i class="bi bi-star-fill"></i>
                        </button>
                    </div>
                    <span class="badge bg-light text-dark" title="Display Order"><i class="bi bi-sort-numeric-up me-1"></i>#<?= (int)$m['display_order'] ?></span>
                </div>

                <!-- Photo -->
                <?php if ($mPhoto): ?>
                    <img src="<?= e($mPhoto) ?>" alt="<?= e($m['name']) ?>" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width:80px;height:80px;background:linear-gradient(135deg,#e2e8f0,#cbd5e1);">
                        <i class="bi bi-person-fill" style="font-size:2rem;color:#94a3b8;"></i>
                    </div>
                <?php endif; ?>

                <h6 class="fw-bold mb-0"><?= e($m['name']) ?></h6>
                <small class="text-muted"><?= e($m['designation'] ?: 'Team Member') ?></small>

                <!-- Actions -->
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#editModal<?= $m['id'] ?>">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteMember(<?= $m['id'] ?>, '<?= e(addslashes($m['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-footer bg-transparent text-center py-2 border-0">
                <small class="text-muted"><i class="bi bi-grip-horizontal"></i> Drag to reorder</small>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal<?= $m['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:1rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="existing_photo" value="<?= e($m['photo'] ?? '') ?>">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit: <?= e($m['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Name *</label>
                                <input type="text" name="member_name" class="form-control" value="<?= e($m['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Designation</label>
                                <input type="text" name="designation" class="form-control" value="<?= e($m['designation'] ?? '') ?>" placeholder="e.g. Principal, Director">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Qualification</label>
                                <input type="text" name="qualification" class="form-control" value="<?= e($m['qualification'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Subject</label>
                                <input type="text" name="subject" class="form-control" value="<?= e($m['subject'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Experience (Years)</label>
                                <input type="number" name="experience_years" class="form-control" value="<?= (int)($m['experience_years'] ?? 0) ?>" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= e($m['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= e($m['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?= (int)($m['display_order'] ?? 0) ?>" min="0" title="1=first, 2=second, etc.">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Photo</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                <?php if ($mPhoto): ?><small class="text-muted">Current: <?= basename($m['photo']) ?></small><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Bio</label>
                                <textarea name="bio" class="form-control" rows="2"><?= e($m['bio'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 d-flex gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="vis<?= $m['id'] ?>" <?= $m['is_visible'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="vis<?= $m['id'] ?>">Visible</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="feat<?= $m['id'] ?>" <?= $m['is_featured'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="feat<?= $m['id'] ?>">Featured</label>
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

<?php if (empty($members)): ?>
<div class="text-center py-5">
    <i class="bi bi-people" style="font-size:3rem;color:#cbd5e1;"></i>
    <p class="text-muted mt-2">No core team members yet. Click "Add Member" to get started.</p>
</div>
<?php endif; ?>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:1rem;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="member_id" value="0">
                <input type="hidden" name="existing_photo" value="">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Add Core Team Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Name *</label>
                            <input type="text" name="member_name" class="form-control" required placeholder="Full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control" placeholder="e.g. Principal, Director">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Ed, Ph.D">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Mathematics">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Experience (Years)</label>
                            <input type="number" name="experience_years" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Display Order</label>
                            <input type="number" name="display_order" class="form-control" value="<?= count($members) + 1 ?>" min="0" title="1=first, 2=second, etc.">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Bio</label>
                            <textarea name="bio" class="form-control" rows="2" placeholder="Short bio..."></textarea>
                        </div>
                        <div class="col-12 d-flex gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" id="addVis" checked>
                                <label class="form-check-label small" for="addVis">Visible</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="addFeat">
                                <label class="form-check-label small" for="addFeat">Featured</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle actions via AJAX
function toggleMember(id, action) {
    var token = document.getElementById('csrf_token').value;
    fetch('/admin/ajax/core-team-actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=' + action + '&id=' + id + '&csrf_token=' + encodeURIComponent(token)
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) location.reload();
        else alert(data.error || 'Action failed');
    }).catch(function(){ alert('Network error'); });
}

// Delete member
function deleteMember(id, name) {
    if (!confirm('Delete "' + name + '" from core team? This cannot be undone.')) return;
    var token = document.getElementById('csrf_token').value;
    fetch('/admin/ajax/core-team-actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=delete&id=' + id + '&csrf_token=' + encodeURIComponent(token)
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) location.reload();
        else alert(data.error || 'Delete failed');
    }).catch(function(){ alert('Network error'); });
}

// Drag & Drop reorder
(function(){
    var grid = document.getElementById('membersGrid');
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
            var token = document.getElementById('csrf_token').value;
            var order = Array.from(grid.querySelectorAll('[data-id]')).map(function(el){ return el.dataset.id; });
            fetch('/admin/ajax/core-team-actions.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(order)) + '&csrf_token=' + encodeURIComponent(token)
            });
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>