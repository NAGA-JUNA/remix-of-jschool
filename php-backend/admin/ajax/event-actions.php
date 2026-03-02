<?php
/**
 * Events AJAX Actions — Toggle public/featured
 * Admin-only, CSRF-protected
 */
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

// ═══ TOGGLE PUBLIC ═══
if ($action === 'toggle_public') {
    $db->prepare("UPDATE events SET is_public = IF(is_public=1,0,1) WHERE id = ?")->execute([$id]);
    $newVal = (int)$db->prepare("SELECT is_public FROM events WHERE id = ?")->execute([$id]) ? $db->query("SELECT is_public FROM events WHERE id = $id")->fetchColumn() : 0;
    auditLog('toggle_event_public', 'event', $id, "Set is_public=$newVal");
    echo json_encode(['success' => true, 'value' => $newVal]);
    exit;
}

// ═══ TOGGLE FEATURED ═══
if ($action === 'toggle_featured') {
    $db->prepare("UPDATE events SET is_featured = IF(is_featured=1,0,1) WHERE id = ?")->execute([$id]);
    $stmt = $db->prepare("SELECT is_featured FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $newVal = (int)$stmt->fetchColumn();
    auditLog('toggle_event_featured', 'event', $id, "Set is_featured=$newVal");
    echo json_encode(['success' => true, 'value' => $newVal]);
    exit;
}

// ═══ QUICK STATUS CHANGE ═══
if ($action === 'change_status') {
    $status = $_POST['status'] ?? '';
    $allowed = ['active', 'draft', 'cancelled', 'completed'];
    if (!in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    $db->prepare("UPDATE events SET status = ? WHERE id = ?")->execute([$status, $id]);
    auditLog('change_event_status', 'event', $id, "Set status=$status");
    echo json_encode(['success' => true, 'status' => $status]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);