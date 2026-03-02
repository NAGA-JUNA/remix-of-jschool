<?php
require_once __DIR__ . '/../../includes/auth.php';
$db = getDB();
header('Content-Type: application/json');

// CSRF check for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'reorder':
            requireAdmin();
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                $stmt = $db->prepare("UPDATE core_team SET display_order=? WHERE id=?");
                foreach ($order as $i => $id) {
                    $stmt->execute([$i + 1, (int)$id]);
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'toggle_visibility':
            requireAdmin();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE core_team SET is_visible = NOT is_visible WHERE id=?")->execute([$id]);
                auditLog('core_team_toggle_vis', 'core_team', $id, 'Toggled visibility');
            }
            echo json_encode(['success' => true]);
            break;

        case 'toggle_featured':
            requireAdmin();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE core_team SET is_featured = NOT is_featured WHERE id=?")->execute([$id]);
                auditLog('core_team_toggle_feat', 'core_team', $id, 'Toggled featured');
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            requireAdmin();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $member = $db->prepare("SELECT photo FROM core_team WHERE id=?");
                $member->execute([$id]);
                $m = $member->fetch();
                if ($m && $m['photo']) {
                    $photoPath = __DIR__ . '/../../' . ltrim($m['photo'], '/');
                    if (file_exists($photoPath)) @unlink($photoPath);
                }
                $db->prepare("DELETE FROM core_team WHERE id=?")->execute([$id]);
                auditLog('core_team_delete', 'core_team', $id, 'Deleted member');
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}