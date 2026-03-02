<?php
$pageTitle = 'Popup Advertisement';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/file-handler.php';
$db = getDB();

// Ensure table exists (safe check)
try {
    $db->query("SELECT 1 FROM popup_ads LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist yet — show message
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Please run the popup_ads migration SQL first. <a href="/admin/support.php">See Support</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Ensure default row exists
$db->exec("INSERT IGNORE INTO popup_ads (id, is_enabled) VALUES (1, 0)");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'popup_settings') {
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $startDate = trim($_POST['start_date'] ?? '') ?: null;
        $endDate = trim($_POST['end_date'] ?? '') ?: null;
        $redirectUrl = trim($_POST['redirect_url'] ?? '') ?: null;
        $buttonText = trim($_POST['button_text'] ?? '') ?: null;
        $showOnHome = isset($_POST['show_on_home']) ? 1 : 0;
        $showOncePerDay = isset($_POST['show_once_per_day']) ? 1 : 0;
        $disableOnMobile = isset($_POST['disable_on_mobile']) ? 1 : 0;

        // Handle image upload
        $imagePath = null;
        if (!empty($_FILES['popup_image']['name']) && $_FILES['popup_image']['error'] === UPLOAD_ERR_OK) {
            $result = FileHandler::uploadImage($_FILES['popup_image'], 'ads', 'popup_', 5);
            if ($result['success']) {
                $imagePath = $result['path'];
                // Delete old image
                $oldImage = $db->query("SELECT image_path FROM popup_ads WHERE id=1")->fetchColumn();
                if ($oldImage) {
                    FileHandler::deleteFile(__DIR__ . '/../' . $oldImage);
                }
            } else {
                setFlash('error', $result['error']);
                header('Location: /admin/popup-ad.php');
                exit;
            }
        }

        $sql = "UPDATE popup_ads SET is_enabled=?, start_date=?, end_date=?, redirect_url=?, button_text=?, show_on_home=?, show_once_per_day=?, disable_on_mobile=?";
        $params = [$isEnabled, $startDate, $endDate, $redirectUrl, $buttonText, $showOnHome, $showOncePerDay, $disableOnMobile];

        if ($imagePath) {
            $sql .= ", image_path=?";
            $params[] = $imagePath;
        }
        $sql .= " WHERE id=1";

        $db->prepare($sql)->execute($params);
        auditLog('update_popup_ad', 'popup_ads');
        setFlash('success', 'Popup ad settings saved successfully.');
        header('Location: /admin/popup-ad.php');
        exit;
    }

    if ($action === 'delete_image') {
        $oldImage = $db->query("SELECT image_path FROM popup_ads WHERE id=1")->fetchColumn();
        if ($oldImage) {
            FileHandler::deleteFile(__DIR__ . '/../' . $oldImage);
            $db->exec("UPDATE popup_ads SET image_path=NULL, is_enabled=0 WHERE id=1");
            setFlash('success', 'Image deleted and popup disabled.');
        }
        header('Location: /admin/popup-ad.php');
        exit;
    }
}

// Fetch popup data
$popup = $db->query("SELECT * FROM popup_ads WHERE id=1")->fetch();

// Fetch analytics
$totalViews = 0;
$totalClicks = 0;
$dailyStats = [];
try {
    $row = $db->query("SELECT COALESCE(SUM(views_count),0) as tv, COALESCE(SUM(clicks_count),0) as tc FROM popup_analytics WHERE popup_id=1")->fetch();
    $totalViews = (int)$row['tv'];
    $totalClicks = (int)$row['tc'];
    $dailyStats = $db->query("SELECT view_date, views_count, clicks_count FROM popup_analytics WHERE popup_id=1 ORDER BY view_date DESC LIMIT 14")->fetchAll();
} catch (Exception $e) { /* table may not exist yet */ }

$ctr = $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 1) : 0;

// Determine status
$statusLabel = 'Disabled';
$statusClass = 'bg-danger';
if ($popup['is_enabled']) {
    $now = date('Y-m-d');
    $inRange = true;
    if ($popup['start_date'] && $now < $popup['start_date']) { $statusLabel = 'Scheduled'; $statusClass = 'bg-warning text-dark'; $inRange = false; }
    if ($popup['end_date'] && $now > $popup['end_date']) { $statusLabel = 'Expired'; $statusClass = 'bg-secondary'; $inRange = false; }
    if ($inRange) { $statusLabel = 'Active'; $statusClass = 'bg-success'; }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-megaphone me-2"></i>Popup Advertisement</h4>
        <p class="text-muted mb-0" style="font-size:.82rem">Manage the homepage popup ad with scheduling, analytics, and display rules.</p>
    </div>
    <span class="badge <?= $statusClass ?> px-3 py-2" style="font-size:.8rem"><?= $statusLabel ?></span>
</div>

<form method="POST" enctype="multipart/form-data">
<?= csrfField() ?>
<input type="hidden" name="form_action" value="popup_settings">

<div class="row g-4">

    <!-- Left Column -->
    <div class="col-lg-7">

        <!-- Image Upload Card -->
        <div class="card border-0 rounded-3 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-image me-2"></i>Popup Image</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3" style="font-size:.78rem"><i class="bi bi-info-circle me-1"></i>Recommended size: 600×800 px. Accepted: JPG, PNG, WebP, GIF. Max 5MB.</p>

                <?php if ($popup['image_path']): ?>
                <div class="text-center mb-3 position-relative">
                    <img src="/<?= e($popup['image_path']) ?>" alt="Popup Preview" id="imagePreview" style="max-height:220px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1)" class="border">
                </div>
                <?php else: ?>
                <div class="text-center mb-3">
                    <img src="" alt="" id="imagePreview" style="max-height:220px;border-radius:12px;display:none" class="border">
                    <div id="noImagePlaceholder" class="rounded-3 d-flex align-items-center justify-content-center" style="height:150px;background:#f1f5f9;border:2px dashed #cbd5e1">
                        <div class="text-center text-muted">
                            <i class="bi bi-cloud-arrow-up" style="font-size:2rem"></i>
                            <p class="mb-0 mt-1" style="font-size:.8rem">Upload popup image</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <input type="file" name="popup_image" id="popupImageInput" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.gif">

                <?php if ($popup['image_path']): ?>
                <div class="mt-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('Delete popup image?')){document.getElementById('deleteForm').submit()}">
                        <i class="bi bi-trash me-1"></i>Remove Image
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings Card -->
        <div class="card border-0 rounded-3 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-gear me-2"></i>Popup Settings</h6>
            </div>
            <div class="card-body">
                <!-- Enable Toggle -->
                <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3 mb-3">
                    <div>
                        <div class="fw-semibold" style="font-size:.85rem">Enable Popup</div>
                        <small class="text-muted" style="font-size:.7rem">Show popup on homepage when active</small>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_enabled" <?= $popup['is_enabled'] ? 'checked' : '' ?> style="width:2.5em;height:1.25em">
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Schedule -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.8rem">Show From</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($popup['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.8rem">Show Until</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($popup['end_date'] ?? '') ?>">
                    </div>

                    <!-- Redirect URL -->
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:.8rem">Redirect URL</label>
                        <input type="url" name="redirect_url" class="form-control form-control-sm" placeholder="https://example.com/apply" value="<?= e($popup['redirect_url'] ?? '') ?>">
                        <small class="text-muted" style="font-size:.7rem">Where visitors go when clicking the popup image or button</small>
                    </div>

                    <!-- Button Text -->
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:.8rem">CTA Button Text</label>
                        <input type="text" name="button_text" class="form-control form-control-sm" placeholder="e.g. Apply Now, Learn More" value="<?= e($popup['button_text'] ?? '') ?>">
                        <small class="text-muted" style="font-size:.7rem">Leave empty to show image only (no button)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Rules Card -->
        <div class="card border-0 rounded-3 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-sliders me-2"></i>Display Rules</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold" style="font-size:.82rem"><i class="bi bi-house me-1"></i>Show only on Home page</div>
                            <small class="text-muted" style="font-size:.7rem">Popup appears only on the main homepage</small>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="show_on_home" <?= ($popup['show_on_home'] ?? 1) ? 'checked' : '' ?> style="width:2.2em;height:1.1em">
                        </div>
                    </div>
                    <hr class="my-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold" style="font-size:.82rem"><i class="bi bi-clock-history me-1"></i>Show once per day per visitor</div>
                            <small class="text-muted" style="font-size:.7rem">Uses cookies/localStorage to limit display frequency</small>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="show_once_per_day" <?= ($popup['show_once_per_day'] ?? 1) ? 'checked' : '' ?> style="width:2.2em;height:1.1em">
                        </div>
                    </div>
                    <hr class="my-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold" style="font-size:.82rem"><i class="bi bi-phone me-1"></i>Disable on mobile devices</div>
                            <small class="text-muted" style="font-size:.7rem">Popup won't appear on screens smaller than 768px</small>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="disable_on_mobile" <?= ($popup['disable_on_mobile'] ?? 0) ? 'checked' : '' ?> style="width:2.2em;height:1.1em">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="bi bi-check-lg me-1"></i>Save Popup Settings
        </button>
    </div>

    <!-- Right Column -->
    <div class="col-lg-5">

        <!-- Analytics Card -->
        <div class="card border-0 rounded-3 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-bar-chart-line me-2"></i>Analytics</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <div class="text-center p-3 rounded-3" style="background:#eff6ff">
                            <div class="fw-bold" style="font-size:1.3rem;color:#2563eb"><?= number_format($totalViews) ?></div>
                            <small class="text-muted" style="font-size:.7rem">Total Views</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-3 rounded-3" style="background:#f0fdf4">
                            <div class="fw-bold" style="font-size:1.3rem;color:#16a34a"><?= number_format($totalClicks) ?></div>
                            <small class="text-muted" style="font-size:.7rem">Total Clicks</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-3 rounded-3" style="background:#fefce8">
                            <div class="fw-bold" style="font-size:1.3rem;color:#ca8a04"><?= $ctr ?>%</div>
                            <small class="text-muted" style="font-size:.7rem">CTR</small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($dailyStats)): ?>
                <div class="table-responsive" style="max-height:280px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" style="font-size:.78rem">
                        <thead class="table-light sticky-top">
                            <tr><th>Date</th><th class="text-center">Views</th><th class="text-center">Clicks</th><th class="text-center">CTR</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dailyStats as $stat):
                            $dayCtr = $stat['views_count'] > 0 ? round(($stat['clicks_count'] / $stat['views_count']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?= date('d M', strtotime($stat['view_date'])) ?></td>
                                <td class="text-center"><?= number_format($stat['views_count']) ?></td>
                                <td class="text-center"><?= number_format($stat['clicks_count']) ?></td>
                                <td class="text-center"><?= $dayCtr ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3" style="font-size:.8rem">
                    <i class="bi bi-graph-up" style="font-size:1.5rem"></i>
                    <p class="mb-0 mt-1">No analytics data yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Live Preview Card -->
        <div class="card border-0 rounded-3 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Live Preview</h6>
            </div>
            <div class="card-body">
                <div class="rounded-3 position-relative" style="background:#0f172a;padding:1.5rem;min-height:200px;display:flex;align-items:center;justify-content:center">
                    <div style="max-width:260px;width:100%;position:relative">
                        <!-- Mock close button -->
                        <div style="position:absolute;top:-8px;right:-8px;width:28px;height:28px;border-radius:50%;background:#dc3545;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;border:2px solid #fff;z-index:2;cursor:default">
                            <i class="bi bi-x"></i>
                        </div>
                        <?php if ($popup['image_path']): ?>
                        <img src="/<?= e($popup['image_path']) ?>" alt="Preview" id="mockPreviewImg" style="width:100%;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.5)">
                        <?php else: ?>
                        <div id="mockPreviewImg" class="d-flex align-items-center justify-content-center" style="width:100%;height:160px;border-radius:12px;background:#1e293b;border:2px dashed #475569">
                            <span style="color:#64748b;font-size:.75rem">No image uploaded</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($popup['button_text']): ?>
                        <div class="text-center mt-2">
                            <span class="btn btn-primary btn-sm rounded-pill px-3" style="font-size:.7rem;pointer-events:none"><?= e($popup['button_text']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted text-center mt-2 mb-0" style="font-size:.7rem">This is how the popup will look to visitors</p>
            </div>
        </div>

    </div>
</div>
</form>

<!-- Hidden delete form -->
<form id="deleteForm" method="POST" style="display:none">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="delete_image">
</form>

<script>
// Live image preview on file select
document.getElementById('popupImageInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(ev) {
        var preview = document.getElementById('imagePreview');
        preview.src = ev.target.result;
        preview.style.display = 'block';
        var placeholder = document.getElementById('noImagePlaceholder');
        if (placeholder) placeholder.style.display = 'none';
        // Update mock preview too
        var mock = document.getElementById('mockPreviewImg');
        if (mock && mock.tagName === 'IMG') {
            mock.src = ev.target.result;
        }
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>