<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$db = getDB();

// Seed templates if needed
require_once __DIR__ . '/seed-templates.php';
seedLetterTemplates($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo 'Invalid letter ID.'; exit; }

// Fetch letter + employee
$stmt = $db->prepare("SELECT l.*, e.employee_id AS emp_code, e.name AS emp_name, e.designation, e.department, e.email, e.phone, e.date_of_joining, e.salary AS current_salary, e.probation_months, e.reporting_to FROM hr_letters l JOIN hr_employees e ON l.employee_id = e.id WHERE l.id=?");
$stmt->execute([$id]);
$letter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$letter) { echo 'Letter not found.'; exit; }

// Fetch template
$tpl = $db->prepare("SELECT template_content FROM letter_templates WHERE letter_type=? AND status='active'");
$tpl->execute([$letter['letter_type']]);
$template = $tpl->fetchColumn();
if (!$template) { echo 'No active template found for this letter type.'; exit; }

// School settings
$schoolName = getSetting('school_name', 'JNV School');
$schoolLogo = getSetting('school_logo', '');
$schoolAddress = getSetting('school_address', '');

// HR branding
$hrLogo = getSetting('hr_logo', '');
$logoUrl = $hrLogo ?: $schoolLogo; // Fallback to school logo

$hrSignature = getSetting('hr_digital_signature', '');
$signatureHtml = $hrSignature ? '<img src="' . htmlspecialchars($hrSignature) . '" style="max-height:60px;max-width:200px;margin-bottom:5px;" alt="Signature">' : '';

// Replace placeholders
$replacements = [
    '{{school_name}}' => htmlspecialchars($schoolName),
    '{{school_logo}}' => htmlspecialchars($logoUrl),
    '{{hr_logo}}' => htmlspecialchars($logoUrl),
    '{{school_address}}' => htmlspecialchars($schoolAddress),
    '{{employee_name}}' => htmlspecialchars($letter['emp_name']),
    '{{employee_id}}' => htmlspecialchars($letter['emp_code']),
    '{{designation}}' => htmlspecialchars($letter['designation'] ?? ''),
    '{{department}}' => htmlspecialchars($letter['department'] ?? ''),
    '{{date_of_joining}}' => $letter['date_of_joining'] ? date('d M Y', strtotime($letter['date_of_joining'])) : '—',
    '{{salary_old}}' => number_format($letter['salary_old'] ?? 0, 0),
    '{{salary_new}}' => number_format($letter['salary_new'] ?? $letter['current_salary'] ?? 0, 0),
    '{{increment_pct}}' => $letter['increment_pct'] ?? '0',
    '{{effective_date}}' => $letter['effective_date'] ? date('d M Y', strtotime($letter['effective_date'])) : '—',
    '{{issue_date}}' => date('d M Y', strtotime($letter['issue_date'])),
    '{{reference_no}}' => htmlspecialchars($letter['reference_no']),
    '{{last_working_date}}' => $letter['last_working_date'] ? date('d M Y', strtotime($letter['last_working_date'])) : '—',
    '{{notice_period}}' => htmlspecialchars($letter['notice_period'] ?? '—'),
    '{{reporting_to}}' => htmlspecialchars($letter['reporting_to'] ?? '—'),
    '{{reason}}' => htmlspecialchars($letter['reason'] ?? '—'),
    '{{probation_months}}' => $letter['probation_months'] ?? 6,
    '{{digital_signature}}' => $signatureHtml,
];

$html = str_replace(array_keys($replacements), array_values($replacements), $template);

// Check if employee has email
$empEmail = trim($letter['email'] ?? '');
$canEmail = !empty($empEmail) && filter_var($empEmail, FILTER_VALIDATE_EMAIL);

// Check email sent status
$extraData = json_decode($letter['extra_data'] ?? '{}', true);
$emailSent = !empty($extraData['email_sent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($letter['letter_type']) ?> Letter — <?= e($letter['reference_no']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Times New Roman', serif; }
        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: #1e40af; color: #fff; padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .toolbar h5 { margin: 0; font-family: 'Inter', sans-serif; font-size: 14px; }
        .toolbar .badge { background: <?= $letter['status'] === 'issued' ? '#22c55e' : '#f59e0b' ?>; padding: 4px 10px; border-radius: 6px; font-size: 11px; margin-left: 8px; }
        .toolbar .badge-email { background: #8b5cf6; padding: 4px 10px; border-radius: 6px; font-size: 11px; margin-left: 4px; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar-actions button {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
            color: #fff; padding: 6px 16px; border-radius: 8px; cursor: pointer;
            font-size: 13px; font-family: 'Inter', sans-serif;
            transition: background 0.2s;
        }
        .toolbar-actions button:hover { background: rgba(255,255,255,0.25); }
        .toolbar-actions button.btn-email { background: rgba(139,92,246,0.3); border-color: rgba(139,92,246,0.5); }
        .toolbar-actions button.btn-email:hover { background: rgba(139,92,246,0.5); }
        .toolbar-actions button:disabled { opacity: 0.5; cursor: not-allowed; }

        .letter-container {
            max-width: 850px; margin: 70px auto 40px; background: #fff;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1); padding: 0;
            min-height: 1100px;
        }
        .letter-body { padding: 20px 50px 50px; }

        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .letter-container { box-shadow: none; margin: 0; max-width: 100%; }
            .letter-body { padding: 20px 40px 40px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h5 style="display:inline;"><?= ucfirst($letter['letter_type']) ?> Letter — <?= e($letter['reference_no']) ?></h5>
            <span class="badge"><?= ucfirst($letter['status']) ?></span>
            <?php if ($emailSent): ?><span class="badge-email"><i class="bi bi-envelope-check me-1"></i>Emailed</span><?php endif; ?>
        </div>
        <div class="toolbar-actions">
            <?php if ($canEmail): ?>
            <button class="btn-email" onclick="sendEmail()" id="btnEmail" <?= $emailSent ? '' : '' ?>>
                <i class="bi bi-envelope-at me-1"></i><?= $emailSent ? 'Resend Email' : 'Send via Email' ?>
            </button>
            <?php endif; ?>
            <button onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save PDF</button>
            <button onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="letter-container">
        <div class="letter-body">
            <?= $html ?>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <script>
    function sendEmail() {
        const btn = document.getElementById('btnEmail');
        if (!confirm('Send this letter via email to <?= htmlspecialchars($empEmail) ?>?')) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Sending...';

        const data = new FormData();
        data.append('csrf_token', '<?= csrfToken() ?>');
        data.append('action', 'send_email');
        data.append('letter_id', '<?= $id ?>');

        fetch('/admin/hr/letter-actions.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    btn.innerHTML = '<i class="bi bi-envelope-check me-1"></i>Email Sent!';
                    btn.style.background = 'rgba(34,197,94,0.3)';
                    btn.style.borderColor = 'rgba(34,197,94,0.5)';
                    setTimeout(() => {
                        btn.innerHTML = '<i class="bi bi-envelope-at me-1"></i>Resend Email';
                        btn.disabled = false;
                    }, 3000);
                } else {
                    alert(res.error || 'Failed to send email.');
                    btn.innerHTML = '<i class="bi bi-envelope-at me-1"></i>Send via Email';
                    btn.disabled = false;
                }
            })
            .catch(() => {
                alert('Network error.');
                btn.innerHTML = '<i class="bi bi-envelope-at me-1"></i>Send via Email';
                btn.disabled = false;
            });
    }
    </script>
</body>
</html>