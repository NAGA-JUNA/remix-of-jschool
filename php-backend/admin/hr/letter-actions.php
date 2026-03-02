<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_employee':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM hr_employees WHERE id=?");
            $stmt->execute([$id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'employee' => $emp ?: null]);
            break;

        case 'create_letter':
            $employee_id = (int)($_POST['employee_id'] ?? 0);
            $letter_type = $_POST['letter_type'] ?? '';
            $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
            $effective_date = $_POST['effective_date'] ?? null;
            $salary_old = $_POST['salary_old'] ?? null;
            $salary_new = $_POST['salary_new'] ?? null;
            $increment_pct = $_POST['increment_pct'] ?? null;
            $last_working_date = $_POST['last_working_date'] ?? null;
            $notice_period = trim($_POST['notice_period'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $status = $_POST['status'] ?? 'draft';

            if (!$employee_id || !in_array($letter_type, ['appointment','joining','resignation','hike'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid employee or letter type.']);
                exit;
            }

            // Generate reference number
            $typeCode = strtoupper(substr($letter_type, 0, 3));
            $year = date('Y');
            $seqStmt = $db->prepare("SELECT COUNT(*) + 1 FROM hr_letters WHERE letter_type=? AND YEAR(created_at)=?");
            $seqStmt->execute([$letter_type, $year]);
            $seq = str_pad($seqStmt->fetchColumn(), 3, '0', STR_PAD_LEFT);
            $schoolShort = getSetting('school_short_name', 'JNV');
            $reference_no = "{$schoolShort}/HR/{$typeCode}/{$year}/{$seq}";

            $extra_data = json_encode([
                'notice_period' => $notice_period,
            ]);

            $stmt = $db->prepare("INSERT INTO hr_letters (employee_id, letter_type, reference_no, issue_date, effective_date, salary_old, salary_new, increment_pct, last_working_date, notice_period, reason, extra_data, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $employee_id, $letter_type, $reference_no, $issue_date,
                $effective_date ?: null, $salary_old ?: null, $salary_new ?: null,
                $increment_pct ?: null, $last_working_date ?: null,
                $notice_period, $reason, $extra_data, $status,
                currentUser()['id'] ?? 0
            ]);

            auditLog('hr_letter_create', 'hr_letters', $db->lastInsertId(), "Created $letter_type letter: $reference_no");
            echo json_encode(['success' => true, 'reference_no' => $reference_no, 'id' => $db->lastInsertId()]);
            break;

        case 'update_letter':
            $id = (int)($_POST['letter_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid letter ID.']);
                exit;
            }
            $effective_date = $_POST['effective_date'] ?? null;
            $salary_old = $_POST['salary_old'] ?? null;
            $salary_new = $_POST['salary_new'] ?? null;
            $increment_pct = $_POST['increment_pct'] ?? null;
            $last_working_date = $_POST['last_working_date'] ?? null;
            $notice_period = trim($_POST['notice_period'] ?? '');
            $reason = trim($_POST['reason'] ?? '');

            $stmt = $db->prepare("UPDATE hr_letters SET effective_date=?, salary_old=?, salary_new=?, increment_pct=?, last_working_date=?, notice_period=?, reason=? WHERE id=? AND status='draft'");
            $stmt->execute([
                $effective_date ?: null, $salary_old ?: null, $salary_new ?: null,
                $increment_pct ?: null, $last_working_date ?: null,
                $notice_period, $reason, $id
            ]);
            auditLog('hr_letter_update', 'hr_letters', $id, 'Updated letter');
            echo json_encode(['success' => true]);
            break;

        case 'change_status':
            $id = (int)($_POST['id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? '';
            if ($id > 0 && in_array($newStatus, ['draft','issued'])) {
                $db->prepare("UPDATE hr_letters SET status=? WHERE id=?")->execute([$newStatus, $id]);
                auditLog('hr_letter_status', 'hr_letters', $id, "Changed status to $newStatus");
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete_letter':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM hr_letters WHERE id=? AND status='draft'")->execute([$id]);
                auditLog('hr_letter_delete', 'hr_letters', $id, 'Deleted draft letter');
            }
            echo json_encode(['success' => true]);
            break;

        case 'send_email':
            $letterId = (int)($_POST['letter_id'] ?? 0);
            if ($letterId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid letter ID.']);
                exit;
            }

            // Fetch letter + employee
            $stmt = $db->prepare("SELECT l.*, e.name AS emp_name, e.email AS emp_email, e.employee_id AS emp_code FROM hr_letters l JOIN hr_employees e ON l.employee_id = e.id WHERE l.id=?");
            $stmt->execute([$letterId]);
            $lt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lt) {
                echo json_encode(['success' => false, 'error' => 'Letter not found.']);
                exit;
            }

            $empEmail = trim($lt['emp_email'] ?? '');
            if (!$empEmail || !filter_var($empEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Employee does not have a valid email address.']);
                exit;
            }

            // Build the letter HTML using the same logic as letter-preview.php
            require_once __DIR__ . '/seed-templates.php';
            seedLetterTemplates($db);

            $tplStmt = $db->prepare("SELECT template_content FROM letter_templates WHERE letter_type=? AND status='active'");
            $tplStmt->execute([$lt['letter_type']]);
            $templateContent = $tplStmt->fetchColumn();
            if (!$templateContent) {
                echo json_encode(['success' => false, 'error' => 'No active template for this letter type.']);
                exit;
            }

            // Fetch full employee for all placeholders
            $empStmt = $db->prepare("SELECT * FROM hr_employees WHERE id=?");
            $empStmt->execute([$lt['employee_id']]);
            $emp = $empStmt->fetch(PDO::FETCH_ASSOC);

            $schoolName = getSetting('school_name', 'JNV School');
            $schoolLogo = getSetting('school_logo', '');
            $hrLogo = getSetting('hr_logo', '');
            $logoUrl = $hrLogo ?: $schoolLogo;
            $hrSignature = getSetting('hr_digital_signature', '');
            $signatureHtml = $hrSignature ? '<img src="' . htmlspecialchars($hrSignature) . '" style="max-height:60px;max-width:200px;margin-bottom:5px;" alt="Signature">' : '';

            $replacements = [
                '{{school_name}}' => htmlspecialchars($schoolName),
                '{{school_logo}}' => htmlspecialchars($logoUrl),
                '{{hr_logo}}' => htmlspecialchars($logoUrl),
                '{{school_address}}' => htmlspecialchars(getSetting('school_address', '')),
                '{{employee_name}}' => htmlspecialchars($emp['name'] ?? ''),
                '{{employee_id}}' => htmlspecialchars($emp['employee_id'] ?? ''),
                '{{designation}}' => htmlspecialchars($emp['designation'] ?? ''),
                '{{department}}' => htmlspecialchars($emp['department'] ?? ''),
                '{{date_of_joining}}' => $emp['date_of_joining'] ? date('d M Y', strtotime($emp['date_of_joining'])) : '—',
                '{{salary_old}}' => number_format($lt['salary_old'] ?? 0, 0),
                '{{salary_new}}' => number_format($lt['salary_new'] ?? $emp['salary'] ?? 0, 0),
                '{{increment_pct}}' => $lt['increment_pct'] ?? '0',
                '{{effective_date}}' => $lt['effective_date'] ? date('d M Y', strtotime($lt['effective_date'])) : '—',
                '{{issue_date}}' => date('d M Y', strtotime($lt['issue_date'])),
                '{{reference_no}}' => htmlspecialchars($lt['reference_no']),
                '{{last_working_date}}' => $lt['last_working_date'] ? date('d M Y', strtotime($lt['last_working_date'])) : '—',
                '{{notice_period}}' => htmlspecialchars($lt['notice_period'] ?? '—'),
                '{{reporting_to}}' => htmlspecialchars($emp['reporting_to'] ?? '—'),
                '{{reason}}' => htmlspecialchars($lt['reason'] ?? '—'),
                '{{probation_months}}' => $emp['probation_months'] ?? 6,
                '{{digital_signature}}' => $signatureHtml,
            ];

            $letterHtml = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

            // Wrap in email-friendly HTML
            $typeLabels = ['appointment'=>'Appointment Letter','joining'=>'Joining Confirmation','resignation'=>'Resignation Acceptance','hike'=>'Salary Hike Letter'];
            $subject = ($typeLabels[$lt['letter_type']] ?? 'Letter') . ' — ' . $lt['reference_no'] . ' | ' . $schoolName;

            $emailBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:20px;background:#f5f5f5;">' . $letterHtml . '</body></html>';

            // Send email using PHP mail()
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . $schoolName . " <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'school.com') . ">\r\n";
            $headers .= "Reply-To: " . getSetting('school_email', 'admin@school.com') . "\r\n";

            $sent = mail($empEmail, $subject, $emailBody, $headers);

            if ($sent) {
                // Record email sent in extra_data
                $extraData = json_decode($lt['extra_data'] ?? '{}', true);
                $extraData['email_sent'] = true;
                $extraData['email_sent_at'] = date('Y-m-d H:i:s');
                $extraData['email_sent_to'] = $empEmail;
                $db->prepare("UPDATE hr_letters SET extra_data=? WHERE id=?")->execute([json_encode($extraData), $letterId]);
                auditLog('hr_letter_email', 'hr_letters', $letterId, "Emailed letter to $empEmail");
                echo json_encode(['success' => true, 'message' => "Email sent to $empEmail"]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send email. Check server mail configuration.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}