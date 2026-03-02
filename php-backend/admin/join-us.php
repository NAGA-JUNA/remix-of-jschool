<?php
/**
 * Public: Join Us / Careers Page
 * No authentication required — accessible to everyone
 */
// Session is started by auth.php below — no need to start it here
require_once __DIR__ . '/../includes/auth.php';
$db = getDB();

// Fallback: define getSetting if not provided by auth.php
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// ── Site settings (matching certificates.php pattern) ──
$schoolName = getSetting('school_name', 'JNV School');
$schoolTagline = getSetting('school_tagline', 'Nurturing Talent, Shaping Future');
$schoolEmail = getSetting('school_email', '');
$schoolPhone = getSetting('school_phone', '');
$schoolAddress = getSetting('school_address', '');
$whatsappNumber = getSetting('whatsapp_api_number', '');
$primaryColor = getSetting('primary_color', '#1e40af');
$navLogo = getSetting('school_logo', '');
$logoVersion = getSetting('logo_updated_at', '0');
$logoPath = '';
if ($navLogo) { $logoPath = (strpos($navLogo, '/uploads/') === 0) ? $navLogo : (file_exists(__DIR__.'/../uploads/branding/'.$navLogo) ? '/uploads/branding/'.$navLogo : '/uploads/logo/'.$navLogo); $logoPath .= '?v=' . $logoVersion; }

$socialFacebook = getSetting('social_facebook', '');
$socialTwitter = getSetting('social_twitter', '');
$socialInstagram = getSetting('social_instagram', '');
$socialYoutube = getSetting('social_youtube', '');
$socialLinkedin = getSetting('social_linkedin', '');

$recruitmentEnabled = getSetting('recruitment_enabled', '0') === '1';

// Fetch active job openings
$openings = [];
if ($recruitmentEnabled) {
    try {
        $stmt = $db->query("SELECT * FROM job_openings WHERE is_active=1 ORDER BY sort_order ASC, created_at DESC");
        $openings = $stmt->fetchAll();
    } catch (Exception $e) { $openings = []; }
}

// Handle form submission
$formSuccess = false;
$formError = '';
$submittedAppId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $recruitmentEnabled) {
    // CSRF check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$csrf || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
        $formError = 'Invalid form submission. Please refresh and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = $_POST['dob'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 0);
        $currentSchool = trim($_POST['current_school'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $jobId = (int)($_POST['job_opening_id'] ?? 0);
        $coverLetter = trim($_POST['cover_letter'] ?? '');

        // Validation
        if (!$fullName || !$phone) {
            $formError = 'Name and Phone are required.';
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $formError = 'Invalid email address.';
        } elseif (strlen($fullName) > 150 || strlen($email) > 255 || strlen($phone) > 20) {
            $formError = 'Input exceeds maximum length.';
        } else {
            // Resume upload
            $resumePath = null;
            if (!empty($_FILES['resume']['name'])) {
                $file = $_FILES['resume'];
                $maxSize = 5 * 1024 * 1024;
                $allowedExt = ['pdf', 'doc', 'docx'];
                $allowedMime = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $mime = $file['type'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $formError = 'File upload error. Please try again.';
                } elseif ($file['size'] > $maxSize) {
                    $formError = 'Resume must be under 5MB.';
                } elseif (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
                    $formError = 'Resume must be PDF, DOC, or DOCX format.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/resumes/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $uniqueName = 'resume_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destPath = $uploadDir . $uniqueName;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $resumePath = 'uploads/resumes/' . $uniqueName;
                    } else {
                        $formError = 'Failed to save resume. Please try again.';
                    }
                }
            }

            if (!$formError) {
                try {
                    $year = date('Y');
                    $lastId = $db->query("SELECT MAX(id) FROM teacher_applications")->fetchColumn();
                    $nextNum = ($lastId ?: 0) + 1;
                    $applicationId = 'APP-' . $year . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

                    $db->prepare("INSERT INTO teacher_applications (application_id, job_opening_id, full_name, email, phone, dob, gender, qualification, experience_years, current_school, address, resume_path, cover_letter, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'new')")
                        ->execute([$applicationId, $jobId ?: null, $fullName, $email ?: null, $phone, $dob ?: null, $gender, $qualification, $experience, $currentSchool, $address, $resumePath, $coverLetter]);

                    $submittedAppId = $applicationId;

                    $posTitle = 'Teaching Position';
                    if ($jobId) {
                        $jt = $db->prepare("SELECT title FROM job_openings WHERE id=?");
                        $jt->execute([$jobId]);
                        $posTitle = $jt->fetchColumn() ?: $posTitle;
                    }

                    if ($email) {
                        try {
                            require_once __DIR__ . '/../config/mail.php';
                            $emailTpl = getSetting('email_recruitment_template', '');
                            if ($emailTpl) {
                                $emailBody = str_replace(['{name}', '{app_id}', '{position}', '{school_name}'], [$fullName, $applicationId, $posTitle, $schoolName], $emailTpl);
                            } else {
                                $emailBody = "<div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:2rem;'><h2>Application Received</h2><p>Dear $fullName,</p><p>Your application <strong>$applicationId</strong> for <strong>$posTitle</strong> has been received. We will review and contact you shortly.</p><hr><p style='color:#64748b;font-size:0.8rem;'>$schoolName</p></div>";
                            }
                            sendMail($email, "Application Received — $schoolName", $emailBody);
                        } catch (Exception $e) { /* silent */ }
                    }

                    $formSuccess = true;
                } catch (Exception $e) {
                    $formError = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// WhatsApp confirmation link
$waConfirmLink = '';
if ($formSuccess && !empty($phone)) {
    $waTpl = getSetting('whatsapp_recruitment_template', 'Hello {name}, regarding your application ({app_id}) for {position}...');
    $posTitle = $posTitle ?? 'Teaching Position';
    $waMsg = str_replace(['{name}', '{app_id}', '{position}'], [$fullName ?? '', $submittedAppId, $posTitle], $waTpl);
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone ?? '');
    $waConfirmLink = 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($waMsg);
}

// Notification bell data (for navbar)
$bellNotifs = [];
$notifCount = 0;
try {
    $bellNotifs = $db->query("SELECT * FROM notifications WHERE is_active=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $notifCount = count($bellNotifs);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join Our Team — <?= e($schoolName) ?></title>
    <meta name="description" content="Career opportunities at <?= e($schoolName) ?>. Apply for teaching and staff positions.">
    <?php $favicon = getSetting('school_favicon', ''); $favVer = getSetting('favicon_updated_at', '0'); if ($favicon): $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-primary: <?= e($primaryColor) ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }

        /* Hero — matches certificates page */
        .careers-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 50%, #3b82f6 100%);
            padding: 4rem 0 3rem; color: #fff; text-align: center;
        }
        .careers-hero h1 { font-family: 'Playfair Display', serif; font-size: 2.4rem; font-weight: 700; }
        .careers-hero p { opacity: .7; max-width: 600px; margin: .5rem auto 0; }

        /* Job cards — matches cert-card style */
        .job-card {
            border: none; border-radius: 16px; overflow: hidden;
            transition: transform .3s, box-shadow .3s;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            background: #fff; height: 100%;
        }
        .job-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,.12); }
        .job-card .card-body { padding: 1.5rem; }
        .job-badge {
            font-size: .65rem; font-weight: 600; padding: .2rem .6rem; border-radius: 50px;
        }
        .job-badge-ft { background: #dcfce7; color: #166534; }
        .job-badge-pt { background: #fef3c7; color: #92400e; }
        .job-badge-ct { background: #dbeafe; color: #1e40af; }

        /* Apply section */
        .apply-section {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            padding: 2.5rem; margin-top: 2rem;
        }

        /* Success card */
        .success-card {
            background: #f0fdf4; border: 2px solid #86efac;
            border-radius: 16px; padding: 2.5rem; text-align: center;
        }

        /* Not hiring */
        .not-hiring { text-align: center; padding: 5rem 2rem; }
        .not-hiring i { font-size: 4rem; color: #94a3b8; margin-bottom: 1.5rem; }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 12px;
            padding: 1.5rem; text-align: center; cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--theme-primary); background: #eff6ff; }
        .upload-zone i { font-size: 2rem; color: #94a3b8; }

        /* Footer */
        .site-footer { background: #1a1a2e; color: #fff; }
        /* Footer CTA */
        .footer-cta { background: #0f2557; padding: 4rem 0; text-align: center; }
        .footer-cta h2 { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 2.2rem; color: #fff; margin-bottom: 1rem; }
        .footer-cta p { color: rgba(255,255,255,0.7); max-width: 600px; margin: 0 auto 1.5rem; }
        .footer-heading { text-transform: uppercase; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; color: #fff; }
        .footer-heading::after { content: ''; position: absolute; bottom: 0; left: 0; width: 30px; height: 2px; background: var(--theme-primary); }
        .footer-link { color: rgba(255,255,255,0.65); text-decoration: none; transition: color 0.2s; font-size: 0.9rem; }
        .footer-link:hover { color: #fff; }
        .footer-social a { width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.3); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; font-size: 0.9rem; }
        .footer-social a:hover { background: var(--theme-primary); border-color: var(--theme-primary); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); }

        @media (max-width: 575.98px) {
            .careers-hero h1 { font-size: 1.6rem; }
            .careers-hero { padding: 3rem 0 2rem; }
            .apply-section { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'join-us'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero -->
<section class="careers-hero">
    <div class="container">
        <div class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">
            <i class="bi bi-briefcase me-1"></i>Careers
        </div>
        <h1>Join Our Team</h1>
        <p>We're looking for passionate educators and staff to join our team and make a difference in students' lives.</p>
    </div>
</section>

<section class="py-4">
<div class="container">

<?php if (!$recruitmentEnabled): ?>
    <!-- Not Hiring -->
    <div class="not-hiring">
        <i class="bi bi-briefcase d-block"></i>
        <h2 class="fw-bold mb-3">We Are Not Hiring Currently</h2>
        <p class="text-muted" style="max-width:500px;margin:0 auto;">Thank you for your interest in joining <?= e($schoolName) ?>. There are no open positions at this time. Please check back later.</p>
        <a href="/" class="btn btn-outline-primary mt-3"><i class="bi bi-house me-1"></i>Back to Home</a>
    </div>

<?php elseif ($formSuccess): ?>
    <!-- Success -->
    <div class="success-card my-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <h2 class="fw-bold mt-3 mb-2">Application Submitted!</h2>
        <p class="mb-1">Your Application ID: <strong class="fs-5"><?= e($submittedAppId) ?></strong></p>
        <p class="text-muted mb-3">We have received your application and will review it shortly.</p>
        <?php if ($waConfirmLink): ?>
        <a href="<?= e($waConfirmLink) ?>" target="_blank" class="btn btn-success btn-lg me-2"><i class="bi bi-whatsapp me-2"></i>Confirm on WhatsApp</a>
        <?php endif; ?>
        <a href="join-us.php" class="btn btn-outline-primary btn-lg"><i class="bi bi-arrow-repeat me-1"></i>Apply Again</a>
    </div>

<?php else: ?>
    <!-- Job Openings -->
    <?php if (!empty($openings)): ?>
    <h2 class="fw-bold mb-1 mt-3">Open Positions</h2>
    <p class="text-muted mb-4">Explore our current openings and find the right fit for you.</p>
    <div class="row g-4 mb-4">
        <?php foreach ($openings as $job):
            $typeBadge = ['full-time'=>'job-badge-ft','part-time'=>'job-badge-pt','contract'=>'job-badge-ct'][$job['employment_type']] ?? 'job-badge-ft';
        ?>
        <div class="col-sm-6 col-md-4 col-lg-4">
            <div class="card job-card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <h5 class="fw-bold mb-0" style="font-size:.95rem;"><?= e($job['title']) ?></h5>
                        <span class="job-badge <?= $typeBadge ?>"><?= ucfirst(str_replace('-',' ',$job['employment_type'])) ?></span>
                    </div>
                    <?php if ($job['department']): ?>
                    <p class="text-muted mb-2" style="font-size:.85rem"><i class="bi bi-diagram-3 me-1"></i><?= e($job['department']) ?></p>
                    <?php endif; ?>
                    <?php if ($job['location']): ?>
                    <p class="text-muted mb-2" style="font-size:.85rem"><i class="bi bi-geo-alt me-1"></i><?= e($job['location']) ?></p>
                    <?php endif; ?>
                    <?php if ($job['description']): ?>
                    <p style="font-size:.9rem" class="mb-2"><?= nl2br(e(mb_substr($job['description'], 0, 200))) ?><?= mb_strlen($job['description']) > 200 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <?php if ($job['salary_range']): ?>
                    <p class="mb-0" style="font-size:.85rem"><i class="bi bi-currency-rupee me-1"></i><strong><?= e($job['salary_range']) ?></strong></p>
                    <?php endif; ?>
                    <a href="#apply-section" class="btn btn-primary btn-sm mt-3 rounded-pill"><i class="bi bi-send me-1"></i>Apply Now</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Application Form -->
    <div class="apply-section" id="apply-section">
        <h2 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Apply Now</h2>
        <p class="text-muted mb-4">Fill in your details and upload your resume to apply.</p>

        <?php if ($formError): ?>
        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($formError) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="applicationForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="row g-3">
                <!-- Name -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required maxlength="150" placeholder="Enter your full name">
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="255" placeholder="your@email.com">
                </div>

                <!-- Phone -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control" required maxlength="20" placeholder="+91 9876543210">
                </div>

                <!-- DOB -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date of Birth</label>
                    <input type="date" name="dob" class="form-control">
                </div>

                <!-- Gender -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Qualification -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Qualification</label>
                    <input type="text" name="qualification" class="form-control" maxlength="200" placeholder="e.g. B.Ed, M.A., Ph.D.">
                </div>

                <!-- Experience -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Years of Experience</label>
                    <input type="number" name="experience_years" class="form-control" min="0" max="50" value="0">
                </div>

                <!-- Current School -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Current School/Organization</label>
                    <input type="text" name="current_school" class="form-control" maxlength="200" placeholder="Currently working at...">
                </div>

                <!-- Position -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Position Applying For</label>
                    <select name="job_opening_id" class="form-select">
                        <option value="0">General Application</option>
                        <?php foreach ($openings as $job): ?>
                        <option value="<?= $job['id'] ?>"><?= e($job['title']) ?> — <?= ucfirst(str_replace('-',' ',$job['employment_type'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Address -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Your full address"></textarea>
                </div>

                <!-- Resume Upload -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Resume / CV</label>
                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('resumeInput').click()">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <p class="mb-1 fw-semibold" id="uploadLabel">Click to upload or drag & drop</p>
                        <small class="text-muted">PDF, DOC, DOCX — Max 5MB</small>
                    </div>
                    <input type="file" name="resume" id="resumeInput" class="d-none" accept=".pdf,.doc,.docx">
                </div>

                <!-- Cover Letter -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Cover Letter (Optional)</label>
                    <textarea name="cover_letter" class="form-control" rows="4" maxlength="5000" placeholder="Tell us why you'd be a great fit..."></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill" id="submitBtn">
                        <i class="bi bi-send me-2"></i>Submit Application
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

</div>
</section>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Upload zone interaction
const uploadZone = document.getElementById('uploadZone');
const resumeInput = document.getElementById('resumeInput');
const uploadLabel = document.getElementById('uploadLabel');

if (resumeInput) {
    resumeInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            uploadLabel.textContent = this.files[0].name;
            uploadZone.style.borderColor = '#22c55e';
            uploadZone.style.background = '#f0fdf4';
        }
    });
}

if (uploadZone) {
    ['dragenter','dragover'].forEach(e => uploadZone.addEventListener(e, ev => { ev.preventDefault(); uploadZone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(e => uploadZone.addEventListener(e, ev => { ev.preventDefault(); uploadZone.classList.remove('dragover'); }));
    uploadZone.addEventListener('drop', ev => {
        const files = ev.dataTransfer.files;
        if (files.length > 0) {
            resumeInput.files = files;
            uploadLabel.textContent = files[0].name;
            uploadZone.style.borderColor = '#22c55e';
            uploadZone.style.background = '#f0fdf4';
        }
    });
}

// Prevent double submit
const form = document.getElementById('applicationForm');
if (form) {
    form.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    });
}
</script>
</body>
</html>