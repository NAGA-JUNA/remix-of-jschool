<?php
/**
 * Gallery Categories & Albums AJAX Actions
 * Admin-only, CSRF-protected
 */
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../../includes/file-handler.php';
$db = getDB();

header('Content-Type: application/json');

// ── GET Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list_categories') {
        $stmt = $db->query("SELECT * FROM gallery_categories ORDER BY sort_order ASC, id ASC");
        echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'list_albums') {
        $catId = (int)($_GET['category_id'] ?? 0);
        $where = $catId ? "WHERE category_id = ?" : "";
        $params = $catId ? [$catId] : [];
        $stmt = $db->prepare("SELECT a.*, c.name as category_name FROM gallery_albums a LEFT JOIN gallery_categories c ON a.category_id = c.id $where ORDER BY a.sort_order ASC, a.id ASC");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'albums' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'get_category') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM gallery_categories WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'category' => $stmt->fetch()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── POST Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ═══ SAVE CATEGORY ═══
    if ($action === 'save_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit;
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');

        // Handle cover image upload
        $coverImage = null;
        if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $result = FileHandler::uploadImage($_FILES['cover_image'], 'gallery/categories', 'cat_', 5);
            if ($result['success']) {
                $coverImage = $result['path'];
            }
        }

        if ($id > 0) {
            // Update
            $sql = "UPDATE gallery_categories SET name=?, slug=?, description=?, status=?";
            $params = [$name, $slug, $description, $status];
            if ($coverImage) {
                $sql .= ", cover_image=?";
                $params[] = $coverImage;
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);
            auditLog('gallery_category_update', 'gallery_category', $id, "Updated: $name");
        } else {
            // Insert
            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM gallery_categories")->fetchColumn();
            $stmt = $db->prepare("INSERT INTO gallery_categories (name, slug, cover_image, description, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $coverImage, $description, $maxOrder + 1, $status]);
            $id = (int)$db->lastInsertId();
            auditLog('gallery_category_create', 'gallery_category', $id, "Created: $name");
        }

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Category saved']);
        exit;
    }

    // ═══ DELETE CATEGORY ═══
    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Unlink gallery_items from albums in this category
            $db->prepare("UPDATE gallery_items SET album_id = NULL WHERE album_id IN (SELECT id FROM gallery_albums WHERE category_id = ?)")->execute([$id]);
            $db->prepare("DELETE FROM gallery_categories WHERE id = ?")->execute([$id]);
            auditLog('gallery_category_delete', 'gallery_category', $id);
            echo json_encode(['success' => true, 'message' => 'Category deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
        exit;
    }

    // ═══ REORDER CATEGORIES ═══
    if ($action === 'reorder_categories') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $db->prepare("UPDATE gallery_categories SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i + 1, (int)$id]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid order']);
        }
        exit;
    }

    // ═══ TOGGLE CATEGORY STATUS ═══
    if ($action === 'toggle_category_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE gallery_categories SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
        exit;
    }

    // ═══ SAVE ALBUM ═══
    if ($action === 'save_album') {
        $id = (int)($_POST['id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate = $_POST['event_date'] ?? null;
        $year = trim($_POST['year'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (!$title || !$categoryId) {
            echo json_encode(['success' => false, 'message' => 'Title and category are required']);
            exit;
        }

        if (!$eventDate) $eventDate = null;

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');

        // Handle cover image
        $coverImage = null;
        if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $result = FileHandler::uploadImage($_FILES['cover_image'], 'gallery/albums', 'album_', 5);
            if ($result['success']) {
                $coverImage = $result['path'];
            }
        }

        if ($id > 0) {
            $sql = "UPDATE gallery_albums SET category_id=?, title=?, slug=?, description=?, event_date=?, year=?, status=?";
            $params = [$categoryId, $title, $slug, $description, $eventDate, $year ?: null, $status];
            if ($coverImage) {
                $sql .= ", cover_image=?";
                $params[] = $coverImage;
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);
            auditLog('gallery_album_update', 'gallery_album', $id, "Updated: $title");
        } else {
            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM gallery_albums WHERE category_id = $categoryId")->fetchColumn();
            $stmt = $db->prepare("INSERT INTO gallery_albums (category_id, title, slug, cover_image, description, event_date, year, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $title, $slug, $coverImage, $description, $eventDate, $year ?: null, $maxOrder + 1, $status]);
            $id = (int)$db->lastInsertId();
            auditLog('gallery_album_create', 'gallery_album', $id, "Created: $title");
        }

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Album saved']);
        exit;
    }

    // ═══ DELETE ALBUM ═══
    if ($action === 'delete_album') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE gallery_items SET album_id = NULL WHERE album_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM gallery_albums WHERE id = ?")->execute([$id]);
            auditLog('gallery_album_delete', 'gallery_album', $id);
            echo json_encode(['success' => true, 'message' => 'Album deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
        exit;
    }

    // ═══ REORDER ALBUMS ═══
    if ($action === 'reorder_albums') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $db->prepare("UPDATE gallery_albums SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i + 1, (int)$id]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid order']);
        }
        exit;
    }

    // ═══ ASSIGN IMAGES TO ALBUM ═══
    if ($action === 'assign_images') {
        $albumId = (int)($_POST['album_id'] ?? 0);
        $ids = json_decode($_POST['image_ids'] ?? '[]', true);
        if ($albumId && is_array($ids) && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$albumId], array_map('intval', $ids));
            $db->prepare("UPDATE gallery_items SET album_id = ? WHERE id IN ($placeholders)")->execute($params);
            echo json_encode(['success' => true, 'message' => count($ids) . ' image(s) assigned']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
