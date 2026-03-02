<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $items = $db->query("SELECT * FROM nav_menu_items ORDER BY sort_order ASC, id ASC")->fetchAll();
            echo json_encode(['success'=>true, 'items'=>$items]);
            break;

        case 'save':
            $id = (int)($_POST['id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $icon = trim($_POST['icon'] ?? '');
            $linkType = in_array($_POST['link_type'] ?? '', ['internal','external']) ? $_POST['link_type'] : 'internal';
            $isVisible = isset($_POST['is_visible']) ? 1 : 0;
            $isCta = isset($_POST['is_cta']) ? 1 : 0;

            if (!$label || !$url) {
                echo json_encode(['success'=>false, 'error'=>'Label and URL are required']);
                exit;
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE nav_menu_items SET label=?, url=?, icon=?, link_type=?, is_visible=?, is_cta=? WHERE id=?");
                $stmt->execute([$label, $url, $icon, $linkType, $isVisible, $isCta, $id]);
                auditLog('nav_menu_update', 'nav_menu_items', $id, "Updated: $label");
            } else {
                $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_menu_items")->fetchColumn();
                $stmt = $db->prepare("INSERT INTO nav_menu_items (label, url, icon, link_type, is_visible, is_cta, sort_order) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$label, $url, $icon, $linkType, $isVisible, $isCta, $maxOrder]);
                auditLog('nav_menu_create', 'nav_menu_items', (int)$db->lastInsertId(), "Created: $label");
            }
            echo json_encode(['success'=>true]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM nav_menu_items WHERE id=?")->execute([$id]);
                auditLog('nav_menu_delete', 'nav_menu_items', $id, 'Deleted nav item');
            }
            echo json_encode(['success'=>true]);
            break;

        case 'reorder':
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                $stmt = $db->prepare("UPDATE nav_menu_items SET sort_order=? WHERE id=?");
                foreach ($order as $i => $id) {
                    $stmt->execute([$i, (int)$id]);
                }
            }
            echo json_encode(['success'=>true]);
            break;

        default:
            echo json_encode(['success'=>false, 'error'=>'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}