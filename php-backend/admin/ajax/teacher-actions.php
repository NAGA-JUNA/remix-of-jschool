<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/file-handler.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$db = getDB();
FileHandler::ensureDir(__DIR__ . '/../../uploads/photos/');

function handlePhotoUpload(): ?string {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) return null;
    $result = FileHandler::uploadImage($_FILES['photo'], 'photos', 'teacher_', 5);
    if ($result['success']) {
        return '/' . $result['path'];
    }
    return null;
}

switch ($action) {

// ── GET PRINCIPAL ──
case 'get_principal':
    $stmt = $db->prepare("SELECT * FROM teachers WHERE status='active' AND designation='Principal' LIMIT 1");
    $stmt->execute();
    $p = $stmt->fetch();
    echo json_encode(['success' => true, 'principal' => $p ?: null]);
    break;

// ── SAVE PRINCIPAL ──
case 'save_principal':
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? 'Principal');
    $qualification = trim($_POST['qualification'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';

    if (!$name) { echo json_encode(['success' => false, 'message' => 'Name is required']); exit; }

    $photoPath = handlePhotoUpload();

    if ($id > 0) {
        // Update existing
        $sets = ['name=?', 'designation=?', 'qualification=?', 'bio=?'];
        $vals = [$name, $designation, $qualification, $bio];
        if ($photoPath) { $sets[] = 'photo=?'; $vals[] = $photoPath; }
        if ($removePhoto && !$photoPath) { $sets[] = 'photo=NULL'; }
        $vals[] = $id;
        $db->prepare("UPDATE teachers SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    } else {
        // Create new principal — generate employee_id
        $empId = 'PRIN' . date('Ymd') . rand(100,999);
        $db->prepare("INSERT INTO teachers (employee_id, name, designation, qualification, bio, photo, status, is_core_team, display_order) VALUES (?,?,?,?,?,?,'active',1,0)")
           ->execute([$empId, $name, $designation, $qualification, $bio, $photoPath]);
        $id = $db->lastInsertId();
    }

    auditLog('save_principal', 'teacher', $id, "Updated principal: $name");
    $stmt = $db->prepare("SELECT * FROM teachers WHERE id=?"); $stmt->execute([$id]);
    echo json_encode(['success' => true, 'principal' => $stmt->fetch()]);
    break;

// ── LIST TEACHERS ──
case 'list_teachers':
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT * FROM teachers WHERE status='active' ORDER BY display_order ASC, name ASC";
    if ($search) {
        $sql = "SELECT * FROM teachers WHERE status='active' AND (name LIKE ? OR subject LIKE ? OR designation LIKE ?) ORDER BY display_order ASC, name ASC";
        $stmt = $db->prepare($sql);
        $like = "%$search%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $db->query($sql);
    }
    echo json_encode(['success' => true, 'teachers' => $stmt->fetchAll()]);
    break;

// ── SAVE TEACHER ──
case 'save_teacher':
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $designation = trim($_POST['designation'] ?? 'Teacher');
    $qualification = trim($_POST['qualification'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $isVisible = isset($_POST['is_visible']) ? intval($_POST['is_visible']) : 1;
    $isFeatured = isset($_POST['is_featured']) ? intval($_POST['is_featured']) : 0;
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';

    if (!$name) { echo json_encode(['success' => false, 'message' => 'Name is required']); exit; }

    $photoPath = handlePhotoUpload();

    if ($id > 0) {
        $sets = ['name=?', 'subject=?', 'designation=?', 'qualification=?', 'bio=?', 'is_visible=?', 'is_featured=?'];
        $vals = [$name, $subject, $designation, $qualification, $bio, $isVisible, $isFeatured];
        if ($photoPath) { $sets[] = 'photo=?'; $vals[] = $photoPath; }
        if ($removePhoto && !$photoPath) { $sets[] = 'photo=NULL'; }
        $vals[] = $id;
        $db->prepare("UPDATE teachers SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    } else {
        $empId = 'TCH' . date('Ymd') . rand(1000,9999);
        $maxOrder = $db->query("SELECT COALESCE(MAX(display_order),0) FROM teachers")->fetchColumn();
        $db->prepare("INSERT INTO teachers (employee_id, name, subject, designation, qualification, bio, photo, status, is_visible, is_featured, display_order) VALUES (?,?,?,?,?,?,?,'active',?,?,?)")
           ->execute([$empId, $name, $subject, $designation, $qualification, $bio, $photoPath, $isVisible, $isFeatured, $maxOrder + 1]);
        $id = $db->lastInsertId();
    }

    auditLog('save_teacher_inline', 'teacher', $id, "Saved teacher: $name");
    $stmt = $db->prepare("SELECT * FROM teachers WHERE id=?"); $stmt->execute([$id]);
    echo json_encode(['success' => true, 'teacher' => $stmt->fetch()]);
    break;

// ── DELETE TEACHER ──
case 'delete_teacher':
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    $db->prepare("DELETE FROM teachers WHERE id=?")->execute([$id]);
    auditLog('delete_teacher_inline', 'teacher', $id, "Deleted teacher ID $id");
    echo json_encode(['success' => true]);
    break;

// ── REORDER ──
case 'reorder':
    $orderData = json_decode($_POST['order'] ?? '[]', true);
    if (is_array($orderData)) {
        $stmt = $db->prepare("UPDATE teachers SET display_order=? WHERE id=?");
        foreach ($orderData as $i => $tid) {
            $stmt->execute([$i, intval($tid)]);
        }
        auditLog('reorder_teachers', 'teacher', 0, 'Reordered teachers');
    }
    echo json_encode(['success' => true]);
    break;

// ── TOGGLE VISIBILITY ──
case 'toggle_visibility':
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false]); exit; }
    $db->prepare("UPDATE teachers SET is_visible = NOT is_visible WHERE id=?")->execute([$id]);
    $v = $db->prepare("SELECT is_visible FROM teachers WHERE id=?"); $v->execute([$id]);
    echo json_encode(['success' => true, 'is_visible' => (int)$v->fetchColumn()]);
    break;

// ── TOGGLE FEATURED ──
case 'toggle_featured':
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false]); exit; }
    $db->prepare("UPDATE teachers SET is_featured = NOT is_featured WHERE id=?")->execute([$id]);
    $v = $db->prepare("SELECT is_featured FROM teachers WHERE id=?"); $v->execute([$id]);
    echo json_encode(['success' => true, 'is_featured' => (int)$v->fetchColumn()]);
    break;

default:
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
