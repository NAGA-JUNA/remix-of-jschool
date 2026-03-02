<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/file-handler.php';
requireAdmin();
$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $linkUrl = trim($_POST['link_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $badgeText = trim($_POST['badge_text'] ?? '');
        $ctaText = trim($_POST['cta_text'] ?? '');
        $animationType = trim($_POST['animation_type'] ?? 'fade');
        $overlayStyle = trim($_POST['overlay_style'] ?? 'gradient-dark');
        $textPosition = trim($_POST['text_position'] ?? 'left');
        $overlayOpacity = (int)($_POST['overlay_opacity'] ?? 70);
        $overlayOpacity = max(0, min(100, $overlayOpacity));

        // Handle image upload
        $imagePath = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $result = FileHandler::uploadImage($_FILES['image'], 'slider', 'slider_', 5);
            if ($result['success']) {
                if ($imagePath) {
                    FileHandler::deleteFile(__DIR__ . '/../' . $imagePath);
                }
                $imagePath = $result['path'];
            } else {
                setFlash('error', $result['error']);
                header('Location: /admin/slider.php');
                exit;
            }
        }

        if ($action === 'add') {
            if (!$imagePath) {
                setFlash('error', 'Image is required for new slides.');
                header('Location: /admin/slider.php');
                exit;
            }
            $stmt = $db->prepare("INSERT INTO home_slider (title, subtitle, image_path, link_url, sort_order, is_active, badge_text, cta_text, animation_type, overlay_style, text_position, overlay_opacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title ?: null, $subtitle ?: null, $imagePath, $linkUrl ?: null, $sortOrder, $isActive, $badgeText ?: null, $ctaText ?: null, $animationType, $overlayStyle, $textPosition, $overlayOpacity]);
            auditLog('add_slider', 'home_slider', (int)$db->lastInsertId(), "Title: $title");
            setFlash('success', 'Slide added successfully.');
        } else {
            $stmt = $db->prepare("UPDATE home_slider SET title=?, subtitle=?, image_path=?, link_url=?, sort_order=?, is_active=?, badge_text=?, cta_text=?, animation_type=?, overlay_style=?, text_position=?, overlay_opacity=? WHERE id=?");
            $stmt->execute([$title ?: null, $subtitle ?: null, $imagePath, $linkUrl ?: null, $sortOrder, $isActive, $badgeText ?: null, $ctaText ?: null, $animationType, $overlayStyle, $textPosition, $overlayOpacity, $id]);
            auditLog('edit_slider', 'home_slider', $id, "Title: $title");
            setFlash('success', 'Slide updated successfully.');
        }
        header('Location: /admin/slider.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $slide = $db->prepare("SELECT image_path FROM home_slider WHERE id=?");
        $slide->execute([$id]);
        $slide = $slide->fetch();
        if ($slide) {
            FileHandler::deleteFile(__DIR__ . '/../' . $slide['image_path']);
            $db->prepare("DELETE FROM home_slider WHERE id=?")->execute([$id]);
            auditLog('delete_slider', 'home_slider', $id);
            setFlash('success', 'Slide deleted.');
        }
        header('Location: /admin/slider.php');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE home_slider SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        auditLog('toggle_slider', 'home_slider', $id);
        setFlash('success', 'Slide visibility toggled.');
        header('Location: /admin/slider.php');
        exit;
    }

    if ($action === 'reorder') {
        $orders = $_POST['orders'] ?? [];
        $stmt = $db->prepare("UPDATE home_slider SET sort_order=? WHERE id=?");
        foreach ($orders as $id => $order) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        setFlash('success', 'Slide order updated.');
        header('Location: /admin/slider.php');
        exit;
    }

    if ($action === 'duplicate') {
        $id = (int)($_POST['id'] ?? 0);
        $orig = $db->prepare("SELECT * FROM home_slider WHERE id=?");
        $orig->execute([$id]);
        $orig = $orig->fetch();
        if ($orig) {
            $stmt = $db->prepare("INSERT INTO home_slider (title, subtitle, image_path, link_url, sort_order, is_active, badge_text, cta_text, animation_type, overlay_style, text_position, overlay_opacity) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$orig['title'] . ' (Copy)', $orig['subtitle'], $orig['image_path'], $orig['link_url'], $orig['sort_order'] + 1, $orig['badge_text'], $orig['cta_text'], $orig['animation_type'] ?? 'fade', $orig['overlay_style'] ?? 'gradient-dark', $orig['text_position'] ?? 'left', $orig['overlay_opacity'] ?? 70]);
            auditLog('duplicate_slider', 'home_slider', (int)$db->lastInsertId());
            setFlash('success', 'Slide duplicated (set to hidden).');
        }
        header('Location: /admin/slider.php');
        exit;
    }
}

// Get editing slide
$editSlide = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM home_slider WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSlide = $stmt->fetch();
}

// Get all slides
$slides = $db->query("SELECT * FROM home_slider ORDER BY sort_order ASC, id ASC")->fetchAll();
$activeCount = count(array_filter($slides, fn($s) => $s['is_active']));

$pageTitle = 'Home Slider';
require_once __DIR__.'/../includes/header.php';
?>

<!-- Stats cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-primary"><?= count($slides) ?></div>
                <small class="text-muted">Total Slides</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-success"><?= $activeCount ?></div>
                <small class="text-muted">Active</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-secondary"><?= count($slides) - $activeCount ?></div>
                <small class="text-muted">Hidden</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-info"><i class="bi bi-aspect-ratio"></i></div>
                <small class="text-muted">1920×600px</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Add/Edit Form -->
    <div class="col-lg-5">
        <div class="card border-0 rounded-3 shadow-sm">
            <div class="card-header bg-white fw-semibold border-0 pt-3">
                <i class="bi bi-<?= $editSlide ? 'pencil-square' : 'plus-circle' ?> me-2 text-primary"></i>
                <?= $editSlide ? 'Edit Slide' : 'Add New Slide' ?>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editSlide ? 'edit' : 'add' ?>">
                    <?php if ($editSlide): ?>
                        <input type="hidden" name="id" value="<?= $editSlide['id'] ?>">
                        <input type="hidden" name="existing_image" value="<?= e($editSlide['image_path']) ?>">
                    <?php endif; ?>

                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slide Image <?= $editSlide ? '' : '<span class="text-danger">*</span>' ?></label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp" <?= $editSlide ? '' : 'required' ?>>
                        <div class="form-text">JPG/PNG/WebP, max 5MB. Recommended: 1920×600px</div>
                        <?php if ($editSlide && $editSlide['image_path']): ?>
                            <img src="/<?= e($editSlide['image_path']) ?>" class="mt-2 rounded" style="max-height:100px;max-width:100%;" alt="Current">
                        <?php endif; ?>
                    </div>

                    <!-- Badge & Title -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Badge Text</label>
                        <input type="text" name="badge_text" class="form-control" maxlength="50" placeholder="e.g. Admissions Open 2025" value="<?= e($editSlide['badge_text'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Heading</label>
                        <input type="text" name="title" class="form-control" maxlength="200" placeholder="e.g. Welcome to JNV School" value="<?= e($editSlide['title'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subtitle</label>
                        <textarea name="subtitle" class="form-control" rows="2" maxlength="500" placeholder="Short description..."><?= e($editSlide['subtitle'] ?? '') ?></textarea>
                    </div>

                    <!-- CTA -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">CTA Button Text</label>
                            <input type="text" name="cta_text" class="form-control" maxlength="50" placeholder="e.g. Apply Now" value="<?= e($editSlide['cta_text'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">CTA Link URL</label>
                            <input type="text" name="link_url" class="form-control" maxlength="255" placeholder="/public/admission-form.php" value="<?= e($editSlide['link_url'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Advanced: Animation & Overlay -->
                    <div class="card bg-light border-0 rounded-3 mb-3">
                        <div class="card-body py-3">
                            <h6 class="fw-semibold mb-3"><i class="bi bi-magic me-1"></i> Advanced Options</h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Animation Type</label>
                                    <select name="animation_type" class="form-select form-select-sm">
                                        <?php $anim = $editSlide['animation_type'] ?? 'fade'; ?>
                                        <option value="fade" <?= $anim === 'fade' ? 'selected' : '' ?>>Fade</option>
                                        <option value="slide-left" <?= $anim === 'slide-left' ? 'selected' : '' ?>>Slide Left</option>
                                        <option value="slide-up" <?= $anim === 'slide-up' ? 'selected' : '' ?>>Slide Up</option>
                                        <option value="zoom-in" <?= $anim === 'zoom-in' ? 'selected' : '' ?>>Zoom In</option>
                                        <option value="zoom-out" <?= $anim === 'zoom-out' ? 'selected' : '' ?>>Zoom Out</option>
                                        <option value="ken-burns" <?= $anim === 'ken-burns' ? 'selected' : '' ?>>Ken Burns</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Overlay Style</label>
                                    <select name="overlay_style" class="form-select form-select-sm">
                                        <?php $ov = $editSlide['overlay_style'] ?? 'gradient-dark'; ?>
                                        <option value="gradient-dark" <?= $ov === 'gradient-dark' ? 'selected' : '' ?>>Gradient Dark</option>
                                        <option value="gradient-blue" <?= $ov === 'gradient-blue' ? 'selected' : '' ?>>Gradient Blue</option>
                                        <option value="gradient-warm" <?= $ov === 'gradient-warm' ? 'selected' : '' ?>>Gradient Warm</option>
                                        <option value="solid-dark" <?= $ov === 'solid-dark' ? 'selected' : '' ?>>Solid Dark</option>
                                        <option value="none" <?= $ov === 'none' ? 'selected' : '' ?>>No Overlay</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Text Position</label>
                                    <select name="text_position" class="form-select form-select-sm">
                                        <?php $tp = $editSlide['text_position'] ?? 'left'; ?>
                                        <option value="left" <?= $tp === 'left' ? 'selected' : '' ?>>Left</option>
                                        <option value="center" <?= $tp === 'center' ? 'selected' : '' ?>>Center</option>
                                        <option value="right" <?= $tp === 'right' ? 'selected' : '' ?>>Right</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Overlay Opacity (<?= $editSlide['overlay_opacity'] ?? 70 ?>%)</label>
                                    <input type="range" name="overlay_opacity" class="form-range" min="0" max="100" step="5" value="<?= $editSlide['overlay_opacity'] ?? 70 ?>" oninput="this.previousElementSibling.textContent='Overlay Opacity ('+this.value+'%)'">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sort & Active -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= $editSlide['sort_order'] ?? count($slides) ?>" min="0">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" <?= ($editSlide['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Active (visible on homepage)</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-<?= $editSlide ? 'check-lg' : 'plus-lg' ?> me-1"></i><?= $editSlide ? 'Update Slide' : 'Add Slide' ?>
                        </button>
                        <?php if ($editSlide): ?>
                            <a href="/admin/slider.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Live Preview -->
        <?php if ($editSlide): ?>
        <div class="card border-0 rounded-3 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold border-0 pt-3"><i class="bi bi-eye me-2"></i>Live Preview</div>
            <div class="card-body p-0">
                <div style="position:relative;height:180px;background-image:url('/<?= e($editSlide['image_path']) ?>');background-size:cover;background-position:center;border-radius:0 0 12px 12px;overflow:hidden;">
                    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(15,23,42,0.85),rgba(30,64,175,0.6));display:flex;flex-direction:column;justify-content:center;padding:1.5rem;">
                        <?php if ($editSlide['badge_text']): ?>
                            <span style="display:inline-block;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.65rem;color:#fff;letter-spacing:1px;text-transform:uppercase;font-weight:600;width:fit-content;margin-bottom:0.5rem;"><?= e($editSlide['badge_text']) ?></span>
                        <?php endif; ?>
                        <h5 style="color:#fff;font-weight:800;margin:0 0 0.3rem;"><?= e($editSlide['title'] ?: '(No title)') ?></h5>
                        <?php if ($editSlide['subtitle']): ?><p style="color:rgba(255,255,255,0.85);font-size:0.8rem;margin:0;"><?= e($editSlide['subtitle']) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Slides List -->
    <div class="col-lg-7">
        <div class="card border-0 rounded-3 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-0 pt-3">
                <span class="fw-semibold"><i class="bi bi-images me-2"></i>All Slides (<?= count($slides) ?>)</span>
                <?php if (count($slides) > 1): ?>
                <form method="POST" class="d-inline" id="reorderForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reorder">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-sort-numeric-up me-1"></i>Save Order</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($slides)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-image display-4 text-muted"></i>
                        <p class="text-muted mt-2 mb-1">No slides yet</p>
                        <small class="text-muted">Add your first slide to create the homepage hero section.</small>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                    <?php foreach ($slides as $s): ?>
                        <div class="list-group-item py-3">
                            <div class="d-flex gap-3 align-items-start">
                                <!-- Sort Order -->
                                <div class="flex-shrink-0" style="width:50px;">
                                    <input type="number" name="orders[<?= $s['id'] ?>]" value="<?= $s['sort_order'] ?>" class="form-control form-control-sm text-center" form="reorderForm" min="0">
                                </div>
                                <!-- Preview Image -->
                                <div class="flex-shrink-0">
                                    <img src="/<?= e($s['image_path']) ?>" class="rounded" style="width:120px;height:68px;object-fit:cover;" alt="Slide">
                                </div>
                                <!-- Details -->
                                <div class="flex-grow-1 min-width-0">
                                    <strong class="d-block mb-1"><?= e($s['title'] ?: '(No title)') ?></strong>
                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                        <?php if ($s['badge_text']): ?><span class="badge bg-info-subtle text-info"><?= e($s['badge_text']) ?></span><?php endif; ?>
                                        <?php if ($s['cta_text']): ?><span class="badge bg-primary-subtle text-primary"><?= e($s['cta_text']) ?></span><?php endif; ?>
                                        <span class="badge bg-secondary-subtle text-secondary"><?= e($s['animation_type'] ?? 'fade') ?></span>
                                        <span class="badge bg-secondary-subtle text-secondary"><?= e($s['text_position'] ?? 'left') ?></span>
                                    </div>
                                    <?php if ($s['subtitle']): ?><small class="text-muted d-block text-truncate" style="max-width:280px;"><?= e($s['subtitle']) ?></small><?php endif; ?>
                                </div>
                                <!-- Actions -->
                                <div class="flex-shrink-0 d-flex flex-column gap-1">
                                    <div class="d-flex gap-1">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>" title="<?= $s['is_active'] ? 'Active' : 'Hidden' ?>">
                                                <i class="bi bi-<?= $s['is_active'] ? 'eye' : 'eye-slash' ?>"></i>
                                            </button>
                                        </form>
                                        <a href="/admin/slider.php?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="duplicate">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Duplicate"><i class="bi bi-copy"></i></button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this slide?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>