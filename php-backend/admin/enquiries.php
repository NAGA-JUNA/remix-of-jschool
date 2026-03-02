<?php
$pageTitle = 'Enquiries';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    verifyCsrf($_GET['csrf_token'] ?? '');
    $where = '';
    $params = [];
    $status = $_GET['status_filter'] ?? '';
    if ($status && in_array($status, ['new', 'contacted', 'closed'])) {
        $where = 'WHERE status = ?';
        $params[] = $status;
    }
    $rows = $db->prepare("SELECT name, phone, email, message, status, created_at FROM enquiries $where ORDER BY created_at DESC");
    $rows->execute($params);
    $data = $rows->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enquiries_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Phone', 'Email', 'Message', 'Status', 'Date']);
    foreach ($data as $r) {
        fputcsv($out, [$r['name'], $r['phone'], $r['email'] ?? '', $r['message'] ?? '', ucfirst($r['status']), $r['created_at']]);
    }
    fclose($out);
    exit;
}

// Status filter
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($statusFilter && in_array($statusFilter, ['new', 'contacted', 'closed'])) {
    $where .= ' AND status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Counts for tabs
$countAll = $db->query("SELECT COUNT(*) FROM enquiries")->fetchColumn();
$countNew = $db->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn();
$countContacted = $db->query("SELECT COUNT(*) FROM enquiries WHERE status='contacted'")->fetchColumn();
$countClosed = $db->query("SELECT COUNT(*) FROM enquiries WHERE status='closed'")->fetchColumn();

// Paginated results
$totalStmt = $db->prepare("SELECT COUNT(*) FROM enquiries WHERE $where");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$p = paginate($total, 20, $page);

$stmt = $db->prepare("SELECT * FROM enquiries WHERE $where ORDER BY created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

$baseUrl = '/admin/enquiries.php?' . http_build_query(array_filter(['status' => $statusFilter, 'q' => $search]));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-chat-dots me-2 text-primary"></i>Enquiries</h5>
        <small class="text-muted"><?= $total ?> total enquiries</small>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/enquiries.php?export=csv&csrf_token=<?= urlencode($_SESSION['csrf_token'] ?? '') ?>&status_filter=<?= urlencode($statusFilter) ?>" class="btn btn-outline-success btn-sm rounded-pill">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Status Tabs -->
<ul class="nav nav-pills mb-3">
    <?php
    $tabs = ['' => ['All', $countAll, 'secondary'], 'new' => ['New', $countNew, 'primary'], 'contacted' => ['Contacted', $countContacted, 'info'], 'closed' => ['Closed', $countClosed, 'success']];
    foreach ($tabs as $key => $t):
    ?>
    <li class="nav-item">
        <a href="/admin/enquiries.php?status=<?= $key ?>&q=<?= urlencode($search) ?>" class="nav-link <?= $statusFilter === $key ? 'active' : '' ?> btn-sm">
            <?= $t[0] ?> <span class="badge bg-<?= $t[2] ?>-subtle text-<?= $t[2] ?> ms-1"><?= $t[1] ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Search -->
<form class="mb-3 d-flex gap-2" method="GET" action="/admin/enquiries.php">
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
    <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search by name or phone..." style="max-width:300px;">
    <button class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-search"></i></button>
    <?php if ($search): ?>
    <a href="/admin/enquiries.php?status=<?= urlencode($statusFilter) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Clear</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card border-0 rounded-3 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($enquiries)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No enquiries found</td></tr>
                <?php else: foreach ($enquiries as $enq): ?>
                    <tr>
                        <td><?= $enq['id'] ?></td>
                        <td><strong><?= e($enq['name']) ?></strong></td>
                        <td style="font-size:.85rem">
                            <a href="tel:<?= e($enq['phone']) ?>" class="text-decoration-none"><?= e($enq['phone']) ?></a>
                        </td>
                        <td style="font-size:.85rem"><?= $enq['email'] ? e($enq['email']) : '<span class="text-muted">—</span>' ?></td>
                        <td style="font-size:.82rem;max-width:200px;" class="text-truncate"><?= $enq['message'] ? e($enq['message']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php
                            $badges = ['new' => 'primary', 'contacted' => 'info', 'closed' => 'success'];
                            $bc = $badges[$enq['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $bc ?>-subtle text-<?= $bc ?>"><?= ucfirst($enq['status']) ?></span>
                        </td>
                        <td style="font-size:.8rem"><?= date('M d, Y', strtotime($enq['created_at'])) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($enq['status'] !== 'contacted'): ?>
                                <button class="btn btn-outline-info py-0 px-2 enq-action" data-id="<?= $enq['id'] ?>" data-action="contacted" title="Mark Contacted">
                                    <i class="bi bi-telephone-outbound"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($enq['status'] !== 'closed'): ?>
                                <button class="btn btn-outline-success py-0 px-2 enq-action" data-id="<?= $enq['id'] ?>" data-action="closed" title="Mark Closed">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger py-0 px-2 enq-action" data-id="<?= $enq['id'] ?>" data-action="delete" title="Delete" onclick="return confirm('Delete this enquiry?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= paginationHtml($p, $baseUrl . '&') ?>

<script>
document.querySelectorAll('.enq-action').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (this.dataset.action === 'delete' && !confirm('Delete this enquiry?')) return;
        const fd = new FormData();
        fd.append('id', this.dataset.id);
        fd.append('action', this.dataset.action === 'delete' ? 'delete' : 'update_status');
        if (this.dataset.action !== 'delete') fd.append('status', this.dataset.action);
        fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

        fetch('/admin/ajax/enquiry-actions.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else alert(d.error || 'Failed');
            })
            .catch(() => alert('Request failed'));
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>