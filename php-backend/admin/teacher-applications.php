<?php
$pageTitle = 'Teacher Applications';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Status definitions
$allStatuses = ['new','reviewed','shortlisted','interview_scheduled','approved','rejected'];
$statusColors = [
    'new'=>'primary', 'reviewed'=>'info', 'shortlisted'=>'warning',
    'interview_scheduled'=>'secondary', 'approved'=>'success', 'rejected'=>'danger'
];
$statusIcons = [
    'new'=>'bi-plus-circle', 'reviewed'=>'bi-eye', 'shortlisted'=>'bi-star',
    'interview_scheduled'=>'bi-calendar-event', 'approved'=>'bi-check-circle', 'rejected'=>'bi-x-circle'
];

$waTemplate = getSetting('whatsapp_recruitment_template', 'Hello {name}, regarding your application ({app_id}) for {position}...');

// Archive tab
$activeTab = $_GET['tab'] ?? 'active';
$isArchiveTab = ($activeTab === 'archived');

// KPI counts
$kpiNew = $db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='new' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiShortlisted = $db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='shortlisted' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiInterview = $db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='interview_scheduled' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiApproved = $db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='approved' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiTotal = $db->query("SELECT COUNT(*) FROM teacher_applications WHERE (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiArchived = $db->query("SELECT COUNT(*) FROM teacher_applications WHERE is_deleted=1")->fetchColumn();

// Status counts
$statusCounts = [];
$scStmt = $db->query("SELECT status, COUNT(*) as c FROM teacher_applications WHERE (is_deleted=0 OR is_deleted IS NULL) GROUP BY status");
while ($r = $scStmt->fetch()) $statusCounts[$r['status']] = (int)$r['c'];

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];

if ($isArchiveTab) {
    $where[] = "a.is_deleted=1";
} else {
    $where[] = "(a.is_deleted=0 OR a.is_deleted IS NULL)";
}

if ($statusFilter && in_array($statusFilter, $allStatuses)) { $where[] = "a.status=?"; $params[] = $statusFilter; }
if ($searchQuery) { $where[] = "(a.full_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ? OR a.application_id LIKE ?)"; $s = "%$searchQuery%"; $params = array_merge($params, [$s,$s,$s,$s]); }
if ($dateFrom) { $where[] = "DATE(a.created_at)>=?"; $params[] = $dateFrom; }
if ($dateTo) { $where[] = "DATE(a.created_at)<=?"; $params[] = $dateTo; }

$whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';
$total = $db->prepare("SELECT COUNT(*) FROM teacher_applications a $whereClause");
$total->execute($params);
$total = $total->fetchColumn();
$p = paginate($total, 20, $page);

$stmt = $db->prepare("SELECT a.*, j.title as job_title, u.name as reviewer_name FROM teacher_applications a LEFT JOIN job_openings j ON a.job_opening_id=j.id LEFT JOIN users u ON a.reviewed_by=u.id $whereClause ORDER BY a.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params);
$applications = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    border: 1px solid transparent; font-size: 0.85rem;
    transition: all 0.2s ease; cursor: pointer; text-decoration: none;
}
.action-btn:hover { transform: scale(1.15); }
.action-btn-view { background: #e8f4fd; color: #1a73e8; }
.action-btn-view:hover { box-shadow: 0 3px 12px rgba(26,115,232,0.3); color: #1a73e8; }
.action-btn-whatsapp { background: #e8f8e8; color: #25d366; }
.action-btn-whatsapp:hover { box-shadow: 0 3px 12px rgba(37,211,102,0.3); color: #25d366; }
.action-btn-call { background: #e0f7fa; color: #00acc1; }
.action-btn-call:hover { box-shadow: 0 3px 12px rgba(0,172,193,0.3); color: #00acc1; }
.action-btn-success { background: #e8f5e9; color: #2e7d32; }
.action-btn-success:hover { box-shadow: 0 3px 12px rgba(46,125,50,0.3); color: #2e7d32; }
.action-btn-warning { background: #fff8e1; color: #f9a825; }
.action-btn-warning:hover { box-shadow: 0 3px 12px rgba(249,168,37,0.3); color: #f9a825; }
.action-btn-danger { background: #fde8e8; color: #d32f2f; }
.action-btn-danger:hover { box-shadow: 0 3px 12px rgba(211,47,47,0.3); color: #d32f2f; }
.action-btn-secondary { background: #f0f0f0; color: #616161; }
.action-btn-secondary:hover { box-shadow: 0 3px 12px rgba(97,97,97,0.2); color: #616161; }
.action-btn-info { background: #e3f2fd; color: #1976d2; }
.action-btn-info:hover { box-shadow: 0 3px 12px rgba(25,118,210,0.3); color: #1976d2; }
</style>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['New', $kpiNew, 'bi-plus-circle-fill', 'primary'],
        ['Shortlisted', $kpiShortlisted, 'bi-star-fill', 'warning'],
        ['Interviews', $kpiInterview, 'bi-calendar-event-fill', 'info'],
        ['Approved', $kpiApproved, 'bi-check-circle-fill', 'success'],
        ['Total', $kpiTotal, 'bi-people-fill', 'secondary'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-6 col-md">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-<?= $k[3] ?>-subtle text-<?= $k[3] ?>"><i class="bi <?= $k[2] ?>"></i></div>
                    <div><div class="fs-3 fw-bold"><?= $k[1] ?></div><div class="text-muted" style="font-size:.75rem"><?= $k[0] ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, phone, email, app ID..." value="<?= e($searchQuery) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach ($allStatuses as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                <a href="teacher-applications.php?tab=<?= e($activeTab) ?>" class="btn btn-outline-secondary btn-sm" title="Clear"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabs -->
<div class="d-flex align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        <a href="teacher-applications.php?tab=active" class="btn <?= !$isArchiveTab ? 'btn-primary' : 'btn-outline-primary' ?>">
            <i class="bi bi-inbox me-1"></i>Active <span class="badge bg-light text-dark ms-1"><?= $kpiTotal ?></span>
        </a>
        <a href="teacher-applications.php?tab=archived" class="btn <?= $isArchiveTab ? 'btn-danger' : 'btn-outline-danger' ?>">
            <i class="bi bi-archive me-1"></i>Archived <span class="badge bg-light text-dark ms-1"><?= $kpiArchived ?></span>
        </a>
    </div>
    <a href="recruitment-settings.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-gear me-1"></i>Settings</a>
</div>

<?php if (!$isArchiveTab): ?>
<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" style="gap:4px;">
    <li class="nav-item"><a href="teacher-applications.php?<?= http_build_query(array_merge($_GET, ['status'=>'','tab'=>'active'])) ?>" class="nav-link <?= !$statusFilter?'active':'' ?> btn-sm">All <span class="badge bg-light text-dark ms-1"><?= $kpiTotal ?></span></a></li>
    <?php foreach ($allStatuses as $s): ?>
    <li class="nav-item"><a href="teacher-applications.php?<?= http_build_query(array_merge($_GET, ['status'=>$s,'tab'=>'active'])) ?>" class="nav-link <?= $statusFilter===$s?'active':'' ?> btn-sm"><?= ucfirst(str_replace('_',' ',$s)) ?> <span class="badge bg-light text-dark ms-1"><?= $statusCounts[$s] ?? 0 ?></span></a></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- Export -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <small class="text-muted"><?= $total ?> application(s) found</small>
    <a href="ajax/recruitment-actions.php?action=export_csv&<?= http_build_query(array_filter(['status'=>$statusFilter,'search'=>$searchQuery,'date_from'=>$dateFrom,'date_to'=>$dateTo])) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>

<!-- Table -->
<div class="card border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>App ID</th><th>Name</th><th>Position</th><th>Phone</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No applications found</td></tr>
                <?php else: foreach ($applications as $a):
                    $sc = $statusColors[$a['status']] ?? 'secondary';
                    $phone = $a['phone'] ?? '';
                    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
                    $posTitle = $a['job_title'] ?? 'General';
                    $waMsg = str_replace(['{name}', '{app_id}', '{position}'], [$a['full_name'], $a['application_id'] ?? '', $posTitle], $waTemplate);
                ?>
                    <tr style="cursor:pointer" onclick="viewApplication(<?= $a['id'] ?>)">
                        <td><?= $a['id'] ?></td>
                        <td><code style="font-size:.8rem"><?= e($a['application_id'] ?? 'N/A') ?></code></td>
                        <td style="font-size:.85rem">
                            <strong><?= e($a['full_name']) ?></strong>
                            <br><small class="text-muted"><?= e($a['qualification'] ?? '') ?> • <?= (int)$a['experience_years'] ?> yrs exp</small>
                        </td>
                        <td style="font-size:.85rem"><?= e($posTitle) ?></td>
                        <td style="font-size:.85rem"><?= e($phone) ?></td>
                        <td><span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                        <td style="font-size:.8rem"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                        <td onclick="event.stopPropagation()">
                            <div class="d-flex align-items-center gap-1 flex-nowrap">
                                <button class="action-btn action-btn-view" onclick="viewApplication(<?= $a['id'] ?>)" title="View"><i class="bi bi-eye"></i></button>

                                <?php if ($cleanPhone): ?>
                                <a href="https://wa.me/<?= ltrim($cleanPhone, '+') ?>?text=<?= urlencode($waMsg) ?>" target="_blank" class="action-btn action-btn-whatsapp" title="WhatsApp" onclick="logContact('whatsapp',<?= $a['id'] ?>)"><i class="bi bi-whatsapp"></i></a>
                                <a href="tel:<?= $cleanPhone ?>" class="action-btn action-btn-call d-md-none" title="Call"><i class="bi bi-telephone-fill"></i></a>
                                <button class="action-btn action-btn-call d-none d-md-inline-flex" title="Copy Number" onclick="copyPhone('<?= e($phone) ?>', <?= $a['id'] ?>)"><i class="bi bi-telephone-fill"></i></button>
                                <?php endif; ?>

                                <?php if ($isArchiveTab): ?>
                                    <button class="action-btn action-btn-success" onclick="showConfirmModal('restore','Restore','Restore this application?',<?= $a['id'] ?>)" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php if (isSuperAdmin()): ?>
                                    <button class="action-btn action-btn-danger" onclick="showConfirmModal('permanent_delete','Delete Forever','Permanently delete this application? This cannot be undone!',<?= $a['id'] ?>,'destructive')" title="Delete"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php
                                    $s = $a['status'];
                                    $id = $a['id'];
                                    if ($s === 'new'): ?>
                                        <button class="action-btn action-btn-info" onclick="showStatusConfirm(<?=$id?>,'reviewed','new')" title="Mark Reviewed"><i class="bi bi-eye-fill"></i></button>
                                    <?php elseif ($s === 'reviewed'): ?>
                                        <button class="action-btn action-btn-warning" onclick="showStatusConfirm(<?=$id?>,'shortlisted','reviewed')" title="Shortlist"><i class="bi bi-star"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'shortlisted'): ?>
                                        <button class="action-btn action-btn-warning" onclick="showInterviewModal(<?=$id?>)" title="Schedule Interview"><i class="bi bi-calendar-event"></i></button>
                                        <button class="action-btn action-btn-success" onclick="showStatusConfirm(<?=$id?>,'approved','shortlisted')" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'interview_scheduled'): ?>
                                        <button class="action-btn action-btn-success" onclick="showStatusConfirm(<?=$id?>,'approved','interview_scheduled')" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'rejected'): ?>
                                        <button class="action-btn action-btn-info" onclick="showStatusConfirm(<?=$id?>,'new','rejected')" title="Reopen"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php endif; ?>
                                    <button class="action-btn action-btn-secondary" onclick="showConfirmModal('soft_delete','Archive','Archive this application?',<?= $a['id'] ?>)" title="Archive"><i class="bi bi-archive"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= paginationHtml($p, 'teacher-applications.php?' . http_build_query(array_filter(['status'=>$statusFilter,'search'=>$searchQuery,'date_from'=>$dateFrom,'date_to'=>$dateTo,'tab'=>$activeTab]))) ?>

<!-- ==================== VIEW APPLICATION MODAL ==================== -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="viewModalTitle">Loading...</h5>
                    <small class="text-muted" id="viewModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationModalBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STATUS CONFIRM MODAL ==================== -->
<div class="modal fade" id="statusConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Confirm Status Change</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <span class="badge bg-secondary-subtle text-secondary fs-6" id="scmOldStatus">—</span>
                    <i class="bi bi-arrow-right mx-2 text-muted"></i>
                    <span class="badge fs-6" id="scmNewStatus">—</span>
                </div>
                <label class="form-label fw-semibold" style="font-size:.8rem">Remarks (optional)</label>
                <textarea id="scmRemarks" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="executeStatusChange()"><i class="bi bi-check-lg me-1"></i>Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REJECT MODAL ==================== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger-subtle"><h6 class="modal-title text-danger"><i class="bi bi-x-circle me-1"></i>Reject Application</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p style="font-size:.85rem" class="mb-2">Reject this application?</p>
                <label class="form-label fw-semibold" style="font-size:.8rem">Rejection Remarks</label>
                <textarea id="rejectRemarks" class="form-control form-control-sm" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="executeReject()"><i class="bi bi-x-lg me-1"></i>Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== INTERVIEW MODAL ==================== -->
<div class="modal fade" id="interviewModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle"><h6 class="modal-title"><i class="bi bi-calendar-event me-1"></i>Schedule Interview</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label fw-semibold" style="font-size:.8rem">Interview Date & Time</label>
                <input type="datetime-local" id="interviewDate" class="form-control form-control-sm" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" onclick="executeInterview()"><i class="bi bi-calendar-event me-1"></i>Schedule</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== GENERIC CONFIRM MODAL ==================== -->
<div class="modal fade" id="genericConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="gcmHeader"><h6 class="modal-title" id="gcmTitle">Confirm</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p style="font-size:.85rem" id="gcmMessage"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm" id="gcmConfirmBtn" onclick="executeGenericConfirm()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== COPY TOAST ==================== -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle me-1"></i><span id="copyToastMsg">Copied!</span></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
let viewModal = null, _currentViewId = null;

function getViewModal() {
    if (!viewModal) viewModal = new bootstrap.Modal(document.getElementById('viewApplicationModal'));
    return viewModal;
}

function viewApplication(id) {
    _currentViewId = id;
    const modal = getViewModal();
    document.getElementById('viewModalTitle').textContent = 'Loading...';
    document.getElementById('viewModalSubtitle').textContent = '';
    document.getElementById('applicationModalBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch('ajax/recruitment-actions.php?action=get_detail&id=' + id, { headers: { 'Accept': 'application/json' } })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            if (!data.success) { document.getElementById('applicationModalBody').innerHTML = '<div class="alert alert-danger">Error loading data</div>'; return; }
            const a = data.application;
            const notes = data.notes || [];
            const history = data.history || [];

            document.getElementById('viewModalTitle').innerHTML = (a.application_id || '#'+a.id) + ' <span class="badge bg-'+statusColor(a.status)+'-subtle text-'+statusColor(a.status)+' ms-2">'+a.status.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())+'</span>';
            document.getElementById('viewModalSubtitle').textContent = 'Applied ' + a.created_at;

            let html = '';

            // Quick actions
            const phone = a.phone || '';
            const cleanPhone = phone.replace(/[^0-9+]/g, '');
            html += '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3 border-bottom">';
            if (cleanPhone) {
                html += '<div class="d-flex gap-1">';
                html += '<a href="https://wa.me/'+cleanPhone.replace('+','')+'?text='+encodeURIComponent(buildWaMsg(a))+'" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>';
                html += '<a href="tel:'+cleanPhone+'" class="btn btn-outline-info btn-sm d-md-none"><i class="bi bi-telephone-fill me-1"></i>Call</a>';
                html += '<button class="btn btn-outline-info btn-sm d-none d-md-inline-flex" onclick="copyPhone(\''+escHtml(phone)+'\','+a.id+')"><i class="bi bi-telephone-fill me-1"></i>Copy</button>';
                html += '</div>';
            }
            if (a.status !== 'approved' && a.status !== 'rejected') {
                html += '<div class="d-flex gap-1 flex-wrap">';
                getNextStatuses(a.status).forEach(ns => {
                    html += '<button class="btn btn-outline-'+statusColor(ns)+' btn-sm py-0 px-2" onclick="getViewModal().hide();showStatusConfirm('+a.id+',\''+ns+'\',\''+a.status+'\')"><i class="bi '+statusIcon(ns)+' me-1"></i>'+ns.replace(/_/g,' ')+'</button>';
                });
                html += '</div>';
            }
            html += '</div>';

            // Personal Info
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-person me-1"></i>Personal Information</h6>';
            html += '<div class="row g-2 mb-4">';
            html += rf('Application ID', a.application_id);
            html += rf('Full Name', a.full_name);
            html += rf('Email', a.email);
            html += rf('Phone', a.phone);
            html += rf('Date of Birth', a.dob);
            html += rf('Gender', a.gender);
            html += '</div>';

            // Professional Info
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-briefcase me-1"></i>Professional Information</h6>';
            html += '<div class="row g-2 mb-4">';
            html += rf('Position Applied', a.job_title || 'General');
            html += rf('Qualification', a.qualification);
            html += rf('Experience', a.experience_years ? a.experience_years + ' years' : null);
            html += rf('Current School', a.current_school);
            html += rf('Address', a.address, true);
            html += '</div>';

            // Cover Letter
            if (a.cover_letter) {
                html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-file-text me-1"></i>Cover Letter</h6>';
                html += '<div class="border rounded p-3 mb-4" style="font-size:.85rem;white-space:pre-wrap">'+escHtml(a.cover_letter)+'</div>';
            }

            // Resume
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-file-earmark me-1"></i>Resume</h6>';
            if (a.resume_path) {
                const isPdf = /\.pdf$/i.test(a.resume_path);
                if (isPdf) {
                    html += '<div class="border rounded mb-4"><iframe src="'+a.resume_path+'" style="width:100%;height:400px;border:none"></iframe>';
                    html += '<div class="p-2 border-top text-center"><a href="'+a.resume_path+'" class="btn btn-outline-primary btn-sm" download><i class="bi bi-download me-1"></i>Download Resume</a></div></div>';
                } else {
                    html += '<div class="border rounded p-3 mb-4 text-center"><i class="bi bi-file-earmark-word text-primary" style="font-size:2rem"></i><p class="mb-2">'+a.resume_path.split('/').pop()+'</p><a href="'+a.resume_path+'" class="btn btn-outline-primary btn-sm" download><i class="bi bi-download me-1"></i>Download</a></div>';
                }
            } else {
                html += '<p class="text-muted mb-4" style="font-size:.85rem">No resume uploaded</p>';
            }

            // Interview Info
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-calendar me-1"></i>Interview & Admin</h6>';
            html += '<div class="row g-2 mb-4">';
            html += rf('Status', a.status ? a.status.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : null);
            html += rf('Interview Date', a.interview_date);
            html += rf('Interview Notes', a.interview_notes, true);
            html += rf('Reviewed By', a.reviewer_name);
            html += rf('Reviewed At', a.reviewed_at);
            html += '</div>';

            // Schedule Interview inline
            if (a.status !== 'approved' && a.status !== 'rejected') {
                html += '<div class="row g-2 mb-4">';
                html += '<div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.75rem">Schedule Interview</label><input type="datetime-local" id="modalInterview" class="form-control form-control-sm" value="'+(a.interview_date ? a.interview_date.replace(' ','T') : '')+'"><button class="btn btn-outline-warning btn-sm mt-1 w-100" onclick="getViewModal().hide();showInterviewModal('+a.id+')">Schedule</button></div>';
                html += '</div>';
            }

            // Notes
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-sticky me-1"></i>Admin Notes</h6>';
            html += '<form onsubmit="event.preventDefault();ajaxAction(\'add_note\',{id:'+a.id+',note:this.note.value});this.note.value=\'\';" class="mb-2"><div class="input-group input-group-sm"><input type="text" name="note" class="form-control" placeholder="Add a note..." required><button class="btn btn-primary"><i class="bi bi-plus"></i> Add</button></div></form>';
            if (notes.length === 0) {
                html += '<p class="text-muted mb-4" style="font-size:.85rem">No notes yet</p>';
            } else {
                notes.forEach(n => {
                    html += '<div class="p-2 rounded mb-2 bg-light" style="font-size:.82rem"><div class="d-flex justify-content-between"><strong>'+(n.user_name||'System')+'</strong><small class="text-muted">'+n.created_at+'</small></div><div class="mt-1">'+escHtml(n.note)+'</div></div>';
                });
                html += '<div class="mb-4"></div>';
            }

            // Timeline
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-clock-history me-1"></i>Status Timeline</h6>';
            if (history.length === 0) {
                html += '<p class="text-muted" style="font-size:.85rem">No status changes recorded</p>';
            } else {
                html += '<div class="position-relative" style="padding-left:20px">';
                history.forEach(h => {
                    html += '<div class="mb-3 position-relative"><div style="position:absolute;left:-20px;top:4px;width:10px;height:10px;border-radius:50%;background:#0d6efd"></div>';
                    html += '<div style="font-size:.82rem"><strong>'+(h.old_status?h.old_status.replace(/_/g,' '):'—')+' → '+h.new_status.replace(/_/g,' ')+'</strong></div>';
                    html += '<div style="font-size:.72rem;color:#6c757d">'+(h.user_name||'System')+' • '+h.created_at+'</div>';
                    if (h.remarks) html += '<div style="font-size:.75rem;color:#6c757d;mt-1">'+escHtml(h.remarks)+'</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            document.getElementById('applicationModalBody').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('applicationModalBody').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Failed: '+err.message+'</div>';
        });
}

function rf(label, value, fullWidth) {
    if (!value && value !== 0) return '<div class="'+(fullWidth?'col-12':'col-md-6')+'"><div class="mb-1"><small class="text-muted d-block" style="font-size:.72rem">'+label+'</small><span style="font-size:.85rem;color:#adb5bd">—</span></div></div>';
    return '<div class="'+(fullWidth?'col-12':'col-md-6')+'"><div class="mb-1"><small class="text-muted d-block" style="font-size:.72rem">'+label+'</small><span style="font-size:.85rem" class="fw-medium">'+escHtml(value)+'</span></div></div>';
}

function statusColor(s) {
    const m = {new:'primary',reviewed:'info',shortlisted:'warning',interview_scheduled:'secondary',approved:'success',rejected:'danger'};
    return m[s]||'secondary';
}
function statusIcon(s) {
    const m = {new:'bi-plus-circle',reviewed:'bi-eye',shortlisted:'bi-star',interview_scheduled:'bi-calendar-event',approved:'bi-check-circle',rejected:'bi-x-circle'};
    return m[s]||'bi-circle';
}
function getNextStatuses(current) {
    const flow = {
        'new': ['reviewed','rejected'],
        'reviewed': ['shortlisted','rejected'],
        'shortlisted': ['interview_scheduled','approved','rejected'],
        'interview_scheduled': ['approved','rejected'],
        'rejected': ['new']
    };
    return flow[current] || [];
}
function escHtml(s) {
    if (!s) return '—';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

const waTemplateStr = <?= json_encode($waTemplate) ?>;
function buildWaMsg(a) {
    return waTemplateStr.replace('{name}', a.full_name||'').replace('{app_id}', a.application_id||'').replace('{position}', a.job_title||'General');
}

function logContact(type, id) {
    const fd = new FormData();
    fd.append('action','log_contact'); fd.append('contact_type',type); fd.append('id',id); fd.append('csrf_token','<?= csrfToken() ?>');
    fetch('ajax/recruitment-actions.php', { method:'POST', body:fd }).catch(()=>{});
}

function copyPhone(phone, id) {
    navigator.clipboard.writeText(phone).then(() => {
        document.getElementById('copyToastMsg').textContent = 'Copied: ' + phone;
        new bootstrap.Toast(document.getElementById('copyToast')).show();
        logContact('call', id);
    });
}

// ==================== CONFIRMATION MODALS ====================
let _scmId=null, _scmNewStatus=null;
function showStatusConfirm(id, newStatus, oldStatus) {
    _scmId=id; _scmNewStatus=newStatus;
    document.getElementById('scmOldStatus').textContent = oldStatus.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase());
    const nb = document.getElementById('scmNewStatus');
    nb.textContent = newStatus.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase());
    nb.className = 'badge bg-'+statusColor(newStatus)+'-subtle text-'+statusColor(newStatus)+' fs-6';
    document.getElementById('scmRemarks').value = '';
    new bootstrap.Modal(document.getElementById('statusConfirmModal')).show();
}
function executeStatusChange() {
    const remarks = document.getElementById('scmRemarks').value;
    bootstrap.Modal.getInstance(document.getElementById('statusConfirmModal')).hide();
    ajaxAction('update_status', {id:_scmId, new_status:_scmNewStatus, remarks:remarks});
}

let _rejectId=null;
function showRejectModal(id) { _rejectId=id; document.getElementById('rejectRemarks').value=''; new bootstrap.Modal(document.getElementById('rejectModal')).show(); }
function executeReject() {
    bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
    ajaxAction('update_status', {id:_rejectId, new_status:'rejected', remarks:document.getElementById('rejectRemarks').value});
}

let _interviewId=null;
function showInterviewModal(id) { _interviewId=id; document.getElementById('interviewDate').value=''; new bootstrap.Modal(document.getElementById('interviewModal')).show(); }
function executeInterview() {
    const dt=document.getElementById('interviewDate').value;
    if(!dt){alert('Select a date');return;}
    bootstrap.Modal.getInstance(document.getElementById('interviewModal')).hide();
    ajaxAction('set_interview', {id:_interviewId, interview_date:dt});
}

let _gcmAction=null, _gcmId=null;
function showConfirmModal(action, title, message, id, style) {
    _gcmAction=action; _gcmId=id;
    document.getElementById('gcmTitle').textContent=title;
    document.getElementById('gcmMessage').textContent=message;
    const h=document.getElementById('gcmHeader'), b=document.getElementById('gcmConfirmBtn');
    if(style==='destructive'){h.className='modal-header bg-danger-subtle';b.className='btn btn-danger btn-sm';}
    else{h.className='modal-header';b.className='btn btn-primary btn-sm';}
    new bootstrap.Modal(document.getElementById('genericConfirmModal')).show();
}
function executeGenericConfirm() {
    bootstrap.Modal.getInstance(document.getElementById('genericConfirmModal')).hide();
    ajaxAction(_gcmAction, {id:_gcmId});
}

function ajaxAction(action, params) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', '<?= csrfToken() ?>');
    for (const [k,v] of Object.entries(params)) fd.append(k, v);

    fetch('ajax/recruitment-actions.php', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(data => {
            if (data.success) {
                if (_currentViewId && !['soft_delete','permanent_delete'].includes(action)) viewApplication(_currentViewId);
                else if (viewModal) viewModal.hide();
                setTimeout(()=>location.reload(), 300);
            } else alert(data.error || 'Action failed');
        })
        .catch(err => alert('Error: ' + err.message));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>