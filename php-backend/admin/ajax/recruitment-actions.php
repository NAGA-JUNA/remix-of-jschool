<?php
require_once __DIR__ . '/../../includes/auth.php';

// AJAX-aware auth check
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
}

requireAdmin();
$db = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============ POST Actions ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $aid = (int)($_POST['id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    if (!$csrf || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']);
        exit;
    }

    // ====== UPDATE STATUS ======
    if ($action === 'update_status' && $aid) {
        $allStatuses = ['new','reviewed','shortlisted','interview_scheduled','approved','rejected'];
        $newStatus = $_POST['new_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        if (!in_array($newStatus, $allStatuses)) {
            echo json_encode(['success'=>false,'error'=>'Invalid status']);
            exit;
        }
        $oldSt = $db->prepare("SELECT status FROM teacher_applications WHERE id=? AND (is_deleted=0 OR is_deleted IS NULL)");
        $oldSt->execute([$aid]);
        $oldStatus = $oldSt->fetchColumn();
        if (!$oldStatus) { echo json_encode(['success'=>false,'error'=>'Application not found']); exit; }

        $db->prepare("UPDATE teacher_applications SET status=?, admin_notes=CASE WHEN ?!='' THEN ? ELSE admin_notes END, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$newStatus, $remarks, $remarks, currentUserId(), $aid]);
        $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")
            ->execute([$aid, $oldStatus, $newStatus, currentUserId(), $remarks]);
        auditLog("recruitment_$newStatus", 'teacher_application', $aid);

        // Email on key status changes
        if (in_array($newStatus, ['approved','rejected','shortlisted','interview_scheduled'])) {
            try {
                $app = $db->prepare("SELECT a.*, j.title as job_title FROM teacher_applications a LEFT JOIN job_openings j ON a.job_opening_id=j.id WHERE a.id=?");
                $app->execute([$aid]);
                $app = $app->fetch();
                if ($app && $app['email']) {
                    require_once __DIR__.'/../../config/mail.php';
                    $schoolName = getSetting('school_name','JNV School');
                    $posTitle = $app['job_title'] ?: 'Teaching Position';
                    $subjects = [
                        'approved' => "Application Approved — $schoolName",
                        'rejected' => "Application Update — $schoolName",
                        'shortlisted' => "You've Been Shortlisted — $schoolName",
                        'interview_scheduled' => "Interview Scheduled — $schoolName"
                    ];
                    $bodies = [
                        'approved' => "<h2>Congratulations!</h2><p>Dear {$app['full_name']},</p><p>Your application <strong>{$app['application_id']}</strong> for <strong>$posTitle</strong> has been <strong style='color:#22c55e'>APPROVED</strong>.</p><p>We will contact you with further details shortly.</p>",
                        'rejected' => "<h2>Application Update</h2><p>Dear {$app['full_name']},</p><p>After careful review, your application <strong>{$app['application_id']}</strong> for <strong>$posTitle</strong> could not be accepted at this time.</p>".($remarks ? "<p><strong>Remarks:</strong> $remarks</p>" : ""),
                        'shortlisted' => "<h2>You've Been Shortlisted!</h2><p>Dear {$app['full_name']},</p><p>We're pleased to inform you that your application <strong>{$app['application_id']}</strong> for <strong>$posTitle</strong> has been <strong style='color:#f59e0b'>SHORTLISTED</strong>.</p><p>We will contact you regarding the next steps.</p>",
                        'interview_scheduled' => "<h2>Interview Scheduled</h2><p>Dear {$app['full_name']},</p><p>An interview has been scheduled for your application <strong>{$app['application_id']}</strong> for <strong>$posTitle</strong>.</p>".($app['interview_date'] ? "<p><strong>Date:</strong> ".date('M d, Y h:i A', strtotime($app['interview_date']))."</p>" : "")
                    ];
                    $emailBody = "<div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:2rem;'>".$bodies[$newStatus]."<hr><p style='color:#64748b;font-size:0.8rem;'>$schoolName | Application: {$app['application_id']}</p></div>";
                    sendMail($app['email'], $subjects[$newStatus], $emailBody);
                }
            } catch (Exception $e) { /* silent */ }
        }

        echo json_encode(['success'=>true,'message'=>'Status updated to '.ucfirst(str_replace('_',' ',$newStatus))]);
        exit;
    }

    // ====== ADD NOTE ======
    if ($action === 'add_note' && $aid) {
        $note = trim($_POST['note'] ?? '');
        if (!$note) { echo json_encode(['success'=>false,'error'=>'Note required']); exit; }
        $db->prepare("INSERT INTO application_notes (application_id, user_id, note) VALUES (?,?,?)")->execute([$aid, currentUserId(), $note]);
        auditLog('recruitment_note_added', 'teacher_application', $aid);
        echo json_encode(['success'=>true,'message'=>'Note added']);
        exit;
    }

    // ====== SET INTERVIEW ======
    if ($action === 'set_interview' && $aid) {
        $intDate = $_POST['interview_date'] ?? null;
        $oldSt = $db->prepare("SELECT status FROM teacher_applications WHERE id=?");
        $oldSt->execute([$aid]);
        $oldStatus = $oldSt->fetchColumn();
        $db->prepare("UPDATE teacher_applications SET interview_date=?, status='interview_scheduled', reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$intDate, currentUserId(), $aid]);
        $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks) VALUES (?,?,'interview_scheduled',?,'Interview scheduled')")->execute([$aid, $oldStatus ?: 'new', currentUserId()]);
        auditLog('recruitment_interview_scheduled', 'teacher_application', $aid);
        echo json_encode(['success'=>true,'message'=>'Interview scheduled']);
        exit;
    }

    // ====== SOFT DELETE ======
    if ($action === 'soft_delete' && $aid) {
        $db->prepare("UPDATE teacher_applications SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")->execute([currentUserId(), $aid]);
        auditLog('recruitment_archived', 'teacher_application', $aid);
        echo json_encode(['success'=>true,'message'=>'Application archived']);
        exit;
    }

    // ====== RESTORE ======
    if ($action === 'restore' && $aid) {
        $db->prepare("UPDATE teacher_applications SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=?")->execute([$aid]);
        auditLog('recruitment_restored', 'teacher_application', $aid);
        echo json_encode(['success'=>true,'message'=>'Application restored']);
        exit;
    }

    // ====== PERMANENT DELETE ======
    if ($action === 'permanent_delete' && $aid) {
        if (!isSuperAdmin()) { echo json_encode(['success'=>false,'error'=>'Unauthorized — Super Admin only']); exit; }
        // Delete resume file
        $resume = $db->prepare("SELECT resume_path FROM teacher_applications WHERE id=?");
        $resume->execute([$aid]);
        $resumePath = $resume->fetchColumn();
        if ($resumePath && file_exists(__DIR__.'/'.$resumePath)) {
            @unlink(__DIR__.'/'.$resumePath);
        }
        $db->prepare("DELETE FROM application_notes WHERE application_id=?")->execute([$aid]);
        $db->prepare("DELETE FROM application_status_history WHERE application_id=?")->execute([$aid]);
        $db->prepare("DELETE FROM teacher_applications WHERE id=?")->execute([$aid]);
        auditLog('recruitment_deleted', 'teacher_application', $aid);
        echo json_encode(['success'=>true,'message'=>'Application permanently deleted']);
        exit;
    }

    // ====== LOG CONTACT ======
    if ($action === 'log_contact' && $aid) {
        $contactType = $_POST['contact_type'] ?? 'call';
        auditLog('recruitment_' . ($contactType === 'whatsapp' ? 'whatsapp' : 'call'), 'teacher_application', $aid);
        echo json_encode(['success'=>true,'message'=>'Contact logged']);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Invalid action']);
    exit;
}

// ============ GET Actions ============

// GET: Detail for modal
if (($action === 'get_detail' || $action === 'get_details') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit; }

    $app = $db->prepare("SELECT a.*, j.title as job_title, u.name as reviewer_name FROM teacher_applications a LEFT JOIN job_openings j ON a.job_opening_id=j.id LEFT JOIN users u ON a.reviewed_by=u.id WHERE a.id=?");
    $app->execute([$id]);
    $app = $app->fetch();
    if (!$app) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }

    $notes = $db->prepare("SELECT n.*, u.name as user_name FROM application_notes n LEFT JOIN users u ON n.user_id=u.id WHERE n.application_id=? ORDER BY n.created_at DESC");
    $notes->execute([$id]);
    $notes = $notes->fetchAll();

    $history = $db->prepare("SELECT h.*, u.name as user_name FROM application_status_history h LEFT JOIN users u ON h.changed_by=u.id WHERE h.application_id=? ORDER BY h.created_at DESC");
    $history->execute([$id]);
    $history = $history->fetchAll();

    echo json_encode(['success'=>true, 'application'=>$app, 'notes'=>$notes, 'history'=>$history]);
    exit;
}

// GET: Export CSV
if ($action === 'export_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = ["(a.is_deleted=0 OR a.is_deleted IS NULL)"];
    $params = [];
    if (!empty($_GET['status'])) { $where[] = "a.status=?"; $params[] = $_GET['status']; }
    if (!empty($_GET['search'])) { $s = '%'.$_GET['search'].'%'; $where[] = "(a.full_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ? OR a.application_id LIKE ?)"; $params = array_merge($params, [$s,$s,$s,$s]); }
    if (!empty($_GET['date_from'])) { $where[] = "DATE(a.created_at)>=?"; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = "DATE(a.created_at)<=?"; $params[] = $_GET['date_to']; }
    $whereClause = 'WHERE '.implode(' AND ', $where);

    $stmt = $db->prepare("SELECT a.application_id, a.full_name, a.email, a.phone, a.dob, a.gender, a.qualification, a.experience_years, a.current_school, a.address, a.status, j.title as position, a.created_at FROM teacher_applications a LEFT JOIN job_openings j ON a.job_opening_id=j.id $whereClause ORDER BY a.created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teacher_applications_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Application ID','Name','Email','Phone','DOB','Gender','Qualification','Experience (Years)','Current School','Address','Status','Position','Applied Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['application_id'],$r['full_name'],$r['email'],$r['phone'],$r['dob'],$r['gender'],$r['qualification'],$r['experience_years'],$r['current_school'],$r['address'],$r['status'],$r['position'],$r['created_at']]);
    }
    fclose($out);
    exit;
}

// GET: Duplicate check
if ($action === 'check_duplicate' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $phone = $_GET['phone'] ?? '';
    $email = $_GET['email'] ?? '';
    $duplicates = [];

    if ($phone) {
        $stmt = $db->prepare("SELECT id, application_id, full_name, status FROM teacher_applications WHERE phone=? AND (is_deleted=0 OR is_deleted IS NULL) LIMIT 5");
        $stmt->execute([$phone]);
        $duplicates = array_merge($duplicates, $stmt->fetchAll());
    }
    if ($email) {
        $stmt = $db->prepare("SELECT id, application_id, full_name, status FROM teacher_applications WHERE email=? AND (is_deleted=0 OR is_deleted IS NULL) LIMIT 5");
        $stmt->execute([$email]);
        $duplicates = array_merge($duplicates, $stmt->fetchAll());
    }

    echo json_encode(['success'=>true, 'duplicates'=>$duplicates]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Invalid action']);