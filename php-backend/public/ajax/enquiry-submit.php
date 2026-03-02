<?php
/**
 * Public Enquiry Submission Endpoint
 * No admin auth required - for public "Need Help?" form
 */
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

// Honeypot spam check
if (!empty($_POST['website_url'])) {
    echo json_encode(['success' => true]); // Fake success for bots
    exit;
}

// Rate limiting: max 3 submissions per 10 minutes
if (!isset($_SESSION['enquiry_submissions'])) $_SESSION['enquiry_submissions'] = [];
$_SESSION['enquiry_submissions'] = array_filter($_SESSION['enquiry_submissions'], fn($t) => $t > time() - 600);
if (count($_SESSION['enquiry_submissions']) >= 3) {
    echo json_encode(['success' => false, 'error' => 'Too many submissions. Please try again later.']);
    exit;
}

$name = trim($_POST['parent_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');


// Validate
if ($name === '' || $mobile === '') {
    echo json_encode(['success' => false, 'error' => 'Name and mobile number are required.']);
    exit;
}
if (strlen($name) > 100 || strlen($mobile) > 20 || strlen($email) > 255) {
    echo json_encode(['success' => false, 'error' => 'Input too long.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit;
}

try {
    $db = getDB();
    $db->prepare("INSERT INTO enquiries (name, phone, email, message, status) VALUES (?, ?, ?, ?, 'new')")
       ->execute([$name, $mobile, $email ?: null, $message ?: null]);
    $_SESSION['enquiry_submissions'][] = time();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}