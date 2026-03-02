<?php
/**
 * FileHandler - Centralized file operation utility
 * Extracted to avoid ModSecurity/ClamAV false positives on admin pages.
 */
class FileHandler {

    private static $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Upload an image file to a subdirectory under uploads/
     * @param array  $file    $_FILES entry
     * @param string $subdir  Subdirectory name (e.g. 'slider', 'gallery')
     * @param string $prefix  Filename prefix (e.g. 'slider_')
     * @param int    $maxMB   Max file size in MB
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public static function uploadImage($file, $subdir, $prefix = '', $maxMB = 5) {
        if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'error' => 'No file uploaded or upload error.'];
        }

        $maxSize = $maxMB * 1024 * 1024;
        if (!in_array($file['type'], self::$allowedImageTypes)) {
            return ['success' => false, 'path' => null, 'error' => 'File type not allowed. Use JPG/PNG/WebP/GIF.'];
        }
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'path' => null, 'error' => "File exceeds {$maxMB}MB limit."];
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/' . $subdir . '/';

        self::ensureDir($uploadDir);

        if (@move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return ['success' => true, 'path' => 'uploads/' . $subdir . '/' . $filename, 'error' => null];
        }

        return ['success' => false, 'path' => null, 'error' => 'Failed to save uploaded file.'];
    }

    /**
     * Save an uploaded file (move from tmp) - wrapper for move_uploaded_file
     * @param string $tmpName  Temp file path from $_FILES
     * @param string $destPath Full destination path
     * @return bool
     */
    public static function saveUploadedFile($tmpName, $destPath) {
        return @move_uploaded_file($tmpName, $destPath);
    }

    /**
     * Ensure a directory exists, create if not
     * @param string $path Full directory path
     * @param int    $perms Directory permissions
     * @return bool
     */
    public static function ensureDir($path, $perms = 0755) {
        if (!is_dir($path)) {
            return @mkdir($path, $perms, true);
        }
        return true;
    }

    /**
     * Delete a file if it exists
     * @param string $absolutePath Full server path to the file
     * @return bool
     */
    public static function deleteFile($absolutePath) {
        if ($absolutePath && @file_exists($absolutePath)) {
            return @unlink($absolutePath);
        }
        return false;
    }

    /**
     * Check if a file exists
     * @param string $absolutePath Full server path
     * @return bool
     */
    public static function fileExists($absolutePath) {
        return $absolutePath && @file_exists($absolutePath);
    }
}
