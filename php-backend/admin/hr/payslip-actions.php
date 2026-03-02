<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
verifyCsrf();
$db = getDB();

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'create_payslip':
            $empId = (int)($_POST['employee_id'] ?? 0);
            $month = trim($_POST['pay_month'] ?? '');
            if (!$empId || !preg_match('/^\d{4}-\d{2}$/', $month)) {
                echo json_encode(['success' => false, 'error' => 'Invalid employee or month.']);
                exit;
            }

            $basic = (float)($_POST['basic_salary'] ?? 0);
            $hra = (float)($_POST['hra'] ?? 0);
            $da = (float)($_POST['da'] ?? 0);
            $otherAllow = (float)($_POST['other_allowances'] ?? 0);
            $pf = (float)($_POST['pf_deduction'] ?? 0);
            $tax = (float)($_POST['tax_deduction'] ?? 0);
            $otherDed = (float)($_POST['other_deductions'] ?? 0);
            $net = ($basic + $hra + $da + $otherAllow) - ($pf + $tax + $otherDed);

            $user = currentUser();
            $stmt = $db->prepare("INSERT INTO hr_payslips (employee_id, pay_month, basic_salary, hra, da, other_allowances, pf_deduction, tax_deduction, other_deductions, net_salary, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?)");
            $stmt->execute([$empId, $month, $basic, $hra, $da, $otherAllow, $pf, $tax, $otherDed, $net, $user['id'] ?? 0]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'delete_payslip':
            $id = (int)($_POST['payslip_id'] ?? 0);
            $db->prepare("DELETE FROM hr_payslips WHERE id=? AND status='draft'")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'change_status':
            $id = (int)($_POST['payslip_id'] ?? 0);
            $status = $_POST['status'] ?? 'issued';
            if (!in_array($status, ['draft','issued'])) $status = 'issued';
            $db->prepare("UPDATE hr_payslips SET status=? WHERE id=?")->execute([$status, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'send_email':
            $id = (int)($_POST['payslip_id'] ?? 0);
            $stmt = $db->prepare("SELECT p.*, e.name AS emp_name, e.email AS emp_email, e.employee_id AS emp_code FROM hr_payslips p JOIN hr_employees e ON p.employee_id=e.id WHERE p.id=?");
            $stmt->execute([$id]);
            $ps = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ps) { echo json_encode(['success' => false, 'error' => 'Payslip not found.']); exit; }

            $email = trim($ps['emp_email'] ?? '');
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Employee has no valid email address.']);
                exit;
            }

            $schoolName = getSetting('school_name', 'JNV School');
            $monthLabel = date('F Y', strtotime($ps['pay_month'] . '-01'));
            $totalEarn = $ps['basic_salary'] + $ps['hra'] + $ps['da'] + $ps['other_allowances'];
            $totalDed = $ps['pf_deduction'] + $ps['tax_deduction'] + $ps['other_deductions'];

            $subject = "Payslip for $monthLabel — $schoolName";
            $body = '<html><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">';
            $body .= '<h2 style="color:#1e40af;">Payslip — ' . htmlspecialchars($monthLabel) . '</h2>';
            $body .= '<p>Dear ' . htmlspecialchars($ps['emp_name']) . ',</p>';
            $body .= '<p>Please find your payslip details for <strong>' . $monthLabel . '</strong>:</p>';
            $body .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;" border="1" cellpadding="8">';
            $body .= '<tr style="background:#f0f4ff;"><th colspan="2">Earnings</th></tr>';
            $body .= '<tr><td>Basic Salary</td><td style="text-align:right;">₹' . number_format($ps['basic_salary'], 2) . '</td></tr>';
            $body .= '<tr><td>HRA</td><td style="text-align:right;">₹' . number_format($ps['hra'], 2) . '</td></tr>';
            $body .= '<tr><td>DA</td><td style="text-align:right;">₹' . number_format($ps['da'], 2) . '</td></tr>';
            $body .= '<tr><td>Other Allowances</td><td style="text-align:right;">₹' . number_format($ps['other_allowances'], 2) . '</td></tr>';
            $body .= '<tr style="background:#e8ffe8;"><td><strong>Total Earnings</strong></td><td style="text-align:right;"><strong>₹' . number_format($totalEarn, 2) . '</strong></td></tr>';
            $body .= '<tr style="background:#fff0f0;"><th colspan="2">Deductions</th></tr>';
            $body .= '<tr><td>PF Deduction</td><td style="text-align:right;">₹' . number_format($ps['pf_deduction'], 2) . '</td></tr>';
            $body .= '<tr><td>Tax Deduction</td><td style="text-align:right;">₹' . number_format($ps['tax_deduction'], 2) . '</td></tr>';
            $body .= '<tr><td>Other Deductions</td><td style="text-align:right;">₹' . number_format($ps['other_deductions'], 2) . '</td></tr>';
            $body .= '<tr style="background:#ffe8e8;"><td><strong>Total Deductions</strong></td><td style="text-align:right;"><strong>₹' . number_format($totalDed, 2) . '</strong></td></tr>';
            $body .= '<tr style="background:#e0e7ff;font-size:1.1em;"><td><strong>Net Salary</strong></td><td style="text-align:right;"><strong>₹' . number_format($ps['net_salary'], 2) . '</strong></td></tr>';
            $body .= '</table>';
            $body .= '<p style="color:#666;font-size:12px;">This is a system-generated payslip from ' . htmlspecialchars($schoolName) . '.</p>';
            $body .= '</body></html>';

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . htmlspecialchars($schoolName) . " <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'school.com') . ">\r\n";

            $sent = @mail($email, $subject, $body, $headers);
            if ($sent) {
                // Record email sent in extra_data
                $extra = json_decode($ps['extra_data'] ?? '{}', true) ?: [];
                $extra['email_sent'] = true;
                $extra['email_sent_at'] = date('Y-m-d H:i:s');
                $db->prepare("UPDATE hr_payslips SET extra_data=? WHERE id=?")->execute([json_encode($extra), $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send email. Check server mail configuration.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    }
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, 'Duplicate entry')) {
        echo json_encode(['success' => false, 'error' => 'A payslip already exists for this employee and month.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $msg]);
    }
}