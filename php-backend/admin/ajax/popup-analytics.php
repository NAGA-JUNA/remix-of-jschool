<?php
/**
 * Popup Analytics Tracker
 * POST: action=view|click, popup_id=1
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$popupId = (int)($_POST['popup_id'] ?? $_GET['popup_id'] ?? 1);

if (!in_array($action, ['view', 'click'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    $db = getDB();

    // Verify popup exists and is enabled
    $popup = $db->prepare("SELECT id, is_enabled FROM popup_ads WHERE id=?");
    $popup->execute([$popupId]);
    $row = $popup->fetch();
    if (!$row || !$row['is_enabled']) {
        echo json_encode(['ok' => false, 'reason' => 'Popup not active']);
        exit;
    }

    $today = date('Y-m-d');
    $column = $action === 'view' ? 'views_count' : 'clicks_count';

    $stmt = $db->prepare("
        INSERT INTO popup_analytics (popup_id, view_date, {$column})
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE {$column} = {$column} + 1
    ");
    $stmt->execute([$popupId, $today]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}