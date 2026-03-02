<?php
$pageTitle='Certificates';require_once __DIR__.'/../includes/auth.php';requireAdmin();require_once __DIR__.'/../includes/file-handler.php';$db=getDB();

// ── Image compression & thumbnail helper ──
function compressCertImage($sourcePath, $destPath, $maxWidth = 1600) {
    $info = @getimagesize($sourcePath);
    if (!$info) return false;
    $origSize = filesize($sourcePath);
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
    return ['path' => $destPath, 'original_size' => $origSize, 'compressed_size' => filesize($destPath)];
}

function generateCertThumb($sourcePath, $thumbPath, $maxWidth = 400) {
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
    $newW = min($w, $maxWidth); $newH = (int)round($h * ($newW / $w));
    $thumb = imagecreatetruecolor($newW, $newH);
    imagealphablending($thumb, false); imagesavealpha($thumb, true);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($img);
    $thumbPath = preg_replace('/\.[^.]+$/', '.webp', $thumbPath);
    if (function_exists('imagewebp')) imagewebp($thumb, $thumbPath, 75);
    else { $thumbPath = preg_replace('/\.webp$/', '.jpg', $thumbPath); imagejpeg($thumb, $thumbPath, 75); }
    imagedestroy($thumb);
    return $thumbPath;
}

// Ensure upload dirs
$uploadDir = __DIR__ . '/../uploads/certificates/';
$thumbDir  = __DIR__ . '/../uploads/certificates/thumbs/';
FileHandler::ensureDir($uploadDir);
FileHandler::ensureDir($thumbDir);

$allowedImg = ['jpg','jpeg','png','webp'];
$allowedAll = ['jpg','jpeg','png','webp','pdf'];
$maxFileSize = 10 * 1024 * 1024; // 10MB
$maxZipSize  = 50 * 1024 * 1024; // 50MB

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['form_action'] ?? '';

    // === Upload single / multi ===
    if ($action === 'upload_certificate') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'recognition');
        $year = (int)($_POST['year'] ?? date('Y'));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $allowDownload = isset($_POST['allow_download']) ? 1 : 0;

        if (!$title) { setFlash('error', 'Title is required.'); header('Location: certificates.php'); exit; }

        $files = $_FILES['cert_files'] ?? null;
        if ($files && $files['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $uploaded = 0;
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > $maxFileSize) continue;

                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedAll)) continue;

                // MIME validation
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
                finfo_close($finfo);
                $allowedMimes = ['image/jpeg','image/png','image/webp','application/pdf'];
                if (!in_array($mimeType, $allowedMimes)) continue;

                $isPdf = ($ext === 'pdf');
                $fileType = $isPdf ? 'pdf' : 'image';
                $fname = 'cert_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destPath = $uploadDir . $fname;
                $filePath = 'uploads/certificates/' . $fname;
                $thumbPathRel = '';

                if (!$isPdf) {
                    // Compress image
                    FileHandler::saveUploadedFile($files['tmp_name'][$i], $destPath);
                    $res = compressCertImage($destPath, $destPath);
                    if ($res) {
                        $filePath = 'uploads/certificates/' . basename($res['path']);
                        // Generate thumbnail
                        $thumbDest = $thumbDir . 'thumb_' . basename($res['path']);
                        $thumbResult = generateCertThumb($res['path'], $thumbDest);
                        if ($thumbResult) $thumbPathRel = 'uploads/certificates/thumbs/' . basename($thumbResult);
                    }
                } else {
                    FileHandler::saveUploadedFile($files['tmp_name'][$i], $destPath);
                    $thumbPathRel = ''; // PDF gets no thumbnail
                }

                $maxOrder = $db->query("SELECT COALESCE(MAX(display_order),0)+1 FROM certificates WHERE is_deleted=0")->fetchColumn();
                $itemTitle = (count($files['name']) > 1) ? $title . ' (' . ($i+1) . ')' : $title;
                $stmt = $db->prepare("INSERT INTO certificates (title,description,category,year,file_path,thumb_path,file_type,is_featured,is_active,allow_download,display_order,created_by) VALUES (?,?,?,?,?,?,?,?,1,?,?,?)");
                $stmt->execute([$itemTitle, $description, $category, $year, $filePath, $thumbPathRel, $fileType, $isFeatured, $allowDownload, $maxOrder, currentUserId()]);
                auditLog('cert_upload', 'certificates', (int)$db->lastInsertId(), "Uploaded: $itemTitle");
                $uploaded++;
            }
            setFlash('success', "$uploaded certificate(s) uploaded successfully.");
        } else {
            setFlash('error', 'No files selected.');
        }
        header('Location: certificates.php'); exit;
    }

    // === Bulk ZIP upload ===
    if ($action === 'bulk_zip_upload') {
        $category = trim($_POST['category'] ?? 'recognition');
        $year = (int)($_POST['year'] ?? date('Y'));
        $zipFile = $_FILES['zip_file'] ?? null;
        if ($zipFile && $zipFile['error'] === UPLOAD_ERR_OK && $zipFile['size'] <= $maxZipSize) {
            $ext = strtolower(pathinfo($zipFile['name'], PATHINFO_EXTENSION));
            if ($ext === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($zipFile['tmp_name']) === true) {
                    $count = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entry = $zip->getNameIndex($i);
                        $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                        if (!in_array($entryExt, $allowedImg) || strpos($entry, '__MACOSX') !== false) continue;
                        $tmpFile = tempnam(sys_get_temp_dir(), 'czip_');
                        file_put_contents($tmpFile, $zip->getFromIndex($i));
                        $fname = 'cert_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $entryExt;
                        $destPath = $uploadDir . $fname;
                        rename($tmpFile, $destPath);
                        $res = compressCertImage($destPath, $destPath);
                        $filePath = 'uploads/certificates/' . ($res ? basename($res['path']) : $fname);
                        $thumbPathRel = '';
                        if ($res) {
                            $thumbDest = $thumbDir . 'thumb_' . basename($res['path']);
                            $thumbResult = generateCertThumb($res['path'] ?? $destPath, $thumbDest);
                            if ($thumbResult) $thumbPathRel = 'uploads/certificates/thumbs/' . basename($thumbResult);
                        }
                        $maxOrder = $db->query("SELECT COALESCE(MAX(display_order),0)+1 FROM certificates WHERE is_deleted=0")->fetchColumn();
                        $entryTitle = pathinfo($entry, PATHINFO_FILENAME);
                        $stmt = $db->prepare("INSERT INTO certificates (title,description,category,year,file_path,thumb_path,file_type,is_featured,is_active,allow_download,display_order,created_by) VALUES (?,?,?,?,?,?,'image',0,1,1,?,?)");
                        $stmt->execute([$entryTitle, '', $category, $year, $filePath, $thumbPathRel, $maxOrder, currentUserId()]);
                        auditLog('cert_zip_upload', 'certificates', (int)$db->lastInsertId(), "ZIP: $entryTitle");
                        $count++;
                    }
                    $zip->close();
                    setFlash('success', "$count certificate(s) extracted from ZIP.");
                } else { setFlash('error', 'Failed to open ZIP.'); }
            } else { setFlash('error', 'Only ZIP files allowed.'); }
        } else { setFlash('error', 'ZIP file too large or missing (max 50MB).'); }
        header('Location: certificates.php'); exit;
    }

    // === Edit certificate ===
    if ($action === 'edit_certificate') {
        $id = (int)($_POST['cert_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'recognition');
        $year = (int)($_POST['year'] ?? date('Y'));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $allowDownload = isset($_POST['allow_download']) ? 1 : 0;
        if ($id && $title) {
            $stmt = $db->prepare("UPDATE certificates SET title=?,description=?,category=?,year=?,is_featured=?,is_active=?,allow_download=?,updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $description, $category, $year, $isFeatured, $isActive, $allowDownload, $id]);

            // Replace file if uploaded
            if (!empty($_FILES['cert_file']['name']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cert_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedAll) && $_FILES['cert_file']['size'] <= $maxFileSize) {
                    $isPdf = ($ext === 'pdf');
                    $fname = 'cert_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $uploadDir . $fname;
                    FileHandler::saveUploadedFile($_FILES['cert_file']['tmp_name'], $destPath);
                    $filePath = 'uploads/certificates/' . $fname;
                    $thumbPathRel = '';
                    if (!$isPdf) {
                        $res = compressCertImage($destPath, $destPath);
                        if ($res) {
                            $filePath = 'uploads/certificates/' . basename($res['path']);
                            $thumbDest = $thumbDir . 'thumb_' . basename($res['path']);
                            $thumbResult = generateCertThumb($res['path'], $thumbDest);
                            if ($thumbResult) $thumbPathRel = 'uploads/certificates/thumbs/' . basename($thumbResult);
                        }
                    }
                    $db->prepare("UPDATE certificates SET file_path=?,thumb_path=?,file_type=? WHERE id=?")->execute([$filePath, $thumbPathRel, $isPdf?'pdf':'image', $id]);
                }
            }
            auditLog('cert_edit', 'certificates', $id, "Edited: $title");
            setFlash('success', 'Certificate updated.');
        }
        header('Location: certificates.php'); exit;
    }

    // === Soft delete ===
    if ($action === 'delete_certificate') {
        $id = (int)($_POST['cert_id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE certificates SET is_deleted=1, deleted_at=NOW() WHERE id=?")->execute([$id]);
            auditLog('cert_delete', 'certificates', $id, 'Soft deleted');
            setFlash('success', 'Certificate moved to trash.');
        }
        header('Location: certificates.php'); exit;
    }

    // === Restore ===
    if ($action === 'restore_certificate') {
        $id = (int)($_POST['cert_id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE certificates SET is_deleted=0, deleted_at=NULL WHERE id=?")->execute([$id]);
            auditLog('cert_restore', 'certificates', $id, 'Restored');
            setFlash('success', 'Certificate restored.');
        }
        header('Location: certificates.php?view=trash'); exit;
    }

    // === Permanent delete ===
    if ($action === 'permanent_delete') {
        $id = (int)($_POST['cert_id'] ?? 0);
        if ($id) {
            $cert = $db->prepare("SELECT file_path, thumb_path FROM certificates WHERE id=?")->execute([$id]);
            $cert = $db->prepare("SELECT file_path, thumb_path FROM certificates WHERE id=?");
            $cert->execute([$id]);
            $c = $cert->fetch();
            if ($c) {
                FileHandler::deleteFile(__DIR__ . '/../' . $c['file_path']);
                if ($c['thumb_path']) FileHandler::deleteFile(__DIR__ . '/../' . $c['thumb_path']);
            }
            $db->prepare("DELETE FROM certificates WHERE id=?")->execute([$id]);
            auditLog('cert_perm_delete', 'certificates', $id, 'Permanently deleted');
            setFlash('success', 'Certificate permanently deleted.');
        }
        header('Location: certificates.php?view=trash'); exit;
    }
}

// ── Fetch certificates ──
$view = $_GET['view'] ?? 'active';
$isTrash = ($view === 'trash');
$certs = $db->query("SELECT c.*, u.name AS creator_name FROM certificates c LEFT JOIN users u ON c.created_by=u.id WHERE c.is_deleted=" . ($isTrash ? '1' : '0') . " ORDER BY c.display_order ASC, c.id DESC")->fetchAll();
$trashCount = $db->query("SELECT COUNT(*) FROM certificates WHERE is_deleted=1")->fetchColumn();
$activeCount = $db->query("SELECT COUNT(*) FROM certificates WHERE is_deleted=0")->fetchColumn();

$categories = [
    'govt_approval' => 'Government Approval',
    'board_affiliation' => 'Board Affiliation',
    'recognition' => 'Recognition',
    'awards' => 'Awards',
];

require_once __DIR__.'/../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-award me-2"></i>Certificates & Accreditations</h5>
        <small class="text-muted"><?= $activeCount ?> active · <?= $trashCount ?> in trash</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($isTrash): ?>
            <a href="certificates.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Active</a>
        <?php else: ?>
            <a href="certificates.php?view=trash" class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash me-1"></i>Trash (<?= $trashCount ?>)</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-plus-lg me-1"></i>Add Certificate</button>
        <?php endif; ?>
    </div>
</div>

<!-- Certificate List -->
<div class="card border-0 rounded-3">
<div class="card-body p-0">
<?php if (empty($certs)): ?>
    <div class="text-center py-5">
        <i class="bi bi-award text-muted" style="font-size:3rem;opacity:.3"></i>
        <p class="text-muted mt-2"><?= $isTrash ? 'Trash is empty.' : 'No certificates yet. Click "Add Certificate" to upload.' ?></p>
    </div>
<?php else: ?>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="certTable">
    <thead class="table-light"><tr>
        <th style="width:50px"></th>
        <th>Preview</th>
        <th>Title</th>
        <th>Category</th>
        <th>Year</th>
        <th>Status</th>
        <th>Featured</th>
        <th class="text-end">Actions</th>
    </tr></thead>
    <tbody id="certBody">
    <?php foreach ($certs as $c):
        $thumbSrc = $c['thumb_path'] ? '/' . $c['thumb_path'] : ($c['file_type']==='pdf' ? '' : '/' . $c['file_path']);
        $catLabel = $categories[$c['category']] ?? ucfirst($c['category']);
        $catBadgeColors = ['govt_approval'=>'success','board_affiliation'=>'primary','recognition'=>'info','awards'=>'warning'];
        $catColor = $catBadgeColors[$c['category']] ?? 'secondary';
    ?>
    <tr data-id="<?= $c['id'] ?>">
        <td class="text-center text-muted" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></td>
        <td>
            <?php if ($c['file_type'] === 'pdf'): ?>
                <div class="d-flex align-items-center justify-content-center rounded" style="width:60px;height:60px;background:#fee2e2;"><i class="bi bi-file-earmark-pdf text-danger" style="font-size:1.5rem"></i></div>
            <?php elseif ($thumbSrc): ?>
                <img src="<?= e($thumbSrc) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:8px;" loading="lazy">
            <?php endif; ?>
        </td>
        <td>
            <strong style="font-size:.85rem"><?= e($c['title']) ?></strong>
            <?php if ($c['description']): ?><br><small class="text-muted"><?= e(mb_strimwidth($c['description'], 0, 60, '...')) ?></small><?php endif; ?>
        </td>
        <td><span class="badge bg-<?= $catColor ?>-subtle text-<?= $catColor ?>"><?= e($catLabel) ?></span></td>
        <td style="font-size:.85rem"><?= $c['year'] ?: '—' ?></td>
        <td>
            <span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>-subtle text-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span>
        </td>
        <td>
            <?php if ($c['is_featured']): ?><i class="bi bi-star-fill text-warning"></i><?php else: ?><i class="bi bi-star text-muted"></i><?php endif; ?>
        </td>
        <td class="text-end text-nowrap">
            <?php if ($isTrash): ?>
                <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="form_action" value="restore_certificate"><input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-outline-success py-0 px-1" title="Restore"><i class="bi bi-arrow-counterclockwise" style="font-size:.75rem"></i></button>
                </form>
                <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="form_action" value="permanent_delete"><input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="return confirm('Permanently delete? Cannot be undone.')" title="Delete Forever"><i class="bi bi-x-lg" style="font-size:.75rem"></i></button>
                </form>
            <?php else: ?>
                <a href="/<?= e($c['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-info py-0 px-1" title="Preview"><i class="bi bi-eye" style="font-size:.75rem"></i></a>
                <button class="btn btn-sm btn-outline-primary py-0 px-1 btn-edit-cert" data-bs-toggle="modal" data-bs-target="#editModal"
                    data-id="<?= $c['id'] ?>" data-title="<?= e($c['title']) ?>" data-description="<?= e($c['description']) ?>"
                    data-category="<?= e($c['category']) ?>" data-year="<?= $c['year'] ?>"
                    data-featured="<?= $c['is_featured'] ?>" data-active="<?= $c['is_active'] ?>" data-download="<?= $c['allow_download'] ?>"
                    title="Edit"><i class="bi bi-pencil" style="font-size:.75rem"></i></button>
                <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_certificate"><input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="return confirm('Move to trash?')" title="Delete"><i class="bi bi-trash" style="font-size:.75rem"></i></button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
<?php endif; ?>
</div>
</div>

<?php if (!$isTrash): ?>
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0 rounded-3">
    <div class="modal-header"><h6 class="modal-title fw-semibold"><i class="bi bi-cloud-arrow-up me-2"></i>Upload Certificate</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <!-- Tabs: Single/Multi | ZIP -->
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active rounded-pill" data-bs-toggle="pill" data-bs-target="#uploadSingle">Single / Multi</button></li>
            <li class="nav-item"><button class="nav-link rounded-pill" data-bs-toggle="pill" data-bs-target="#uploadZip">Bulk ZIP</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="uploadSingle">
                <form method="POST" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="form_action" value="upload_certificate">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label fw-semibold">Title *</label><input type="text" name="title" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Year</label><input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="1900" max="2099"></div>
                    <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Files (Image / PDF)</label>
                        <input type="file" name="cert_files[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple required>
                        <small class="text-muted">Max 10MB each. JPG, PNG, WebP, PDF.</small>
                    </div>
                    <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="upFeatured"><label class="form-check-label" for="upFeatured">Featured on Home</label></div></div>
                    <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_download" id="upDownload" checked><label class="form-check-label" for="upDownload">Allow Download</label></div></div>
                    <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload Certificate(s)</button></div>
                </div>
                </form>
            </div>
            <div class="tab-pane fade" id="uploadZip">
                <form method="POST" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="form_action" value="bulk_zip_upload">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Year</label><input type="number" name="year" class="form-control" value="<?= date('Y') ?>"></div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">ZIP File (images only)</label>
                        <input type="file" name="zip_file" class="form-control" accept=".zip" required>
                        <small class="text-muted">Max 50MB. Images inside will be auto-extracted, compressed, and thumbnailed.</small>
                    </div>
                    <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-file-earmark-zip me-1"></i>Upload & Extract</button></div>
                </div>
                </form>
            </div>
        </div>
    </div>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-0 rounded-3">
    <div class="modal-header"><h6 class="modal-title fw-semibold"><i class="bi bi-pencil me-2"></i>Edit Certificate</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form method="POST" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="form_action" value="edit_certificate"><input type="hidden" name="cert_id" id="editId">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label fw-semibold">Title *</label><input type="text" name="title" id="editTitle" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Year</label><input type="number" name="year" id="editYear" class="form-control" min="1900" max="2099"></div>
            <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" id="editDesc" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Category</label>
                <select name="category" id="editCategory" class="form-select">
                    <?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Replace File (optional)</label>
                <input type="file" name="cert_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
            </div>
            <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="editFeatured"><label class="form-check-label" for="editFeatured">Featured</label></div></div>
            <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="editActive"><label class="form-check-label" for="editActive">Active</label></div></div>
            <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_download" id="editDownload"><label class="form-check-label" for="editDownload">Download</label></div></div>
            <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Save Changes</button></div>
        </div>
        </form>
    </div>
</div></div></div>
<?php endif; ?>

<script>
// Edit modal population
document.querySelectorAll('.btn-edit-cert').forEach(btn => {
    btn.addEventListener('click', function(){
        document.getElementById('editId').value = this.dataset.id;
        document.getElementById('editTitle').value = this.dataset.title;
        document.getElementById('editDesc').value = this.dataset.description;
        document.getElementById('editCategory').value = this.dataset.category;
        document.getElementById('editYear').value = this.dataset.year;
        document.getElementById('editFeatured').checked = this.dataset.featured === '1';
        document.getElementById('editActive').checked = this.dataset.active === '1';
        document.getElementById('editDownload').checked = this.dataset.download === '1';
    });
});

// Drag & drop reorder
(function(){
    const tbody = document.getElementById('certBody');
    if (!tbody) return;
    let dragRow = null;
    tbody.querySelectorAll('tr').forEach(row => {
        row.draggable = true;
        row.addEventListener('dragstart', function(e){ dragRow = this; this.style.opacity = '0.4'; });
        row.addEventListener('dragend', function(){ this.style.opacity = '1'; });
        row.addEventListener('dragover', function(e){ e.preventDefault(); });
        row.addEventListener('drop', function(e){
            e.preventDefault();
            if (dragRow !== this) {
                const allRows = [...tbody.querySelectorAll('tr')];
                const fromIdx = allRows.indexOf(dragRow);
                const toIdx = allRows.indexOf(this);
                if (fromIdx < toIdx) this.after(dragRow);
                else this.before(dragRow);
                // Save order via AJAX
                const order = [...tbody.querySelectorAll('tr')].map(r => r.dataset.id);
                fetch('/admin/ajax/certificate-actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(order))
                });
            }
        });
    });
})();
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
