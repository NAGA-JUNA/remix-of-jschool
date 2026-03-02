<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed']);
    exit;
}

$handle = fopen($file, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Could not read file']);
    exit;
}

$headers = fgetcsv($handle);
if (!$headers) {
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'Empty CSV file']);
    exit;
}

$headers = array_map('trim', array_map('strtolower', $headers));
$required = ['employee_id', 'name'];
foreach ($required as $r) {
    if (!in_array($r, $headers)) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => "Missing required column: $r"]);
        exit;
    }
}

$allowedCols = ['employee_id','name','email','phone','subject','qualification','experience_years','dob','gender','address','joining_date','status'];

$db = getDB();
$added = 0;
$skipped = 0;
$failed = 0;
$errors = [];
$rowNum = 1;

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;
    if (count($row) < count($headers)) {
        $row = array_pad($row, count($headers), '');
    }
    $data = array_combine($headers, array_slice($row, 0, count($headers)));

    $empId = trim($data['employee_id'] ?? '');
    $name = trim($data['name'] ?? '');
    if (!$empId || !$name) {
        $failed++;
        $errors[] = "Row $rowNum: Missing employee_id or name";
        continue;
    }

    // Check duplicate
    $chk = $db->prepare("SELECT id FROM teachers WHERE employee_id=?");
    $chk->execute([$empId]);
    if ($chk->fetch()) {
        $skipped++;
        $errors[] = "Row $rowNum: Duplicate employee_id '$empId'";
        continue;
    }

    // Build insert for teacher
    $cols = [];
    $vals = [];
    $placeholders = [];
    foreach ($allowedCols as $col) {
        if (isset($data[$col]) && trim($data[$col]) !== '') {
            $cols[] = "`$col`";
            $vals[] = trim($data[$col]);
            $placeholders[] = '?';
        }
    }

    try {
        $db->beginTransaction();

        // Insert teacher
        $sql = "INSERT INTO teachers (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $stmt->execute($vals);
        $teacherId = $db->lastInsertId();

        // Create user account if email provided
        $email = trim($data['email'] ?? '');
        if ($email) {
            $chkUser = $db->prepare("SELECT id FROM users WHERE email=?");
            $chkUser->execute([$email]);
            if (!$chkUser->fetch()) {
                $hash = password_hash('Teacher@123', PASSWORD_DEFAULT);
                $uStmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'teacher')");
                $uStmt->execute([$name, $email, $hash]);
                $userId = $db->lastInsertId();
                $db->prepare("UPDATE teachers SET user_id=? WHERE id=?")->execute([$userId, $teacherId]);
            }
        }

        $db->commit();
        $added++;
    } catch (Exception $e) {
        $db->rollBack();
        $failed++;
        $errors[] = "Row $rowNum: " . $e->getMessage();
    }
}

fclose($handle);
auditLog('import_teachers', 'teacher', 0, "Imported: $added added, $skipped skipped, $failed failed");

echo json_encode([
    'success' => true,
    'total' => $rowNum - 1,
    'added' => $added,
    'skipped' => $skipped,
    'failed' => $failed,
    'errors' => $errors
]);
