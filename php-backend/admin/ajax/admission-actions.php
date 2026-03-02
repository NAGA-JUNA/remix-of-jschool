<?php
require_once __DIR__ . '/../../includes/auth.php';

// AJAX-aware auth: return JSON error instead of HTML redirect for AJAX requests
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

// ============ POST: AJAX Actions ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $aid = (int)($_POST['id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';

    // Verify CSRF
    if (!$csrf || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']);
        exit;
    }

    if ($action === 'update_status' && $aid) {
        $allStatuses = ['new','contacted','documents_verified','interview_scheduled','approved','rejected','waitlisted','converted'];
        $newStatus = $_POST['new_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        if (!in_array($newStatus, $allStatuses)) {
            echo json_encode(['success'=>false,'error'=>'Invalid status']);
            exit;
        }
        $oldSt = $db->prepare("SELECT status FROM admissions WHERE id=? AND (is_deleted=0 OR is_deleted IS NULL)");
        $oldSt->execute([$aid]);
        $oldStatus = $oldSt->fetchColumn();
        if (!$oldStatus) { echo json_encode(['success'=>false,'error'=>'Admission not found']); exit; }

        $db->prepare("UPDATE admissions SET status=?, remarks=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$newStatus, $remarks, currentUserId(), $aid]);
        $db->prepare("INSERT INTO admission_status_history (admission_id, old_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)")->execute([$aid, $oldStatus, $newStatus, currentUserId(), $remarks]);
        auditLog("admission_$newStatus", 'admission', $aid);

        // Auto-create student if requested
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

        // Send email on key status changes
        if (in_array($newStatus, ['approved','rejected','waitlisted','interview_scheduled'])) {
            try {
                $adm = $db->prepare("SELECT * FROM admissions WHERE id=?");
                $adm->execute([$aid]);
                $adm = $adm->fetch();
                if ($adm && $adm['email']) {
                    require_once __DIR__.'/../../config/mail.php';
                    $schoolName = getSetting('school_name','JNV School');
                    $subjects = [
                        'approved' => "Admission Approved — $schoolName",
                        'rejected' => "Admission Update — $schoolName",
                        'waitlisted' => "Admission Waitlisted — $schoolName",
                        'interview_scheduled' => "Interview Scheduled — $schoolName"
                    ];
                    $bodies = [
                        'approved' => "<h2>Congratulations!</h2><p>Dear {$adm['student_name']},</p><p>Your admission application <strong>{$adm['application_id']}</strong> has been <strong style='color:#22c55e'>APPROVED</strong>.</p>",
                        'rejected' => "<h2>Admission Update</h2><p>Dear {$adm['student_name']},</p><p>After careful review, your application <strong>{$adm['application_id']}</strong> could not be accepted at this time.</p>".($remarks ? "<p><strong>Remarks:</strong> $remarks</p>" : ""),
                        'waitlisted' => "<h2>Application Waitlisted</h2><p>Dear {$adm['student_name']},</p><p>Your application <strong>{$adm['application_id']}</strong> has been placed on the <strong>waitlist</strong>.</p>",
                        'interview_scheduled' => "<h2>Interview Scheduled</h2><p>Dear {$adm['student_name']},</p><p>An interview has been scheduled for your application <strong>{$adm['application_id']}</strong>.</p>"
                    ];
                    $emailBody = "<div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:2rem;'>".$bodies[$newStatus]."</div>";
                    sendMail($adm['email'], $subjects[$newStatus], $emailBody);
                }
            } catch (Exception $e) { /* silent */ }
        }

        echo json_encode(['success'=>true,'message'=>'Status updated to '.ucfirst(str_replace('_',' ',$newStatus))]);
        exit;
    }

    if ($action === 'add_note' && $aid) {
        $note = trim($_POST['note'] ?? '');
        if (!$note) { echo json_encode(['success'=>false,'error'=>'Note is required']); exit; }
        $db->prepare("INSERT INTO admission_notes (admission_id, user_id, note) VALUES (?,?,?)")->execute([$aid, currentUserId(), $note]);
        auditLog('admission_note_added', 'admission', $aid);
        echo json_encode(['success'=>true,'message'=>'Note added']);
        exit;
    }

    if ($action === 'set_followup' && $aid) {
        $followUp = $_POST['follow_up_date'] ?? null;
        $db->prepare("UPDATE admissions SET follow_up_date=? WHERE id=?")->execute([$followUp ?: null, $aid]);
        echo json_encode(['success'=>true,'message'=>'Follow-up date updated']);
        exit;
    }

    if ($action === 'set_interview' && $aid) {
        $intDate = $_POST['interview_date'] ?? null;
        $oldSt = $db->prepare("SELECT status FROM admissions WHERE id=?");
        $oldSt->execute([$aid]);
        $oldStatus = $oldSt->fetchColumn();
        $db->prepare("UPDATE admissions SET interview_date=?, status='interview_scheduled', reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$intDate, currentUserId(), $aid]);
        $db->prepare("INSERT INTO admission_status_history (admission_id, old_status, new_status, changed_by, remarks) VALUES (?,?,'interview_scheduled',?,'Interview scheduled')")->execute([$aid, $oldStatus ?: 'new', currentUserId()]);
        echo json_encode(['success'=>true,'message'=>'Interview scheduled']);
        exit;
    }

    // ====== SOFT DELETE (Archive) ======
    if ($action === 'soft_delete' && $aid) {
        $db->prepare("UPDATE admissions SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")->execute([currentUserId(), $aid]);
        auditLog('admission_archived', 'admission', $aid);
        echo json_encode(['success'=>true,'message'=>'Admission archived']);
        exit;
    }

    // ====== RESTORE from archive ======
    if ($action === 'restore' && $aid) {
        $db->prepare("UPDATE admissions SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=?")->execute([$aid]);
        auditLog('admission_restored', 'admission', $aid);
        echo json_encode(['success'=>true,'message'=>'Admission restored']);
        exit;
    }

    // ====== PERMANENT DELETE (Super Admin only) ======
    if ($action === 'permanent_delete' && $aid) {
        if (!isSuperAdmin()) { echo json_encode(['success'=>false,'error'=>'Unauthorized — Super Admin only']); exit; }
        $db->prepare("DELETE FROM admission_notes WHERE admission_id=?")->execute([$aid]);
        $db->prepare("DELETE FROM admission_status_history WHERE admission_id=?")->execute([$aid]);
        $db->prepare("DELETE FROM admissions WHERE id=?")->execute([$aid]);
        auditLog('admission_deleted', 'admission', $aid);
        echo json_encode(['success'=>true,'message'=>'Admission permanently deleted']);
        exit;
    }

    // ====== LOG CONTACT (WhatsApp/Call tracking) ======
    if ($action === 'log_contact' && $aid) {
        $contactType = $_POST['contact_type'] ?? 'call'; // 'call' or 'whatsapp'
        $auditAction = $contactType === 'whatsapp' ? 'admission_whatsapp' : 'admission_call';
        auditLog($auditAction, 'admission', $aid);
        echo json_encode(['success'=>true,'message'=>'Contact logged']);
        exit;
    }

    // Legacy delete handler (redirects to soft delete)
    if ($action === 'delete' && $aid) {
        if (!isSuperAdmin()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
        // Soft delete instead of hard delete for safety
        $db->prepare("UPDATE admissions SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")->execute([currentUserId(), $aid]);
        auditLog('admission_archived', 'admission', $aid);
        echo json_encode(['success'=>true,'message'=>'Admission archived']);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Invalid action']);
    exit;
}

// ============ GET Actions ============

// GET: Detail view for modal (supports both get_detail and get_details)
if (($action === 'get_detail' || $action === 'get_details') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit; }

    $adm = $db->prepare("SELECT a.*, u.name as reviewer_name FROM admissions a LEFT JOIN users u ON a.reviewed_by=u.id WHERE a.id=?");
    $adm->execute([$id]);
    $adm = $adm->fetch();
    if (!$adm) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }

    $notes = $db->prepare("SELECT n.*, u.name as user_name FROM admission_notes n LEFT JOIN users u ON n.user_id=u.id WHERE n.admission_id=? ORDER BY n.created_at DESC");
    $notes->execute([$id]);
    $notes = $notes->fetchAll();

    $history = $db->prepare("SELECT h.*, u.name as user_name FROM admission_status_history h LEFT JOIN users u ON h.changed_by=u.id WHERE h.admission_id=? ORDER BY h.created_at DESC");
    $history->execute([$id]);
    $history = $history->fetchAll();

    $documents = [];
    if ($adm['documents']) {
        $docs = json_decode($adm['documents'], true);
        if (is_array($docs)) $documents = $docs;
        elseif (is_string($adm['documents']) && !empty($adm['documents'])) {
            $documents = ['document' => $adm['documents']];
        }
    }

    echo json_encode([
        'success' => true,
        'admission' => $adm,
        'notes' => $notes,
        'history' => $history,
        'documents' => $documents
    ]);
    exit;
}

// GET: Export CSV
if ($action === 'export_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = ["(a.is_deleted=0 OR a.is_deleted IS NULL)"];
    $params = [];
    if (!empty($_GET['status'])) { $where[] = "a.status=?"; $params[] = $_GET['status']; }
    if (!empty($_GET['search'])) { $s = '%'.$_GET['search'].'%'; $where[] = "(a.student_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ? OR a.application_id LIKE ?)"; $params = array_merge($params, [$s,$s,$s,$s]); }
    if (!empty($_GET['class'])) { $where[] = "a.class_applied=?"; $params[] = $_GET['class']; }
    if (!empty($_GET['date_from'])) { $where[] = "DATE(a.created_at)>=?"; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = "DATE(a.created_at)<=?"; $params[] = $_GET['date_to']; }
    $whereClause = 'WHERE '.implode(' AND ', $where);

    $stmt = $db->prepare("SELECT a.application_id, a.student_name, a.father_name, a.mother_name, a.dob, a.gender, a.class_applied, a.phone, a.email, a.address, a.status, a.source, a.priority, a.created_at FROM admissions a $whereClause ORDER BY a.created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="admissions_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Application ID','Student Name','Father Name','Mother Name','DOB','Gender','Class','Phone','Email','Address','Status','Source','Priority','Applied Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['application_id'],$r['student_name'],$r['father_name'],$r['mother_name'],$r['dob'],$r['gender'],$r['class_applied'],$r['phone'],$r['email'],$r['address'],$r['status'],$r['source'],$r['priority'],$r['created_at']]);
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
        $stmt = $db->prepare("SELECT id, application_id, student_name, status FROM admissions WHERE phone=? AND (is_deleted=0 OR is_deleted IS NULL) LIMIT 5");
        $stmt->execute([$phone]);
        $duplicates = array_merge($duplicates, $stmt->fetchAll());
    }
    if ($email) {
        $stmt = $db->prepare("SELECT id, application_id, student_name, status FROM admissions WHERE email=? AND (is_deleted=0 OR is_deleted IS NULL) LIMIT 5");
        $stmt->execute([$email]);
        $duplicates = array_merge($duplicates, $stmt->fetchAll());
    }

    echo json_encode(['success'=>true, 'duplicates'=>$duplicates]);
    exit;
}

// GET: Seat count
if ($action === 'seat_count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $class = $_GET['class'] ?? '';
    $year = getSetting('academic_year', date('Y').'-'.(date('Y')+1));

    $stmt = $db->prepare("SELECT total_seats FROM class_seat_capacity WHERE class=? AND academic_year=? AND is_active=1");
    $stmt->execute([$class, $year]);
    $capacity = $stmt->fetchColumn();

    $filled = $db->prepare("SELECT COUNT(*) FROM admissions WHERE class_applied=? AND status IN ('approved','converted') AND (is_deleted=0 OR is_deleted IS NULL)");
    $filled->execute([$class]);
    $filled = $filled->fetchColumn();

    echo json_encode(['success'=>true, 'total'=>(int)$capacity, 'filled'=>(int)$filled, 'available'=>max(0,(int)$capacity-(int)$filled)]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Invalid action']);