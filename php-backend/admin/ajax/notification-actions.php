<?php
/**
 * AJAX Notification Actions
 * Handles: version history, restore, attachments, engagement analytics
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/file-handler.php';
$db = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ─── Fetch version history ───
        case 'versions':
            $nid = (int)($_GET['id'] ?? 0);
            if (!$nid) throw new Exception('Missing notification ID');
            $stmt = $db->prepare("SELECT v.*, u.name as changed_by_name FROM notification_versions v LEFT JOIN users u ON v.changed_by=u.id WHERE v.notification_id=? ORDER BY v.changed_at DESC");
            $stmt->execute([$nid]);
            echo json_encode(['success' => true, 'versions' => $stmt->fetchAll()]);
            break;

        // ─── Restore a version ───
        case 'restore_version':
            if (!verifyCsrf()) throw new Exception('Invalid CSRF');
            $vid = (int)($_POST['version_id'] ?? 0);
            if (!$vid) throw new Exception('Missing version ID');
            $ver = $db->prepare("SELECT * FROM notification_versions WHERE id=?");
            $ver->execute([$vid]);
            $ver = $ver->fetch();
            if (!$ver) throw new Exception('Version not found');

            // Save current state before restoring
            $cur = $db->prepare("SELECT * FROM notifications WHERE id=?");
            $cur->execute([$ver['notification_id']]);
            $cur = $cur->fetch();
            if ($cur) {
                $db->prepare("INSERT INTO notification_versions (notification_id, title, content, type, priority, target_audience, category, tags, changed_by) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$cur['id'], $cur['title'], $cur['content'], $cur['type'], $cur['priority'], $cur['target_audience'], $cur['category'] ?? '', $cur['tags'] ?? '', currentUserId()]);
            }

            $db->prepare("UPDATE notifications SET title=?, content=?, type=?, priority=?, target_audience=?, category=?, tags=? WHERE id=?")
                ->execute([$ver['title'], $ver['content'], $ver['type'], $ver['priority'], $ver['target_audience'], $ver['category'], $ver['tags'], $ver['notification_id']]);
            auditLog('restore_notification_version', 'notification', $ver['notification_id'], "Restored version #$vid");
            echo json_encode(['success' => true, 'message' => 'Version restored']);
            break;

        // ─── Upload attachments ───
        case 'upload_attachments':
            if (!verifyCsrf()) throw new Exception('Invalid CSRF');
            $nid = (int)($_POST['notification_id'] ?? 0);
            if (!$nid) throw new Exception('Missing notification ID');
            $allowedExts = ['pdf','doc','docx','jpg','jpeg','png','gif','zip','xlsx','pptx'];
            $maxSize = 10 * 1024 * 1024;
            $uploaded = [];
            if (!empty($_FILES['files'])) {
                $files = $_FILES['files'];
                $count = is_array($files['name']) ? count($files['name']) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                    $err = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                    if ($err !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExts) || $size > $maxSize) continue;
                    $saveName = 'notif_' . $nid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = __DIR__ . '/../../uploads/documents/' . $saveName;
                    if (FileHandler::saveUploadedFile($tmp, $dest)) {
                        $ftype = in_array($ext, ['jpg','jpeg','png','gif']) ? 'image' : (($ext === 'pdf') ? 'pdf' : 'document');
                        $db->prepare("INSERT INTO notification_attachments (notification_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?)")
                            ->execute([$nid, $name, $saveName, $ftype, $size, currentUserId()]);
                        $uploaded[] = ['id' => (int)$db->lastInsertId(), 'file_name' => $name, 'file_path' => $saveName, 'file_type' => $ftype, 'file_size' => $size];
                    }
                }
            }
            echo json_encode(['success' => true, 'attachments' => $uploaded]);
            break;

        // ─── Delete attachment ───
        case 'delete_attachment':
            if (!verifyCsrf()) throw new Exception('Invalid CSRF');
            $aid = (int)($_POST['attachment_id'] ?? 0);
            if (!$aid) throw new Exception('Missing attachment ID');
            $att = $db->prepare("SELECT * FROM notification_attachments WHERE id=?");
            $att->execute([$aid]);
            $att = $att->fetch();
            if ($att) {
                $filepath = __DIR__ . '/../../uploads/documents/' . $att['file_path'];
                FileHandler::deleteFile($filepath);
                $db->prepare("DELETE FROM notification_attachments WHERE id=?")->execute([$aid]);
                auditLog('delete_attachment', 'notification_attachment', $aid);
            }
            echo json_encode(['success' => true]);
            break;

        // ─── Fetch attachments ───
        case 'attachments':
            $nid = (int)($_GET['id'] ?? 0);
            if (!$nid) throw new Exception('Missing notification ID');
            $stmt = $db->prepare("SELECT * FROM notification_attachments WHERE notification_id=? ORDER BY created_at DESC");
            $stmt->execute([$nid]);
            echo json_encode(['success' => true, 'attachments' => $stmt->fetchAll()]);
            break;

        // ─── Engagement analytics ───
        case 'analytics':
            $nid = (int)($_GET['id'] ?? 0);
            if (!$nid) throw new Exception('Missing notification ID');
            // Total views
            $n = $db->prepare("SELECT view_count FROM notifications WHERE id=?");
            $n->execute([$nid]);
            $viewCount = (int)$n->fetchColumn();
            // Unique readers breakdown
            $readers = $db->prepare("SELECT u.role, COUNT(*) as cnt, MIN(nr.read_at) as first_read, MAX(nr.read_at) as last_read FROM notification_reads nr JOIN users u ON nr.user_id=u.id WHERE nr.notification_id=? GROUP BY u.role");
            $readers->execute([$nid]);
            $breakdown = $readers->fetchAll();
            // Recent readers
            $recent = $db->prepare("SELECT u.name, u.role, nr.read_at FROM notification_reads nr JOIN users u ON nr.user_id=u.id WHERE nr.notification_id=? ORDER BY nr.read_at DESC LIMIT 20");
            $recent->execute([$nid]);
            $recentReaders = $recent->fetchAll();
            echo json_encode(['success' => true, 'view_count' => $viewCount, 'breakdown' => $breakdown, 'recent_readers' => $recentReaders]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}