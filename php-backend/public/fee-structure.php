<?php
require_once __DIR__ . '/../includes/auth.php';
checkMaintenance();
$db = getDB();

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

// Filters
$filterClass = trim($_GET['class'] ?? '');
$filterYear = trim($_GET['year'] ?? '');

// Get available classes and years for dropdowns
$availableClasses = $db->query("SELECT DISTINCT class FROM fee_structures WHERE is_visible=1 ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
$availableYears = $db->query("SELECT DISTINCT academic_year FROM fee_structures WHERE is_visible=1 ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Load fee structure
$feeStructure = null;
$components = [];
$totalAmount = 0;

if ($filterClass && $filterYear) {
    $stmt = $db->prepare("SELECT * FROM fee_structures WHERE class=? AND academic_year=? AND is_visible=1");
    $stmt->execute([$filterClass, $filterYear]);
    $feeStructure = $stmt->fetch();
    if ($feeStructure) {
        $cstmt = $db->prepare("SELECT * FROM fee_components WHERE fee_structure_id=? ORDER BY display_order ASC");
        $cstmt->execute([$feeStructure['id']]);
        $components = $cstmt->fetchAll();
        foreach ($components as $c) { $totalAmount += $c['amount']; }
    }
}

// Notification bell data
$bellNotifs = $db->query("SELECT * FROM notifications WHERE status='published' AND is_public=1 AND is_deleted=0 ORDER BY created_at DESC LIMIT 5")->fetchAll();
$notifCount = count($bellNotifs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fee Structure — <?= e($schoolName) ?></title>
    <meta name="description" content="View the fee structure for different classes at <?= e($schoolName) ?>. Select class and academic year to see detailed fee breakdown.">
    <?php $favicon = getSetting('school_favicon', ''); $favVer = getSetting('favicon_updated_at', '0'); if ($favicon): $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-primary: <?= e($primaryColor) ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }

        .fee-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 50%, #3b82f6 100%);
            padding: 4rem 0 3rem; color: #fff; text-align: center;
        }
        .fee-hero h1 { font-family: 'Playfair Display', serif; font-size: 2.4rem; font-weight: 700; }
        .fee-hero p { opacity: .7; max-width: 600px; margin: .5rem auto 0; }

        .fee-filter-card {
            background: #fff; border-radius: 16px; padding: 1.5rem; margin-top: -2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.08); position: relative; z-index: 10;
        }

        .fee-table { border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .fee-table thead { background: linear-gradient(135deg, #0f172a, #1e40af); color: #fff; }
        .fee-table thead th { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; padding: .75rem 1rem; border: none; }
        .fee-table tbody td { padding: .75rem 1rem; font-size: .88rem; vertical-align: middle; }
        .fee-table tbody tr:hover { background: #f1f5f9; }
        .fee-table .total-row { background: #f0f9ff; font-weight: 700; border-top: 2px solid #1e40af; }
        .fee-table .total-row td { font-size: .95rem; }

        .freq-badge { font-size: .7rem; padding: .2rem .55rem; border-radius: 50px; font-weight: 600; }
        .opt-badge { font-size: .65rem; padding: .15rem .45rem; border-radius: 50px; }

        .site-footer { background: #1a1a2e; color: #fff; }
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
        .whatsapp-float {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            width: 60px; height: 60px; border-radius: 50%; background: #25D366;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.8rem; text-decoration: none;
            box-shadow: 0 4px 20px rgba(37,211,102,0.4); transition: transform 0.3s;
        }
        .whatsapp-float:hover { transform: scale(1.1); color: #fff; }

        @media print {
            .fee-hero, .premium-navbar, .pn-top-bar, .site-footer, .whatsapp-float,
            .fee-filter-card, .no-print, .pn-mobile-bottom-cta { display: none !important; }
            body { background: #fff !important; }
            .fee-table { box-shadow: none; }
            .print-header { display: block !important; text-align: center; margin-bottom: 1rem; }
        }
        .print-header { display: none; }

        @media (max-width: 575.98px) {
            .fee-hero h1 { font-size: 1.6rem; }
            .fee-hero { padding: 3rem 0 2rem; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'fee-structure'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero -->
<section class="fee-hero">
    <div class="container">
        <div class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">
            <i class="bi bi-cash-stack me-1"></i>Fee Information
        </div>
        <h1>Fee Structure</h1>
        <p>View the detailed fee breakdown for each class and academic year. Select your class below to get started.</p>
    </div>
</section>

<!-- Filter Card -->
<section class="py-4">
    <div class="container">
        <div class="fee-filter-card no-print">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-sm-5">
                    <label class="form-label fw-semibold"><i class="bi bi-mortarboard me-1"></i>Select Class</label>
                    <select name="class" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Class --</option>
                        <?php foreach ($availableClasses as $ac): ?>
                        <option value="<?= e($ac) ?>" <?= $filterClass === $ac ? 'selected' : '' ?>><?= e($ac) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-5">
                    <label class="form-label fw-semibold"><i class="bi bi-calendar3 me-1"></i>Academic Year</label>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Year --</option>
                        <?php foreach ($availableYears as $ay): ?>
                        <option value="<?= e($ay) ?>" <?= $filterYear === $ay ? 'selected' : '' ?>><?= e($ay) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>View</button>
                </div>
            </form>
        </div>

        <?php if ($filterClass && $filterYear): ?>
            <!-- Print Header -->
            <div class="print-header">
                <?php if ($navLogo): ?><img src="<?= e($logoPath) ?>" alt="Logo" style="height:60px;margin-bottom:.5rem;"><br><?php endif; ?>
                <h2 style="margin:0;"><?= e($schoolName) ?></h2>
                <p style="margin:0;color:#666;"><?= e($schoolTagline) ?></p>
                <h4 style="margin:.75rem 0 .25rem;">Fee Structure — <?= e($filterClass) ?> (<?= e($filterYear) ?>)</h4>
            </div>

            <?php if ($feeStructure && !empty($components)): ?>
            <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                    <h5 class="fw-bold mb-0"><?= e($filterClass) ?> — <?= e($filterYear) ?></h5>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
                </div>
                <?php if ($feeStructure['notes']): ?>
                <div class="alert alert-info py-2 mb-3" style="font-size:.85rem;"><i class="bi bi-info-circle me-1"></i><?= e($feeStructure['notes']) ?></div>
                <?php endif; ?>

                <table class="table fee-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:10%">#</th>
                            <th>Fee Component</th>
                            <th style="width:20%">Frequency</th>
                            <th style="width:20%" class="text-end">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($components as $i => $comp):
                        $freqColors = ['one-time'=>'info','monthly'=>'primary','quarterly'=>'warning','yearly'=>'success'];
                        $freqColor = $freqColors[$comp['frequency']] ?? 'secondary';
                    ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <?= e($comp['component_name']) ?>
                                <?php if ($comp['is_optional']): ?>
                                <span class="opt-badge bg-warning-subtle text-warning ms-1">Optional</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="freq-badge bg-<?= $freqColor ?>-subtle text-<?= $freqColor ?>"><?= ucfirst(str_replace('-', ' ', $comp['frequency'])) ?></span></td>
                            <td class="text-end fw-semibold">₹<?= number_format($comp['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3"><i class="bi bi-calculator me-1"></i>Total</td>
                            <td class="text-end">₹<?= number_format($totalAmount, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php elseif (!$feeStructure): ?>
            <div class="text-center py-5 mt-3">
                <i class="bi bi-exclamation-circle text-muted" style="font-size:3rem;opacity:.3;"></i>
                <p class="text-muted mt-2">No fee structure found for <strong><?= e($filterClass) ?></strong> in <strong><?= e($filterYear) ?></strong>.</p>
            </div>
            <?php endif; ?>
        <?php elseif (empty($availableClasses)): ?>
        <div class="text-center py-5 mt-3">
            <i class="bi bi-cash-stack text-muted" style="font-size:3rem;opacity:.3;"></i>
            <p class="text-muted mt-2">Fee structure information is not yet available.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
