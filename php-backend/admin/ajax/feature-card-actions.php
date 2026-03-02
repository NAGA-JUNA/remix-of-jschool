<?php
require_once __DIR__ . '/../includes/auth.php';
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
        case 'toggle_visibility':
            requireAdmin();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE feature_cards SET is_visible = NOT is_visible WHERE id=?")->execute([$id]);
                auditLog('feature_card_toggle_vis', 'feature_cards', $id, 'Toggled visibility');
            }
            echo json_encode(['success' => true]);
            break;

        case 'toggle_featured':
            requireAdmin();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE feature_cards SET is_featured = NOT is_featured WHERE id=?")->execute([$id]);
                auditLog('feature_card_toggle_feat', 'feature_cards', $id, 'Toggled featured');
            }
            echo json_encode(['success' => true]);
            break;

        case 'reorder':
            requireAdmin();
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                $stmt = $db->prepare("UPDATE feature_cards SET sort_order=? WHERE id=?");
                foreach ($order as $i => $id) {
                    $stmt->execute([$i + 1, (int)$id]);
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'track_click':
            $slug = trim($_GET['slug'] ?? $_POST['slug'] ?? '');
            if ($slug) {
                $db->prepare("UPDATE feature_cards SET click_count = click_count + 1 WHERE slug=?")->execute([$slug]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete_card':
            requireAdmin();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $card = $db->prepare("SELECT title FROM feature_cards WHERE id=?");
                $card->execute([$id]);
                $c = $card->fetch();
                $db->prepare("DELETE FROM feature_cards WHERE id=?")->execute([$id]);
                auditLog('feature_card_delete', 'feature_cards', $id, 'Deleted: ' . ($c['title'] ?? 'unknown'));
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}