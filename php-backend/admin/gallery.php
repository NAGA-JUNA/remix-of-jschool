<?php
$pageTitle='Gallery';require_once __DIR__.'/../includes/auth.php';requireAdmin();require_once __DIR__.'/../includes/file-handler.php';$db=getDB();

// ── Image compression helper ──
function compressGalleryImage($sourcePath, $destPath, $maxWidth = 1600) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;
    $origSize = filesize($sourcePath);
    $mime = $info['mime']; $w = $info[0]; $h = $info[1];
    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $img = imagecreatefrompng($sourcePath); break;
        case 'image/webp': $img = imagecreatefromwebp($sourcePath); break;
        case 'image/gif':  $img = imagecreatefromgif($sourcePath); break;
        default: return false;
    }
    if (!$img) return false;
    if ($w > $maxWidth) {
        $newW = $maxWidth; $newH = (int)round($h * ($maxWidth / $w));
        $resized = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png' || $mime === 'image/webp') { imagealphablending($resized, false); imagesavealpha($resized, true); }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img); $img = $resized;
    }
    if (function_exists('imagewebp')) {
        $destPath = preg_replace('/\.[^.]+$/', '.webp', $destPath);
        imagewebp($img, $destPath, 80);
    } else {
        $destPath = preg_replace('/\.[^.]+$/', '.jpg', $destPath);
        imagejpeg($img, $destPath, 80);
    }
    imagedestroy($img);
    return ['path' => $destPath, 'original_size' => $origSize, 'compressed_size' => filesize($destPath)];
}

// ── ZIP extraction helper ──
function extractGalleryZip($zipPath, $uploadDir, $compress, $maxWidth = 1600) {
    $allowedExt = ['jpg','jpeg','png','webp','gif']; $results = [];
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt) || strpos($entry, '__MACOSX') !== false) continue;
        $tmpFile = tempnam(sys_get_temp_dir(), 'gzip_');
        file_put_contents($tmpFile, $zip->getFromIndex($i));
        $origSize = filesize($tmpFile);
        $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $filename;
        if ($compress) {
            $res = compressGalleryImage($tmpFile, $destPath); unlink($tmpFile);
            if ($res) $results[] = ['path' => str_replace($uploadDir, 'uploads/gallery/', $res['path']), 'original_size' => $res['original_size'], 'compressed_size' => $res['compressed_size']];
        } else {
            rename($tmpFile, $destPath);
            $results[] = ['path' => 'uploads/gallery/' . $filename, 'original_size' => $origSize, 'compressed_size' => $origSize];
        }
    }
    $zip->close();
    return $results;
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    // ── Approval / Delete actions ──
    if (in_array($action, ['approved','rejected','delete','approve_batch','reject_batch'])) {
        if ($action === 'approve_batch' || $action === 'reject_batch') {
            $bid = $_POST['batch_id'] ?? '';
            $newStatus = ($action === 'approve_batch') ? 'approved' : 'rejected';
            if ($bid) {
                $db->prepare("UPDATE gallery_items SET status=?, approved_by=?, approved_at=NOW() WHERE batch_id=? AND status='pending'")->execute([$newStatus, currentUserId(), $bid]);
                auditLog("gallery_batch_$newStatus", 'gallery', 0, "Batch: $bid");
                setFlash('success', "Batch $newStatus.");
            }
        } else {
            $gid = (int)($_POST['id'] ?? 0);
            if ($gid) {
                if ($action === 'delete') $db->prepare("DELETE FROM gallery_items WHERE id=?")->execute([$gid]);
                else $db->prepare("UPDATE gallery_items SET status=?, approved_by=?, approved_at=NOW() WHERE id=?")->execute([$action, currentUserId(), $gid]);
                auditLog("gallery_$action", 'gallery', $gid);
                setFlash('success', 'Done.');
            }
        }
        header('Location: /admin/gallery.php' . (isset($_GET['status']) ? '?status=' . $_GET['status'] : ''));
        exit;
    }

    // ── Admin Upload ──
    if ($action === 'upload') {
        $title       = trim($_POST['title'] ?? '');
        $category    = $_POST['category'] ?? 'general';
        $description = trim($_POST['description'] ?? '');
        $eventName   = trim($_POST['event_name'] ?? '');
        $eventDate   = $_POST['event_date'] ?? null;
        $tags        = trim($_POST['tags'] ?? '');
        $visibility  = in_array($_POST['visibility'] ?? '', ['public','private']) ? $_POST['visibility'] : 'public';
        $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
        $compress    = isset($_POST['compress']);
        $uploadMode  = $_POST['upload_mode'] ?? 'single';
        if (!$eventDate) $eventDate = null;
        if (!$title) { setFlash('error', 'Title is required.'); header('Location: /admin/gallery.php'); exit; }

        $uploadDir = __DIR__ . '/../uploads/gallery/';
        FileHandler::ensureDir($uploadDir);
        $batchId = bin2hex(random_bytes(16));
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxSize = 5 * 1024 * 1024;
        $successCount = 0;

        // ZIP
        if ($uploadMode === 'zip' && !empty($_FILES['zip_file']['name'])) {
            if ($_FILES['zip_file']['size'] > 50 * 1024 * 1024) { setFlash('error', 'ZIP must be under 50MB.'); }
            else {
                $results = extractGalleryZip($_FILES['zip_file']['tmp_name'], $uploadDir, $compress);
                if (!$results || empty($results)) { setFlash('error', 'ZIP contains no valid images.'); }
                else {
                    $stmt = $db->prepare("INSERT INTO gallery_items (title, category, description, event_name, event_date, tags, visibility, is_featured, file_path, file_type, original_size, compressed_size, batch_id, uploaded_by, approved_by, approved_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'image', ?, ?, ?, ?, ?, NOW(), 'approved')");
                    foreach ($results as $idx => $r) {
                        $imgTitle = count($results) > 1 ? $title . ' (' . ($idx + 1) . ')' : $title;
                        $stmt->execute([$imgTitle, $category, $description, $eventName ?: null, $eventDate, $tags ?: null, $visibility, $isFeatured, $r['path'], $r['original_size'], $r['compressed_size'], $batchId, currentUserId(), currentUserId()]);
                        $successCount++;
                    }
                    auditLog('admin_upload_zip', 'gallery', 0, "ZIP batch $batchId: $successCount images");
                    setFlash('success', "$successCount image(s) uploaded and auto-approved.");
                }
            }
            header('Location: /admin/gallery.php'); exit;
        }

        // Single / Multiple
        $fileKey = ($uploadMode === 'multiple') ? 'images' : 'image';
        if (!empty($_FILES[$fileKey]['name'])) {
            $files = [];
            if (is_array($_FILES[$fileKey]['name'])) {
                for ($i = 0; $i < count($_FILES[$fileKey]['name']); $i++) {
                    if ($_FILES[$fileKey]['error'][$i] === UPLOAD_ERR_OK)
                        $files[] = ['name' => $_FILES[$fileKey]['name'][$i], 'type' => $_FILES[$fileKey]['type'][$i], 'tmp_name' => $_FILES[$fileKey]['tmp_name'][$i], 'size' => $_FILES[$fileKey]['size'][$i]];
                }
            } else {
                if ($_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) $files[] = $_FILES[$fileKey];
            }
            if (count($files) > 20) $files = array_slice($files, 0, 20);

            $stmt = $db->prepare("INSERT INTO gallery_items (title, category, description, event_name, event_date, tags, visibility, is_featured, file_path, file_type, original_size, compressed_size, batch_id, uploaded_by, approved_by, approved_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'image', ?, ?, ?, ?, ?, NOW(), 'approved')");
            foreach ($files as $idx => $file) {
                if (!in_array($file['type'], $allowed) || $file['size'] > $maxSize) continue;
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destPath = $uploadDir . $filename;
                $origSize = $file['size']; $compSize = $origSize; $finalPath = 'uploads/gallery/' . $filename;
                if ($compress) {
                    $tmpDest = $uploadDir . 'tmp_' . $filename;
                    if (FileHandler::saveUploadedFile($file['tmp_name'], $tmpDest)) {
                        $res = compressGalleryImage($tmpDest, $destPath); FileHandler::deleteFile($tmpDest);
                        if ($res) { $compSize = $res['compressed_size']; $finalPath = str_replace($uploadDir, 'uploads/gallery/', $res['path']); }
                    } else continue;
                } else {
                    if (!FileHandler::saveUploadedFile($file['tmp_name'], $destPath)) continue;
                }
                $imgTitle = count($files) > 1 ? $title . ' (' . ($idx + 1) . ')' : $title;
                $useBatch = count($files) > 1 ? $batchId : null;
                $stmt->execute([$imgTitle, $category, $description, $eventName ?: null, $eventDate, $tags ?: null, $visibility, $isFeatured, $finalPath, $origSize, $compSize, $useBatch, currentUserId(), currentUserId()]);
                $successCount++;
            }
            if ($successCount) { setFlash('success', "$successCount image(s) uploaded and auto-approved."); auditLog('admin_upload', 'gallery', 0, "$successCount images"); }
            else setFlash('error', 'No valid images uploaded.');
        }
        header('Location: /admin/gallery.php'); exit;
    }
}

// ── List items ──
$statusFilter = $_GET['status'] ?? 'pending';
$catFilter = $_GET['cat'] ?? '';
$albumFilter = (int)($_GET['album'] ?? 0);
$where = "WHERE 1=1";
$params = [];
if ($statusFilter) { $where .= " AND g.status=?"; $params[] = $statusFilter; }
if ($catFilter) { $where .= " AND g.category=?"; $params[] = $catFilter; }
if ($albumFilter) { $where .= " AND g.album_id=?"; $params[] = $albumFilter; }
$stmt = $db->prepare("SELECT g.*, u.name as uploader_name FROM gallery_items g LEFT JOIN users u ON g.uploaded_by=u.id $where ORDER BY g.created_at DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Group by batch for pending
$batches = [];
foreach ($items as $item) {
    if ($item['batch_id'] && $item['status'] === 'pending') {
        $batches[$item['batch_id']][] = $item;
    }
}

// Load categories & albums for filters
$galCategories = $db->query("SELECT * FROM gallery_categories ORDER BY sort_order")->fetchAll();
$galAlbums = $db->query("SELECT * FROM gallery_albums ORDER BY sort_order")->fetchAll();

require_once __DIR__.'/../includes/header.php';
?>

<!-- Upload Section (Collapsible) -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#adminUploadSection" style="cursor:pointer">
        <span><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>Upload Images</span>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="adminUploadSection">
        <div class="card-body">
            <!-- Upload Mode Tabs -->
            <ul class="nav nav-pills nav-fill mb-3" id="adminUploadTabs">
                <li class="nav-item"><a class="nav-link active" href="#" data-mode="single"><i class="bi bi-image me-1"></i>Single</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-mode="multiple"><i class="bi bi-images me-1"></i>Multiple</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-mode="zip"><i class="bi bi-file-zip me-1"></i>ZIP</a></li>
            </ul>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="upload_mode" id="adminUploadMode" value="single">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="general">General</option>
                            <option value="academic">Academic</option>
                            <option value="sports">Sports</option>
                            <option value="cultural">Cultural</option>
                            <option value="infrastructure">Infrastructure</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Visibility</label>
                        <select name="visibility" class="form-select">
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                </div>

                <!-- File zones -->
                <div class="mt-3">
                    <div id="adminSingleZone">
                        <div class="drop-zone" id="adminDropSingle">
                            <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                            <p class="mb-0 text-muted small">Drag & drop or click — JPG, PNG, WebP, GIF (Max 5MB)</p>
                            <input type="file" name="image" class="drop-zone-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        <div id="adminPreviewSingle" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                    <div id="adminMultiZone" class="d-none">
                        <div class="drop-zone" id="adminDropMulti">
                            <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                            <p class="mb-0 text-muted small">Select multiple images — Max 5MB each, 20 max</p>
                            <input type="file" name="images[]" class="drop-zone-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                        </div>
                        <div id="adminPreviewMulti" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                    <div id="adminZipZone" class="d-none">
                        <div class="drop-zone" id="adminDropZip">
                            <i class="bi bi-file-earmark-zip fs-2 text-muted"></i>
                            <p class="mb-0 text-muted small">ZIP containing images — Max 50MB</p>
                            <input type="file" name="zip_file" class="drop-zone-input" accept=".zip">
                        </div>
                        <div id="adminPreviewZip" class="mt-2"></div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Event Name</label>
                        <input type="text" name="event_name" class="form-control" maxlength="200">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Event Date</label>
                        <input type="date" name="event_date" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Tags</label>
                        <input type="text" name="tags" class="form-control" maxlength="500" placeholder="comma-separated">
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-8">
                        <textarea name="description" class="form-control" rows="2" placeholder="Description (optional)" maxlength="500"></textarea>
                    </div>
                    <div class="col-md-4 d-flex flex-column justify-content-center gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="compress" id="adminCompress" checked>
                            <label class="form-check-label small" for="adminCompress"><i class="bi bi-lightning-charge text-warning"></i> Compress images</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="adminFeatured">
                            <label class="form-check-label small" for="adminFeatured"><i class="bi bi-star text-warning"></i> Featured</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-upload me-1"></i>Upload & Auto-Approve</button>
            </form>
        </div>
    </div>
</div>

<!-- Status Filter -->
<ul class="nav nav-pills mb-3">
    <?php foreach(['pending'=>'warning','approved'=>'success','rejected'=>'danger',''=>'secondary'] as $s=>$c): ?>
    <li class="nav-item">
        <a href="/admin/gallery.php?status=<?=$s?>&cat=<?=urlencode($catFilter)?>&album=<?=$albumFilter?>" class="nav-link <?=$statusFilter===$s?'active':''?> btn-sm"><?=$s?ucfirst($s):'All'?></a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Category & Album Filters -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <select class="form-select form-select-sm" style="max-width:200px;" onchange="filterByCat(this.value)">
        <option value="">All Categories</option>
        <?php foreach ($galCategories as $gc): ?>
        <option value="<?= e($gc['name']) ?>" <?= strtolower($catFilter) === strtolower($gc['name']) ? 'selected' : '' ?>><?= e($gc['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" style="max-width:200px;" onchange="filterByAlbum(this.value)">
        <option value="0">All Albums</option>
        <?php foreach ($galAlbums as $ga): ?>
        <option value="<?= $ga['id'] ?>" <?= $albumFilter == $ga['id'] ? 'selected' : '' ?>><?= e($ga['title']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<script>
function filterByCat(v) { window.location = '/admin/gallery.php?status=<?= urlencode($statusFilter) ?>&cat=' + encodeURIComponent(v) + '&album=<?= $albumFilter ?>'; }
function filterByAlbum(v) { window.location = '/admin/gallery.php?status=<?= urlencode($statusFilter) ?>&cat=<?= urlencode($catFilter) ?>&album=' + v; }
</script>

<!-- Batch Approve/Reject -->
<?php if ($statusFilter === 'pending' && !empty($batches)): ?>
<div class="mb-3">
    <?php foreach ($batches as $bid => $batchItems): ?>
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="bi bi-collection me-1"></i>
                <strong>Batch:</strong> <?= count($batchItems) ?> images
                <span class="text-muted">· <?= e($batchItems[0]['uploader_name'] ?? '?') ?></span>
                <span class="text-muted">· <?= e($batchItems[0]['category']) ?></span>
            </div>
            <div class="d-flex gap-1">
                <form method="POST" class="d-inline"><input type="hidden" name="batch_id" value="<?= e($bid) ?>"><input type="hidden" name="action" value="approve_batch"><?= csrfField() ?><button class="btn btn-sm btn-success"><i class="bi bi-check-all me-1"></i>Approve All</button></form>
                <form method="POST" class="d-inline"><input type="hidden" name="batch_id" value="<?= e($bid) ?>"><input type="hidden" name="action" value="reject_batch"><?= csrfField() ?><button class="btn btn-sm btn-danger"><i class="bi bi-x-lg me-1"></i>Reject All</button></form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Gallery Grid -->
<div class="row g-3">
    <?php if (empty($items)): ?>
    <div class="col-12"><div class="card border-0 rounded-3"><div class="card-body text-center text-muted py-5">No items</div></div></div>
    <?php else: foreach ($items as $g): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 rounded-3 h-100 shadow-sm">
            <?php if ($g['file_type'] === 'image'): ?>
                <img src="/<?= e($g['file_path']) ?>" class="card-img-top" style="height:160px;object-fit:cover;border-radius:12px 12px 0 0" alt="">
            <?php else: ?>
                <div class="bg-dark text-white d-flex align-items-center justify-content-center" style="height:160px;border-radius:12px 12px 0 0"><i class="bi bi-play-circle-fill fs-1"></i></div>
            <?php endif; ?>
            <div class="card-body p-2">
                <h6 class="mb-1" style="font-size:.85rem"><?= e($g['title']) ?></h6>
                <small class="text-muted"><?= e($g['uploader_name'] ?? '?') ?> · <?= e($g['category']) ?></small>
                <?php if ($g['is_featured']): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i></span><?php endif; ?>
                <?php if ($g['visibility'] === 'private'): ?><span class="badge bg-secondary ms-1"><i class="bi bi-lock"></i></span><?php endif; ?>
                <?php if ($g['original_size'] && $g['compressed_size'] && $g['original_size'] > $g['compressed_size']):
                    $saved = round((1 - $g['compressed_size'] / $g['original_size']) * 100);
                    $origMB = number_format($g['original_size'] / 1048576, 1);
                    $compMB = number_format($g['compressed_size'] / 1048576, 1); ?>
                    <br><small class="text-success"><i class="bi bi-lightning-charge"></i> Saved <?= $saved ?>% (<?= $origMB ?>→<?= $compMB ?>MB)</small>
                <?php endif; ?>
                <?php if ($g['event_name']): ?><br><small class="text-muted"><i class="bi bi-calendar-event"></i> <?= e($g['event_name']) ?></small><?php endif; ?>
                <div class="mt-2 d-flex gap-1">
                    <?php if ($g['status'] === 'pending'): ?>
                    <form method="POST"><input type="hidden" name="id" value="<?=$g['id']?>"><input type="hidden" name="action" value="approved"><?=csrfField()?><button class="btn btn-sm btn-success py-0 px-2"><i class="bi bi-check"></i></button></form>
                    <form method="POST"><input type="hidden" name="id" value="<?=$g['id']?>"><input type="hidden" name="action" value="rejected"><?=csrfField()?><button class="btn btn-sm btn-danger py-0 px-2"><i class="bi bi-x"></i></button></form>
                    <?php endif; ?>
                    <form method="POST"><input type="hidden" name="id" value="<?=$g['id']?>"><input type="hidden" name="action" value="delete"><?=csrfField()?><button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<style>
.drop-zone{border:2px dashed #dee2e6;border-radius:12px;padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:all .3s;position:relative}
.drop-zone:hover,.drop-zone.drag-over{border-color:var(--theme-primary,#1e40af);background:rgba(30,64,175,.04)}
.drop-zone-input{position:absolute;inset:0;opacity:0;cursor:pointer}
.preview-thumb{width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #dee2e6}
</style>

<script>
// Admin upload tabs
document.querySelectorAll('#adminUploadTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('#adminUploadTabs .nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const mode = this.dataset.mode;
        document.getElementById('adminUploadMode').value = mode;
        document.getElementById('adminSingleZone').classList.toggle('d-none', mode !== 'single');
        document.getElementById('adminMultiZone').classList.toggle('d-none', mode !== 'multiple');
        document.getElementById('adminZipZone').classList.toggle('d-none', mode !== 'zip');
    });
});

// Drag-drop
document.querySelectorAll('.drop-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('drag-over');
        const input = zone.querySelector('input[type="file"]');
        if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); }
    });
});

// Previews
function showThumb(file, container) {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
        const div = document.createElement('div');
        div.innerHTML = '<img src="'+e.target.result+'" class="preview-thumb">';
        container.appendChild(div);
    };
    reader.readAsDataURL(file);
}

// Single
document.querySelector('#adminSingleZone input[type="file"]')?.addEventListener('change', function() {
    const c = document.getElementById('adminPreviewSingle'); c.innerHTML = '';
    if (this.files[0]) showThumb(this.files[0], c);
});
// Multiple
document.querySelector('#adminMultiZone input[type="file"]')?.addEventListener('change', function() {
    const c = document.getElementById('adminPreviewMulti'); c.innerHTML = '';
    for (let i = 0; i < Math.min(this.files.length, 20); i++) showThumb(this.files[i], c);
});
// ZIP
document.querySelector('#adminZipZone input[type="file"]')?.addEventListener('change', function() {
    const c = document.getElementById('adminPreviewZip'); c.innerHTML = '';
    if (this.files[0]) {
        const f = this.files[0], mb = (f.size/1048576).toFixed(1);
        c.innerHTML = '<div class="alert alert-info py-2 mb-0 small"><i class="bi bi-file-zip me-2"></i><strong>'+f.name+'</strong> ('+mb+' MB)</div>';
    }
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
