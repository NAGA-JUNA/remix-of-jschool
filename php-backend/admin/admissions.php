<?php
$pageTitle = 'Admissions';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Status definitions
$allStatuses = ['new','contacted','documents_verified','interview_scheduled','approved','rejected','waitlisted','converted'];
$statusColors = [
    'new'=>'primary', 'contacted'=>'info', 'documents_verified'=>'secondary',
    'interview_scheduled'=>'warning', 'approved'=>'success', 'rejected'=>'danger',
    'waitlisted'=>'dark', 'converted'=>'success'
];
$statusIcons = [
    'new'=>'bi-plus-circle', 'contacted'=>'bi-telephone', 'documents_verified'=>'bi-file-check',
    'interview_scheduled'=>'bi-calendar-event', 'approved'=>'bi-check-circle', 'rejected'=>'bi-x-circle',
    'waitlisted'=>'bi-hourglass', 'converted'=>'bi-person-check'
];

// WhatsApp template from settings
$waTemplate = getSetting('whatsapp_admission_template', 'Hello {name}, this is regarding your admission application ({app_id}) for Class {class}. Please contact us for further details.');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $aid = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status' && $aid) {
        $newStatus = $_POST['new_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        if (in_array($newStatus, $allStatuses)) {
            $oldStatus = $db->prepare("SELECT status FROM admissions WHERE id=?");
            $oldStatus->execute([$aid]);
            $oldStatus = $oldStatus->fetchColumn();

            $db->prepare("UPDATE admissions SET status=?, remarks=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$newStatus, $remarks, currentUserId(), $aid]);
            $db->prepare("INSERT INTO admission_status_history (admission_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")->execute([$aid, $oldStatus, $newStatus, currentUserId(), $remarks]);
            auditLog("admission_$newStatus", 'admission', $aid);

            // Send email on key status changes
            if (in_array($newStatus, ['approved','rejected','waitlisted','interview_scheduled'])) {
                try {
                    $adm = $db->prepare("SELECT * FROM admissions WHERE id=?");
                    $adm->execute([$aid]);
                    $adm = $adm->fetch();
                    if ($adm && $adm['email']) {
                        require_once __DIR__.'/../config/mail.php';
                        $schoolName = getSetting('school_name','JNV School');
                        $subjects = [
                            'approved' => "Admission Approved — $schoolName",
                            'rejected' => "Admission Update — $schoolName",
                            'waitlisted' => "Admission Waitlisted — $schoolName",
                            'interview_scheduled' => "Interview Scheduled — $schoolName"
                        ];
                        $bodies = [
                            'approved' => "<h2>Congratulations!</h2><p>Dear {$adm['student_name']},</p><p>Your admission application <strong>{$adm['application_id']}</strong> for Class {$adm['class_applied']} has been <strong style='color:#22c55e'>APPROVED</strong>.</p><p>Please visit the school office to complete the admission process.</p>",
                            'rejected' => "<h2>Admission Update</h2><p>Dear {$adm['student_name']},</p><p>After careful review, we regret to inform you that your application <strong>{$adm['application_id']}</strong> could not be accepted at this time.</p>".($remarks ? "<p><strong>Remarks:</strong> $remarks</p>" : ""),
                            'waitlisted' => "<h2>Application Waitlisted</h2><p>Dear {$adm['student_name']},</p><p>Your application <strong>{$adm['application_id']}</strong> has been placed on the <strong>waitlist</strong> for Class {$adm['class_applied']}. We will notify you if a seat becomes available.</p>",
                            'interview_scheduled' => "<h2>Interview Scheduled</h2><p>Dear {$adm['student_name']},</p><p>An interview has been scheduled for your admission application <strong>{$adm['application_id']}</strong>.</p>".($adm['interview_date'] ? "<p><strong>Date:</strong> ".date('M d, Y h:i A', strtotime($adm['interview_date']))."</p>" : "")
                        ];
                        $emailBody = "<div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:2rem;'>".$bodies[$newStatus]."<hr><p style='color:#64748b;font-size:0.8rem;'>$schoolName | Application: {$adm['application_id']}</p></div>";
                        sendMail($adm['email'], $subjects[$newStatus], $emailBody);
                    }
                } catch (Exception $e) { /* silent */ }
            }

            // Auto-create student on approval
            if ($newStatus === 'approved' && !empty($_POST['create_student'])) {
                $adm = $db->prepare("SELECT * FROM admissions WHERE id=?");
                $adm->execute([$aid]);
                $adm = $adm->fetch();
                if ($adm) {
                    $admNo = 'STU-'.date('Y').'-'.str_pad($aid, 5, '0', STR_PAD_LEFT);
                    $db->prepare("INSERT INTO students (admission_no, name, father_name, mother_name, dob, gender, class, phone, email, address, blood_group, category, aadhar_no, status, admission_date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'active',CURDATE(),?)")
                        ->execute([$admNo, $adm['student_name'], $adm['father_name'], $adm['mother_name'], $adm['dob'], $adm['gender'], $adm['class_applied'], $adm['phone'], $adm['email'], $adm['address'], $adm['blood_group'], $adm['category'], $adm['aadhar_no'], currentUserId()]);
                    $studentId = (int)$db->lastInsertId();
                    $db->prepare("UPDATE admissions SET status='converted', converted_student_id=? WHERE id=?")->execute([$studentId, $aid]);
                    $db->prepare("INSERT INTO admission_status_history (admission_id, old_status, new_status, changed_by, remarks) VALUES (?,'approved','converted',?,'Student record created')")->execute([$aid, currentUserId()]);
                    auditLog('admission_converted', 'admission', $aid, "Student ID: $studentId");
                }
            }

            setFlash('success', "Status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".");
        }
    } elseif ($action === 'add_note' && $aid) {
        $note = trim($_POST['note'] ?? '');
        if ($note) {
            $db->prepare("INSERT INTO admission_notes (admission_id, user_id, note) VALUES (?,?,?)")->execute([$aid, currentUserId(), $note]);
            auditLog('admission_note_added', 'admission', $aid);
            setFlash('success', 'Note added.');
        }
    } elseif ($action === 'set_followup' && $aid) {
        $followUp = $_POST['follow_up_date'] ?? null;
        $db->prepare("UPDATE admissions SET follow_up_date=? WHERE id=?")->execute([$followUp ?: null, $aid]);
        setFlash('success', 'Follow-up date updated.');
    } elseif ($action === 'set_interview' && $aid) {
        $intDate = $_POST['interview_date'] ?? null;
        $oldSt = $db->prepare("SELECT status FROM admissions WHERE id=?");
        $oldSt->execute([$aid]);
        $oldSt = $oldSt->fetchColumn();
        $db->prepare("UPDATE admissions SET interview_date=?, status='interview_scheduled', reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$intDate, currentUserId(), $aid]);
        $db->prepare("INSERT INTO admission_status_history (admission_id, old_status, new_status, changed_by, remarks) VALUES (?,?,'interview_scheduled',?,'Interview scheduled')")->execute([$aid, $oldSt ?: 'new', currentUserId()]);
        setFlash('success', 'Interview scheduled.');
    } elseif ($action === 'soft_delete' && $aid) {
        // Soft delete — Admin+ can archive
        $db->prepare("UPDATE admissions SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")->execute([currentUserId(), $aid]);
        auditLog('admission_archived', 'admission', $aid);
        setFlash('success', 'Admission archived.');
    } elseif ($action === 'restore' && $aid) {
        $db->prepare("UPDATE admissions SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=?")->execute([$aid]);
        auditLog('admission_restored', 'admission', $aid);
        setFlash('success', 'Admission restored.');
    } elseif ($action === 'permanent_delete' && $aid && isSuperAdmin()) {
        $db->prepare("DELETE FROM admission_notes WHERE admission_id=?")->execute([$aid]);
        $db->prepare("DELETE FROM admission_status_history WHERE admission_id=?")->execute([$aid]);
        $db->prepare("DELETE FROM admissions WHERE id=?")->execute([$aid]);
        auditLog('admission_deleted', 'admission', $aid);
        setFlash('success', 'Admission permanently deleted.');
    }

    header('Location: /admin/admissions.php?' . http_build_query(array_filter(['status'=>$_GET['status']??'','search'=>$_GET['search']??'','class'=>$_GET['class']??'','tab'=>$_GET['tab']??''])));
    exit;
}

// Archive tab detection
$activeTab = $_GET['tab'] ?? 'active';
$isArchiveTab = ($activeTab === 'archived');

// KPI counts (only active records)
$kpiNew = $db->query("SELECT COUNT(*) FROM admissions WHERE status='new' AND (is_deleted=0 OR is_deleted IS NULL) AND DATE(created_at)=CURDATE()")->fetchColumn();
$kpiPending = $db->query("SELECT COUNT(*) FROM admissions WHERE status IN ('new','contacted') AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiApproved = $db->query("SELECT COUNT(*) FROM admissions WHERE status='approved' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiRejected = $db->query("SELECT COUNT(*) FROM admissions WHERE status='rejected' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiWaitlisted = $db->query("SELECT COUNT(*) FROM admissions WHERE status='waitlisted' AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiTotal = $db->query("SELECT COUNT(*) FROM admissions WHERE (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn();
$kpiArchived = $db->query("SELECT COUNT(*) FROM admissions WHERE is_deleted=1")->fetchColumn();
$kpiConversion = $kpiTotal > 0 ? round(($kpiApproved / $kpiTotal) * 100, 1) : 0;

// Status counts for tabs (active records only)
$statusCounts = [];
$scStmt = $db->query("SELECT status, COUNT(*) as c FROM admissions WHERE (is_deleted=0 OR is_deleted IS NULL) GROUP BY status");
while ($r = $scStmt->fetch()) $statusCounts[$r['status']] = (int)$r['c'];

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$classFilter = $_GET['class'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];

// Archive filter
if ($isArchiveTab) {
    $where[] = "a.is_deleted=1";
} else {
    $where[] = "(a.is_deleted=0 OR a.is_deleted IS NULL)";
}

if ($statusFilter && in_array($statusFilter, $allStatuses)) { $where[] = "a.status=?"; $params[] = $statusFilter; }
if ($searchQuery) { $where[] = "(a.student_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ? OR a.application_id LIKE ? OR a.father_name LIKE ?)"; $s = "%$searchQuery%"; $params = array_merge($params, [$s,$s,$s,$s,$s]); }
if ($classFilter) { $where[] = "a.class_applied=?"; $params[] = $classFilter; }
if ($dateFrom) { $where[] = "DATE(a.created_at)>=?"; $params[] = $dateFrom; }
if ($dateTo) { $where[] = "DATE(a.created_at)<=?"; $params[] = $dateTo; }

$whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';
$total = $db->prepare("SELECT COUNT(*) FROM admissions a $whereClause");
$total->execute($params);
$total = $total->fetchColumn();
$p = paginate($total, 20, $page);

$stmt = $db->prepare("SELECT a.*, u.name as reviewer_name FROM admissions a LEFT JOIN users u ON a.reviewed_by=u.id $whereClause ORDER BY a.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
$stmt->execute($params);
$admissions = $stmt->fetchAll();

// Check duplicates
$dupPhones = [];
try {
    $dupStmt = $db->query("SELECT phone, COUNT(*) as c FROM admissions WHERE phone IS NOT NULL AND (is_deleted=0 OR is_deleted IS NULL) GROUP BY phone HAVING c > 1");
    while ($r = $dupStmt->fetch()) $dupPhones[$r['phone']] = (int)$r['c'];
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}
.action-btn:hover { transform: scale(1.15); }
.action-btn:active { transform: scale(0.95); }

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
        ['New Today', $kpiNew, 'bi-plus-circle-fill', 'primary'],
        ['Pending', $kpiPending, 'bi-clock-fill', 'warning'],
        ['Approved', $kpiApproved, 'bi-check-circle-fill', 'success'],
        ['Rejected', $kpiRejected, 'bi-x-circle-fill', 'danger'],
        ['Waitlisted', $kpiWaitlisted, 'bi-hourglass-split', 'dark'],
        ['Conversion', $kpiConversion.'%', 'bi-graph-up-arrow', 'info'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-<?= $k[3] ?>-subtle text-<?= $k[3] ?>"><i class="bi <?= $k[2] ?>"></i></div>
                    <div>
                        <div class="fs-3 fw-bold"><?= $k[1] ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= $k[0] ?></div>
                    </div>
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
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, phone, email, app ID..." value="<?= e($searchQuery) ?>">
            </div>
            <div class="col-md-2">
                <select name="class" class="form-select form-select-sm">
                    <option value="">All Classes</option>
                    <?php for ($i=1;$i<=12;$i++): ?><option value="<?= $i ?>" <?= $classFilter==(string)$i?'selected':'' ?>>Class <?= $i ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach ($allStatuses as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>" placeholder="To">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                <a href="/admin/admissions.php?tab=<?= e($activeTab) ?>" class="btn btn-outline-secondary btn-sm" title="Clear"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Active / Archived Toggle + Status Tabs -->
<div class="d-flex align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        <a href="/admin/admissions.php?tab=active" class="btn <?= !$isArchiveTab ? 'btn-primary' : 'btn-outline-primary' ?>">
            <i class="bi bi-inbox me-1"></i>Active <span class="badge bg-light text-dark ms-1"><?= $kpiTotal ?></span>
        </a>
        <a href="/admin/admissions.php?tab=archived" class="btn <?= $isArchiveTab ? 'btn-danger' : 'btn-outline-danger' ?>">
            <i class="bi bi-archive me-1"></i>Archived <span class="badge bg-light text-dark ms-1"><?= $kpiArchived ?></span>
        </a>
    </div>
</div>

<?php if (!$isArchiveTab): ?>
<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" style="gap:4px;">
    <li class="nav-item"><a href="/admin/admissions.php?<?= http_build_query(array_merge($_GET, ['status'=>'','tab'=>'active'])) ?>" class="nav-link <?= !$statusFilter?'active':'' ?> btn-sm">All <span class="badge bg-light text-dark ms-1"><?= $kpiTotal ?></span></a></li>
    <?php foreach ($allStatuses as $s): if ($s==='converted') continue; ?>
    <li class="nav-item"><a href="/admin/admissions.php?<?= http_build_query(array_merge($_GET, ['status'=>$s,'tab'=>'active'])) ?>" class="nav-link <?= $statusFilter===$s?'active':'' ?> btn-sm"><?= ucfirst(str_replace('_',' ',$s)) ?> <span class="badge bg-light text-dark ms-1"><?= $statusCounts[$s] ?? 0 ?></span></a></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- Export -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <small class="text-muted"><?= $total ?> admission(s) found</small>
    <a href="ajax/admission-actions.php?action=export_csv&<?= http_build_query(array_filter(['status'=>$statusFilter,'search'=>$searchQuery,'class'=>$classFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo])) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>

<!-- Table -->
<div class="card border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>App ID</th><th>Student</th><th>Class</th><th>Phone</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($admissions)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No admissions found</td></tr>
                <?php else: foreach ($admissions as $a):
                    $isDup = isset($dupPhones[$a['phone']]);
                    $sc = $statusColors[$a['status']] ?? 'secondary';
                    $phone = $a['phone'] ?? '';
                    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
                    // Build WhatsApp message from template
                    $waMsg = str_replace(
                        ['{name}', '{app_id}', '{class}', '{father}'],
                        [$a['student_name'], $a['application_id'] ?? '', $a['class_applied'] ?? '', $a['father_name'] ?? ''],
                        $waTemplate
                    );
                ?>
                    <tr style="cursor:pointer" onclick="viewAdmission(<?= $a['id'] ?>)" class="admission-row">
                        <td><?= $a['id'] ?></td>
                        <td><code style="font-size:.8rem"><?= e($a['application_id'] ?? 'N/A') ?></code></td>
                        <td style="font-size:.85rem">
                            <strong><?= e($a['student_name']) ?></strong>
                            <?php if ($isDup): ?><i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Duplicate phone detected" style="font-size:.75rem"></i><?php endif; ?>
                            <br><small class="text-muted">F: <?= e($a['father_name'] ?? '-') ?></small>
                        </td>
                        <td>Class <?= e($a['class_applied']) ?></td>
                        <td style="font-size:.85rem"><?= e($a['phone'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                        <td style="font-size:.8rem"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                        <td onclick="event.stopPropagation()">
                            <div class="d-flex align-items-center gap-1 flex-nowrap">
                                <!-- View -->
                                <button class="action-btn action-btn-view" onclick="viewAdmission(<?= $a['id'] ?>)" title="View Details"><i class="bi bi-eye"></i></button>

                                <!-- WhatsApp -->
                                <?php if ($cleanPhone): ?>
                                <a href="https://wa.me/<?= ltrim($cleanPhone, '+') ?>?text=<?= urlencode($waMsg) ?>" target="_blank" class="action-btn action-btn-whatsapp" title="WhatsApp" onclick="logContact('whatsapp',<?= $a['id'] ?>)"><i class="bi bi-whatsapp"></i></a>
                                <?php endif; ?>

                                <!-- Call -->
                                <?php if ($cleanPhone): ?>
                                <a href="tel:<?= $cleanPhone ?>" class="action-btn action-btn-call d-md-none" title="Call" onclick="logContact('call',<?= $a['id'] ?>)"><i class="bi bi-telephone-fill"></i></a>
                                <button class="action-btn action-btn-call d-none d-md-inline-flex" title="Copy Number" onclick="copyPhone('<?= e($phone) ?>', <?= $a['id'] ?>)"><i class="bi bi-telephone-fill"></i></button>
                                <?php endif; ?>

                                <?php if ($isArchiveTab): ?>
                                    <!-- Archive tab: Restore + Permanent Delete -->
                                    <button class="action-btn action-btn-success" onclick="showConfirmModal('restore','Restore Application','Are you sure you want to restore this admission?',<?= $a['id'] ?>)" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php if (isSuperAdmin()): ?>
                                    <button class="action-btn action-btn-danger" onclick="showConfirmModal('permanent_delete','Permanently Delete','This will permanently delete this admission and all related data. This cannot be undone!',<?= $a['id'] ?>,'destructive')" title="Permanent Delete"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Active tab: Status actions -->
                                    <?php
                                    $s = $a['status'];
                                    $id = $a['id'];
                                    if ($s === 'new'): ?>
                                        <button class="action-btn action-btn-info" onclick="showStatusConfirm(<?=$id?>,'contacted','new')" title="Mark Contacted"><i class="bi bi-telephone"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'contacted'): ?>
                                        <button class="action-btn action-btn-info" onclick="showStatusConfirm(<?=$id?>,'documents_verified','contacted')" title="Docs Verified"><i class="bi bi-file-earmark-check"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'documents_verified'): ?>
                                        <button class="action-btn action-btn-warning" onclick="showInterviewModal(<?=$id?>)" title="Schedule Interview"><i class="bi bi-calendar-event"></i></button>
                                        <button class="action-btn action-btn-success" onclick="showStatusConfirm(<?=$id?>,'approved','documents_verified')" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'interview_scheduled'): ?>
                                        <button class="action-btn action-btn-success" onclick="showStatusConfirm(<?=$id?>,'approved','interview_scheduled')" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        <button class="action-btn action-btn-warning" onclick="showStatusConfirm(<?=$id?>,'waitlisted','interview_scheduled')" title="Waitlist"><i class="bi bi-hourglass-split"></i></button>
                                    <?php elseif ($s === 'waitlisted'): ?>
                                        <button class="action-btn action-btn-success" onclick="showStatusConfirm(<?=$id?>,'approved','waitlisted')" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        <button class="action-btn action-btn-danger" onclick="showRejectModal(<?=$id?>)" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    <?php elseif ($s === 'approved'): ?>
                                        <button class="action-btn action-btn-success" onclick="showConvertModal(<?=$id?>)" title="Create Student"><i class="bi bi-person-plus"></i></button>
                                    <?php elseif ($s === 'rejected'): ?>
                                        <button class="action-btn action-btn-warning" onclick="showStatusConfirm(<?=$id?>,'new','rejected')" title="Reopen"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php endif; ?>

                                    <!-- Archive button (soft delete) -->
                                    <button class="action-btn action-btn-secondary" onclick="showConfirmModal('soft_delete','Archive Application','Move this admission to the archive? You can restore it later.',<?= $a['id'] ?>)" title="Archive"><i class="bi bi-archive"></i></button>
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
<?= paginationHtml($p, '/admin/admissions.php?' . http_build_query(array_filter(['status'=>$statusFilter,'search'=>$searchQuery,'class'=>$classFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo,'tab'=>$activeTab]))) ?>

<!-- ==================== VIEW ADMISSION MODAL ==================== -->
<div class="modal fade" id="viewAdmissionModal" tabindex="-1" aria-labelledby="viewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="viewModalTitle">Loading...</h5>
                    <small class="text-muted" id="viewModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="admissionModalBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STATUS CONFIRMATION MODAL ==================== -->
<div class="modal fade" id="statusConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Confirm Status Change</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <span class="badge bg-secondary-subtle text-secondary fs-6" id="scmOldStatus">—</span>
                    <i class="bi bi-arrow-right mx-2 text-muted"></i>
                    <span class="badge fs-6" id="scmNewStatus">—</span>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold" style="font-size:.8rem">Remarks (optional)</label>
                    <textarea id="scmRemarks" class="form-control form-control-sm" rows="2" placeholder="Add remarks..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="scmConfirmBtn" onclick="executeStatusChange()"><i class="bi bi-check-lg me-1"></i>Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REJECT MODAL ==================== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger-subtle">
                <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-1"></i>Reject Application</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:.85rem" class="mb-2">Are you sure you want to reject this application?</p>
                <label class="form-label fw-semibold" style="font-size:.8rem">Rejection Remarks</label>
                <textarea id="rejectRemarks" class="form-control form-control-sm" rows="3" placeholder="Reason for rejection..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="executeReject()"><i class="bi bi-x-lg me-1"></i>Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== INTERVIEW SCHEDULE MODAL ==================== -->
<div class="modal fade" id="interviewModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h6 class="modal-title"><i class="bi bi-calendar-event me-1"></i>Schedule Interview</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
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

<!-- ==================== GENERIC CONFIRMATION MODAL ==================== -->
<div class="modal fade" id="genericConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="gcmHeader">
                <h6 class="modal-title" id="gcmTitle">Confirm</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:.85rem" id="gcmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm" id="gcmConfirmBtn" onclick="executeGenericConfirm()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CONVERT TO STUDENT MODAL ==================== -->
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success-subtle">
                <h6 class="modal-title text-success"><i class="bi bi-person-plus me-1"></i>Create Student Record</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:.85rem" class="mb-0">This will create a new student record from the admission data and mark it as converted.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="convertForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" id="convertId">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="new_status" value="approved">
                    <input type="hidden" name="create_student" value="1">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-person-plus me-1"></i>Create Student</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DOCUMENT LIGHTBOX ==================== -->
<div class="modal fade" id="docLightbox" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <span class="text-white" id="lightboxTitle">Document</span>
                <div class="d-flex gap-2">
                    <a href="#" id="lightboxDownload" class="btn btn-outline-light btn-sm" download><i class="bi bi-download"></i></a>
                    <button type="button" id="lightboxFullscreen" class="btn btn-outline-light btn-sm" onclick="toggleDocFullscreen()"><i class="bi bi-arrows-fullscreen"></i></button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0 text-center" id="lightboxBody" style="min-height:60vh">
                <!-- Content injected by JS -->
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
// ==================== VIEW ADMISSION MODAL ====================
let viewModal = null;
let _currentViewId = null;

function getViewModal() {
    if (!viewModal) {
        viewModal = new bootstrap.Modal(document.getElementById('viewAdmissionModal'));
    }
    return viewModal;
}

function viewAdmission(id) {
    _currentViewId = id;
    const modal = getViewModal();
    document.getElementById('viewModalTitle').textContent = 'Loading...';
    document.getElementById('viewModalSubtitle').textContent = '';
    document.getElementById('admissionModalBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch('ajax/admission-actions.php?action=get_detail&id=' + id, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(text => {
            try { return JSON.parse(text); }
            catch(e) { throw new Error('Invalid JSON response: ' + text.substring(0, 100)); }
        })
        .then(data => {
            if (!data.success) {
                document.getElementById('admissionModalBody').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error loading data</div>';
                return;
            }
            const a = data.admission;
            const notes = data.notes || [];
            const history = data.history || [];
            const docs = data.documents || {};

            // Header
            document.getElementById('viewModalTitle').innerHTML = (a.application_id || 'Application #' + a.id) + ' <span class="badge bg-'+statusColor(a.status)+'-subtle text-'+statusColor(a.status)+' ms-2">'+a.status.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())+'</span>';
            document.getElementById('viewModalSubtitle').textContent = 'Submitted ' + a.created_at;

            let html = '';

            // Quick actions bar
            const phone = a.phone || '';
            const cleanPhone = phone.replace(/[^0-9+]/g, '');
            html += '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3 border-bottom">';
            if (a.priority && a.priority !== 'normal') html += '<span class="badge bg-danger">'+a.priority.toUpperCase()+' PRIORITY</span>';
            if (cleanPhone) {
                html += '<div class="d-flex gap-1">';
                html += '<a href="https://wa.me/'+cleanPhone.replace('+','')+'?text='+encodeURIComponent(buildWaMsg(a))+'" target="_blank" class="btn btn-success btn-sm" onclick="logContact(\'whatsapp\','+a.id+')"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>';
                html += '<button class="btn btn-outline-info btn-sm" onclick="copyPhone(\''+escHtml(phone)+'\','+a.id+')"><i class="bi bi-telephone-fill me-1"></i>Copy Phone</button>';
                html += '</div>';
            }
            // Quick status buttons
            if (a.status !== 'converted') {
                html += '<div class="d-flex gap-1 flex-wrap">';
                getNextStatuses(a.status).forEach(ns => {
                    html += '<button class="btn btn-outline-'+statusColor(ns)+' btn-sm py-0 px-2" onclick="getViewModal().hide();showStatusConfirm('+a.id+',\''+ns+'\',\''+a.status+'\')"><i class="bi '+statusIcon(ns)+' me-1"></i>'+ns.replace(/_/g,' ')+'</button>';
                });
                html += '</div>';
            }
            html += '</div>';

            // ===== STUDENT INFORMATION =====
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-person me-1"></i>Student Information</h6>';
            html += '<div class="row g-2 mb-4">';
            html += renderField('Application ID', a.application_id);
            html += renderField('Student Name', a.student_name);
            html += renderField('Date of Birth', a.dob);
            html += renderField('Gender', a.gender);
            html += renderField('Blood Group', a.blood_group);
            html += renderField('Category', a.category);
            html += renderField('Aadhar No', a.aadhar_no);
            html += renderField('Class Applied', a.class_applied ? 'Class ' + a.class_applied : null);
            html += renderField('Previous School', a.previous_school);
            html += renderField('Religion', a.religion);
            html += renderField('Nationality', a.nationality);
            html += '</div>';

            // ===== PARENT INFORMATION =====
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-people me-1"></i>Parent / Guardian Information</h6>';
            html += '<div class="row g-2 mb-4">';
            html += renderField('Father Name', a.father_name);
            html += renderField('Father Phone', a.father_phone);
            html += renderField('Father Occupation', a.father_occupation);
            html += renderField('Mother Name', a.mother_name);
            html += renderField('Mother Phone', a.mother_phone);
            html += renderField('Mother Occupation', a.mother_occupation);
            html += renderField('Guardian Name', a.guardian_name);
            html += renderField('Guardian Phone', a.guardian_phone);
            html += renderField('Guardian Relation', a.guardian_relation);
            html += '</div>';

            // ===== CONTACT & ADDRESS =====
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-geo-alt me-1"></i>Contact & Address</h6>';
            html += '<div class="row g-2 mb-4">';
            html += renderField('Phone', a.phone);
            html += renderField('Email', a.email);
            html += renderField('Address', a.address, true);
            html += renderField('Village/Town', a.village);
            html += renderField('District', a.district);
            html += renderField('State', a.state);
            html += renderField('PIN Code', a.pincode);
            html += '</div>';

            // ===== DOCUMENTS =====
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-file-earmark me-1"></i>Documents</h6>';
            if (Object.keys(docs).length === 0) {
                html += '<p class="text-muted mb-4" style="font-size:.85rem">No documents uploaded</p>';
            } else {
                html += '<div class="row g-2 mb-4">';
                for (const [label, path] of Object.entries(docs)) {
                    const isImg = /\.(jpg|jpeg|png|webp|gif)$/i.test(path);
                    const isPdf = /\.pdf$/i.test(path);
                    const displayLabel = label.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase());
                    html += '<div class="col-md-6">';
                    html += '<div class="border rounded p-2 d-flex align-items-center gap-2" style="cursor:pointer" onclick="openDocPreview(\''+escAttr(label)+'\',\''+escAttr(path)+'\','+isImg+','+isPdf+')">';
                    if (isImg) {
                        html += '<img src="/'+escAttr(path)+'" class="rounded" style="width:50px;height:50px;object-fit:cover">';
                    } else {
                        html += '<div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;background:#fee2e2;border-radius:8px"><i class="bi bi-file-pdf text-danger" style="font-size:1.3rem"></i></div>';
                    }
                    html += '<div class="flex-grow-1"><div class="fw-semibold" style="font-size:.82rem">'+displayLabel+'</div><small class="text-muted">'+(isImg?'Image':'PDF')+' — Click to preview</small></div>';
                    html += '<a href="/'+escAttr(path)+'" class="btn btn-outline-secondary btn-sm py-0" download title="Download" onclick="event.stopPropagation()"><i class="bi bi-download"></i></a>';
                    html += '</div></div>';
                }
                html += '</div>';
            }

            // ===== ADMIN FIELDS =====
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-gear me-1"></i>Admin Fields</h6>';
            html += '<div class="row g-2 mb-4">';
            html += renderField('Status', a.status ? a.status.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : null);
            html += renderField('Source', a.source ? a.source.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : null);
            html += renderField('Priority', a.priority ? a.priority.replace(/\b\w/g,l=>l.toUpperCase()) : null);
            html += renderField('Remarks', a.remarks, true);
            html += renderField('Follow-up Date', a.follow_up_date);
            html += renderField('Interview Date', a.interview_date);
            html += renderField('Reviewed By', a.reviewer_name);
            html += renderField('Reviewed At', a.reviewed_at);
            html += renderField('Converted Student ID', a.converted_student_id ? 'STU-'+a.converted_student_id : null);
            html += renderField('Created At', a.created_at);
            html += renderField('Updated At', a.updated_at);
            html += '</div>';

            // Follow-up & interview forms
            if (a.status !== 'converted') {
                html += '<div class="row g-2 mb-4">';
                html += '<div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.75rem">Set Follow-up Date</label><input type="date" id="modalFollowup" class="form-control form-control-sm" value="'+(a.follow_up_date||'')+'"><button class="btn btn-outline-primary btn-sm mt-1 w-100" onclick="ajaxAction(\'set_followup\',{id:'+a.id+',follow_up_date:document.getElementById(\'modalFollowup\').value})">Set Follow-up</button></div>';
                html += '<div class="col-md-6"><label class="form-label fw-semibold" style="font-size:.75rem">Schedule Interview</label><input type="datetime-local" id="modalInterview" class="form-control form-control-sm" value="'+(a.interview_date ? a.interview_date.replace(' ','T') : '')+'"><button class="btn btn-outline-warning btn-sm mt-1 w-100" onclick="getViewModal().hide();showInterviewModal('+a.id+')">Schedule</button></div>';
                html += '</div>';
            }

            // ===== NOTES =====
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

            // ===== TIMELINE =====
            html += '<h6 class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.5px"><i class="bi bi-clock-history me-1"></i>Status Timeline</h6>';
            if (history.length === 0) {
                html += '<p class="text-muted" style="font-size:.85rem">No status changes recorded</p>';
            } else {
                html += '<div class="position-relative" style="padding-left:20px">';
                history.forEach(h => {
                    html += '<div class="mb-3 position-relative"><div style="position:absolute;left:-20px;top:4px;width:10px;height:10px;border-radius:50%;background:#0d6efd"></div>';
                    html += '<div style="font-size:.82rem"><strong>'+(h.old_status?h.old_status.replace(/_/g,' '):'—')+' → '+h.new_status.replace(/_/g,' ')+'</strong></div>';
                    html += '<div style="font-size:.72rem;color:#6c757d">'+(h.user_name||'System')+' • '+h.created_at+'</div>';
                    if (h.remarks) html += '<div style="font-size:.75rem;color:#6c757d;margin-top:2px">'+escHtml(h.remarks)+'</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            document.getElementById('admissionModalBody').innerHTML = html;
        })
        .catch(err => {
            console.error('Modal fetch error:', err);
            document.getElementById('admissionModalBody').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Failed to load: '+err.message+'</div>';
        });
}

// Helper: render a single field as col-md-6
function renderField(label, value, fullWidth) {
    if (!value && value !== 0) return '<div class="'+(fullWidth?'col-12':'col-md-6')+'"><div class="mb-1"><small class="text-muted d-block" style="font-size:.72rem">'+label+'</small><span style="font-size:.85rem;color:#adb5bd">—</span></div></div>';
    return '<div class="'+(fullWidth?'col-12':'col-md-6')+'"><div class="mb-1"><small class="text-muted d-block" style="font-size:.72rem">'+label+'</small><span style="font-size:.85rem" class="fw-medium">'+escHtml(value)+'</span></div></div>';
}

// ==================== HELPER FUNCTIONS ====================

function statusColor(s) {
    const m = {new:'primary',contacted:'info',documents_verified:'secondary',interview_scheduled:'warning',approved:'success',rejected:'danger',waitlisted:'dark',converted:'success'};
    return m[s]||'secondary';
}
function statusIcon(s) {
    const m = {new:'bi-plus-circle',contacted:'bi-telephone',documents_verified:'bi-file-check',interview_scheduled:'bi-calendar-event',approved:'bi-check-circle',rejected:'bi-x-circle',waitlisted:'bi-hourglass',converted:'bi-person-check'};
    return m[s]||'bi-circle';
}
function getNextStatuses(current) {
    const flow = {
        'new': ['contacted','rejected'],
        'contacted': ['documents_verified','rejected'],
        'documents_verified': ['interview_scheduled','approved','rejected'],
        'interview_scheduled': ['approved','rejected','waitlisted'],
        'approved': ['converted','waitlisted'],
        'waitlisted': ['approved','rejected'],
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
function escAttr(s) {
    return String(s).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// ==================== WHATSAPP TEMPLATE ====================
const waTemplateStr = <?= json_encode($waTemplate) ?>;
function buildWaMsg(a) {
    return waTemplateStr
        .replace('{name}', a.student_name || '')
        .replace('{app_id}', a.application_id || '')
        .replace('{class}', a.class_applied || '')
        .replace('{father}', a.father_name || '');
}

// ==================== CONTACT LOGGING ====================
function logContact(type, id) {
    const fd = new FormData();
    fd.append('action', 'log_contact');
    fd.append('contact_type', type);
    fd.append('id', id);
    fd.append('csrf_token', '<?= csrfToken() ?>');
    fetch('ajax/admission-actions.php', { method: 'POST', body: fd }).catch(() => {});
}

function copyPhone(phone, id) {
    navigator.clipboard.writeText(phone).then(() => {
        document.getElementById('copyToastMsg').textContent = 'Phone copied: ' + phone;
        new bootstrap.Toast(document.getElementById('copyToast')).show();
        logContact('call', id);
    });
}

// ==================== CONFIRMATION MODALS ====================
let _scmId = null, _scmNewStatus = null;
function showStatusConfirm(id, newStatus, oldStatus) {
    _scmId = id;
    _scmNewStatus = newStatus;
    const oldLabel = oldStatus.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    const newLabel = newStatus.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('scmOldStatus').textContent = oldLabel;
    const newBadge = document.getElementById('scmNewStatus');
    newBadge.textContent = newLabel;
    newBadge.className = 'badge bg-'+statusColor(newStatus)+'-subtle text-'+statusColor(newStatus)+' fs-6';
    document.getElementById('scmRemarks').value = '';
    new bootstrap.Modal(document.getElementById('statusConfirmModal')).show();
}

function executeStatusChange() {
    const remarks = document.getElementById('scmRemarks').value;
    bootstrap.Modal.getInstance(document.getElementById('statusConfirmModal')).hide();
    ajaxAction('update_status', {id: _scmId, new_status: _scmNewStatus, remarks: remarks});
}

let _rejectId = null;
function showRejectModal(id) {
    _rejectId = id;
    document.getElementById('rejectRemarks').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
function executeReject() {
    const remarks = document.getElementById('rejectRemarks').value;
    bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
    ajaxAction('update_status', {id: _rejectId, new_status: 'rejected', remarks: remarks});
}

let _interviewId = null;
function showInterviewModal(id) {
    _interviewId = id;
    document.getElementById('interviewDate').value = '';
    new bootstrap.Modal(document.getElementById('interviewModal')).show();
}
function executeInterview() {
    const dt = document.getElementById('interviewDate').value;
    if (!dt) { alert('Please select a date'); return; }
    bootstrap.Modal.getInstance(document.getElementById('interviewModal')).hide();
    ajaxAction('set_interview', {id: _interviewId, interview_date: dt});
}

let _gcmAction = null, _gcmId = null;
function showConfirmModal(action, title, message, id, style) {
    _gcmAction = action;
    _gcmId = id;
    document.getElementById('gcmTitle').textContent = title;
    document.getElementById('gcmMessage').textContent = message;
    const header = document.getElementById('gcmHeader');
    const btn = document.getElementById('gcmConfirmBtn');
    if (style === 'destructive') {
        header.className = 'modal-header bg-danger-subtle';
        btn.className = 'btn btn-danger btn-sm';
    } else {
        header.className = 'modal-header';
        btn.className = 'btn btn-primary btn-sm';
    }
    new bootstrap.Modal(document.getElementById('genericConfirmModal')).show();
}
function executeGenericConfirm() {
    bootstrap.Modal.getInstance(document.getElementById('genericConfirmModal')).hide();
    ajaxAction(_gcmAction, {id: _gcmId});
}

function showConvertModal(id) {
    document.getElementById('convertId').value = id;
    new bootstrap.Modal(document.getElementById('convertModal')).show();
}

// ==================== DOCUMENT VIEWER ====================
function openDocPreview(label, path, isImg, isPdf) {
    const title = label.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('lightboxTitle').textContent = title;
    document.getElementById('lightboxDownload').href = '/' + path;
    const body = document.getElementById('lightboxBody');

    if (isImg) {
        body.innerHTML = '<img src="/'+path+'" style="max-width:100%;max-height:80vh;object-fit:contain;cursor:zoom-in" onclick="this.style.transform=this.style.transform===\'scale(2)\'?\'scale(1)\':\'scale(2)\';this.style.transition=\'transform 0.3s\'" />';
    } else if (isPdf) {
        body.innerHTML = '<iframe src="/'+path+'" style="width:100%;height:80vh;border:none"></iframe>';
    } else {
        body.innerHTML = '<div class="p-5 text-white"><i class="bi bi-file-earmark fs-1 d-block mb-3"></i><p>Preview not available for this file type.</p><a href="/'+path+'" class="btn btn-outline-light" download>Download File</a></div>';
    }
    new bootstrap.Modal(document.getElementById('docLightbox')).show();
}

function toggleDocFullscreen() {
    const el = document.getElementById('docLightbox');
    if (!document.fullscreenElement) {
        el.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen();
    }
}

// ==================== AJAX ACTION ====================
function ajaxAction(action, params, confirmMsg) {
    // No more browser confirm() — all confirmations handled by modals above
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', '<?= csrfToken() ?>');
    for (const [k,v] of Object.entries(params)) fd.append(k, v);

    fetch('ajax/admission-actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (_currentViewId && !['delete','soft_delete','permanent_delete'].includes(action)) {
                    viewAdmission(_currentViewId);
                } else if (viewModal) {
                    viewModal.hide();
                }
                setTimeout(() => location.reload(), 300);
            } else {
                alert(data.error || 'Action failed');
            }
        })
        .catch(err => alert('Error: ' + err.message));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>