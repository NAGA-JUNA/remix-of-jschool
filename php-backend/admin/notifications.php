<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// --- Auto-expire old notifications ---
$db->exec("UPDATE notifications SET status='expired' WHERE status IN ('approved','published') AND expires_at IS NOT NULL AND expires_at < CURDATE()");

// --- Fetch all users for "posted by" filter ---
$allPosters = $db->query("SELECT DISTINCT u.id, u.name FROM users u INNER JOIN notifications n ON n.posted_by=u.id ORDER BY u.name")->fetchAll();

// --- POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $nid = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $status = isSuperAdmin() || isAdmin() ? ($_POST['save_as'] ?? 'approved') : 'pending';
        $stmt = $db->prepare("INSERT INTO notifications (title, content, type, priority, category, tags, target_audience, target_class, target_section, is_public, schedule_at, expires_at, show_popup, show_banner, show_marquee, show_dashboard, status, posted_by, approved_by, approved_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            trim($_POST['title']), trim($_POST['content']), $_POST['type'] ?? 'general',
            $_POST['priority'] ?? 'normal', $_POST['category'] ?? 'general', trim($_POST['tags'] ?? ''),
            $_POST['target_audience'] ?? 'all',
            $_POST['target_class'] ?? null, $_POST['target_section'] ?? null,
            isset($_POST['is_public']) ? 1 : 0,
            $_POST['schedule_at'] ?: null, $_POST['expires_at'] ?: null,
            isset($_POST['show_popup']) ? 1 : 0, isset($_POST['show_banner']) ? 1 : 0,
            isset($_POST['show_marquee']) ? 1 : 0, isset($_POST['show_dashboard']) ? 1 : 0,
            $status, currentUserId(), $status === 'approved' ? currentUserId() : null
        ]);
        $newId = (int)$db->lastInsertId();
        // Handle multi-attachments
        if (!empty($_FILES['attachments'])) {
            $allowedExts = ['pdf','doc','docx','jpg','jpeg','png','gif','zip','xlsx','pptx'];
            $maxSize = 10 * 1024 * 1024;
            $files = $_FILES['attachments'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts) || $files['size'][$i] > $maxSize) continue;
                $saveName = 'notif_' . $newId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/documents/' . $saveName;
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $ftype = in_array($ext, ['jpg','jpeg','png','gif']) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'document');
                    $db->prepare("INSERT INTO notification_attachments (notification_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?)")
                        ->execute([$newId, $files['name'][$i], $saveName, $ftype, $files['size'][$i], currentUserId()]);
                }
            }
        }
        auditLog('create_notification', 'notification', $newId);
        setFlash('success', 'Notification created (' . ucfirst($status) . ').');
    } elseif ($nid) {
        switch ($action) {
            case 'approve':
                $db->prepare("UPDATE notifications SET status='approved', approved_by=?, approved_at=NOW(), is_public=1 WHERE id=?")->execute([currentUserId(), $nid]);
                auditLog('approve_notification', 'notification', $nid);
                setFlash('success', 'Notification approved.');
                break;
            case 'publish':
                $db->prepare("UPDATE notifications SET status='published', approved_by=?, approved_at=NOW(), is_public=1 WHERE id=?")->execute([currentUserId(), $nid]);
                auditLog('publish_notification', 'notification', $nid);
                setFlash('success', 'Notification published.');
                break;
            case 'reject':
                $reason = trim($_POST['reject_reason'] ?? '');
                $db->prepare("UPDATE notifications SET status='rejected', reject_reason=?, approved_by=?, approved_at=NOW(), is_public=0 WHERE id=?")->execute([$reason, currentUserId(), $nid]);
                auditLog('reject_notification', 'notification', $nid);
                setFlash('success', 'Notification rejected.');
                break;
            case 'delete':
                $db->prepare("UPDATE notifications SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")->execute([currentUserId(), $nid]);
                auditLog('delete_notification', 'notification', $nid);
                setFlash('success', 'Notification deleted.');
                break;
            case 'pin':
                $db->prepare("UPDATE notifications SET is_pinned=1 WHERE id=?")->execute([$nid]);
                auditLog('pin_notification', 'notification', $nid);
                setFlash('success', 'Pinned.');
                break;
            case 'unpin':
                $db->prepare("UPDATE notifications SET is_pinned=0 WHERE id=?")->execute([$nid]);
                auditLog('unpin_notification', 'notification', $nid);
                setFlash('success', 'Unpinned.');
                break;
            case 'edit':
                // Save version before edit
                $old = $db->prepare("SELECT * FROM notifications WHERE id=?");
                $old->execute([$nid]);
                $old = $old->fetch();
                if ($old) {
                    $db->prepare("INSERT INTO notification_versions (notification_id, title, content, type, priority, target_audience, category, tags, changed_by) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$nid, $old['title'], $old['content'], $old['type'], $old['priority'], $old['target_audience'], $old['category'] ?? '', $old['tags'] ?? '', currentUserId()]);
                }
                $db->prepare("UPDATE notifications SET title=?, content=?, type=?, priority=?, category=?, tags=?, target_audience=?, target_class=?, target_section=?, is_public=?, schedule_at=?, expires_at=?, show_popup=?, show_banner=?, show_marquee=?, show_dashboard=? WHERE id=?")->execute([
                    trim($_POST['title']), trim($_POST['content']), $_POST['type'] ?? 'general',
                    $_POST['priority'] ?? 'normal', $_POST['category'] ?? 'general', trim($_POST['tags'] ?? ''),
                    $_POST['target_audience'] ?? 'all',
                    $_POST['target_class'] ?? null, $_POST['target_section'] ?? null,
                    isset($_POST['is_public']) ? 1 : 0,
                    $_POST['schedule_at'] ?: null, $_POST['expires_at'] ?: null,
                    isset($_POST['show_popup']) ? 1 : 0, isset($_POST['show_banner']) ? 1 : 0,
                    isset($_POST['show_marquee']) ? 1 : 0, isset($_POST['show_dashboard']) ? 1 : 0,
                    $nid
                ]);
                auditLog('edit_notification', 'notification', $nid);
                setFlash('success', 'Notification updated. Previous version saved.');
                break;
            case 'bulk_approve':
                $ids = array_map('intval', $_POST['ids'] ?? []);
                if ($ids) { $ph = implode(',', array_fill(0, count($ids), '?')); $db->prepare("UPDATE notifications SET status='approved', approved_by=?, approved_at=NOW(), is_public=1 WHERE id IN ($ph)")->execute(array_merge([currentUserId()], $ids)); auditLog('bulk_approve', 'notification', 0, implode(',', $ids)); setFlash('success', count($ids).' approved.'); }
                break;
            case 'bulk_reject':
                $ids = array_map('intval', $_POST['ids'] ?? []);
                if ($ids) { $ph = implode(',', array_fill(0, count($ids), '?')); $db->prepare("UPDATE notifications SET status='rejected', approved_by=?, approved_at=NOW(), is_public=0 WHERE id IN ($ph)")->execute(array_merge([currentUserId()], $ids)); auditLog('bulk_reject', 'notification', 0, implode(',', $ids)); setFlash('success', count($ids).' rejected.'); }
                break;
            case 'bulk_delete':
                $ids = array_map('intval', $_POST['ids'] ?? []);
                if ($ids) { $ph = implode(',', array_fill(0, count($ids), '?')); $db->prepare("UPDATE notifications SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id IN ($ph)")->execute(array_merge([currentUserId()], $ids)); auditLog('bulk_delete', 'notification', 0, implode(',', $ids)); setFlash('success', count($ids).' deleted.'); }
                break;
            case 'bulk_pin':
                $ids = array_map('intval', $_POST['ids'] ?? []);
                if ($ids) { $ph = implode(',', array_fill(0, count($ids), '?')); $db->prepare("UPDATE notifications SET is_pinned=1 WHERE id IN ($ph)")->execute($ids); setFlash('success', count($ids).' pinned.'); }
                break;
        }
    }
    header('Location: /admin/notifications.php?status=' . ($_GET['status'] ?? $_POST['redirect_status'] ?? 'pending'));
    exit;
}

// --- Export CSV ---
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $statusFilter = $_GET['status'] ?? '';
    $where = "WHERE n.is_deleted=0"; $params = [];
    if ($statusFilter && in_array($statusFilter, ['draft','pending','approved','published','expired','rejected'])) { $where .= " AND n.status=?"; $params[] = $statusFilter; }
    $rows = $db->prepare("SELECT n.id, n.title, n.type, n.priority, n.category, n.tags, n.target_audience, n.status, n.view_count, n.created_at, u.name as posted_by, a.name as approved_by_name, n.approved_at FROM notifications n LEFT JOIN users u ON n.posted_by=u.id LEFT JOIN users a ON n.approved_by=a.id $where ORDER BY n.created_at DESC");
    $rows->execute($params); $rows = $rows->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="notifications_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Title','Type','Priority','Category','Tags','Target','Status','Views','Posted By','Created','Approved By','Approved At']);
    foreach ($rows as $r) { fputcsv($out, [$r['id'],$r['title'],$r['type'],$r['priority'],$r['category']??'',$r['tags']??'',$r['target_audience'],$r['status'],$r['view_count'],$r['posted_by'],$r['created_at'],$r['approved_by_name'],$r['approved_at']]); }
    fclose($out); exit;
}

// --- Fetch Data with Advanced Filters ---
$statusFilter = $_GET['status'] ?? 'pending';
$search = trim($_GET['q'] ?? '');
$filterType = $_GET['filter_type'] ?? '';
$filterPriority = $_GET['filter_priority'] ?? '';
$filterCategory = $_GET['filter_category'] ?? '';
$filterTarget = $_GET['filter_target'] ?? '';
$filterVisibility = $_GET['filter_visibility'] ?? '';
$filterPostedBy = $_GET['filter_posted_by'] ?? '';
$filterDateFrom = $_GET['filter_from'] ?? '';
$filterDateTo = $_GET['filter_to'] ?? '';

$where = "WHERE n.is_deleted=0"; $params = [];
if ($statusFilter === 'pinned') { $where .= " AND n.is_pinned=1"; }
elseif ($statusFilter && in_array($statusFilter, ['draft','pending','approved','published','expired','rejected'])) { $where .= " AND n.status=?"; $params[] = $statusFilter; }
if ($search) { $where .= " AND (n.title LIKE ? OR n.content LIKE ? OR n.tags LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterType) { $where .= " AND n.type=?"; $params[] = $filterType; }
if ($filterPriority) { $where .= " AND n.priority=?"; $params[] = $filterPriority; }
if ($filterCategory) { $where .= " AND n.category=?"; $params[] = $filterCategory; }
if ($filterTarget) { $where .= " AND n.target_audience=?"; $params[] = $filterTarget; }
if ($filterVisibility === 'popup') { $where .= " AND n.show_popup=1"; }
elseif ($filterVisibility === 'banner') { $where .= " AND n.show_banner=1"; }
elseif ($filterVisibility === 'marquee') { $where .= " AND n.show_marquee=1"; }
elseif ($filterVisibility === 'dashboard') { $where .= " AND n.show_dashboard=1"; }
elseif ($filterVisibility === 'public') { $where .= " AND n.is_public=1"; }
if ($filterPostedBy) { $where .= " AND n.posted_by=?"; $params[] = (int)$filterPostedBy; }
if ($filterDateFrom) { $where .= " AND n.created_at >= ?"; $params[] = "$filterDateFrom 00:00:00"; }
if ($filterDateTo) { $where .= " AND n.created_at <= ?"; $params[] = "$filterDateTo 23:59:59"; }

// Counts
$counts = [];
foreach (['draft','pending','approved','published','expired','rejected'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM notifications WHERE is_deleted=0 AND status=?"); $c->execute([$s]); $counts[$s] = $c->fetchColumn();
}
$pinnedCount = $db->query("SELECT COUNT(*) FROM notifications WHERE is_deleted=0 AND is_pinned=1")->fetchColumn();
$allCount = $db->query("SELECT COUNT(*) FROM notifications WHERE is_deleted=0")->fetchColumn();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$countStmt = $db->prepare("SELECT COUNT(*) FROM notifications n $where"); $countStmt->execute($params); $totalFiltered = $countStmt->fetchColumn();
$p = paginate($totalFiltered, 20, $page);

$stmt = $db->prepare("SELECT n.*, u.name as poster_name, u.role as poster_role, a.name as approver_name FROM notifications n LEFT JOIN users u ON n.posted_by=u.id LEFT JOIN users a ON n.approved_by=a.id $where ORDER BY n.is_pinned DESC, n.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$p['per_page'], $p['offset']]));
$notifications = $stmt->fetchAll();

// Fetch categories list for filter
$categories = $db->query("SELECT DISTINCT category FROM notifications WHERE is_deleted=0 AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';

$priorityColors = ['normal'=>'secondary','important'=>'warning','urgent'=>'danger'];
$statusColors = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','published'=>'primary','expired'=>'dark','rejected'=>'danger'];
$tabs = [
    'pending' => ['label'=>'Pending','icon'=>'bi-hourglass-split','color'=>'warning','count'=>$counts['pending']],
    'draft' => ['label'=>'Draft','icon'=>'bi-file-earmark','color'=>'secondary','count'=>$counts['draft']],
    'approved' => ['label'=>'Approved','icon'=>'bi-check-circle','color'=>'success','count'=>$counts['approved']],
    'published' => ['label'=>'Published','icon'=>'bi-globe','color'=>'primary','count'=>$counts['published']],
    'expired' => ['label'=>'Expired','icon'=>'bi-clock-history','color'=>'dark','count'=>$counts['expired']],
    'rejected' => ['label'=>'Rejected','icon'=>'bi-x-circle','color'=>'danger','count'=>$counts['rejected']],
    'pinned' => ['label'=>'Pinned','icon'=>'bi-pin-fill','color'=>'info','count'=>$pinnedCount],
    '' => ['label'=>'All','icon'=>'bi-collection','color'=>'secondary','count'=>$allCount],
];

// Build filter query string for pagination
$filterParams = array_filter(['status'=>$statusFilter,'q'=>$search,'filter_type'=>$filterType,'filter_priority'=>$filterPriority,'filter_category'=>$filterCategory,'filter_target'=>$filterTarget,'filter_visibility'=>$filterVisibility,'filter_posted_by'=>$filterPostedBy,'filter_from'=>$filterDateFrom,'filter_to'=>$filterDateTo]);
$filterQS = http_build_query($filterParams);
?>

<style>
.notif-filter-bar{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:16px;margin-bottom:12px}
.notif-filter-bar.collapsed .filter-body{display:none}
.floating-bulk-bar{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:10px 20px;box-shadow:0 8px 32px rgba(0,0,0,.15);z-index:1050;display:none;align-items:center;gap:10px}
.floating-bulk-bar.show{display:flex}
.status-badge{font-size:.68rem;padding:3px 10px;border-radius:20px;font-weight:600;letter-spacing:.3px}
.priority-dot{width:8px;height:8px;border-radius:50%;display:inline-block}
.priority-dot.urgent{background:#ef4444}.priority-dot.important{background:#f59e0b}.priority-dot.normal{background:#94a3b8}
.col-toggle-menu{min-width:200px;padding:8px}
.col-toggle-menu label{font-size:.8rem;padding:4px 8px;display:flex;align-items:center;gap:6px;cursor:pointer;border-radius:6px}
.col-toggle-menu label:hover{background:var(--sidebar-hover)}
.preview-drawer{position:fixed;top:0;right:-480px;width:480px;height:100vh;background:var(--bg-card);border-left:1px solid var(--border-color);box-shadow:-4px 0 24px rgba(0,0,0,.1);z-index:1055;transition:right .3s ease;overflow-y:auto}
.preview-drawer.open{right:0}
.preview-drawer .drawer-header{padding:16px 20px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--bg-card);z-index:2}
.preview-drawer .drawer-body{padding:20px}
.preview-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.3);z-index:1054;display:none}
.preview-overlay.show{display:block}
.tag-chip{display:inline-block;background:var(--brand-primary-light);color:var(--brand-primary);font-size:.68rem;padding:2px 8px;border-radius:12px;margin:1px 2px;font-weight:500}
.saved-filter-item{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:6px;font-size:.8rem;cursor:pointer}
.saved-filter-item:hover{background:var(--sidebar-hover)}
</style>

<!-- Header Actions -->
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1"></i>Create New</button>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item small" href="/admin/notifications.php?action=export&status=<?= e($statusFilter) ?>"><i class="bi bi-filetype-csv me-2"></i>Export CSV</a></li>
                <li><a class="dropdown-item small" href="#" onclick="exportPDF();return false;"><i class="bi bi-filetype-pdf me-2"></i>Export PDF</a></li>
            </ul>
        </div>
        <!-- Column Toggle -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-layout-three-columns me-1"></i>Columns</button>
            <div class="dropdown-menu col-toggle-menu shadow-sm" onclick="event.stopPropagation()">
                <?php $cols = ['cb'=>'','title'=>'Title','priority'=>'Priority','type'=>'Type','category'=>'Category','tags'=>'Tags','poster'=>'Posted By','target'=>'Target','visibility'=>'Visibility','status'=>'Status','views'=>'Views','date'=>'Date','actions'=>'Actions']; ?>
                <?php foreach ($cols as $key => $label): if ($key === 'cb') continue; ?>
                <label><input type="checkbox" class="form-check-input col-toggle-check" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <!-- Saved Filters -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-bookmark me-1"></i>Saved Views</button>
            <div class="dropdown-menu shadow-sm p-2" style="min-width:220px" id="savedFiltersMenu">
                <div id="savedFiltersList"></div>
                <hr class="my-1">
                <button class="btn btn-sm btn-outline-primary w-100" onclick="saveCurrentFilter()"><i class="bi bi-plus me-1"></i>Save Current View</button>
            </div>
        </div>
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title, content, tags..." value="<?= e($search) ?>" style="width:220px;">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
</div>

<!-- Advanced Filter Bar -->
<div class="notif-filter-bar" id="filterBar">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0" style="font-size:.85rem"><i class="bi bi-funnel me-1"></i>Advanced Filters</h6>
        <button class="btn btn-sm btn-link text-muted p-0" onclick="toggleFilterBar()"><i class="bi bi-chevron-up" id="filterToggleIcon"></i></button>
    </div>
    <div class="filter-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Date From</label>
                <input type="date" name="filter_from" class="form-control form-control-sm" value="<?= e($filterDateFrom) ?>">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Date To</label>
                <input type="date" name="filter_to" class="form-control form-control-sm" value="<?= e($filterDateTo) ?>">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Type</label>
                <select name="filter_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach(['general','academic','exam','event','holiday','urgent'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Priority</label>
                <select name="filter_priority" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="normal" <?= $filterPriority==='normal'?'selected':'' ?>>Normal</option>
                    <option value="important" <?= $filterPriority==='important'?'selected':'' ?>>Important</option>
                    <option value="urgent" <?= $filterPriority==='urgent'?'selected':'' ?>>Urgent</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Category</label>
                <select name="filter_category" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $filterCategory===$cat?'selected':'' ?>><?= ucfirst(e($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Target</label>
                <select name="filter_target" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach(['all','students','teachers','parents','class','section'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterTarget===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Visibility</label>
                <select name="filter_visibility" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="public" <?= $filterVisibility==='public'?'selected':'' ?>>Public</option>
                    <option value="popup" <?= $filterVisibility==='popup'?'selected':'' ?>>Popup</option>
                    <option value="banner" <?= $filterVisibility==='banner'?'selected':'' ?>>Banner</option>
                    <option value="marquee" <?= $filterVisibility==='marquee'?'selected':'' ?>>Marquee</option>
                    <option value="dashboard" <?= $filterVisibility==='dashboard'?'selected':'' ?>>Dashboard</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label mb-0" style="font-size:.7rem">Posted By</label>
                <select name="filter_posted_by" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($allPosters as $poster): ?>
                    <option value="<?= $poster['id'] ?>" <?= $filterPostedBy==$poster['id']?'selected':'' ?>><?= e($poster['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6 d-flex gap-1">
                <button class="btn btn-sm btn-dark flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="/admin/notifications.php?status=<?= e($statusFilter) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Status Tabs -->
<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <?php foreach ($tabs as $key => $tab): ?>
    <li class="nav-item">
        <a href="/admin/notifications.php?status=<?= $key ?>" class="nav-link <?= $statusFilter===$key?'active':'' ?> btn-sm px-3 py-1" style="font-size:.82rem;">
            <i class="bi <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
            <span class="badge bg-<?= $tab['color'] ?>-subtle text-<?= $tab['color'] ?> ms-1"><?= $tab['count'] ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Table -->
<div class="card border-0 rounded-3 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" id="notifTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px" data-col="cb"><input type="checkbox" class="form-check-input" id="checkAll"></th>
                        <th data-col="title">Title</th>
                        <th data-col="priority">Priority</th>
                        <th data-col="type">Type</th>
                        <th data-col="category">Category</th>
                        <th data-col="tags">Tags</th>
                        <th data-col="poster">Posted By</th>
                        <th data-col="target">Target</th>
                        <th data-col="visibility" class="text-center">Visibility</th>
                        <th data-col="status">Status</th>
                        <th data-col="views">Views</th>
                        <th data-col="date">Date</th>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($notifications)): ?>
                    <tr><td colspan="13" class="text-center text-muted py-4"><i class="bi bi-inbox display-6 d-block mb-2"></i>No notifications found</td></tr>
                <?php else: foreach ($notifications as $n): ?>
                    <tr>
                        <td data-col="cb"><input type="checkbox" class="form-check-input row-check" value="<?= $n['id'] ?>"></td>
                        <td data-col="title" style="max-width:220px">
                            <div class="d-flex align-items-center gap-1">
                                <?php if ($n['is_pinned']): ?><i class="bi bi-pin-fill text-primary" title="Pinned"></i><?php endif; ?>
                                <a href="#" onclick="openPreview(<?= $n['id'] ?>);return false;" class="text-truncate text-decoration-none" style="font-size:.85rem;color:var(--text-primary)"><?= e($n['title']) ?></a>
                            </div>
                        </td>
                        <td data-col="priority"><span class="priority-dot <?= $n['priority'] ?? 'normal' ?>" title="<?= ucfirst($n['priority'] ?? 'normal') ?>"></span> <span style="font-size:.75rem"><?= ucfirst($n['priority'] ?? 'normal') ?></span></td>
                        <td data-col="type"><span class="badge bg-light text-dark" style="font-size:.7rem"><?= ucfirst(e($n['type'])) ?></span></td>
                        <td data-col="category"><span style="font-size:.78rem"><?= ucfirst(e($n['category'] ?? 'general')) ?></span></td>
                        <td data-col="tags" style="max-width:120px">
                            <?php if (!empty($n['tags'])): foreach(array_slice(explode(',', $n['tags']), 0, 3) as $tag): ?>
                                <span class="tag-chip"><?= e(trim($tag)) ?></span>
                            <?php endforeach; endif; ?>
                        </td>
                        <td data-col="poster" style="font-size:.8rem">
                            <?= e($n['poster_name'] ?? 'System') ?>
                            <span class="badge bg-info-subtle text-info" style="font-size:.55rem"><?= ucfirst(str_replace('_',' ',$n['poster_role'] ?? '')) ?></span>
                        </td>
                        <td data-col="target"><span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem"><?= ucfirst($n['target_audience'] ?? 'all') ?></span></td>
                        <td data-col="visibility" class="text-center">
                            <?php
                            $vis = [];
                            if ($n['show_popup'] ?? 0) $vis[] = '<i class="bi bi-window-stack text-primary" title="Popup"></i>';
                            if ($n['show_banner'] ?? 0) $vis[] = '<i class="bi bi-flag-fill text-success" title="Banner"></i>';
                            if ($n['show_marquee'] ?? 0) $vis[] = '<i class="bi bi-broadcast text-warning" title="Marquee"></i>';
                            if ($n['show_dashboard'] ?? 0) $vis[] = '<i class="bi bi-grid-fill text-info" title="Dashboard"></i>';
                            echo $vis ? implode(' ', $vis) : '<span class="text-muted">—</span>';
                            ?>
                        </td>
                        <td data-col="status"><span class="status-badge badge bg-<?= $statusColors[$n['status']] ?? 'secondary' ?>-subtle text-<?= $statusColors[$n['status']] ?? 'secondary' ?>"><?= ucfirst($n['status']) ?></span></td>
                        <td data-col="views" style="font-size:.8rem">
                            <a href="#" onclick="showAnalytics(<?= $n['id'] ?>);return false;" class="text-decoration-none" title="View analytics"><?= $n['view_count'] ?? 0 ?> <i class="bi bi-graph-up-arrow text-muted" style="font-size:.65rem"></i></a>
                        </td>
                        <td data-col="date" style="font-size:.75rem"><?= date('d M Y', strtotime($n['created_at'])) ?></td>
                        <td data-col="actions">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="z-index:1060;min-width:180px">
                                    <li><a class="dropdown-item small" href="#" onclick="openPreview(<?= $n['id'] ?>);return false;"><i class="bi bi-eye me-2"></i>Preview</a></li>
                                    <li><a class="dropdown-item small" href="#" onclick="editNotif(<?= $n['id'] ?>);return false;"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item small" href="#" onclick="showVersions(<?= $n['id'] ?>);return false;"><i class="bi bi-clock-history me-2"></i>Version History</a></li>
                                    <li><a class="dropdown-item small" href="#" onclick="showAnalytics(<?= $n['id'] ?>);return false;"><i class="bi bi-graph-up me-2"></i>Analytics</a></li>
                                    <li><a class="dropdown-item small" href="#" onclick="showWhatsApp(<?= $n['id'] ?>);return false;"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Share</a></li>
                                    <?php if (in_array($n['status'], ['pending','draft'])): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" class="d-inline"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="approve"><?= csrfField() ?><button class="dropdown-item small text-success"><i class="bi bi-check-lg me-2"></i>Approve</button></form></li>
                                        <li><form method="POST" class="d-inline"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="publish"><?= csrfField() ?><button class="dropdown-item small text-primary"><i class="bi bi-globe me-2"></i>Publish</button></form></li>
                                        <li><a class="dropdown-item small text-danger" href="#" onclick="rejectNotif(<?= $n['id'] ?>);return false;"><i class="bi bi-x-lg me-2"></i>Reject</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if ($n['is_pinned']): ?>
                                        <li><form method="POST"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="unpin"><?= csrfField() ?><button class="dropdown-item small"><i class="bi bi-pin-angle me-2"></i>Unpin</button></form></li>
                                    <?php else: ?>
                                        <li><form method="POST"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="pin"><?= csrfField() ?><button class="dropdown-item small"><i class="bi bi-pin-fill me-2"></i>Pin</button></form></li>
                                    <?php endif; ?>
                                    <li><form method="POST" onsubmit="return confirm('Delete?')"><input type="hidden" name="id" value="<?= $n['id'] ?>"><input type="hidden" name="action" value="delete"><?= csrfField() ?><button class="dropdown-item small text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($p['total_pages'] > 1): ?>
        <div class="card-footer bg-white"><?= paginationHtml($p, '/admin/notifications.php?' . $filterQS) ?></div>
    <?php endif; ?>
</div>

<!-- Floating Bulk Action Bar -->
<div class="floating-bulk-bar" id="floatingBulk">
    <span class="text-muted small fw-semibold"><i class="bi bi-check2-square me-1"></i><span id="selectedCount">0</span> selected</span>
    <div class="vr"></div>
    <button class="btn btn-sm btn-outline-success" onclick="bulkAction('bulk_approve')" title="Approve"><i class="bi bi-check-all me-1"></i>Approve</button>
    <button class="btn btn-sm btn-outline-danger" onclick="bulkAction('bulk_reject')" title="Reject"><i class="bi bi-x-lg me-1"></i>Reject</button>
    <button class="btn btn-sm btn-outline-secondary" onclick="bulkAction('bulk_delete')" title="Delete"><i class="bi bi-trash me-1"></i>Delete</button>
    <button class="btn btn-sm btn-outline-info" onclick="bulkAction('bulk_pin')" title="Pin"><i class="bi bi-pin me-1"></i>Pin</button>
    <div class="vr"></div>
    <button class="btn btn-sm btn-outline-secondary" onclick="bulkExportCSV()" title="Export selected CSV"><i class="bi bi-filetype-csv"></i></button>
    <button class="btn btn-sm btn-outline-success" onclick="bulkWhatsApp()" title="Copy WhatsApp"><i class="bi bi-whatsapp"></i></button>
</div>

<!-- Preview Drawer Overlay -->
<div class="preview-overlay" id="previewOverlay" onclick="closePreview()"></div>

<!-- Right-side Preview Drawer -->
<div class="preview-drawer" id="previewDrawer">
    <div class="drawer-header">
        <h6 class="fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Preview</h6>
        <div class="d-flex gap-2 align-items-center">
            <ul class="nav nav-pills nav-sm" id="previewTabs" style="font-size:.75rem">
                <li class="nav-item"><a class="nav-link active py-1 px-2" data-preview="student" href="#" onclick="switchPreviewTab('student');return false;">Student</a></li>
                <li class="nav-item"><a class="nav-link py-1 px-2" data-preview="teacher" href="#" onclick="switchPreviewTab('teacher');return false;">Teacher</a></li>
            </ul>
            <button class="btn btn-sm btn-light border-0" onclick="closePreview()"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <div class="drawer-body" id="previewBody"></div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" id="rejectId">
        <div class="modal-header bg-danger-subtle"><h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject Notification</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><label class="form-label fw-semibold">Reason</label><textarea name="reject_reason" class="form-control" rows="3" required></textarea></div>
        <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger btn-sm">Reject</button></div>
    </form>
</div></div></div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header bg-primary-subtle"><h5 class="modal-title text-primary"><i class="bi bi-plus-circle me-2"></i>Create Notification</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12"><label class="form-label fw-semibold">Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required maxlength="200"></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Type</label><select name="type" class="form-select"><option value="general">General</option><option value="academic">Academic</option><option value="exam">Exam</option><option value="event">Event</option><option value="holiday">Holiday</option><option value="urgent">Urgent</option></select></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Priority</label><select name="priority" class="form-select"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></select></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Category</label><select name="category" class="form-select"><option value="general">General</option><option value="academic">Academic</option><option value="administrative">Administrative</option><option value="sports">Sports</option><option value="cultural">Cultural</option><option value="exam">Exam</option><option value="holiday">Holiday</option></select></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Target</label><select name="target_audience" class="form-select" onchange="toggleTarget(this)"><option value="all">All</option><option value="students">Students</option><option value="teachers">Teachers</option><option value="parents">Parents</option><option value="class">Class</option><option value="section">Section</option></select></div>
                <div class="col-md-6 d-none target-class-field"><label class="form-label fw-semibold">Class</label><input type="text" name="target_class" class="form-control" placeholder="e.g. 10"></div>
                <div class="col-md-6 d-none target-section-field"><label class="form-label fw-semibold">Section</label><input type="text" name="target_section" class="form-control" placeholder="e.g. A"></div>
                <div class="col-12"><label class="form-label fw-semibold">Tags <span class="text-muted fw-normal">(comma-separated)</span></label><input type="text" name="tags" class="form-control" placeholder="e.g. exam, result, 2025"></div>
                <div class="col-12"><label class="form-label fw-semibold">Content <span class="text-danger">*</span></label><textarea name="content" class="form-control" rows="5" required></textarea></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Schedule Date/Time</label><input type="datetime-local" name="schedule_at" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Expiry Date</label><input type="date" name="expires_at" class="form-control"></div>
                <div class="col-12"><label class="form-label fw-semibold">Attachments <span class="text-muted fw-normal">(max 10MB each)</span></label><input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.xlsx,.pptx"></div>
                <div class="col-12">
                    <label class="form-label fw-semibold d-block">Visibility</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" id="cPublic" value="1" checked><label class="form-check-label" for="cPublic">Public</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_popup" id="cPopup" value="1"><label class="form-check-label" for="cPopup">Popup</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_banner" id="cBanner" value="1"><label class="form-check-label" for="cBanner">Banner</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_marquee" id="cMarquee" value="1"><label class="form-check-label" for="cMarquee">Marquee</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_dashboard" id="cDash" value="1"><label class="form-check-label" for="cDash">Dashboard</label></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-outline-secondary btn-sm" name="save_as" value="draft"><i class="bi bi-file-earmark me-1"></i>Save as Draft</button>
            <button class="btn btn-primary btn-sm" name="save_as" value="approved"><i class="bi bi-send me-1"></i>Create & Publish</button>
        </div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-header bg-warning-subtle"><h5 class="modal-title text-warning"><i class="bi bi-pencil-square me-2"></i>Edit Notification</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12"><label class="form-label fw-semibold">Title</label><input type="text" name="title" id="editTitle" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Type</label><select name="type" id="editType" class="form-select"><option value="general">General</option><option value="academic">Academic</option><option value="exam">Exam</option><option value="event">Event</option><option value="holiday">Holiday</option><option value="urgent">Urgent</option></select></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Priority</label><select name="priority" id="editPriority" class="form-select"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></select></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Category</label><select name="category" id="editCategory" class="form-select"><option value="general">General</option><option value="academic">Academic</option><option value="administrative">Administrative</option><option value="sports">Sports</option><option value="cultural">Cultural</option><option value="exam">Exam</option><option value="holiday">Holiday</option></select></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Target</label><select name="target_audience" id="editTarget" class="form-select" onchange="toggleTarget(this)"><option value="all">All</option><option value="students">Students</option><option value="teachers">Teachers</option><option value="parents">Parents</option><option value="class">Class</option><option value="section">Section</option></select></div>
                <div class="col-md-6 d-none target-class-field"><label class="form-label fw-semibold">Class</label><input type="text" name="target_class" id="editTargetClass" class="form-control"></div>
                <div class="col-md-6 d-none target-section-field"><label class="form-label fw-semibold">Section</label><input type="text" name="target_section" id="editTargetSection" class="form-control"></div>
                <div class="col-12"><label class="form-label fw-semibold">Tags</label><input type="text" name="tags" id="editTags" class="form-control" placeholder="comma-separated"></div>
                <div class="col-12"><label class="form-label fw-semibold">Content</label><textarea name="content" id="editContent" class="form-control" rows="5" required></textarea></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Schedule</label><input type="datetime-local" name="schedule_at" id="editSchedule" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Expiry</label><input type="date" name="expires_at" id="editExpiry" class="form-control"></div>
                <div class="col-12" id="editAttachmentsArea"></div>
                <div class="col-12">
                    <label class="form-label fw-semibold d-block">Visibility</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" id="ePublic" value="1"><label class="form-check-label" for="ePublic">Public</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_popup" id="ePopup" value="1"><label class="form-check-label" for="ePopup">Popup</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_banner" id="eBanner" value="1"><label class="form-check-label" for="eBanner">Banner</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_marquee" id="eMarquee" value="1"><label class="form-check-label" for="eMarquee">Marquee</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="show_dashboard" id="eDash" value="1"><label class="form-check-label" for="eDash">Dashboard</label></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button></div>
    </form>
</div></div></div>

<!-- Version History Modal -->
<div class="modal fade" id="versionModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Version History</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="versionBody"><div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>
    <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-graph-up me-2"></i>Engagement Analytics</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="analyticsBody"><div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>
    <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<!-- WhatsApp Modal -->
<div class="modal fade" id="whatsappModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-success-subtle"><h5 class="modal-title text-success"><i class="bi bi-whatsapp me-2"></i>WhatsApp Share</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label fw-semibold">Message Template</label>
        <textarea class="form-control" id="whatsappText" rows="8" readonly></textarea>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-success flex-fill" onclick="copyWhatsApp()"><i class="bi bi-clipboard me-1"></i>Copy to Clipboard</button>
            <a href="#" id="whatsappLink" target="_blank" class="btn btn-sm btn-outline-success flex-fill"><i class="bi bi-whatsapp me-1"></i>Open WhatsApp</a>
        </div>
    </div>
</div></div></div>

<!-- jsPDF CDN for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<script>
const notifsData = <?= json_encode(array_map(function($n) {
    return [
        'id'=>$n['id'],'title'=>$n['title'],'content'=>$n['content'],'type'=>$n['type'],
        'priority'=>$n['priority']??'normal','target_audience'=>$n['target_audience']??'all',
        'target_class'=>$n['target_class']??'','target_section'=>$n['target_section']??'',
        'category'=>$n['category']??'general','tags'=>$n['tags']??'',
        'status'=>$n['status'],'poster_name'=>$n['poster_name']??'System',
        'poster_role'=>$n['poster_role']??'','approver_name'=>$n['approver_name']??'',
        'approved_at'=>$n['approved_at']??'','reject_reason'=>$n['reject_reason']??'',
        'is_public'=>$n['is_public'],'is_pinned'=>$n['is_pinned'],
        'show_popup'=>$n['show_popup']??0,'show_banner'=>$n['show_banner']??0,
        'show_marquee'=>$n['show_marquee']??0,'show_dashboard'=>$n['show_dashboard']??0,
        'schedule_at'=>$n['schedule_at']??'','expires_at'=>$n['expires_at']??'',
        'view_count'=>$n['view_count']??0,'created_at'=>$n['created_at'],
        'attachment'=>$n['attachment']??''
    ];
}, $notifications)) ?>;

const csrfToken = '<?= csrfToken() ?>';
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

// ─── Preview Drawer ───
function openPreview(id) {
    const n = notifsData.find(x=>x.id==id);
    if (!n) return;
    renderPreview(n, 'student');
    document.getElementById('previewDrawer').classList.add('open');
    document.getElementById('previewOverlay').classList.add('show');
    window._previewNotif = n;
}
function closePreview() {
    document.getElementById('previewDrawer').classList.remove('open');
    document.getElementById('previewOverlay').classList.remove('show');
}
function switchPreviewTab(tab) {
    document.querySelectorAll('#previewTabs .nav-link').forEach(a=>a.classList.toggle('active', a.dataset.preview===tab));
    if (window._previewNotif) renderPreview(window._previewNotif, tab);
}
function renderPreview(n, view) {
    const pColors = {'normal':'#94a3b8','important':'#f59e0b','urgent':'#ef4444'};
    const sColors = {'draft':'#6b7280','pending':'#f59e0b','approved':'#22c55e','published':'#3b82f6','expired':'#1f2937','rejected':'#ef4444'};
    let tags = n.tags ? n.tags.split(',').map(t=>`<span class="tag-chip">${esc(t.trim())}</span>`).join('') : '';
    let html = `
        <div style="border-left:4px solid ${pColors[n.priority]};padding:16px;background:${view==='student'?'#f0f9ff':'#fdf4ff'};border-radius:8px;margin-bottom:16px">
            <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:${view==='student'?'#3b82f6':'#a855f7'};font-weight:600;margin-bottom:4px">
                ${view==='student'?'📚 Student View':'👩‍🏫 Teacher View'}
            </div>
            <h5 style="margin:0 0 8px;font-weight:700">${esc(n.title)}</h5>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
                <span style="font-size:.7rem;padding:2px 8px;border-radius:12px;background:${pColors[n.priority]}22;color:${pColors[n.priority]};font-weight:600">${n.priority}</span>
                <span style="font-size:.7rem;padding:2px 8px;border-radius:12px;background:#f1f5f9;color:#475569">${n.type}</span>
                <span style="font-size:.7rem;padding:2px 8px;border-radius:12px;background:${sColors[n.status]}22;color:${sColors[n.status]};font-weight:600">${n.status}</span>
            </div>
            <div style="white-space:pre-wrap;font-size:.88rem;line-height:1.6;color:#334155;margin-bottom:12px">${esc(n.content)}</div>
            ${tags ? `<div style="margin-bottom:8px">${tags}</div>` : ''}
            <div style="font-size:.75rem;color:#94a3b8;display:flex;gap:12px;flex-wrap:wrap">
                <span><i class="bi bi-person"></i> ${esc(n.poster_name)}</span>
                <span><i class="bi bi-calendar"></i> ${n.created_at}</span>
                <span><i class="bi bi-eye"></i> ${n.view_count} views</span>
            </div>
        </div>`;
    if (n.attachment) {
        html += `<div class="mb-3"><a href="/uploads/documents/${esc(n.attachment)}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip me-1"></i>Attachment</a></div>`;
    }
    if (n.status === 'rejected' && n.reject_reason) {
        html += `<div class="alert alert-danger small"><i class="bi bi-x-circle me-1"></i><strong>Rejected:</strong> ${esc(n.reject_reason)}</div>`;
    }
    document.getElementById('previewBody').innerHTML = html;
}

// ─── Edit ───
function editNotif(id) {
    const n = notifsData.find(x=>x.id==id);
    if (!n) return;
    document.getElementById('editId').value = n.id;
    document.getElementById('editTitle').value = n.title;
    document.getElementById('editContent').value = n.content;
    document.getElementById('editType').value = n.type;
    document.getElementById('editPriority').value = n.priority;
    document.getElementById('editCategory').value = n.category || 'general';
    document.getElementById('editTags').value = n.tags || '';
    document.getElementById('editTarget').value = n.target_audience;
    document.getElementById('editTargetClass').value = n.target_class;
    document.getElementById('editTargetSection').value = n.target_section;
    document.getElementById('editSchedule').value = n.schedule_at ? n.schedule_at.replace(' ','T').substring(0,16) : '';
    document.getElementById('editExpiry').value = n.expires_at || '';
    document.getElementById('ePublic').checked = !!n.is_public;
    document.getElementById('ePopup').checked = !!n.show_popup;
    document.getElementById('eBanner').checked = !!n.show_banner;
    document.getElementById('eMarquee').checked = !!n.show_marquee;
    document.getElementById('eDash').checked = !!n.show_dashboard;
    toggleTarget(document.getElementById('editTarget'));
    // Load attachments
    fetch(`/admin/ajax/notification-actions.php?action=attachments&id=${n.id}`).then(r=>r.json()).then(d=>{
        let html = '<label class="form-label fw-semibold">Attachments</label>';
        if (d.attachments && d.attachments.length) {
            html += '<div class="list-group mb-2">';
            d.attachments.forEach(a=>{
                html += `<div class="list-group-item d-flex justify-content-between align-items-center py-1">
                    <a href="/uploads/documents/${esc(a.file_path)}" target="_blank" class="small text-decoration-none"><i class="bi bi-paperclip me-1"></i>${esc(a.file_name)}</a>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteAttachment(${a.id},this)"><i class="bi bi-x"></i></button>
                </div>`;
            });
            html += '</div>';
        }
        document.getElementById('editAttachmentsArea').innerHTML = html;
    });
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function deleteAttachment(aid, btn) {
    if (!confirm('Delete this attachment?')) return;
    fetch('/admin/ajax/notification-actions.php?action=delete_attachment', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=delete_attachment&attachment_id=${aid}&csrf_token=${csrfToken}`
    }).then(r=>r.json()).then(d=>{ if(d.success) btn.closest('.list-group-item').remove(); });
}
function rejectNotif(id) { document.getElementById('rejectId').value=id; new bootstrap.Modal(document.getElementById('rejectModal')).show(); }
function toggleTarget(sel) {
    const form = sel.closest('form')||sel.closest('.modal-body');
    const v = sel.value;
    form.querySelectorAll('.target-class-field').forEach(el=>el.classList.toggle('d-none',v!=='class'&&v!=='section'));
    form.querySelectorAll('.target-section-field').forEach(el=>el.classList.toggle('d-none',v!=='section'));
}

// ─── Version History ───
function showVersions(id) {
    document.getElementById('versionBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('versionModal')).show();
    fetch(`/admin/ajax/notification-actions.php?action=versions&id=${id}`).then(r=>r.json()).then(d=>{
        if (!d.versions || !d.versions.length) { document.getElementById('versionBody').innerHTML = '<p class="text-muted text-center py-3">No version history yet</p>'; return; }
        let html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>#</th><th>Title</th><th>Type</th><th>Priority</th><th>Changed By</th><th>Date</th><th></th></tr></thead><tbody>';
        d.versions.forEach((v,i)=>{
            html += `<tr>
                <td class="text-muted small">${d.versions.length - i}</td>
                <td style="font-size:.85rem">${esc(v.title)}</td>
                <td><span class="badge bg-light text-dark" style="font-size:.65rem">${esc(v.type)}</span></td>
                <td style="font-size:.8rem">${esc(v.priority)}</td>
                <td style="font-size:.8rem">${esc(v.changed_by_name||'Unknown')}</td>
                <td style="font-size:.75rem">${v.changed_at}</td>
                <td><button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="restoreVersion(${v.id})" style="font-size:.75rem"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        document.getElementById('versionBody').innerHTML = html;
    });
}
function restoreVersion(vid) {
    if (!confirm('Restore this version? Current content will be saved as a new version.')) return;
    fetch('/admin/ajax/notification-actions.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=restore_version&version_id=${vid}&csrf_token=${csrfToken}`
    }).then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.error||'Error'); });
}

// ─── Analytics ───
function showAnalytics(id) {
    document.getElementById('analyticsBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('analyticsModal')).show();
    fetch(`/admin/ajax/notification-actions.php?action=analytics&id=${id}`).then(r=>r.json()).then(d=>{
        let html = `<div class="row g-3 mb-4">
            <div class="col-md-4"><div class="text-center p-3 bg-primary-subtle rounded-3"><h3 class="fw-bold mb-0 text-primary">${d.view_count}</h3><small class="text-muted">Total Views</small></div></div>
            <div class="col-md-4"><div class="text-center p-3 bg-success-subtle rounded-3"><h3 class="fw-bold mb-0 text-success">${d.breakdown?d.breakdown.reduce((a,b)=>a+parseInt(b.cnt),0):0}</h3><small class="text-muted">Unique Readers</small></div></div>
            <div class="col-md-4"><div class="text-center p-3 bg-info-subtle rounded-3"><h3 class="fw-bold mb-0 text-info">${d.breakdown?d.breakdown.length:0}</h3><small class="text-muted">Role Groups</small></div></div>
        </div>`;
        if (d.breakdown && d.breakdown.length) {
            html += '<h6 class="fw-semibold mb-2">Breakdown by Role</h6><div class="table-responsive mb-3"><table class="table table-sm"><thead><tr><th>Role</th><th>Readers</th><th>First Read</th><th>Last Read</th></tr></thead><tbody>';
            d.breakdown.forEach(b=>{
                html += `<tr><td><span class="badge bg-secondary-subtle text-secondary">${esc(b.role)}</span></td><td>${b.cnt}</td><td style="font-size:.8rem">${b.first_read||'-'}</td><td style="font-size:.8rem">${b.last_read||'-'}</td></tr>`;
            });
            html += '</tbody></table></div>';
        }
        if (d.recent_readers && d.recent_readers.length) {
            html += '<h6 class="fw-semibold mb-2">Recent Readers</h6><div class="list-group">';
            d.recent_readers.forEach(r=>{
                html += `<div class="list-group-item d-flex justify-content-between py-1"><span style="font-size:.85rem">${esc(r.name)} <span class="badge bg-light text-dark" style="font-size:.6rem">${esc(r.role)}</span></span><small class="text-muted">${r.read_at}</small></div>`;
            });
            html += '</div>';
        }
        if ((!d.breakdown || !d.breakdown.length) && (!d.recent_readers || !d.recent_readers.length)) {
            html += '<p class="text-muted text-center py-3">No engagement data yet</p>';
        }
        document.getElementById('analyticsBody').innerHTML = html;
    });
}

// ─── WhatsApp ───
function showWhatsApp(id) {
    const n = notifsData.find(x=>x.id==id);
    if (!n) return;
    const text = `📢 *${n.title}*\n\n${n.content}\n\n📅 Date: ${n.created_at}\n🏫 ${document.title.split('—')[1]?.trim()||'School'}\n\n_This is an official notification._`;
    document.getElementById('whatsappText').value = text;
    document.getElementById('whatsappLink').href = `https://wa.me/?text=${encodeURIComponent(text)}`;
    new bootstrap.Modal(document.getElementById('whatsappModal')).show();
}
function copyWhatsApp() {
    const t = document.getElementById('whatsappText');
    t.select(); navigator.clipboard.writeText(t.value);
    const btn = t.nextElementSibling.querySelector('button');
    btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
    setTimeout(()=>{ btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy to Clipboard'; }, 2000);
}

// ─── Bulk Selection ───
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb=>cb.checked=this.checked);
    updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(cb=>cb.addEventListener('change', updateBulkBar));
function updateBulkBar() {
    const cnt = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = cnt;
    document.getElementById('floatingBulk').classList.toggle('show', cnt > 0);
}
function bulkAction(action) {
    if (!confirm('Are you sure?')) return;
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb=>cb.value);
    if (!ids.length) return;
    const form = document.createElement('form');
    form.method='POST';
    form.innerHTML=`<input type="hidden" name="action" value="${action}"><input type="hidden" name="id" value="0"><input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="redirect_status" value="<?= e($statusFilter) ?>">`;
    ids.forEach(id=>{const i=document.createElement('input');i.type='hidden';i.name='ids[]';i.value=id;form.appendChild(i);});
    document.body.appendChild(form); form.submit();
}
function bulkExportCSV() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb=>cb.value);
    const selected = notifsData.filter(n=>ids.includes(String(n.id)));
    let csv = 'ID,Title,Type,Priority,Category,Tags,Target,Status,Views,Date\n';
    selected.forEach(n=>{ csv += `${n.id},"${n.title}",${n.type},${n.priority},${n.category||''},${n.tags||''},${n.target_audience},${n.status},${n.view_count},${n.created_at}\n`; });
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='selected_notifications.csv'; a.click();
}
function bulkWhatsApp() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb=>cb.value);
    const selected = notifsData.filter(n=>ids.includes(String(n.id)));
    let text = '📢 *School Notifications*\n\n';
    selected.forEach(n=>{ text += `• *${n.title}* (${n.created_at})\n`; });
    navigator.clipboard.writeText(text);
    alert('WhatsApp text copied to clipboard!');
}

// ─── Column Toggle ───
(function(){
    const saved = JSON.parse(localStorage.getItem('notif_columns')||'{}');
    document.querySelectorAll('.col-toggle-check').forEach(cb=>{
        const col = cb.dataset.col;
        if (saved[col] === false) { cb.checked = false; toggleCol(col, false); }
        cb.addEventListener('change', function(){ toggleCol(col, this.checked); const s=JSON.parse(localStorage.getItem('notif_columns')||'{}'); s[col]=this.checked; localStorage.setItem('notif_columns',JSON.stringify(s)); });
    });
})();
function toggleCol(col, show) {
    document.querySelectorAll(`[data-col="${col}"]`).forEach(el=>el.style.display=show?'':'none');
}

// ─── Filter Bar Toggle ───
function toggleFilterBar() {
    const bar = document.getElementById('filterBar');
    bar.classList.toggle('collapsed');
    const icon = document.getElementById('filterToggleIcon');
    icon.className = bar.classList.contains('collapsed') ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
}

// ─── Saved Filter Views ───
function loadSavedFilters() {
    const list = document.getElementById('savedFiltersList');
    const filters = JSON.parse(localStorage.getItem('notif_saved_filters')||'[]');
    if (!filters.length) { list.innerHTML = '<div class="text-muted small text-center py-2">No saved views</div>'; return; }
    list.innerHTML = '';
    filters.forEach((f,i)=>{
        list.innerHTML += `<div class="saved-filter-item"><a href="/admin/notifications.php?${f.qs}" class="text-decoration-none small">${esc(f.name)}</a><button class="btn btn-sm btn-link text-danger p-0" onclick="deleteSavedFilter(${i})"><i class="bi bi-x"></i></button></div>`;
    });
}
function saveCurrentFilter() {
    const name = prompt('Name this filter view:');
    if (!name) return;
    const filters = JSON.parse(localStorage.getItem('notif_saved_filters')||'[]');
    filters.push({ name, qs: location.search.substring(1) });
    localStorage.setItem('notif_saved_filters', JSON.stringify(filters));
    loadSavedFilters();
}
function deleteSavedFilter(i) {
    const filters = JSON.parse(localStorage.getItem('notif_saved_filters')||'[]');
    filters.splice(i,1);
    localStorage.setItem('notif_saved_filters', JSON.stringify(filters));
    loadSavedFilters();
}
loadSavedFilters();

// ─── PDF Export ───
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(14);
    doc.text('Notifications Report', 14, 15);
    doc.setFontSize(8);
    doc.text('Generated: ' + new Date().toLocaleString(), 14, 22);
    const rows = notifsData.map(n=>[n.id, n.title.substring(0,40), n.type, n.priority, n.category||'', n.status, n.view_count, n.created_at.substring(0,10)]);
    doc.autoTable({ head: [['ID','Title','Type','Priority','Category','Status','Views','Date']], body: rows, startY: 26, styles:{fontSize:7}, headStyles:{fillColor:[30,64,175]} });
    doc.save('notifications_report.pdf');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>