<?php
require_once __DIR__.'/../includes/auth.php';
checkMaintenance();
$db = getDB();
$schoolName = getSetting('school_name', 'JNV School');
$schoolTagline = getSetting('school_tagline', 'Nurturing Talent, Shaping Future');
$schoolEmail = getSetting('school_email', '');
$schoolPhone = getSetting('school_phone', '');
$schoolAddress = getSetting('school_address', '');
$whatsappNumber = getSetting('whatsapp_api_number', '');
$navLogo = getSetting('school_logo', '');
$logoVersion = getSetting('logo_updated_at', '0');
$logoPath = '';
if ($navLogo) { $logoPath = (strpos($navLogo, '/uploads/') === 0) ? $navLogo : (file_exists(__DIR__.'/../uploads/branding/'.$navLogo) ? '/uploads/branding/'.$navLogo : '/uploads/logo/'.$navLogo); $logoPath .= '?v=' . $logoVersion; }

$socialFacebook = getSetting('social_facebook', '');
$socialTwitter = getSetting('social_twitter', '');
$socialInstagram = getSetting('social_instagram', '');
$socialYoutube = getSetting('social_youtube', '');
$socialLinkedin = getSetting('social_linkedin', '');

$bellNotifs = $db->query("SELECT title, type, created_at FROM notifications WHERE status='approved' AND is_public=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
$notifCount = $db->query("SELECT COUNT(*) FROM notifications WHERE status='approved' AND is_public=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Seat availability
$academicYear = getSetting('academic_year', date('Y').'-'.(date('Y')+1));
$seatData = [];
try {
    $seatStmt = $db->prepare("SELECT c.class, c.total_seats, 
        COALESCE((SELECT COUNT(*) FROM admissions a WHERE a.class_applied=c.class AND a.status IN ('approved','converted')),0) as filled
        FROM class_seat_capacity c WHERE c.academic_year=? AND c.is_active=1 GROUP BY c.class, c.total_seats ORDER BY CASE WHEN c.class REGEXP '^[0-9]+$' THEN CAST(c.class AS UNSIGNED) ELSE 999 END, c.class ASC");
    $seatStmt->execute([$academicYear]);
    while ($r = $seatStmt->fetch()) {
        $seatData[$r['class']] = ['total'=>(int)$r['total_seats'], 'filled'=>(int)$r['filled'], 'available'=>(int)$r['total_seats']-(int)$r['filled']];
    }
} catch (Exception $e) { /* table may not exist yet */ }

$success = false;
$applicationId = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = trim($_POST['student_name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $aadharNo = trim($_POST['aadhar_no'] ?? '');
    $classApplied = $_POST['class_applied'] ?? '';
    $previousSchool = trim($_POST['previous_school'] ?? '');
    $fatherName = trim($_POST['father_name'] ?? '');
    $fatherPhone = trim($_POST['father_phone'] ?? '');
    $fatherOccupation = trim($_POST['father_occupation'] ?? '');
    $motherName = trim($_POST['mother_name'] ?? '');
    $motherOccupation = trim($_POST['mother_occupation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $village = trim($_POST['village'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');

    if (!$studentName) $errors[] = 'Student name is required.';
    if (!$dob) $errors[] = 'Date of birth is required.';
    if (!$gender) $errors[] = 'Gender is required.';
    if (!$classApplied) $errors[] = 'Class applied for is required.';
    if (!$fatherName) $errors[] = "Father's name is required.";
    if (!$phone) $errors[] = 'Phone number is required.';
    if ($phone && !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone must be 10 digits.';
    if ($fatherPhone && !preg_match('/^[0-9]{10}$/', $fatherPhone)) $errors[] = "Father's phone must be 10 digits.";
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if ($aadharNo && !preg_match('/^[0-9]{12}$/', $aadharNo)) $errors[] = 'Aadhar must be 12 digits.';

    // Handle multiple document uploads
    $uploadedDocs = [];
    if (!empty($_FILES['documents']['name'][0])) {
        $allowed = ['application/pdf','image/jpeg','image/png','image/webp'];
        $maxSize = 5*1024*1024;
        $uploadDir = __DIR__.'/../uploads/documents/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $docLabels = ['birth_certificate','transfer_certificate','report_card','photo','aadhar_card'];
        
        foreach ($_FILES['documents']['name'] as $idx => $name) {
            if ($_FILES['documents']['error'][$idx] !== UPLOAD_ERR_OK || empty($name)) continue;
            $label = $docLabels[$idx] ?? 'other_'.$idx;
            $ftype = $_FILES['documents']['type'][$idx];
            $fsize = $_FILES['documents']['size'][$idx];
            if (!in_array($ftype, $allowed)) { $errors[] = "File '$name': Invalid type. Only PDF/JPG/PNG/WEBP."; continue; }
            if ($fsize > $maxSize) { $errors[] = "File '$name': Exceeds 5MB limit."; continue; }
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = 'adm_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$idx], $uploadDir.$filename)) {
                $uploadedDocs[$label] = 'uploads/documents/'.$filename;
            }
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate application ID
            $year = date('Y');
            $lastId = $db->query("SELECT MAX(id) FROM admissions")->fetchColumn();
            $nextNum = ($lastId ?: 0) + 1;
            $applicationId = 'ADM-'.$year.'-'.str_pad($nextNum, 5, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("INSERT INTO admissions (application_id, student_name, dob, gender, blood_group, category, aadhar_no, class_applied, previous_school, father_name, father_phone, father_occupation, mother_name, mother_occupation, phone, email, address, village, district, state, pincode, documents, status, source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'new','online')");
            $stmt->execute([$applicationId, $studentName, $dob, $gender, $bloodGroup ?: null, $category ?: null, $aadharNo ?: null, $classApplied, $previousSchool, $fatherName, $fatherPhone ?: null, $fatherOccupation ?: null, $motherName ?: null, $motherOccupation ?: null, $phone, $email ?: null, $address, $village ?: null, $district ?: null, $state ?: null, $pincode ?: null, !empty($uploadedDocs) ? json_encode($uploadedDocs) : null]);
            
            $admId = (int)$db->lastInsertId();
            
            // Log initial status (wrapped in try-catch so missing table doesn't break submission)
            try {
                $db->prepare("INSERT INTO admission_status_history (admission_id, old_status, new_status, remarks) VALUES (?, NULL, 'new', 'Application submitted online')")->execute([$admId]);
            } catch (Exception $histErr) {
                error_log("admission_status_history insert failed (table may not exist): " . $histErr->getMessage());
            }
            
            $db->commit();
            
            auditLog('public_admission_submit', 'admission', $admId, "Student: $studentName, Class: $classApplied, App: $applicationId");
            $success = true;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Admission submit failed: " . $e->getMessage());
            $errors[] = "Something went wrong while submitting. Please try again. If the problem persists, contact the school office.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply for Admission — <?= e($schoolName) ?></title>
    <meta name="description" content="Apply for admission at <?= e($schoolName) ?>. Submit your application online with our easy multi-step form.">
    <?php $favicon = getSetting('school_favicon', ''); if ($favicon): $favVer = getSetting('favicon_updated_at', '0'); $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }
        .top-bar { background: #0a0f1a; color: #fff; padding: 0.4rem 0; font-size: 0.78rem; }
        .top-bar a { color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.2s; }
        .top-bar a:hover { color: #fff; }
        .marquee-text { white-space: nowrap; overflow: hidden; }
        .marquee-text span { display: inline-block; animation: marqueeScroll 20s linear infinite; }
        @keyframes marqueeScroll { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        .main-navbar { background: #0f172a; padding: 0.5rem 0; }
        .main-navbar .nav-link { color: rgba(255,255,255,0.85); font-weight: 500; font-size: 0.9rem; padding: 0.5rem 0.8rem; }
        .main-navbar .nav-link:hover, .main-navbar .nav-link.active { color: #fff; }
        .notif-bell-btn { background: #dc3545; color: #fff; border: none; border-radius: 8px; padding: 0.4rem 0.9rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; position: relative; transition: background 0.2s; }
        .notif-bell-btn:hover { background: #c82333; }
        .notif-badge { position: absolute; top: -6px; right: -8px; background: #ffc107; color: #000; font-size: 0.65rem; font-weight: 700; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
        .login-nav-btn { background: transparent; border: 1.5px solid rgba(255,255,255,0.5); color: #fff; border-radius: 8px; padding: 0.4rem 1.2rem; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.2s; }
        .login-nav-btn:hover { background: #fff; color: #0f172a; }
        .whatsapp-float { position: fixed; bottom: 24px; right: 24px; z-index: 9999; width: 60px; height: 60px; border-radius: 50%; background: #25D366; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; text-decoration: none; box-shadow: 0 4px 20px rgba(37,211,102,0.4); transition: transform 0.3s; animation: whatsappPulse 2s infinite; }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }
        @keyframes whatsappPulse { 0%, 100% { box-shadow: 0 4px 20px rgba(37,211,102,0.4); } 50% { box-shadow: 0 4px 30px rgba(37,211,102,0.7); } }
        .hero-banner { background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%); color: #fff; padding: 3rem 0; }
        .site-footer { background: #1a1a2e; color: #fff; margin-top: 0; }
        .footer-cta { background: #0f2557; padding: 4rem 0; text-align: center; }
        .footer-cta h2 { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 2.2rem; color: #fff; margin-bottom: 1rem; }
        .footer-cta p { color: rgba(255,255,255,0.7); max-width: 600px; margin: 0 auto 1.5rem; }
        .footer-heading { text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; color: #fff; }
        .footer-heading::after { content: ''; position: absolute; bottom: 0; left: 0; width: 30px; height: 2px; background: var(--theme-primary, #1e40af); }
        .footer-link { color: rgba(255,255,255,0.65); text-decoration: none; transition: color 0.2s; font-size: 0.9rem; }
        .footer-link:hover { color: #fff; }
        .footer-social a { width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.3); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; font-size: 0.9rem; }
        .footer-social a:hover { background: var(--theme-primary, #1e40af); border-color: var(--theme-primary, #1e40af); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); }

        /* Multi-step wizard */
        .wizard-progress { display: flex; justify-content: center; gap: 0; margin-bottom: 2rem; position: relative; }
        .wizard-step-indicator { display: flex; flex-direction: column; align-items: center; position: relative; flex: 1; }
        .wizard-step-indicator::before { content: ''; position: absolute; top: 18px; left: -50%; right: 50%; height: 3px; background: #e2e8f0; z-index: 0; }
        .wizard-step-indicator:first-child::before { display: none; }
        .wizard-step-indicator.completed::before { background: #22c55e; }
        .wizard-step-indicator.active::before { background: #3b82f6; }
        .step-circle { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; background: #e2e8f0; color: #64748b; position: relative; z-index: 1; transition: all 0.3s; }
        .wizard-step-indicator.active .step-circle { background: #3b82f6; color: #fff; box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
        .wizard-step-indicator.completed .step-circle { background: #22c55e; color: #fff; }
        .step-label { font-size: 0.7rem; margin-top: 6px; color: #94a3b8; font-weight: 500; text-align: center; }
        .wizard-step-indicator.active .step-label { color: #3b82f6; font-weight: 600; }
        .wizard-step-indicator.completed .step-label { color: #22c55e; }
        .wizard-panel { display: none; animation: fadeIn 0.3s ease; }
        .wizard-panel.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .seat-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .seat-green { background: #dcfce7; color: #16a34a; }
        .seat-yellow { background: #fef9c3; color: #ca8a04; }
        .seat-red { background: #fee2e2; color: #dc2626; }
        .doc-preview { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; }
        .doc-preview-box { display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px; }
        .review-section { background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
        .review-section h6 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 0.75rem; }
        .review-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 0.85rem; }
        .review-row .label { color: #64748b; }
        .review-row .value { font-weight: 600; color: #1e293b; }
        .success-card { text-align: center; padding: 3rem 2rem; }
        .success-icon { width: 80px; height: 80px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
        .success-icon i { font-size: 2.5rem; color: #22c55e; }
        .app-id-box { background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 2px dashed #3b82f6; border-radius: 12px; padding: 1rem; margin: 1.5rem auto; max-width: 320px; }
        .app-id-box .app-id { font-size: 1.5rem; font-weight: 800; color: #1e40af; letter-spacing: 1px; }
        @media (max-width: 767.98px) {
            .top-bar .d-flex { flex-direction: column; gap: 0.3rem; text-align: center; }
            .step-label { font-size: 0.6rem; }
            .step-circle { width: 30px; height: 30px; font-size: 0.75rem; }
        }
        @media (max-width: 575.98px) {
            .navbar-brand { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .navbar-collapse .d-flex { flex-direction: column; width: 100%; gap: 0.5rem; margin-top: 0.75rem; }
            .notif-bell-btn, .login-nav-btn { width: 100%; text-align: center; display: block; }
            .hero-banner { padding: 2rem 0; }
            .hero-banner h1 { font-size: 1.5rem; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 1.5rem; bottom: 16px; right: 16px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'apply'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<div class="hero-banner">
    <div class="container">
        <h1 class="fw-bold mb-2"><i class="bi bi-file-earmark-plus-fill me-2"></i><?= e(getSetting('admission_hero_title', 'Apply for Admission')) ?></h1>
        <p class="mb-0 opacity-75"><?= e(getSetting('admission_hero_subtitle', 'Submit your application to ' . $schoolName)) ?></p>
    </div>
</div>

<div class="container py-4">
    <?php if ($success): ?>
        <div class="card border-0 shadow-sm" style="max-width:650px;margin:2rem auto;">
            <div class="card-body success-card">
                <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
                <h3 class="fw-bold text-success">Application Submitted Successfully!</h3>
                <p class="text-muted mb-3">Your admission application has been received. Our team will review it and contact you soon.</p>
                <div class="app-id-box">
                    <div style="font-size:0.75rem;color:#64748b;margin-bottom:4px;">Your Application ID</div>
                    <div class="app-id"><?= e($applicationId) ?></div>
                    <div style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">Please save this for future reference</div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i>Print Receipt</button>
                    <a href="/public/admission-form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Submit Another</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" style="max-width:800px;margin:0 auto 1rem;">
                <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm" style="max-width:800px;margin:0 auto;">
            <div class="card-body p-4">
                <!-- Progress Bar -->
                <div class="wizard-progress">
                    <div class="wizard-step-indicator active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Student Info</div>
                    </div>
                    <div class="wizard-step-indicator" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Parent Info</div>
                    </div>
                    <div class="wizard-step-indicator" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Documents</div>
                    </div>
                    <div class="wizard-step-indicator" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="admissionForm">
                    <!-- Step 1: Student Information -->
                    <div class="wizard-panel active" id="step1">
                        <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-person me-2"></i>Student Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Student Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="student_name" class="form-control" required maxlength="100" value="<?= e($_POST['student_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="dob" class="form-control" required value="<?= e($_POST['dob'] ?? '') ?>" id="dobField">
                                <div class="form-text text-warning" id="ageWarning" style="display:none;font-size:0.72rem;"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Class Applied For <span class="text-danger">*</span></label>
                                <select name="class_applied" class="form-select" required id="classField">
                                    <option value="">Select Class</option>
                                    <?php foreach ($seatData as $cls => $seatInfo): ?>
                                        <option value="<?= e($cls) ?>" <?= ($_POST['class_applied'] ?? '') == $cls ? 'selected' : '' ?>
                                            data-seats="<?= $seatInfo['available'] ?>">
                                            <?= is_numeric($cls) ? 'Class '.$cls : e($cls) ?> (<?= $seatInfo['available'] ?> seats)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="seatBadge" class="mt-1"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach (['General','OBC','SC','ST','EWS'] as $cat): ?>
                                        <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Aadhar No.</label>
                                <input type="text" name="aadhar_no" class="form-control" maxlength="12" value="<?= e($_POST['aadhar_no'] ?? '') ?>" placeholder="12 digits (optional)">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Previous School</label>
                                <input type="text" name="previous_school" class="form-control" maxlength="200" value="<?= e($_POST['previous_school'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-primary" onclick="wizardNext(1)">Next <i class="bi bi-arrow-right ms-1"></i></button>
                        </div>
                    </div>

                    <!-- Step 2: Parent Information -->
                    <div class="wizard-panel" id="step2">
                        <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-people me-2"></i>Parent / Guardian Details</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Father's Name <span class="text-danger">*</span></label>
                                <input type="text" name="father_name" class="form-control" required maxlength="100" value="<?= e($_POST['father_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Father's Phone</label>
                                <input type="tel" name="father_phone" class="form-control" maxlength="10" value="<?= e($_POST['father_phone'] ?? '') ?>" placeholder="10 digits (optional)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Father's Occupation</label>
                                <input type="text" name="father_occupation" class="form-control" maxlength="100" value="<?= e($_POST['father_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control" maxlength="100" value="<?= e($_POST['mother_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Mother's Occupation</label>
                                <input type="text" name="mother_occupation" class="form-control" maxlength="100" value="<?= e($_POST['mother_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Contact Phone <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" required maxlength="10" pattern="[0-9]{10}" value="<?= e($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" maxlength="100" value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea name="address" class="form-control" rows="1" maxlength="500"><?= e($_POST['address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Village/Town</label>
                                <input type="text" name="village" class="form-control" maxlength="100" value="<?= e($_POST['village'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">District</label>
                                <input type="text" name="district" class="form-control" maxlength="100" value="<?= e($_POST['district'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">State</label>
                                <input type="text" name="state" class="form-control" maxlength="100" value="<?= e($_POST['state'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">PIN Code</label>
                                <input type="text" name="pincode" class="form-control" maxlength="6" value="<?= e($_POST['pincode'] ?? '') ?>" placeholder="6 digits (optional)">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="wizardPrev(2)"><i class="bi bi-arrow-left me-1"></i> Back</button>
                            <button type="button" class="btn btn-primary" onclick="wizardNext(2)">Next <i class="bi bi-arrow-right ms-1"></i></button>
                        </div>
                    </div>

                    <!-- Step 3: Documents -->
                    <div class="wizard-panel" id="step3">
                        <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-file-earmark-arrow-up me-2"></i>Document Upload</h5>
                        <p class="text-muted mb-3" style="font-size:0.85rem;">Upload supporting documents (PDF/JPG/PNG, max 5MB each). All documents are optional.</p>
                        <div class="row g-3">
                            <?php
                            $docFields = [
                                ['Birth Certificate', 'birth_certificate'],
                                ['Transfer Certificate', 'transfer_certificate'],
                                ['Previous Report Card', 'report_card'],
                                ['Student Photo', 'photo'],
                                ['Aadhar Card', 'aadhar_card'],
                            ];
                            foreach ($docFields as $idx => $df): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?= $df[0] ?></label>
                                <input type="file" name="documents[]" class="form-control doc-input" accept=".pdf,.jpg,.jpeg,.png,.webp" data-label="<?= $df[1] ?>" data-idx="<?= $idx ?>">
                                <div class="doc-preview-container" id="preview_<?= $idx ?>"></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="wizardPrev(3)"><i class="bi bi-arrow-left me-1"></i> Back</button>
                            <button type="button" class="btn btn-primary" onclick="wizardNext(3)">Review <i class="bi bi-arrow-right ms-1"></i></button>
                        </div>
                    </div>

                    <!-- Step 4: Review & Submit -->
                    <div class="wizard-panel" id="step4">
                        <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-clipboard-check me-2"></i>Review & Submit</h5>
                        <div id="reviewContent"></div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="wizardPrev(4)"><i class="bi bi-arrow-left me-1"></i> Back</button>
                            <button type="submit" class="btn btn-success btn-lg px-4" id="finalSubmitBtn"><i class="bi bi-send me-2"></i>Submit Application</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>

<script>
let currentStep = 1;
const totalSteps = 4;

// Fix: Intercept submit, do full JS validation, then submit programmatically
document.getElementById('admissionForm')?.addEventListener('submit', function(e) {
    e.preventDefault(); // Always prevent default to avoid hidden-field browser validation issues
    
    // Validate all steps
    let firstInvalidStep = 0;
    for (let s = 1; s <= 3; s++) {
        if (!validateStep(s)) { firstInvalidStep = s; break; }
    }
    
    if (firstInvalidStep) {
        currentStep = firstInvalidStep;
        updateProgress();
        window.scrollTo({top: 0, behavior: 'smooth'});
        
        // Focus first invalid field
        const panel = document.getElementById('step' + firstInvalidStep);
        const inv = panel.querySelector('.is-invalid');
        if (inv) setTimeout(() => inv.focus(), 350);
        return;
    }
    
    // All valid — disable button and submit via JS
    const btn = document.getElementById('finalSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    
    // Remove all pattern attributes to prevent browser blocking on hidden fields
    this.querySelectorAll('[pattern]').forEach(el => el.removeAttribute('pattern'));
    
    // Show all panels so browser doesn't block on hidden required fields
    this.querySelectorAll('.wizard-panel').forEach(p => p.style.display = 'block');
    
    // Submit the form natively
    this.submit();
});

function updateProgress() {
    document.querySelectorAll('.wizard-step-indicator').forEach(ind => {
        const s = parseInt(ind.dataset.step);
        ind.classList.remove('active','completed');
        if (s === currentStep) ind.classList.add('active');
        else if (s < currentStep) ind.classList.add('completed');
    });
    document.querySelectorAll('.wizard-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('step'+currentStep).classList.add('active');
}

function validateStep(step) {
    const panel = document.getElementById('step'+step);
    const required = panel.querySelectorAll('[required]');
    let valid = true;
    let errorMsg = '';
    required.forEach(el => {
        if (!el.value.trim()) { el.classList.add('is-invalid'); valid = false; }
        else { el.classList.remove('is-invalid'); }
    });
    // Phone validation (required contact phone)
    if (step === 2) {
        const phone = panel.querySelector('[name="phone"]');
        if (phone && phone.value && !/^[0-9]{10}$/.test(phone.value)) {
            phone.classList.add('is-invalid'); valid = false;
            errorMsg = 'Contact phone must be exactly 10 digits.';
        }
        // Father phone: optional but validate format if filled
        const fatherPhone = panel.querySelector('[name="father_phone"]');
        if (fatherPhone && fatherPhone.value.trim() && !/^[0-9]{10}$/.test(fatherPhone.value.trim())) {
            fatherPhone.classList.add('is-invalid'); valid = false;
            errorMsg = "Father's phone must be exactly 10 digits or left blank.";
        } else if (fatherPhone) { fatherPhone.classList.remove('is-invalid'); }
        // Pincode: optional but validate if filled
        const pincode = panel.querySelector('[name="pincode"]');
        if (pincode && pincode.value.trim() && !/^[0-9]{6}$/.test(pincode.value.trim())) {
            pincode.classList.add('is-invalid'); valid = false;
            errorMsg = 'PIN code must be exactly 6 digits or left blank.';
        } else if (pincode) { pincode.classList.remove('is-invalid'); }
    }
    // Aadhar: optional but validate if filled
    if (step === 1) {
        const aadhar = panel.querySelector('[name="aadhar_no"]');
        if (aadhar && aadhar.value.trim() && !/^[0-9]{12}$/.test(aadhar.value.trim())) {
            aadhar.classList.add('is-invalid'); valid = false;
            errorMsg = 'Aadhar number must be exactly 12 digits or left blank.';
        } else if (aadhar) { aadhar.classList.remove('is-invalid'); }
    }
    if (!valid && errorMsg) {
        // Show a toast/alert for clarity
        let alertEl = panel.querySelector('.step-validation-alert');
        if (!alertEl) {
            alertEl = document.createElement('div');
            alertEl.className = 'alert alert-danger alert-dismissible fade show mt-2 step-validation-alert';
            alertEl.style.fontSize = '0.85rem';
            panel.prepend(alertEl);
        }
        alertEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>' + errorMsg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    } else {
        const old = panel.querySelector('.step-validation-alert');
        if (old) old.remove();
    }
    return valid;
}

function wizardNext(step) {
    if (!validateStep(step)) return;
    if (step === 3) buildReview();
    currentStep = Math.min(step + 1, totalSteps);
    updateProgress();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function wizardPrev(step) {
    currentStep = Math.max(step - 1, 1);
    updateProgress();
}

function buildReview() {
    const form = document.getElementById('admissionForm');
    const fd = new FormData(form);
    const get = k => fd.get(k) || '—';
    let html = '<div class="review-section"><h6><i class="bi bi-person me-1"></i>Student Information <button type="button" class="btn btn-link btn-sm p-0 float-end" onclick="wizardPrev(2);wizardPrev(1);">Edit</button></h6>';
    html += rv('Name', get('student_name')) + rv('DOB', get('dob')) + rv('Gender', get('gender')) + rv('Class', 'Class '+get('class_applied'));
    html += rv('Blood Group', get('blood_group')) + rv('Category', get('category')) + rv('Aadhar', get('aadhar_no')) + rv('Previous School', get('previous_school'));
    html += '</div>';
    html += '<div class="review-section"><h6><i class="bi bi-people me-1"></i>Parent Details <button type="button" class="btn btn-link btn-sm p-0 float-end" onclick="wizardPrev(3);wizardPrev(2);">Edit</button></h6>';
    html += rv("Father", get('father_name')) + rv("Father Phone", get('father_phone')) + rv("Father Occupation", get('father_occupation'));
    html += rv("Mother", get('mother_name')) + rv("Mother Occupation", get('mother_occupation'));
    html += rv("Phone", get('phone')) + rv("Email", get('email'));
    html += rv("Address", get('address')) + rv("Village", get('village')) + rv("District", get('district')) + rv("State", get('state')) + rv("PIN", get('pincode'));
    html += '</div>';
    // Document count
    const docs = form.querySelectorAll('.doc-input');
    let docCount = 0;
    docs.forEach(d => { if (d.files.length) docCount++; });
    html += '<div class="review-section"><h6><i class="bi bi-file-earmark me-1"></i>Documents <button type="button" class="btn btn-link btn-sm p-0 float-end" onclick="wizardPrev(4);">Edit</button></h6>';
    html += rv('Files Uploaded', docCount + ' document(s)');
    html += '</div>';
    document.getElementById('reviewContent').innerHTML = html;
}

function rv(label, value) {
    return '<div class="review-row"><span class="label">'+label+'</span><span class="value">'+value+'</span></div>';
}

// Seat badge
document.getElementById('classField')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const seats = opt.dataset.seats;
    const badge = document.getElementById('seatBadge');
    if (!seats || seats === '') { badge.innerHTML = ''; return; }
    const s = parseInt(seats);
    if (s <= 0) badge.innerHTML = '<span class="seat-badge seat-red"><i class="bi bi-x-circle me-1"></i>Full — Waitlist</span>';
    else if (s <= 9) badge.innerHTML = '<span class="seat-badge seat-yellow"><i class="bi bi-exclamation-triangle me-1"></i>'+s+' seats left</span>';
    else badge.innerHTML = '<span class="seat-badge seat-green"><i class="bi bi-check-circle me-1"></i>'+s+' seats available</span>';
});

// DOB age warning
document.getElementById('dobField')?.addEventListener('change', function() {
    const dob = new Date(this.value);
    const age = Math.floor((Date.now() - dob) / (365.25*24*60*60*1000));
    const cls = document.getElementById('classField')?.value;
    const warn = document.getElementById('ageWarning');
    if (!cls) { warn.style.display='none'; return; }
    const expectedAge = parseInt(cls) + 5;
    if (Math.abs(age - expectedAge) > 2) {
        warn.textContent = 'Age '+age+' may not match Class '+cls+' (expected ~'+expectedAge+' yrs)';
        warn.style.display = 'block';
    } else { warn.style.display = 'none'; }
});

// Doc preview
document.querySelectorAll('.doc-input').forEach(input => {
    input.addEventListener('change', function() {
        const idx = this.dataset.idx;
        const container = document.getElementById('preview_'+idx);
        container.innerHTML = '';
        if (!this.files.length) return;
        const file = this.files[0];
        if (file.size > 5*1024*1024) { container.innerHTML = '<small class="text-danger">File too large (max 5MB)</small>'; this.value=''; return; }
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.className = 'doc-preview mt-1';
            img.src = URL.createObjectURL(file);
            container.appendChild(img);
        } else {
            container.innerHTML = '<div class="doc-preview-box mt-1"><i class="bi bi-file-pdf text-danger" style="font-size:1.5rem"></i><small>'+file.name+'</small></div>';
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>