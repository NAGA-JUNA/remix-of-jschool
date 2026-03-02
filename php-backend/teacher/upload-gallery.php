<?php
require_once __DIR__.'/../includes/auth.php';
requireTeacher();
require_once __DIR__.'/../includes/file-handler.php';
$db = getDB();
$uid = currentUserId();

// ── Image compression helper ──
function compressGalleryImage($sourcePath, $destPath, $maxWidth = 1600) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;
    $origSize = filesize($sourcePath);
    $mime = $info['mime'];
    $w = $info[0]; $h = $info[1];

    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $img = imagecreatefrompng($sourcePath); break;
        case 'image/webp': $img = imagecreatefromwebp($sourcePath); break;
        case 'image/gif':  $img = imagecreatefromgif($sourcePath); break;
        default: return false;
    }
    if (!$img) return false;

    if ($w > $maxWidth) {
        $newW = $maxWidth;
        $newH = (int)round($h * ($maxWidth / $w));
        $resized = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    // Try WebP first, fallback to JPEG
    if (function_exists('imagewebp')) {
        $destPath = preg_replace('/\.[^.]+$/', '.webp', $destPath);
        imagewebp($img, $destPath, 80);
    } else {
        $destPath = preg_replace('/\.[^.]+$/', '.jpg', $destPath);
        imagejpeg($img, $destPath, 80);
    }
    imagedestroy($img);
    $compSize = filesize($destPath);
    return ['path' => $destPath, 'original_size' => $origSize, 'compressed_size' => $compSize];
}

// ── ZIP extraction helper ──
function extractGalleryZip($zipPath, $uploadDir, $compress, $maxWidth = 1600) {
    $allowedExt = ['jpg','jpeg','png','webp','gif'];
    $results = [];
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        // skip macOS resource forks
        if (strpos($entry, '__MACOSX') !== false || strpos($entry, '._') === 0) continue;

        $tmpFile = tempnam(sys_get_temp_dir(), 'gzip_');
        file_put_contents($tmpFile, $zip->getFromIndex($i));
        $origSize = filesize($tmpFile);

        $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if ($compress) {
            $res = compressGalleryImage($tmpFile, $destPath, $maxWidth);
            unlink($tmpFile);
            if ($res) {
                $results[] = ['path' => str_replace($uploadDir, 'uploads/gallery/', $res['path']),
                              'original_size' => $res['original_size'], 'compressed_size' => $res['compressed_size']];
            }
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
    $title       = trim($_POST['title'] ?? '');
    $category    = $_POST['category'] ?? 'general';
    $description = trim($_POST['description'] ?? '');
    $eventName   = trim($_POST['event_name'] ?? '');
    $eventDate   = $_POST['event_date'] ?? null;
    $tags        = trim($_POST['tags'] ?? '');
    $visibility  = in_array($_POST['visibility'] ?? '', ['public','private']) ? $_POST['visibility'] : 'public';
    $compress    = isset($_POST['compress']);
    $uploadMode  = $_POST['upload_mode'] ?? 'single';

    if (!$eventDate) $eventDate = null;

    // Video upload (unchanged)
    if ($category === 'videos') {
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');
        if ($title && $youtubeUrl) {
            preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtubeUrl, $m);
            $ytId = $m[1] ?? '';
            if ($ytId) {
                $stmt = $db->prepare("INSERT INTO gallery_items (title, category, description, event_name, event_date, tags, visibility, file_path, file_type, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'video', ?, 'pending')");
                $stmt->execute([$title, $category, $description, $eventName ?: null, $eventDate, $tags ?: null, $visibility, $youtubeUrl, $uid]);
                auditLog('upload_gallery', 'gallery_item', (int)$db->lastInsertId(), "Video: $title");
                setFlash('success', 'Video submitted for approval.');
            } else {
                setFlash('error', 'Invalid YouTube URL.');
            }
        } else {
            setFlash('error', 'Title and YouTube URL are required.');
        }
        header('Location: /teacher/upload-gallery.php');
        exit;
    }

    if (!$title) { setFlash('error', 'Title is required.'); header('Location: /teacher/upload-gallery.php'); exit; }

    $uploadDir = __DIR__ . '/../uploads/gallery/';
    FileHandler::ensureDir($uploadDir);
    $batchId = bin2hex(random_bytes(16));
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $maxSize = 5 * 1024 * 1024;
    $successCount = 0;
    $failCount = 0;

    // ── ZIP Upload ──
    if ($uploadMode === 'zip' && !empty($_FILES['zip_file']['name'])) {
        $zipFile = $_FILES['zip_file'];
        $maxZip = 50 * 1024 * 1024;
        if ($zipFile['size'] > $maxZip) {
            setFlash('error', 'ZIP file must be under 50MB.');
        } elseif ($zipFile['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'ZIP upload failed.');
        } else {
            $results = extractGalleryZip($zipFile['tmp_name'], $uploadDir, $compress);
            if ($results === false || empty($results)) {
                setFlash('error', 'ZIP contains no valid images.');
            } else {
                $stmt = $db->prepare("INSERT INTO gallery_items (title, category, description, event_name, event_date, tags, visibility, file_path, file_type, original_size, compressed_size, batch_id, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'image', ?, ?, ?, ?, 'pending')");
                foreach ($results as $idx => $r) {
                    $imgTitle = count($results) > 1 ? $title . ' (' . ($idx + 1) . ')' : $title;
                    $stmt->execute([$imgTitle, $category, $description, $eventName ?: null, $eventDate, $tags ?: null, $visibility, $r['path'], $r['original_size'], $r['compressed_size'], $batchId, $uid]);
                    $successCount++;
                }
                auditLog('upload_gallery_zip', 'gallery_item', 0, "ZIP batch $batchId: $successCount images");
                setFlash('success', "$successCount image(s) extracted from ZIP and submitted for approval.");
            }
        }
        header('Location: /teacher/upload-gallery.php');
        exit;
    }

    // ── Single / Multiple Upload ──
    $fileKey = ($uploadMode === 'multiple') ? 'images' : 'image';
    if (!empty($_FILES[$fileKey]['name'])) {
        // Normalize to array
        $files = [];
        if (is_array($_FILES[$fileKey]['name'])) {
            for ($i = 0; $i < count($_FILES[$fileKey]['name']); $i++) {
                if ($_FILES[$fileKey]['error'][$i] === UPLOAD_ERR_OK) {
                    $files[] = ['name' => $_FILES[$fileKey]['name'][$i], 'type' => $_FILES[$fileKey]['type'][$i],
                                'tmp_name' => $_FILES[$fileKey]['tmp_name'][$i], 'size' => $_FILES[$fileKey]['size'][$i]];
                }
            }
        } else {
            if ($_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $files[] = $_FILES[$fileKey];
            }
        }

        if (count($files) > 20) { $files = array_slice($files, 0, 20); }

        $stmt = $db->prepare("INSERT INTO gallery_items (title, category, description, event_name, event_date, tags, visibility, file_path, file_type, original_size, compressed_size, batch_id, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'image', ?, ?, ?, ?, 'pending')");

        foreach ($files as $idx => $file) {
            if (!in_array($file['type'], $allowed)) { $failCount++; continue; }
            if ($file['size'] > $maxSize) { $failCount++; continue; }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDir . $filename;
            $origSize = $file['size'];
            $compSize = $origSize;
            $finalPath = 'uploads/gallery/' . $filename;

            if ($compress) {
                // Move to temp then compress
                $tmpDest = $uploadDir . 'tmp_' . $filename;
                if (FileHandler::saveUploadedFile($file['tmp_name'], $tmpDest)) {
                    $res = compressGalleryImage($tmpDest, $destPath);
                    FileHandler::deleteFile($tmpDest);
                    if ($res) {
                        $compSize = $res['compressed_size'];
                        $finalPath = str_replace($uploadDir, 'uploads/gallery/', $res['path']);
                    } else {
                        rename($tmpDest, $destPath);
                    }
                } else { $failCount++; continue; }
            } else {
                if (!FileHandler::saveUploadedFile($file['tmp_name'], $destPath)) { $failCount++; continue; }
            }

            $imgTitle = count($files) > 1 ? $title . ' (' . ($idx + 1) . ')' : $title;
            $useBatch = count($files) > 1 ? $batchId : null;
            $stmt->execute([$imgTitle, $category, $description, $eventName ?: null, $eventDate, $tags ?: null, $visibility, $finalPath, $origSize, $compSize, $useBatch, $uid]);
            $successCount++;
        }

        if ($successCount > 0) {
            auditLog('upload_gallery', 'gallery_item', 0, "Batch: $successCount images");
            setFlash('success', "$successCount image(s) submitted for approval." . ($failCount ? " $failCount skipped." : ''));
        } else {
            setFlash('error', 'No valid images uploaded.' . ($failCount ? " $failCount file(s) rejected." : ''));
        }
    } else {
        setFlash('error', 'Please select at least one image.');
    }

    header('Location: /teacher/upload-gallery.php');
    exit;
}

// ── My Uploads ──
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $db->prepare("SELECT COUNT(*) FROM gallery_items WHERE uploaded_by=?"); $total->execute([$uid]); $total = $total->fetchColumn();
$p = paginate($total, 12, $page);
$items = $db->prepare("SELECT id, title, category, file_path, file_type, status, batch_id, original_size, compressed_size, created_at FROM gallery_items WHERE uploaded_by=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$items->execute([$uid, $p['per_page'], $p['offset']]);
$items = $items->fetchAll();

$pageTitle = 'Upload to Gallery';
require_once __DIR__.'/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-camera-fill me-2 text-warning"></i>Upload New</div>
            <div class="card-body">
                <!-- Upload Mode Tabs -->
                <ul class="nav nav-pills nav-fill mb-3" id="uploadTabs">
                    <li class="nav-item"><a class="nav-link active" href="#" data-mode="single"><i class="bi bi-image me-1"></i>Single</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-mode="multiple"><i class="bi bi-images me-1"></i>Multiple</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-mode="zip"><i class="bi bi-file-zip me-1"></i>ZIP</a></li>
                </ul>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="upload_mode" id="uploadModeInput" value="single">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select" id="categorySelect" onchange="toggleUploadType()">
                            <option value="general">General</option>
                            <?php
                            $galCats = $db->query("SELECT * FROM gallery_categories WHERE status='active' ORDER BY sort_order")->fetchAll();
                            foreach ($galCats as $gc): ?>
                            <option value="<?= e(strtolower($gc['name'])) ?>"><?= e($gc['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="videos">Videos (YouTube)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Album <small class="text-muted">(optional)</small></label>
                        <select name="album_id" class="form-select" id="albumSelect">
                            <option value="">— No Album —</option>
                            <?php
                            $galAlbums = $db->query("SELECT a.*, c.name as cat_name FROM gallery_albums a JOIN gallery_categories c ON a.category_id=c.id WHERE a.status='active' ORDER BY c.sort_order, a.sort_order")->fetchAll();
                            foreach ($galAlbums as $ga): ?>
                            <option value="<?= $ga['id'] ?>" data-cat="<?= e(strtolower($ga['cat_name'])) ?>"><?= e($ga['cat_name']) ?> → <?= e($ga['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Video input (shown only for videos category) -->
                    <div id="videoUpload" class="mb-3 d-none">
                        <label class="form-label fw-semibold">YouTube URL <span class="text-danger">*</span></label>
                        <input type="url" name="youtube_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                    </div>

                    <!-- Image upload zones -->
                    <div id="imageUploadSection">
                        <!-- Single Image -->
                        <div id="singleZone" class="mb-3">
                            <label class="form-label fw-semibold">Image <span class="text-danger">*</span></label>
                            <div class="drop-zone" id="dropSingle">
                                <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                                <p class="mb-1 text-muted">Drag & drop or click to browse</p>
                                <small class="text-muted">JPG, PNG, WebP, GIF — Max 5MB</small>
                                <input type="file" name="image" class="drop-zone-input" accept="image/jpeg,image/png,image/webp,image/gif">
                            </div>
                            <div id="previewSingle" class="d-flex flex-wrap gap-2 mt-2"></div>
                        </div>

                        <!-- Multiple Images -->
                        <div id="multiZone" class="mb-3 d-none">
                            <label class="form-label fw-semibold">Images (up to 20) <span class="text-danger">*</span></label>
                            <div class="drop-zone" id="dropMulti">
                                <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                                <p class="mb-1 text-muted">Drag & drop or click to browse</p>
                                <small class="text-muted">Select multiple — Max 5MB each, 20 max</small>
                                <input type="file" name="images[]" class="drop-zone-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                            </div>
                            <div id="previewMulti" class="d-flex flex-wrap gap-2 mt-2"></div>
                        </div>

                        <!-- ZIP Upload -->
                        <div id="zipZone" class="mb-3 d-none">
                            <label class="form-label fw-semibold">ZIP File <span class="text-danger">*</span></label>
                            <div class="drop-zone" id="dropZip">
                                <i class="bi bi-file-earmark-zip fs-1 text-muted"></i>
                                <p class="mb-1 text-muted">Drag & drop or click to browse</p>
                                <small class="text-muted">ZIP containing images — Max 50MB</small>
                                <input type="file" name="zip_file" class="drop-zone-input" accept=".zip">
                            </div>
                            <div id="previewZip" class="mt-2"></div>
                        </div>

                        <!-- Compression toggle -->
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="compress" id="compressCheck" checked>
                            <label class="form-check-label" for="compressCheck">
                                <i class="bi bi-lightning-charge text-warning"></i> Compress images for faster loading <span class="badge bg-success">Recommended</span>
                            </label>
                            <div class="form-text">Resizes to max 1600px width, converts to WebP format</div>
                        </div>
                    </div>

                    <!-- Metadata fields -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Event Name</label>
                            <input type="text" name="event_name" class="form-control" maxlength="200" placeholder="e.g. Annual Day 2026">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Event Date</label>
                            <input type="date" name="event_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tags</label>
                        <input type="text" name="tags" class="form-control" maxlength="500" placeholder="e.g. sports, cricket, winners">
                        <div class="form-text">Comma-separated</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Visibility</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visibility" value="public" id="visPublic" checked>
                                <label class="form-check-label" for="visPublic">Public</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visibility" value="private" id="visPrivate">
                                <label class="form-check-label" for="visPrivate">Private</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="500"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Submit for Approval</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-images me-2"></i>My Uploads (<?= $total ?>)</div>
            <div class="card-body">
                <?php if (empty($items)): ?>
                    <p class="text-muted mb-0">No uploads yet.</p>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($items as $item): ?>
                    <div class="col-sm-6 col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <?php if ($item['file_type'] === 'image'): ?>
                                <img src="/<?= e($item['file_path']) ?>" class="card-img-top" style="height:120px;object-fit:cover;" alt="<?= e($item['title']) ?>">
                            <?php else: ?>
                                <div class="bg-dark text-white d-flex align-items-center justify-content-center" style="height:120px;"><i class="bi bi-play-circle-fill fs-1"></i></div>
                            <?php endif; ?>
                            <div class="card-body p-2">
                                <small class="fw-semibold d-block text-truncate"><?= e($item['title']) ?></small>
                                <?php $sc = match($item['status']) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }; ?>
                                <span class="badge bg-<?= $sc ?> mt-1"><?= e(ucfirst($item['status'])) ?></span>
                                <?php if ($item['batch_id']): ?><span class="badge bg-info mt-1"><i class="bi bi-collection"></i> Batch</span><?php endif; ?>
                                <?php if ($item['original_size'] && $item['compressed_size'] && $item['original_size'] > $item['compressed_size']):
                                    $saved = round((1 - $item['compressed_size'] / $item['original_size']) * 100); ?>
                                    <span class="badge bg-success mt-1">-<?= $saved ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($p['total_pages'] > 1): ?>
                <div class="card-footer bg-white"><?= paginationHtml($p, '/teacher/upload-gallery.php') ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.drop-zone {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}
.drop-zone:hover, .drop-zone.drag-over {
    border-color: var(--theme-primary, #1e40af);
    background: rgba(30,64,175,0.04);
}
.drop-zone-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}
.preview-thumb {
    width: 80px; height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #dee2e6;
    position: relative;
}
.preview-item {
    position: relative;
    display: inline-block;
}
.preview-item .remove-btn {
    position: absolute; top: -6px; right: -6px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #dc3545; color: #fff; border: none;
    font-size: 10px; line-height: 20px; text-align: center;
    cursor: pointer; padding: 0;
}
</style>

<script>
// Tab switching
document.querySelectorAll('#uploadTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('#uploadTabs .nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const mode = this.dataset.mode;
        document.getElementById('uploadModeInput').value = mode;
        document.getElementById('singleZone').classList.toggle('d-none', mode !== 'single');
        document.getElementById('multiZone').classList.toggle('d-none', mode !== 'multiple');
        document.getElementById('zipZone').classList.toggle('d-none', mode !== 'zip');
    });
});

// Category toggle
function toggleUploadType() {
    const cat = document.getElementById('categorySelect').value;
    document.getElementById('imageUploadSection').classList.toggle('d-none', cat === 'videos');
    document.getElementById('videoUpload').classList.toggle('d-none', cat !== 'videos');
}

// Drag and drop
document.querySelectorAll('.drop-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const input = zone.querySelector('input[type="file"]');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
});

// Preview: Single
document.querySelector('#singleZone input[type="file"]').addEventListener('change', function() {
    const container = document.getElementById('previewSingle');
    container.innerHTML = '';
    if (this.files[0]) showThumb(this.files[0], container);
});

// Preview: Multiple
document.querySelector('#multiZone input[type="file"]').addEventListener('change', function() {
    const container = document.getElementById('previewMulti');
    container.innerHTML = '';
    const max = Math.min(this.files.length, 20);
    for (let i = 0; i < max; i++) showThumb(this.files[i], container);
    if (this.files.length > 20) container.innerHTML += '<small class="text-danger d-block w-100 mt-1">Max 20 images. Extra files will be ignored.</small>';
});

// Preview: ZIP
document.querySelector('#zipZone input[type="file"]').addEventListener('change', function() {
    const container = document.getElementById('previewZip');
    container.innerHTML = '';
    if (this.files[0]) {
        const f = this.files[0];
        const sizeMB = (f.size / 1024 / 1024).toFixed(1);
        container.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="bi bi-file-zip me-2"></i><strong>' +
            f.name + '</strong> (' + sizeMB + ' MB) — Will be extracted on upload</div>';
    }
});

function showThumb(file, container) {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
        const div = document.createElement('div');
        div.className = 'preview-item';
        div.innerHTML = '<img src="' + e.target.result + '" class="preview-thumb"><small class="d-block text-truncate" style="max-width:80px;font-size:10px">' +
            file.name + '</small>';
        container.appendChild(div);
    };
    reader.readAsDataURL(file);
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
