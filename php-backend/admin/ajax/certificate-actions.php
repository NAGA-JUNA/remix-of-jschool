<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'reorder':
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                $stmt = $db->prepare("UPDATE certificates SET display_order=? WHERE id=?");
                foreach ($order as $i => $id) {
                    $stmt->execute([$i, (int)$id]);
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'toggle_active':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE certificates SET is_active = NOT is_active WHERE id=?")->execute([$id]);
                auditLog('cert_toggle', 'certificates', $id, 'Toggled active');
            }
            echo json_encode(['success' => true]);
            break;

        case 'toggle_featured':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE certificates SET is_featured = NOT is_featured WHERE id=?")->execute([$id]);
                auditLog('cert_toggle_featured', 'certificates', $id, 'Toggled featured');
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}