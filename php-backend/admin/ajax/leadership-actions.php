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

function handleLeaderPhotoUpload(): ?string {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) return null;
    $result = FileHandler::uploadImage($_FILES['photo'], 'photos', 'leader_', 5);
    if ($result['success']) {
        return '/' . $result['path'];
    }
    return null;
}

switch ($action) {

// ── LIST LEADERS ──
case 'list_leaders':
    $search = trim($_GET['search'] ?? '');
    if ($search) {
        $stmt = $db->prepare("SELECT * FROM leadership_profiles WHERE name LIKE ? OR designation LIKE ? ORDER BY display_order ASC, name ASC");
        $like = "%$search%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $db->query("SELECT * FROM leadership_profiles ORDER BY display_order ASC, name ASC");
    }
    echo json_encode(['success' => true, 'leaders' => $stmt->fetchAll()]);
    break;

// ── SAVE LEADER ──
case 'save_leader':
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';

    if (!$name) { echo json_encode(['success' => false, 'message' => 'Name is required']); exit; }

    $photoPath = handleLeaderPhotoUpload();

    if ($id > 0) {
        $sets = ['name=?', 'designation=?', 'bio=?', 'status=?'];
        $vals = [$name, $designation, $bio, $status];
        if ($photoPath) { $sets[] = 'photo=?'; $vals[] = $photoPath; }
        if ($removePhoto && !$photoPath) { $sets[] = 'photo=NULL'; }
        $vals[] = $id;
        $db->prepare("UPDATE leadership_profiles SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    } else {
        $maxOrder = $db->query("SELECT COALESCE(MAX(display_order),0) FROM leadership_profiles")->fetchColumn();
        $db->prepare("INSERT INTO leadership_profiles (name, designation, bio, photo, status, display_order) VALUES (?,?,?,?,?,?)")
           ->execute([$name, $designation, $bio, $photoPath, $status, $maxOrder + 1]);
        $id = $db->lastInsertId();
    }

    auditLog('save_leader', 'leadership', $id, "Saved leader: $name");
    $stmt = $db->prepare("SELECT * FROM leadership_profiles WHERE id=?"); $stmt->execute([$id]);
    echo json_encode(['success' => true, 'leader' => $stmt->fetch()]);
    break;

// ── DELETE LEADER ──
case 'delete_leader':
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    $db->prepare("DELETE FROM leadership_profiles WHERE id=?")->execute([$id]);
    auditLog('delete_leader', 'leadership', $id, "Deleted leader ID $id");
    echo json_encode(['success' => true]);
    break;

// ── REORDER ──
case 'reorder':
    $orderData = json_decode($_POST['order'] ?? '[]', true);
    if (is_array($orderData)) {
        $stmt = $db->prepare("UPDATE leadership_profiles SET display_order=? WHERE id=?");
        foreach ($orderData as $i => $lid) {
            $stmt->execute([$i, intval($lid)]);
        }
        auditLog('reorder_leaders', 'leadership', 0, 'Reordered leadership profiles');
    }
    echo json_encode(['success' => true]);
    break;

// ── TOGGLE STATUS ──
case 'toggle_status':
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false]); exit; }
    $db->prepare("UPDATE leadership_profiles SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
    $v = $db->prepare("SELECT status FROM leadership_profiles WHERE id=?"); $v->execute([$id]);
    echo json_encode(['success' => true, 'status' => $v->fetchColumn()]);
    break;

default:
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
