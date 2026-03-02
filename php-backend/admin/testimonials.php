<?php
$pageTitle = 'Testimonials';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/file-handler.php';
$db = getDB();

// Image compression helper (reuse pattern from certificates)
function compressTestimonialPhoto($sourcePath, $destPath, $maxWidth = 400) {
    $info = @getimagesize($sourcePath);
    if (!$info) return false;
    $mime = $info['mime']; $w = $info[0]; $h = $info[1];
    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $img = imagecreatefrompng($sourcePath); break;
        case 'image/webp': $img = imagecreatefromwebp($sourcePath); break;
        default: return false;
    }
    if (!$img) return false;
    if ($w > $maxWidth) {
        $newW = $maxWidth; $newH = (int)round($h * ($maxWidth / $w));
        $resized = imagecreatetruecolor($newW, $newH);
        imagealphablending($resized, false); imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img); $img = $resized;
    }
    $destPath = preg_replace('/\.[^.]+$/', '.webp', $destPath);
    if (function_exists('imagewebp')) { imagewebp($img, $destPath, 82); }
    else { $destPath = preg_replace('/\.webp$/', '.jpg', $destPath); imagejpeg($img, $destPath, 82); }
    imagedestroy($img);
    return basename($destPath);
}

$uploadDir = __DIR__ . '/../uploads/photos/';
FileHandler::ensureDir($uploadDir);
$allowedImg = ['jpg', 'jpeg', 'png', 'webp'];
$maxFileSize = 5 * 1024 * 1024;

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['form_action'] ?? '';

    // Add testimonial
    if ($action === 'add_testimonial') {
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'Parent');
        $message = trim($_POST['message'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $photoFile = null;

        if (!$name || !$message) { setFlash('error', 'Name and message are required.'); header('Location: testimonials.php'); exit; }

        // Photo upload
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedImg) && $_FILES['photo']['size'] <= $maxFileSize) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                finfo_close($finfo);
                if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
                    $fname = 'testimonial_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $uploadDir . $fname;
                    FileHandler::saveUploadedFile($_FILES['photo']['tmp_name'], $destPath);
                    $photoFile = compressTestimonialPhoto($destPath, $destPath);
                    if ($photoFile && $photoFile !== $fname) { @unlink($uploadDir . $fname); }
                }
            }
        }

        $stmt = $db->prepare("INSERT INTO testimonials (name, role, message, rating, photo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $role, $message, $rating, $photoFile]);
        auditLog('add_testimonial', 'testimonials', (int)$db->lastInsertId(), "Added: $name");
        setFlash('success', 'Testimonial added successfully.');
        header('Location: testimonials.php'); exit;
    }

    // Approve
    if ($action === 'approve_testimonial') {
        $id = (int)($_POST['testimonial_id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE testimonials SET is_approved=1, approved_by=?, approved_at=NOW() WHERE id=?")->execute([currentUserId(), $id]);
            auditLog('approve_testimonial', 'testimonials', $id);
            setFlash('success', 'Testimonial approved.');
        }
        header('Location: testimonials.php'); exit;
    }

    // Reject (set to -1)
    if ($action === 'reject_testimonial') {
        $id = (int)($_POST['testimonial_id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE testimonials SET is_approved=-1 WHERE id=?")->execute([$id]);
            auditLog('reject_testimonial', 'testimonials', $id);
            setFlash('success', 'Testimonial rejected.');
        }
        header('Location: testimonials.php'); exit;
    }

    // Delete
    if ($action === 'delete_testimonial') {
        $id = (int)($_POST['testimonial_id'] ?? 0);
        if ($id) {
            $t = $db->prepare("SELECT photo FROM testimonials WHERE id=?"); $t->execute([$id]); $row = $t->fetch();
            if ($row && $row['photo']) { @unlink($uploadDir . $row['photo']); }
            $db->prepare("DELETE FROM testimonials WHERE id=?")->execute([$id]);
            auditLog('delete_testimonial', 'testimonials', $id);
            setFlash('success', 'Testimonial deleted.');
        }
        header('Location: testimonials.php'); exit;
    }

    // Edit
    if ($action === 'edit_testimonial') {
        $id = (int)($_POST['testimonial_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'Parent');
        $message = trim($_POST['message'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        if ($id && $name && $message) {
            $db->prepare("UPDATE testimonials SET name=?, role=?, message=?, rating=? WHERE id=?")->execute([$name, $role, $message, $rating, $id]);

            // Replace photo if uploaded
            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedImg) && $_FILES['photo']['size'] <= $maxFileSize) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                    finfo_close($finfo);
                    if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
                        // Delete old photo
                        $old = $db->prepare("SELECT photo FROM testimonials WHERE id=?"); $old->execute([$id]); $oldRow = $old->fetch();
                        if ($oldRow && $oldRow['photo']) { @unlink($uploadDir . $oldRow['photo']); }
                        $fname = 'testimonial_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $destPath = $uploadDir . $fname;
                        FileHandler::saveUploadedFile($_FILES['photo']['tmp_name'], $destPath);
                        $photoFile = compressTestimonialPhoto($destPath, $destPath);
                        if ($photoFile && $photoFile !== $fname) { @unlink($uploadDir . $fname); }
                        $db->prepare("UPDATE testimonials SET photo=? WHERE id=?")->execute([$photoFile, $id]);
                    }
                }
            }
            auditLog('edit_testimonial', 'testimonials', $id, "Edited: $name");
            setFlash('success', 'Testimonial updated.');
        }
        header('Location: testimonials.php'); exit;
    }
}

// ── Fetch testimonials ──
$testimonials = $db->query("SELECT t.*, u.name AS approver_name FROM testimonials t LEFT JOIN users u ON t.approved_by=u.id ORDER BY t.created_at DESC")->fetchAll();
$pendingCount = 0; $approvedCount = 0; $rejectedCount = 0;
foreach ($testimonials as $t) {
    if ($t['is_approved'] == 1) $approvedCount++;
    elseif ($t['is_approved'] == -1) $rejectedCount++;
    else $pendingCount++;
}

$roles = ['Parent', 'Student', 'Alumni', 'Teacher', 'Other'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-chat-quote me-2"></i>Testimonials</h5>
        <small class="text-muted"><?= $approvedCount ?> approved · <?= $pendingCount ?> pending · <?= $rejectedCount ?> rejected</small>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Add Testimonial</button>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-pills mb-4 gap-2" style="font-size:.82rem;">
    <li class="nav-item"><a class="nav-link <?= !isset($_GET['filter']) ? 'active' : '' ?>" href="testimonials.php">All (<?= count($testimonials) ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?= ($_GET['filter'] ?? '') === 'pending' ? 'active' : '' ?>" href="?filter=pending">Pending (<?= $pendingCount ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?= ($_GET['filter'] ?? '') === 'approved' ? 'active' : '' ?>" href="?filter=approved">Approved (<?= $approvedCount ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?= ($_GET['filter'] ?? '') === 'rejected' ? 'active' : '' ?>" href="?filter=rejected">Rejected (<?= $rejectedCount ?>)</a></li>
</ul>

<?php
$filter = $_GET['filter'] ?? '';
$filtered = $testimonials;
if ($filter === 'pending') $filtered = array_filter($testimonials, fn($t) => $t['is_approved'] == 0);
elseif ($filter === 'approved') $filtered = array_filter($testimonials, fn($t) => $t['is_approved'] == 1);
elseif ($filter === 'rejected') $filtered = array_filter($testimonials, fn($t) => $t['is_approved'] == -1);
?>

<!-- Testimonials Grid -->
<?php if (empty($filtered)): ?>
<div class="card border-0 rounded-3">
    <div class="card-body text-center py-5">
        <i class="bi bi-chat-quote text-muted" style="font-size:3rem;opacity:.3"></i>
        <p class="text-muted mt-2">No testimonials found.</p>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($filtered as $t):
    $statusBadge = $t['is_approved'] == 1 ? '<span class="badge bg-success-subtle text-success">Approved</span>' : ($t['is_approved'] == -1 ? '<span class="badge bg-danger-subtle text-danger">Rejected</span>' : '<span class="badge bg-warning-subtle text-warning">Pending</span>');
    $photoSrc = $t['photo'] ? '/uploads/photos/' . $t['photo'] : '';
    $initials = strtoupper(substr($t['name'], 0, 1));
?>
<div class="col-lg-4 col-md-6">
    <div class="card border-0 rounded-3 h-100" style="box-shadow:var(--shadow-sm);">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <?php if ($photoSrc): ?>
                <img src="<?= e($photoSrc) ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--brand-primary),var(--brand-secondary));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;"><?= $initials ?></div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-0" style="font-size:.85rem;"><?= e($t['name']) ?></h6>
                    <small class="text-muted"><?= e($t['role']) ?></small>
                </div>
                <?= $statusBadge ?>
            </div>
            <div class="mb-2">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                <i class="bi bi-star<?= $s <= $t['rating'] ? '-fill' : '' ?>" style="color:<?= $s <= $t['rating'] ? '#f59e0b' : '#d1d5db' ?>;font-size:.8rem;"></i>
                <?php endfor; ?>
            </div>
            <p class="text-muted mb-3" style="font-size:.82rem;line-height:1.6;">"<?= e(mb_strimwidth($t['message'], 0, 200, '...')) ?>"</p>
            <div class="d-flex gap-1 flex-wrap" style="font-size:.75rem;">
                <?php if ($t['is_approved'] != 1): ?>
                <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="form_action" value="approve_testimonial"><input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>"><button class="btn btn-success btn-sm py-0 px-2" style="font-size:.75rem;"><i class="bi bi-check-lg"></i> Approve</button></form>
                <?php endif; ?>
                <?php if ($t['is_approved'] != -1): ?>
                <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="form_action" value="reject_testimonial"><input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>"><button class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size:.75rem;"><i class="bi bi-x-lg"></i> Reject</button></form>
                <?php endif; ?>
                <button class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.75rem;" data-bs-toggle="modal" data-bs-target="#editModal<?= $t['id'] ?>"><i class="bi bi-pencil"></i> Edit</button>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this testimonial permanently?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_testimonial"><input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>"><button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.75rem;"><i class="bi bi-trash"></i></button></form>
            </div>
            <?php if ($t['approver_name'] && $t['is_approved'] == 1): ?>
            <div class="mt-2"><small class="text-muted" style="font-size:.7rem;">Approved by <?= e($t['approver_name']) ?> on <?= date('d M Y', strtotime($t['approved_at'])) ?></small></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $t['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-0 rounded-3">
    <div class="modal-header border-0"><h6 class="modal-title fw-semibold">Edit Testimonial</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="edit_testimonial">
    <input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Name *</label><input type="text" name="name" class="form-control" value="<?= e($t['name']) ?>" required></div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Role</label>
            <select name="role" class="form-select">
                <?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= $t['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Message *</label><textarea name="message" class="form-control" rows="4" required><?= e($t['message']) ?></textarea></div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Rating</label>
            <select name="rating" class="form-select">
                <?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>" <?= $t['rating'] == $r ? 'selected' : '' ?>><?= $r ?> Star<?= $r > 1 ? 's' : '' ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Replace Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
    </div>
    <div class="modal-footer border-0"><button class="btn btn-primary btn-sm">Save Changes</button></div>
    </form>
</div></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-0 rounded-3">
    <div class="modal-header border-0"><h6 class="modal-title fw-semibold"><i class="bi bi-chat-quote me-2"></i>Add Testimonial</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="add_testimonial">
    <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Name *</label><input type="text" name="name" class="form-control" placeholder="e.g. Rajesh Kumar" required></div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Role</label>
            <select name="role" class="form-select">
                <?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Message *</label><textarea name="message" class="form-control" rows="4" placeholder="Write the testimonial message..." required></textarea></div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Rating</label>
            <select name="rating" class="form-select">
                <?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>"><?= $r ?> Star<?= $r > 1 ? 's' : '' ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Photo (optional)</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
    </div>
    <div class="modal-footer border-0"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Testimonial</button></div>
    </form>
</div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
