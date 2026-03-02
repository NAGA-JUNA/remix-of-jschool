<?php
require_once __DIR__.'/../includes/auth.php';
checkMaintenance();
$db = getDB();

// Check if certificates page is enabled
if (getSetting('certificates_page_enabled', '1') !== '1') {
    header('Location: /'); exit;
}

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

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Filters
$filterCat = trim($_GET['category'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');

$where = "is_active=1 AND is_deleted=0";
$params = [];
if ($filterCat) { $where .= " AND category=?"; $params[] = $filterCat; }
if ($filterSearch) { $where .= " AND title LIKE ?"; $params[] = "%$filterSearch%"; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE $where");
$countStmt->execute($params);
$totalCerts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCerts / $perPage));

$dataStmt = $db->prepare("SELECT * FROM certificates WHERE $where ORDER BY display_order ASC, id DESC LIMIT $perPage OFFSET $offset");
$dataStmt->execute($params);
$certs = $dataStmt->fetchAll();

$categories = [
    'govt_approval' => 'Government Approval',
    'board_affiliation' => 'Board Affiliation',
    'recognition' => 'Recognition',
    'awards' => 'Awards',
];
$catBadgeColors = ['govt_approval'=>'success','board_affiliation'=>'primary','recognition'=>'info','awards'=>'warning'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificates & Accreditations — <?= e($schoolName) ?></title>
    <meta name="description" content="View all certificates, accreditations, and recognitions of <?= e($schoolName) ?>. Government approvals, board affiliations, and awards.">
    <?php $favicon = getSetting('school_favicon', ''); $favVer = getSetting('favicon_updated_at', '0'); if ($favicon): $favPath = (strpos($favicon, '/uploads/') === 0) ? $favicon : (file_exists(__DIR__.'/../uploads/branding/'.$favicon) ? '/uploads/branding/'.$favicon : '/uploads/logo/'.$favicon); ?><link rel="icon" href="<?= e($favPath) ?>?v=<?= e($favVer) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <style>
        :root { --theme-primary: <?= e($primaryColor) ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; }

        /* Hero */
        .cert-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e40af 50%, #3b82f6 100%);
            padding: 4rem 0 3rem; color: #fff; text-align: center;
        }
        .cert-hero h1 { font-family: 'Playfair Display', serif; font-size: 2.4rem; font-weight: 700; }
        .cert-hero p { opacity: .7; max-width: 600px; margin: .5rem auto 0; }

        /* Filter pills */
        .filter-pills { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; }
        .filter-pill {
            padding: .4rem 1rem; border-radius: 50px; border: 1.5px solid #e2e8f0;
            background: #fff; color: #64748b; font-size: .8rem; font-weight: 500;
            text-decoration: none; transition: all .2s;
        }
        .filter-pill:hover, .filter-pill.active { background: var(--theme-primary); color: #fff; border-color: var(--theme-primary); }

        /* Certificate card */
        .cert-card {
            border: none; border-radius: 16px; overflow: hidden;
            transition: transform .3s, box-shadow .3s; cursor: pointer;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .cert-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,.12); }
        .cert-card img { width: 100%; height: 220px; object-fit: cover; }
        .cert-card .pdf-placeholder {
            width: 100%; height: 220px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
        }
        .cert-card .card-body { padding: 1rem; }
        .cert-card .cert-title { font-weight: 600; font-size: .9rem; margin-bottom: .25rem; }
        .cert-badge { font-size: .65rem; font-weight: 600; padding: .2rem .6rem; border-radius: 50px; }

        /* Lightbox */
        .lightbox-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.9); z-index: 10000;
            display: none; align-items: center; justify-content: center; padding: 1rem;
        }
        .lightbox-overlay.show { display: flex; }
        .lightbox-overlay img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 12px; }
        .lightbox-overlay iframe { width: 90vw; height: 85vh; border: none; border-radius: 12px; background: #fff; }
        .lightbox-close {
            position: absolute; top: 1rem; right: 1.5rem; background: rgba(255,255,255,.15);
            border: none; color: #fff; font-size: 1.5rem; width: 44px; height: 44px;
            border-radius: 50%; cursor: pointer; z-index: 10001; backdrop-filter: blur(5px);
        }
        .lightbox-actions { position: absolute; bottom: 1.5rem; display: flex; gap: .75rem; }
        .lightbox-actions a {
            background: rgba(255,255,255,.15); color: #fff; padding: .5rem 1.2rem;
            border-radius: 50px; text-decoration: none; font-size: .85rem; backdrop-filter: blur(5px);
        }
        .lightbox-actions a:hover { background: rgba(255,255,255,.3); color: #fff; }

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

        @media (max-width: 575.98px) {
            .cert-hero h1 { font-size: 1.6rem; }
            .cert-hero { padding: 3rem 0 2rem; }
            .cert-card img, .cert-card .pdf-placeholder { height: 160px; }
        }
    </style>
</head>
<body>

<?php $currentPage = 'certificates'; include __DIR__ . '/../includes/public-navbar.php'; ?>

<!-- Hero -->
<section class="cert-hero">
    <div class="container">
        <div class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">
            <i class="bi bi-award me-1"></i>Our Credentials
        </div>
        <h1>Certificates & Accreditations</h1>
        <p>Explore our school's official certifications, government approvals, board affiliations, and awards that affirm our commitment to quality education.</p>
    </div>
</section>

<!-- Filters + Search -->
<section class="py-4">
    <div class="container">
        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
            <div class="filter-pills">
                <a href="certificates.php" class="filter-pill <?= !$filterCat ? 'active' : '' ?>">All</a>
                <?php foreach ($categories as $k => $v): ?>
                    <a href="certificates.php?category=<?= $k ?>" class="filter-pill <?= $filterCat === $k ? 'active' : '' ?>"><?= $v ?></a>
                <?php endforeach; ?>
            </div>
            <form class="d-flex gap-2" method="GET">
                <?php if ($filterCat): ?><input type="hidden" name="category" value="<?= e($filterCat) ?>"><?php endif; ?>
                <input type="search" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= e($filterSearch) ?>" style="max-width:200px;border-radius:50px;">
                <button class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <?php if (empty($certs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-award text-muted" style="font-size:3rem;opacity:.3"></i>
                <p class="text-muted mt-2">No certificates found.</p>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($certs as $c):
                $thumbSrc = $c['thumb_path'] ? '/' . $c['thumb_path'] : ($c['file_type']==='pdf' ? '' : '/' . $c['file_path']);
                $catLabel = $categories[$c['category']] ?? ucfirst($c['category']);
                $catColor = $catBadgeColors[$c['category']] ?? 'secondary';
            ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card cert-card h-100" onclick="openLightbox('<?= e('/' . $c['file_path']) ?>', '<?= $c['file_type'] ?>', <?= $c['allow_download'] ? 'true' : 'false' ?>)">
                    <?php if ($c['file_type'] === 'pdf'): ?>
                        <div class="pdf-placeholder"><i class="bi bi-file-earmark-pdf text-danger" style="font-size:3rem"></i></div>
                    <?php else: ?>
                        <img src="<?= e($thumbSrc) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="cert-badge bg-<?= $catColor ?>-subtle text-<?= $catColor ?>"><?= e($catLabel) ?></span>
                            <?php if ($c['year']): ?><small class="text-muted"><?= $c['year'] ?></small><?php endif; ?>
                        </div>
                        <div class="cert-title"><?= e($c['title']) ?></div>
                        <?php if ($c['description']): ?><small class="text-muted"><?= e(mb_strimwidth($c['description'], 0, 80, '...')) ?></small><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4 d-flex justify-content-center">
            <ul class="pagination pagination-sm">
                <?php for ($p = 1; $p <= $totalPages; $p++):
                    $qs = http_build_query(array_filter(['category'=>$filterCat, 'search'=>$filterSearch, 'page'=>$p]));
                ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="certificates.php?<?= $qs ?>"><?= $p ?></a></li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <div id="lightboxContent"></div>
    <div class="lightbox-actions" id="lightboxActions"></div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>

<script>
function openLightbox(src, type, allowDownload) {
    const content = document.getElementById('lightboxContent');
    const actions = document.getElementById('lightboxActions');
    if (type === 'pdf') {
        content.innerHTML = '<iframe src="' + src + '"></iframe>';
    } else {
        content.innerHTML = '<img src="' + src + '" alt="Certificate">';
    }
    let actionsHtml = '';
    if (allowDownload) {
        actionsHtml += '<a href="' + src + '" download><i class="bi bi-download me-1"></i>Download</a>';
    }
    actionsHtml += '<a href="' + src + '" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Open</a>';
    actions.innerHTML = actionsHtml;
    document.getElementById('lightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeLightbox(e) {
    if (e.target === document.getElementById('lightbox') || e.target.closest('.lightbox-close')) {
        document.getElementById('lightbox').classList.remove('show');
        document.body.style.overflow = '';
        document.getElementById('lightboxContent').innerHTML = '';
    }
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') { document.getElementById('lightbox').classList.remove('show'); document.body.style.overflow=''; }});
</script>
</body>
</html>
