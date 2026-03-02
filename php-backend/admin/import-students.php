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
$required = ['admission_no', 'name'];
foreach ($required as $r) {
    if (!in_array($r, $headers)) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => "Missing required column: $r"]);
        exit;
    }
}

$allowedCols = ['admission_no','name','father_name','mother_name','dob','gender','class','section','roll_no','phone','email','address','blood_group','category','aadhar_no','status','admission_date'];

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

    $admNo = trim($data['admission_no'] ?? '');
    $name = trim($data['name'] ?? '');
    if (!$admNo || !$name) {
        $failed++;
        $errors[] = "Row $rowNum: Missing admission_no or name";
        continue;
    }

    // Check duplicate
    $chk = $db->prepare("SELECT id FROM students WHERE admission_no=?");
    $chk->execute([$admNo]);
    if ($chk->fetch()) {
        $skipped++;
        $errors[] = "Row $rowNum: Duplicate admission_no '$admNo'";
        continue;
    }

    // Build insert
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
        $sql = "INSERT INTO students (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $stmt->execute($vals);
        $added++;
    } catch (Exception $e) {
        $failed++;
        $errors[] = "Row $rowNum: " . $e->getMessage();
    }
}

fclose($handle);
auditLog('import_students', 'student', 0, "Imported: $added added, $skipped skipped, $failed failed");

echo json_encode([
    'success' => true,
    'total' => $rowNum - 1,
    'added' => $added,
    'skipped' => $skipped,
    'failed' => $failed,
    'errors' => $errors
]);
