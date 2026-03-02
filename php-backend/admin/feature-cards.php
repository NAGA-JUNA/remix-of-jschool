<?php
$pageTitle = 'Feature Cards Manager';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $id = (int)($_POST['card_id'] ?? 0);
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'icon_class' => trim($_POST['icon_class'] ?? 'bi-star'),
        'accent_color' => trim($_POST['accent_color'] ?? 'auto'),
        'btn_text' => trim($_POST['btn_text'] ?? 'Learn More'),
        'btn_link' => trim($_POST['btn_link'] ?? '#'),
        'badge_text' => trim($_POST['badge_text'] ?? '') ?: null,
        'badge_color' => trim($_POST['badge_color'] ?? '#ef4444'),
        'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'show_stats' => isset($_POST['show_stats']) ? 1 : 0,
    ];

    if ($id > 0 && $data['title']) {
        $stmt = $db->prepare("UPDATE feature_cards SET title=?, description=?, icon_class=?, accent_color=?, btn_text=?, btn_link=?, badge_text=?, badge_color=?, is_visible=?, is_featured=?, show_stats=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([
            $data['title'], $data['description'], $data['icon_class'], $data['accent_color'],
            $data['btn_text'], $data['btn_link'], $data['badge_text'], $data['badge_color'],
            $data['is_visible'], $data['is_featured'], $data['show_stats'], $id
        ]);
        auditLog('feature_card_update', 'feature_cards', $id, 'Updated: ' . $data['title']);
        setFlash('success', 'Card updated successfully!');
    } else if ($id === 0 && $data['title']) {
        // INSERT new card
        $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM feature_cards")->fetchColumn();
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $data['title']));
        $stmt = $db->prepare("INSERT INTO feature_cards (title, description, icon_class, accent_color, btn_text, btn_link, badge_text, badge_color, is_visible, is_featured, show_stats, sort_order, slug, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $data['title'], $data['description'], $data['icon_class'], $data['accent_color'],
            $data['btn_text'], $data['btn_link'], $data['badge_text'], $data['badge_color'],
            $data['is_visible'], $data['is_featured'], $data['show_stats'], $maxOrder, $slug
        ]);
        $newId = $db->lastInsertId();
        auditLog('feature_card_create', 'feature_cards', $newId, 'Created: ' . $data['title']);
        setFlash('success', 'Card created successfully!');
    }

    // Update admission status if posted
    if (isset($_POST['admission_open'])) {
        $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='admission_open'")->execute([$_POST['admission_open']]);
    }

    header('Location: /admin/feature-cards.php');
    exit;
}

// Fetch all cards
$cards = $db->query("SELECT * FROM feature_cards ORDER BY sort_order ASC")->fetchAll();
$admissionOpen = getSetting('admission_open', '0');

// Common icons list
$iconOptions = [
    'bi-mortarboard-fill','bi-bell-fill','bi-images','bi-calendar-event-fill',
    'bi-star-fill','bi-trophy-fill','bi-book-fill','bi-people-fill',
    'bi-megaphone-fill','bi-camera-fill','bi-globe','bi-pencil-square',
    'bi-lightning-fill','bi-heart-fill','bi-shield-check','bi-award-fill',
    'bi-music-note-beamed','bi-palette-fill','bi-calculator-fill','bi-house-fill',
    'bi-envelope-fill','bi-chat-dots-fill','bi-clipboard2-data-fill','bi-graph-up-arrow',
];

include __DIR__ . '/../includes/header.php';
?>

<input type="hidden" id="csrf_token" value="<?= csrfToken() ?>">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Feature Cards Manager</h4>
        <p class="text-muted mb-0 small">Manage the glassmorphism feature cards on the home page.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary"><?= count($cards) ?> Cards</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCardModal">
            <i class="bi bi-plus-lg me-1"></i> Add Card
        </button>
        <div class="form-check form-switch ms-3">
            <input class="form-check-input" type="checkbox" id="admissionToggle" <?= $admissionOpen === '1' ? 'checked' : '' ?> onchange="toggleAdmission(this.checked)">
            <label class="form-check-label small fw-semibold" for="admissionToggle">Admissions <?= $admissionOpen === '1' ? 'Open' : 'Closed' ?></label>
        </div>
    </div>
</div>

<!-- Sortable Cards Grid -->
<div class="row g-3" id="cardsGrid">
    <?php foreach ($cards as $card): ?>
    <div class="col-md-6 col-xl-3" data-id="<?= $card['id'] ?>" draggable="true">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:1rem; <?= !$card['is_visible'] ? 'opacity:0.5;' : '' ?>">
            <div class="card-body text-center p-4">
                <!-- Quick toggles -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" title="Toggle Visibility" onclick="toggleCard(<?= $card['id'] ?>, 'toggle_visibility')">
                            <i class="bi <?= $card['is_visible'] ? 'bi-eye-fill' : 'bi-eye-slash' ?>"></i>
                        </button>
                        <button class="btn btn-sm <?= $card['is_featured'] ? 'btn-warning' : 'btn-outline-secondary' ?>" title="Toggle Featured" onclick="toggleCard(<?= $card['id'] ?>, 'toggle_featured')">
                            <i class="bi bi-star-fill"></i>
                        </button>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <span class="badge bg-light text-muted" title="Clicks"><i class="bi bi-hand-index-thumb"></i> <?= number_format($card['click_count']) ?></span>
                        <button class="btn btn-sm btn-outline-danger" title="Delete Card" onclick="deleteCard(<?= $card['id'] ?>, '<?= e(addslashes($card['title'])) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Card preview -->
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;background:<?= e($card['accent_color'] === 'auto' ? 'var(--brand-primary)' : $card['accent_color']) ?>;color:#fff;font-size:1.5rem;">
                    <i class="bi <?= e($card['icon_class']) ?>"></i>
                </div>
                <h6 class="fw-bold mb-1"><?= e($card['title']) ?></h6>
                <p class="text-muted small mb-2" style="font-size:0.8rem;"><?= e(mb_strimwidth($card['description'], 0, 60, '...')) ?></p>
                <?php if ($card['badge_text']): ?>
                    <span class="badge" style="background:<?= e($card['badge_color']) ?>;font-size:0.65rem;"><?= e($card['badge_text']) ?></span>
                <?php endif; ?>

                <!-- Edit button -->
                <div class="mt-3">
                    <button class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#editModal<?= $card['id'] ?>">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                </div>
            </div>
            <div class="card-footer bg-transparent text-center py-2 border-0">
                <small class="text-muted"><i class="bi bi-grip-horizontal"></i> Drag to reorder</small>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal<?= $card['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:1rem;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Edit: <?= e($card['title']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Title</label>
                                <input type="text" name="title" class="form-control" value="<?= e($card['title']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Icon Class</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi <?= e($card['icon_class']) ?>" id="iconPreview<?= $card['id'] ?>"></i></span>
                                    <select name="icon_class" class="form-select" onchange="document.getElementById('iconPreview<?= $card['id'] ?>').className='bi '+this.value">
                                        <?php foreach ($iconOptions as $ico): ?>
                                            <option value="<?= $ico ?>" <?= $card['icon_class'] === $ico ? 'selected' : '' ?>><?= $ico ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="2"><?= e($card['description']) ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Accent Color</label>
                                <div class="input-group">
                                    <input type="color" name="accent_color" class="form-control form-control-color" value="<?= e($card['accent_color'] === 'auto' ? '#3b82f6' : $card['accent_color']) ?>" style="width:48px;">
                                    <input type="text" class="form-control" value="<?= e($card['accent_color']) ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Button Text</label>
                                <input type="text" name="btn_text" class="form-control" value="<?= e($card['btn_text']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Button Link</label>
                                <input type="text" name="btn_link" class="form-control" value="<?= e($card['btn_link']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Badge Text</label>
                                <input type="text" name="badge_text" class="form-control" value="<?= e($card['badge_text'] ?? '') ?>" placeholder="e.g. New, Live, Open">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Badge Color</label>
                                <input type="color" name="badge_color" class="form-control form-control-color" value="<?= e($card['badge_color'] ?: '#ef4444') ?>" style="width:48px;">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="vis<?= $card['id'] ?>" <?= $card['is_visible'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="vis<?= $card['id'] ?>">Visible</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="feat<?= $card['id'] ?>" <?= $card['is_featured'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="feat<?= $card['id'] ?>">Featured</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_stats" id="stats<?= $card['id'] ?>" <?= $card['show_stats'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="stats<?= $card['id'] ?>">Stats</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add Card Modal -->
<div class="modal fade" id="addCardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:1rem;">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="card_id" value="0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Card</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Photo Gallery" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Icon Class</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-star-fill" id="iconPreviewNew"></i></span>
                                <select name="icon_class" class="form-select" onchange="document.getElementById('iconPreviewNew').className='bi '+this.value">
                                    <?php foreach ($iconOptions as $ico): ?>
                                        <option value="<?= $ico ?>" <?= $ico === 'bi-star-fill' ? 'selected' : '' ?>><?= $ico ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this feature card..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Accent Color</label>
                            <div class="input-group">
                                <input type="color" name="accent_color" class="form-control form-control-color" value="#3b82f6" style="width:48px;">
                                <input type="text" class="form-control" value="auto" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Button Text</label>
                            <input type="text" name="btn_text" class="form-control" value="Learn More">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Button Link</label>
                            <input type="text" name="btn_link" class="form-control" value="#">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Badge Text</label>
                            <input type="text" name="badge_text" class="form-control" placeholder="e.g. New, Live, Open">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Badge Color</label>
                            <input type="color" name="badge_color" class="form-control form-control-color" value="#ef4444" style="width:48px;">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" id="visNew" checked>
                                <label class="form-check-label small" for="visNew">Visible</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="featNew">
                                <label class="form-check-label small" for="featNew">Featured</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_stats" id="statsNew">
                                <label class="form-check-label small" for="statsNew">Stats</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i> Create Card</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle actions via AJAX
function toggleCard(id, action) {
    var token = document.getElementById('csrf_token').value;
    fetch('/admin/ajax/feature-card-actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=' + action + '&id=' + id + '&csrf_token=' + encodeURIComponent(token)
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) location.reload();
        else alert(data.error || 'Action failed');
    }).catch(function(){ alert('Network error'); });
}

// Delete card via AJAX
function deleteCard(id, title) {
    if (!confirm('Are you sure you want to delete "' + title + '"? This cannot be undone.')) return;
    var token = document.getElementById('csrf_token').value;
    fetch('/admin/ajax/feature-card-actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=delete_card&id=' + id + '&csrf_token=' + encodeURIComponent(token)
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.success) location.reload();
        else alert(data.error || 'Delete failed');
    }).catch(function(){ alert('Network error'); });
}

// Toggle admission status
function toggleAdmission(open) {
    var token = document.getElementById('csrf_token').value;
    fetch('/admin/feature-cards.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'card_id=0&admission_open=' + (open ? '1' : '0') + '&csrf_token=' + encodeURIComponent(token)
    }).then(function(){ location.reload(); });
}

// Drag & Drop reorder
(function(){
    var grid = document.getElementById('cardsGrid');
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
            // Save new order
            var token = document.getElementById('csrf_token').value;
            var order = Array.from(grid.querySelectorAll('[data-id]')).map(function(el){ return el.dataset.id; });
            fetch('/admin/ajax/feature-card-actions.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(order)) + '&csrf_token=' + encodeURIComponent(token)
            });
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>