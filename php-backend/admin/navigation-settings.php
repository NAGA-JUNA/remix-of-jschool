<?php
ob_start();

$debugMode = true;
if ($debugMode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$pageTitle = 'Navigation Menu';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Fallback: define getSetting/setSetting if not provided by auth.php
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}
if (!function_exists('setSetting')) {
    function setSetting(string $key, string $value): void {
        global $db;
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
}

// Auto-create table if missing
try {
    $db->exec("CREATE TABLE IF NOT EXISTS nav_menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(100) NOT NULL,
        url VARCHAR(255) NOT NULL,
        icon VARCHAR(50) DEFAULT 'bi-circle',
        link_type ENUM('internal','external') DEFAULT 'internal',
        is_cta TINYINT(1) DEFAULT 0,
        is_visible TINYINT(1) DEFAULT 1,
        parent_id INT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table may already exist
}

// Ensure parent_id column exists (migration for existing installs)
try {
    $db->exec("ALTER TABLE nav_menu_items ADD COLUMN parent_id INT DEFAULT NULL");
} catch (Exception $e) {
    // Column already exists — ignore
}

// Default menu items for reset
function getDefaultMenuItems() {
    return [
        ['label'=>'Home','url'=>'/','icon'=>'bi-house-fill','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>0],
        ['label'=>'About Us','url'=>'/public/about.php','icon'=>'bi-info-circle','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>1],
        ['label'=>'Fee Structure','url'=>'/public/fee-structure.php','icon'=>'bi-cash-stack','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>2,'_parent_label'=>'About Us'],
        ['label'=>'Join Us','url'=>'/join-us.php','icon'=>'bi-briefcase-fill','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>3,'_parent_label'=>'About Us'],
        ['label'=>'Our Teachers','url'=>'/public/teachers.php','icon'=>'bi-person-badge','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>4,'_parent_label'=>'About Us'],
        ['label'=>'Certificates','url'=>'/public/certificates.php','icon'=>'bi-award','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>5,'_parent_label'=>'About Us'],
        ['label'=>'Notifications','url'=>'/public/notifications.php','icon'=>'bi-bell','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>6],
        ['label'=>'Gallery','url'=>'/public/gallery.php','icon'=>'bi-images','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>7],
        ['label'=>'Events','url'=>'/public/events.php','icon'=>'bi-calendar-event','link_type'=>'internal','is_cta'=>0,'is_visible'=>1,'parent_id'=>null,'sort_order'=>8],
        ['label'=>'Apply Now','url'=>'/public/admission-form.php','icon'=>'bi-pencil-square','link_type'=>'internal','is_cta'=>1,'is_visible'=>1,'parent_id'=>null,'sort_order'=>9],
    ];
}

// Handle POST actions
try {
    $csrfOk = verifyCsrf();
} catch (Exception $e) {
    $csrfOk = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrfOk) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_item') {
            $label = trim($_POST['label'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $icon = trim($_POST['icon'] ?? 'bi-circle');
            $linkType = $_POST['link_type'] ?? 'internal';
            $isCta = !empty($_POST['is_cta']) ? 1 : 0;
            $isVisible = !empty($_POST['is_visible']) ? 1 : 0;
            $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if ($label && $url) {
                $stmt = $db->prepare("INSERT INTO nav_menu_items (label, url, icon, link_type, is_cta, is_visible, parent_id, sort_order) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$label, $url, $icon, $linkType, $isCta, $isVisible, $parentId, $sortOrder]);
                auditLog('nav_item_created', 'nav_menu_items', (int)$db->lastInsertId());
                setFlash('success', "Menu item \"{$label}\" added.");
            }
        }

        if ($action === 'edit_item') {
            $id = (int)($_POST['item_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $url = trim($_POST['url'] ?? '');
            if ($id && $label && $url) {
                $stmt = $db->prepare("UPDATE nav_menu_items SET label=?, url=?, icon=?, link_type=?, is_cta=?, is_visible=?, parent_id=?, sort_order=? WHERE id=?");
                $stmt->execute([
                    $label, $url,
                    trim($_POST['icon'] ?? 'bi-circle'),
                    $_POST['link_type'] ?? 'internal',
                    !empty($_POST['is_cta']) ? 1 : 0,
                    !empty($_POST['is_visible']) ? 1 : 0,
                    !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                    (int)($_POST['sort_order'] ?? 0),
                    $id
                ]);
                auditLog('nav_item_updated', 'nav_menu_items', $id);
                setFlash('success', "Menu item \"{$label}\" updated.");
            }
        }

        if ($action === 'delete_item') {
            $id = (int)($_POST['item_id'] ?? 0);
            if ($id) {
                // Also unparent any children
                $db->prepare("UPDATE nav_menu_items SET parent_id=NULL WHERE parent_id=?")->execute([$id]);
                $db->prepare("DELETE FROM nav_menu_items WHERE id=?")->execute([$id]);
                auditLog('nav_item_deleted', 'nav_menu_items', $id);
                setFlash('success', 'Menu item deleted.');
            }
        }

        if ($action === 'toggle_visibility') {
            $id = (int)($_POST['item_id'] ?? 0);
            if ($id) {
                $db->prepare("UPDATE nav_menu_items SET is_visible = NOT is_visible WHERE id=?")->execute([$id]);
                auditLog('nav_item_visibility_toggled', 'nav_menu_items', $id);
                setFlash('success', 'Visibility toggled.');
            }
        }

        if ($action === 'update_order') {
            $orders = $_POST['orders'] ?? [];
            if (is_array($orders)) {
                $stmt = $db->prepare("UPDATE nav_menu_items SET sort_order=? WHERE id=?");
                foreach ($orders as $id => $order) {
                    $stmt->execute([(int)$order, (int)$id]);
                }
                setFlash('success', 'Menu order updated.');
            }
        }

        if ($action === 'move_item') {
            $id = (int)($_POST['item_id'] ?? 0);
            $direction = $_POST['direction'] ?? '';
            if ($id && in_array($direction, ['up','down'])) {
                $currentItem = $db->prepare("SELECT * FROM nav_menu_items WHERE id=?");
                $currentItem->execute([$id]);
                $current = $currentItem->fetch(PDO::FETCH_ASSOC);
                if ($current) {
                    $parentCond = $current['parent_id'] ? "parent_id={$current['parent_id']}" : "parent_id IS NULL";
                    if ($direction === 'up') {
                        $neighbor = $db->query("SELECT * FROM nav_menu_items WHERE {$parentCond} AND sort_order < {$current['sort_order']} ORDER BY sort_order DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $neighbor = $db->query("SELECT * FROM nav_menu_items WHERE {$parentCond} AND sort_order > {$current['sort_order']} ORDER BY sort_order ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    }
                    if ($neighbor) {
                        $db->prepare("UPDATE nav_menu_items SET sort_order=? WHERE id=?")->execute([$neighbor['sort_order'], $current['id']]);
                        $db->prepare("UPDATE nav_menu_items SET sort_order=? WHERE id=?")->execute([$current['sort_order'], $neighbor['id']]);
                        setFlash('success', 'Item moved.');
                    }
                }
            }
        }

        if ($action === 'reset_defaults') {
            $db->exec("TRUNCATE TABLE nav_menu_items");
            $defaults = getDefaultMenuItems();
            $stmt = $db->prepare("INSERT INTO nav_menu_items (label, url, icon, link_type, is_cta, is_visible, parent_id, sort_order) VALUES (?,?,?,?,?,?,?,?)");
            // First pass: insert top-level items
            $parentMap = [];
            foreach ($defaults as $d) {
                if (empty($d['_parent_label'])) {
                    $stmt->execute([$d['label'], $d['url'], $d['icon'], $d['link_type'], $d['is_cta'], $d['is_visible'], null, $d['sort_order']]);
                    $parentMap[$d['label']] = (int)$db->lastInsertId();
                }
            }
            // Second pass: insert children with parent_id
            foreach ($defaults as $d) {
                if (!empty($d['_parent_label']) && isset($parentMap[$d['_parent_label']])) {
                    $stmt->execute([$d['label'], $d['url'], $d['icon'], $d['link_type'], $d['is_cta'], $d['is_visible'], $parentMap[$d['_parent_label']], $d['sort_order']]);
                }
            }
            auditLog('nav_menu_reset', 'nav_menu_items', 0);
            setFlash('success', 'Menu reset to defaults.');
        }

    } catch (Exception $e) {
        error_log("Navigation settings error: " . $e->getMessage());
        setFlash('danger', 'An error occurred: ' . $e->getMessage());
    }

    header('Location: navigation-settings.php');
    exit;
}

// Fetch all items
try {
    $allItems = $db->query("SELECT * FROM nav_menu_items ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allItems = [];
}

// Separate parents and children for display
$topLevelItems = [];
$childItems = [];
foreach ($allItems as $item) {
    if ($item['parent_id']) {
        $childItems[$item['parent_id']][] = $item;
    } else {
        $topLevelItems[] = $item;
    }
}

// Get top-level items for parent dropdown
$parentOptions = array_filter($allItems, fn($i) => !$i['parent_id'] && !$i['is_cta']);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .settings-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        padding: 1.75rem;
        margin-bottom: 1.5rem;
    }
    .settings-card:hover { box-shadow: 0 8px 32px rgba(26,86,219,0.1); }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    .menu-item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        margin-bottom: 0.5rem;
        transition: background 0.15s;
    }
    .menu-item-row:hover { background: var(--bs-tertiary-bg); }
    .menu-item-row.is-child {
        margin-left: 2rem;
        border-left: 3px solid var(--brand-primary, #1e40af);
        opacity: 0.92;
    }
    .menu-icon-preview {
        width: 32px; height: 32px; border-radius: 8px;
        background: var(--brand-primary-light, #e0e7ff);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; color: var(--brand-primary, #1e40af);
        flex-shrink: 0;
    }
    html[data-theme="dark"] .menu-icon-preview {
        background: rgba(99,102,241,0.15);
        color: #818cf8;
    }
    .info-card {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        font-size: 0.85rem;
        color: #1e40af;
    }
    html[data-theme="dark"] .info-card {
        background: rgba(30,64,175,0.1);
        border-color: rgba(59,130,246,0.2);
        color: #93c5fd;
    }
    [data-bs-theme="dark"] .settings-card { box-shadow: 0 4px 24px rgba(0,0,0,0.25); }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-list-nested me-2"></i>Navigation Menu</h4>
        <p class="text-muted mb-0" style="font-size:.85rem">Manage the public website's top navigation bar items</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="bi bi-plus-lg me-1"></i>Add Menu Item</button>
        <form method="POST" class="d-inline" onsubmit="return confirm('Reset menu to default items? This will remove all custom items.')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_defaults">
            <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Defaults</button>
        </form>
    </div>
</div>

<!-- Info Card -->
<div class="info-card">
    <i class="bi bi-info-circle me-1"></i>
    <strong>How it works:</strong> Top-level items appear in the main navbar. Items with a <strong>Parent</strong> appear as dropdown sub-items under that parent.
    The <strong>"About Us"</strong> dropdown groups Fee Structure, Join Us, Our Teachers, and Certificates by default.
    Mark an item as <strong>CTA</strong> to show it as a highlighted action button (e.g., "Apply Now").
</div>

<!-- Menu Items List -->
<div class="settings-card">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="section-title mb-0"><i class="bi bi-menu-button-wide me-2"></i>Menu Items</h6>
        <span class="badge bg-secondary-subtle text-secondary"><?= count($allItems) ?> items</span>
    </div>

    <?php if (empty($allItems)): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox text-muted" style="font-size:2rem"></i>
            <p class="text-muted mt-2 mb-0">No menu items configured. Click "Add Menu Item" or "Reset Defaults" to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($topLevelItems as $item): ?>
            <!-- Top-level item -->
            <div class="menu-item-row">
                <div class="d-flex align-items-center gap-3">
                    <div class="menu-icon-preview"><i class="bi <?= htmlspecialchars($item['icon'] ?? 'bi-circle') ?>"></i></div>
                    <div>
                        <strong><?= htmlspecialchars($item['label']) ?></strong>
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            <span class="badge bg-light text-dark border" style="font-size:.68rem"><?= htmlspecialchars($item['url']) ?></span>
                            <?php if ($item['is_cta']): ?><span class="badge bg-danger-subtle text-danger" style="font-size:.68rem">CTA</span><?php endif; ?>
                            <?php if (!$item['is_visible']): ?><span class="badge bg-warning-subtle text-warning" style="font-size:.68rem">Hidden</span><?php endif; ?>
                            <?php if ($item['link_type'] === 'external'): ?><span class="badge bg-info-subtle text-info" style="font-size:.68rem">External</span><?php endif; ?>
                            <?php if (isset($childItems[$item['id']]) && count($childItems[$item['id']]) > 0): ?>
                                <span class="badge bg-primary-subtle text-primary" style="font-size:.68rem"><?= count($childItems[$item['id']]) ?> sub-items</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-1 align-items-center">
                    <!-- Move buttons -->
                    <form method="POST" class="d-inline"><input type="hidden" name="action" value="move_item"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input type="hidden" name="direction" value="up"><?= csrfField() ?><button type="submit" class="btn btn-outline-secondary btn-sm" title="Move up"><i class="bi bi-arrow-up"></i></button></form>
                    <form method="POST" class="d-inline"><input type="hidden" name="action" value="move_item"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input type="hidden" name="direction" value="down"><?= csrfField() ?><button type="submit" class="btn btn-outline-secondary btn-sm" title="Move down"><i class="bi bi-arrow-down"></i></button></form>
                    <!-- Toggle visibility -->
                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="action" value="toggle_visibility"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><button type="submit" class="btn btn-outline-<?= $item['is_visible'] ? 'success' : 'warning' ?> btn-sm" title="<?= $item['is_visible'] ? 'Hide' : 'Show' ?>"><i class="bi bi-<?= $item['is_visible'] ? 'eye' : 'eye-slash' ?>"></i></button></form>
                    <!-- Edit -->
                    <button class="btn btn-outline-primary btn-sm" onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                    <!-- Delete -->
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this menu item?')"><?= csrfField() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
            <!-- Children of this item -->
            <?php if (isset($childItems[$item['id']])): ?>
                <?php foreach ($childItems[$item['id']] as $child): ?>
                <div class="menu-item-row is-child">
                    <div class="d-flex align-items-center gap-3">
                        <div class="menu-icon-preview"><i class="bi <?= htmlspecialchars($child['icon'] ?? 'bi-circle') ?>"></i></div>
                        <div>
                            <strong><?= htmlspecialchars($child['label']) ?></strong>
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <span class="badge bg-light text-dark border" style="font-size:.68rem"><?= htmlspecialchars($child['url']) ?></span>
                                <?php if (!$child['is_visible']): ?><span class="badge bg-warning-subtle text-warning" style="font-size:.68rem">Hidden</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <form method="POST" class="d-inline"><input type="hidden" name="action" value="move_item"><input type="hidden" name="item_id" value="<?= $child['id'] ?>"><input type="hidden" name="direction" value="up"><?= csrfField() ?><button type="submit" class="btn btn-outline-secondary btn-sm" title="Move up"><i class="bi bi-arrow-up"></i></button></form>
                        <form method="POST" class="d-inline"><input type="hidden" name="action" value="move_item"><input type="hidden" name="item_id" value="<?= $child['id'] ?>"><input type="hidden" name="direction" value="down"><?= csrfField() ?><button type="submit" class="btn btn-outline-secondary btn-sm" title="Move down"><i class="bi bi-arrow-down"></i></button></form>
                        <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="action" value="toggle_visibility"><input type="hidden" name="item_id" value="<?= $child['id'] ?>"><button type="submit" class="btn btn-outline-<?= $child['is_visible'] ? 'success' : 'warning' ?> btn-sm" title="<?= $child['is_visible'] ? 'Hide' : 'Show' ?>"><i class="bi bi-<?= $child['is_visible'] ? 'eye' : 'eye-slash' ?>"></i></button></form>
                        <button class="btn btn-outline-primary btn-sm" onclick="editItem(<?= htmlspecialchars(json_encode($child)) ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this menu item?')"><?= csrfField() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?= $child['id'] ?>"><button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Menu Preview Card -->
<div class="settings-card">
    <h6 class="section-title"><i class="bi bi-eye me-2"></i>Navbar Preview</h6>
    <div style="background:#0f172a;border-radius:12px;padding:1rem 1.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
        <span style="color:#fff;font-weight:700;font-size:0.9rem;">LOGO</span>
        <span style="color:rgba(255,255,255,0.3);">|</span>
        <?php foreach ($topLevelItems as $item): ?>
            <?php if ($item['is_cta']) continue; ?>
            <?php if (!$item['is_visible']) continue; ?>
            <span style="color:rgba(255,255,255,0.8);font-size:0.82rem;font-weight:500;">
                <?= htmlspecialchars($item['label']) ?>
                <?php if (isset($childItems[$item['id']])): ?>
                    <i class="bi bi-chevron-down" style="font-size:0.6rem;margin-left:2px;"></i>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
        <span style="margin-left:auto;display:flex;gap:0.5rem;align-items:center;">
            <span style="color:rgba(255,255,255,0.6);font-size:0.8rem;border:1px solid rgba(255,255,255,0.3);padding:3px 12px;border-radius:50px;">Login</span>
            <?php foreach ($allItems as $item): if ($item['is_cta'] && $item['is_visible']): ?>
                <span style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;padding:4px 14px;border-radius:50px;font-size:0.8rem;font-weight:600;"><?= htmlspecialchars($item['label']) ?></span>
            <?php endif; endforeach; ?>
        </span>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_item">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Menu Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold">Label *</label>
                            <input type="text" name="label" class="form-control" required maxlength="100" placeholder="e.g. About Us">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">URL *</label>
                            <input type="text" name="url" class="form-control" required maxlength="255" placeholder="/public/about.php">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Icon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-circle" id="addIconPreview"></i></span>
                                <input type="text" name="icon" class="form-control" value="bi-circle" maxlength="50" placeholder="bi-house-fill" oninput="document.getElementById('addIconPreview').className='bi '+this.value">
                            </div>
                            <small class="text-muted">Bootstrap Icons class (e.g. bi-house-fill)</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Link Type</label>
                            <select name="link_type" class="form-select">
                                <option value="internal">Internal</option>
                                <option value="external">External (new tab)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Parent Item</label>
                            <select name="parent_id" class="form-select">
                                <option value="">— None (Top Level) —</option>
                                <?php foreach ($parentOptions as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Select a parent to make this a dropdown sub-item</small>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" value="1" checked id="addVisible">
                                <label class="form-check-label fw-semibold" for="addVisible">Visible</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_cta" value="1" id="addCta">
                                <label class="form-check-label fw-semibold" for="addCta">CTA Button</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="item_id" id="editItemId">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1"></i>Edit Menu Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold">Label *</label>
                            <input type="text" name="label" id="editLabel" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" name="sort_order" id="editSortOrder" class="form-control" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">URL *</label>
                            <input type="text" name="url" id="editUrl" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Icon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-circle" id="editIconPreview"></i></span>
                                <input type="text" name="icon" id="editIcon" class="form-control" maxlength="50" oninput="document.getElementById('editIconPreview').className='bi '+this.value">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Link Type</label>
                            <select name="link_type" id="editLinkType" class="form-select">
                                <option value="internal">Internal</option>
                                <option value="external">External (new tab)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Parent Item</label>
                            <select name="parent_id" id="editParentId" class="form-select">
                                <option value="">— None (Top Level) —</option>
                                <?php foreach ($parentOptions as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="editVisible">
                                <label class="form-check-label fw-semibold" for="editVisible">Visible</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_cta" value="1" id="editCta">
                                <label class="form-check-label fw-semibold" for="editCta">CTA Button</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editItem(item) {
    document.getElementById('editItemId').value = item.id;
    document.getElementById('editLabel').value = item.label;
    document.getElementById('editUrl').value = item.url;
    document.getElementById('editIcon').value = item.icon || 'bi-circle';
    document.getElementById('editIconPreview').className = 'bi ' + (item.icon || 'bi-circle');
    document.getElementById('editLinkType').value = item.link_type || 'internal';
    document.getElementById('editSortOrder').value = item.sort_order || 0;
    document.getElementById('editParentId').value = item.parent_id || '';
    document.getElementById('editVisible').checked = item.is_visible == 1;
    document.getElementById('editCta').checked = item.is_cta == 1;
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>