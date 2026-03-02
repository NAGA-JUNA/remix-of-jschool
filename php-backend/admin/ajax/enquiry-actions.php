<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed']);
    exit;
}

$db = getDB();
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    if ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['new', 'contacted', 'closed'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        $db->prepare("UPDATE enquiries SET status = ? WHERE id = ?")->execute([$status, $id]);
        auditLog('enquiry_status_update', 'enquiry', $id);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM enquiries WHERE id = ?")->execute([$id]);
        auditLog('enquiry_delete', 'enquiry', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}