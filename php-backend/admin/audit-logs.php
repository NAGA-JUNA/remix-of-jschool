<?php
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = []; $params = [];
if ($search) { $where[] = "(a.action LIKE ? OR a.entity_type LIKE ? OR u.name LIKE ? OR a.details LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($dateFrom) { $where[] = "a.created_at >= ?"; $params[] = "$dateFrom 00:00:00"; }
if ($dateTo) { $where[] = "a.created_at <= ?"; $params[] = "$dateTo 23:59:59"; }
$whereStr = $where ? 'WHERE '.implode(' AND ', $where) : '';

$total = $db->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id=u.id $whereStr"); $total->execute($params); $total = $total->fetchColumn();
$p = paginate($total, 25, $page);

$stmt = $db->prepare("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id=u.id $whereStr ORDER BY a.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params); $logs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card border-0 rounded-3 mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search action, user, entity..." value="<?= e($search) ?>"></div>
            <div class="col-md-2"><label class="form-label mb-0" style="font-size:.7rem">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
            <div class="col-md-2"><label class="form-label mb-0" style="font-size:.7rem">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-dark w-100"><i class="bi bi-search me-1"></i>Filter</button></div>
            <div class="col-md-2"><a href="/admin/audit-logs.php" class="btn btn-sm btn-outline-secondary w-100">Clear</a></div>
        </form>
    </div>
</div>

<div class="card border-0 rounded-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0">Logs (<?= $total ?>)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>User</th><th>Action</th><th>Entity</th><th>ID</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No logs found</td></tr>
                <?php else: foreach ($logs as $l): ?>
                    <tr>
                        <td style="font-size:.85rem"><?= e($l['user_name'] ?? 'System') ?></td>
                        <td><span class="badge bg-light text-dark"><?= e($l['action']) ?></span></td>
                        <td style="font-size:.85rem"><?= e($l['entity_type'] ?? '-') ?></td>
                        <td style="font-size:.85rem"><?= e($l['entity_id'] ?? '-') ?></td>
                        <td style="font-size:.8rem;max-width:200px" class="text-truncate"><?= e($l['details'] ?? '-') ?></td>
                        <td style="font-size:.75rem;color:#94a3b8"><?= e($l['ip_address'] ?? '-') ?></td>
                        <td style="font-size:.8rem;color:#94a3b8"><?= date('M d, H:i', strtotime($l['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= paginationHtml($p, '/admin/audit-logs.php?'.http_build_query(array_filter(['search'=>$search,'from'=>$dateFrom,'to'=>$dateTo]))) ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
