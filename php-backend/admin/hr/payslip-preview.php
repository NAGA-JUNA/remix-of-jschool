<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo 'Invalid payslip ID.'; exit; }

$stmt = $db->prepare("SELECT p.*, e.name AS emp_name, e.employee_id AS emp_code, e.designation, e.department, e.email, e.date_of_joining FROM hr_payslips p JOIN hr_employees e ON p.employee_id=e.id WHERE p.id=?");
$stmt->execute([$id]);
$ps = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ps) { echo 'Payslip not found.'; exit; }

$schoolName = getSetting('school_name', 'JNV School');
$schoolAddress = getSetting('school_address', '');
$hrLogo = getSetting('hr_logo', '');
$schoolLogo = getSetting('school_logo', '');
$logoUrl = $hrLogo ?: $schoolLogo;
$hrSignature = getSetting('hr_digital_signature', '');

$monthLabel = date('F Y', strtotime($ps['pay_month'] . '-01'));
$totalEarn = $ps['basic_salary'] + $ps['hra'] + $ps['da'] + $ps['other_allowances'];
$totalDed = $ps['pf_deduction'] + $ps['tax_deduction'] + $ps['other_deductions'];

$empEmail = trim($ps['email'] ?? '');
$canEmail = !empty($empEmail) && filter_var($empEmail, FILTER_VALIDATE_EMAIL);
$extraData = json_decode($ps['extra_data'] ?? '{}', true);
$emailSent = !empty($extraData['email_sent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip — <?= e($ps['emp_name']) ?> — <?= $monthLabel ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: Arial, sans-serif; }
        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: #1e40af; color: #fff; padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .toolbar h5 { margin: 0; font-size: 14px; }
        .toolbar .badge { background: <?= $ps['status'] === 'issued' ? '#22c55e' : '#f59e0b' ?>; padding: 4px 10px; border-radius: 6px; font-size: 11px; margin-left: 8px; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar-actions button {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
            color: #fff; padding: 6px 16px; border-radius: 8px; cursor: pointer;
            font-size: 13px; transition: background 0.2s;
        }
        .toolbar-actions button:hover { background: rgba(255,255,255,0.25); }
        .toolbar-actions button.btn-email { background: rgba(139,92,246,0.3); border-color: rgba(139,92,246,0.5); }
        .toolbar-actions button.btn-email:hover { background: rgba(139,92,246,0.5); }

        .payslip-container {
            max-width: 850px; margin: 70px auto 40px; background: #fff;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1); padding: 50px;
        }
        .payslip-header { text-align: center; margin-bottom: 30px; }
        .payslip-header img { height: 70px; margin-bottom: 10px; }
        .payslip-header h2 { color: #1e40af; margin: 0; }
        .payslip-header p { color: #666; font-size: 14px; margin: 3px 0; }
        .payslip-title { text-align: center; margin: 20px 0; padding: 10px; background: #e0e7ff; border-radius: 8px; }
        .payslip-title h3 { margin: 0; color: #1e40af; }
        .payslip-title small { color: #666; }

        .emp-info { display: flex; justify-content: space-between; margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; }
        .emp-info div { font-size: 14px; }
        .emp-info strong { display: block; color: #374151; }
        .emp-info span { color: #6b7280; }

        .salary-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .salary-table th, .salary-table td { padding: 10px 15px; border: 1px solid #e5e7eb; font-size: 14px; }
        .salary-table th { background: #f0f4ff; color: #1e40af; text-align: left; }
        .salary-table .total-row { background: #f0fdf4; font-weight: bold; }
        .salary-table .ded-header { background: #fff0f0; color: #dc2626; }
        .salary-table .total-ded { background: #fef2f2; font-weight: bold; }
        .salary-table .net-row { background: #e0e7ff; font-size: 1.1em; font-weight: bold; }
        .salary-table .amount { text-align: right; }

        .signature-area { margin-top: 60px; text-align: right; }
        .signature-area img { max-height: 60px; max-width: 200px; margin-bottom: 5px; }
        .footer-note { text-align: center; margin-top: 40px; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb; padding-top: 15px; }

        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .payslip-container { box-shadow: none; margin: 0; max-width: 100%; padding: 30px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h5 style="display:inline;">Payslip — <?= e($ps['emp_name']) ?> — <?= $monthLabel ?></h5>
            <span class="badge"><?= ucfirst($ps['status']) ?></span>
            <?php if ($emailSent): ?><span class="badge" style="background:#8b5cf6;margin-left:4px;">Emailed</span><?php endif; ?>
        </div>
        <div class="toolbar-actions">
            <?php if ($canEmail): ?>
            <button class="btn-email" onclick="sendEmail()" id="btnEmail">
                <i class="bi bi-envelope-at me-1"></i><?= $emailSent ? 'Resend Email' : 'Send via Email' ?>
            </button>
            <?php endif; ?>
            <button onclick="window.print()">Print / Save PDF</button>
            <button onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="payslip-container">
        <!-- Header -->
        <div class="payslip-header">
            <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo"><?php endif; ?>
            <h2><?= htmlspecialchars($schoolName) ?></h2>
            <?php if ($schoolAddress): ?><p><?= htmlspecialchars($schoolAddress) ?></p><?php endif; ?>
            <hr style="border:2px solid #1e40af;margin:15px 0;">
        </div>

        <!-- Title -->
        <div class="payslip-title">
            <h3>PAYSLIP</h3>
            <small>For the month of <?= $monthLabel ?></small>
        </div>

        <!-- Employee Info -->
        <div class="emp-info">
            <div>
                <strong>Employee Name</strong><span><?= htmlspecialchars($ps['emp_name']) ?></span>
                <strong style="margin-top:8px;">Employee ID</strong><span><?= htmlspecialchars($ps['emp_code']) ?></span>
            </div>
            <div>
                <strong>Designation</strong><span><?= htmlspecialchars($ps['designation'] ?? '—') ?></span>
                <strong style="margin-top:8px;">Department</strong><span><?= htmlspecialchars($ps['department'] ?? '—') ?></span>
            </div>
            <div>
                <strong>Date of Joining</strong><span><?= $ps['date_of_joining'] ? date('d M Y', strtotime($ps['date_of_joining'])) : '—' ?></span>
                <strong style="margin-top:8px;">Pay Period</strong><span><?= $monthLabel ?></span>
            </div>
        </div>

        <!-- Salary Table -->
        <table class="salary-table">
            <tr><th colspan="2">Earnings</th></tr>
            <tr><td>Basic Salary</td><td class="amount">₹<?= number_format($ps['basic_salary'], 2) ?></td></tr>
            <tr><td>House Rent Allowance (HRA)</td><td class="amount">₹<?= number_format($ps['hra'], 2) ?></td></tr>
            <tr><td>Dearness Allowance (DA)</td><td class="amount">₹<?= number_format($ps['da'], 2) ?></td></tr>
            <tr><td>Other Allowances</td><td class="amount">₹<?= number_format($ps['other_allowances'], 2) ?></td></tr>
            <tr class="total-row"><td><strong>Total Earnings</strong></td><td class="amount"><strong>₹<?= number_format($totalEarn, 2) ?></strong></td></tr>

            <tr><th class="ded-header" colspan="2">Deductions</th></tr>
            <tr><td>Provident Fund (PF)</td><td class="amount">₹<?= number_format($ps['pf_deduction'], 2) ?></td></tr>
            <tr><td>Tax Deduction</td><td class="amount">₹<?= number_format($ps['tax_deduction'], 2) ?></td></tr>
            <tr><td>Other Deductions</td><td class="amount">₹<?= number_format($ps['other_deductions'], 2) ?></td></tr>
            <tr class="total-ded"><td><strong>Total Deductions</strong></td><td class="amount"><strong>₹<?= number_format($totalDed, 2) ?></strong></td></tr>

            <tr class="net-row"><td>Net Salary Payable</td><td class="amount">₹<?= number_format($ps['net_salary'], 2) ?></td></tr>
        </table>

        <!-- Signature -->
        <div class="signature-area">
            <?php if ($hrSignature): ?>
            <img src="<?= htmlspecialchars($hrSignature) ?>" alt="Signature"><br>
            <?php endif; ?>
            <div style="border-top:1px solid #333;display:inline-block;padding-top:5px;width:200px;text-align:center;">
                <strong>Authorized Signatory</strong><br>
                <?= htmlspecialchars($schoolName) ?>
            </div>
        </div>

        <div class="footer-note">
            This is a computer-generated payslip and does not require a physical signature.<br>
            Generated on <?= date('d M Y H:i') ?>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script>
    function sendEmail() {
        const btn = document.getElementById('btnEmail');
        if (!confirm('Send payslip via email to <?= htmlspecialchars($empEmail) ?>?')) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Sending...';

        const data = new FormData();
        data.append('csrf_token', '<?= csrfToken() ?>');
        data.append('action', 'send_email');
        data.append('payslip_id', '<?= $id ?>');

        fetch('/admin/hr/payslip-actions.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    btn.innerHTML = '<i class="bi bi-envelope-check me-1"></i>Email Sent!';
                    btn.style.background = 'rgba(34,197,94,0.3)';
                    setTimeout(() => { btn.innerHTML = '<i class="bi bi-envelope-at me-1"></i>Resend Email'; btn.disabled = false; }, 3000);
                } else {
                    alert(res.error || 'Failed to send.');
                    btn.innerHTML = '<i class="bi bi-envelope-at me-1"></i>Send via Email';
                    btn.disabled = false;
                }
            })
            .catch(() => { alert('Network error.'); btn.innerHTML = '<i class="bi bi-envelope-at me-1"></i>Send via Email'; btn.disabled = false; });
    }
    </script>
</body>
</html>