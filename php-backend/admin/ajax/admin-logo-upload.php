<?php
/**
 * Admin Dashboard Logo Upload Handler
 * Handles upload/delete of the admin sidebar logo (separate from school_logo)
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Ensure admin access
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Local fallback for getSetting/setSetting
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $pdo;
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }
}
if (!function_exists('setSetting')) {
    function setSetting($key, $value) {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'delete') {
    // Delete current admin logo
    $currentLogo = getSetting('admin_logo', '');
    if ($currentLogo) {
        $filePath = __DIR__ . '/../../uploads/branding/' . basename($currentLogo);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        setSetting('admin_logo', '');
        setSetting('admin_logo_updated_at', time());
    }
    echo json_encode(['success' => true, 'message' => 'Admin logo removed successfully']);
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['admin_logo'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['admin_logo'];

// Validate file
$allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
$allowedExts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file['type'], $allowedTypes) || !in_array($ext, $allowedExts)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, SVG, WEBP']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5MB allowed']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
    exit;
}

// Create upload directory if needed
$uploadDir = __DIR__ . '/../../uploads/branding/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Delete old logo file
$oldLogo = getSetting('admin_logo', '');
if ($oldLogo) {
    $oldPath = $uploadDir . basename($oldLogo);
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
}

// Save new file
$filename = 'admin_logo_' . time() . '.' . $ext;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Update settings
$logoPath = '/uploads/branding/' . $filename;
setSetting('admin_logo', $logoPath);
setSetting('admin_logo_updated_at', time());

echo json_encode([
    'success' => true,
    'message' => 'Admin dashboard logo uploaded successfully',
    'logo_path' => $logoPath
]);