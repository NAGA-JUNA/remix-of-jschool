<?php
$pageTitle = 'School Location';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Handle form submission
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $fields = [
                'school_map_enabled'   => isset($_POST['school_map_enabled']) ? '1' : '0',
                'school_map_embed_url' => trim($_POST['school_map_embed_url'] ?? ''),
                'school_latitude'      => trim($_POST['school_latitude'] ?? ''),
                'school_longitude'     => trim($_POST['school_longitude'] ?? ''),
                'school_landmark'      => trim($_POST['school_landmark'] ?? ''),
            ];
            foreach ($fields as $key => $value) {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, $value]);
            }
            $success = 'School location settings saved successfully!';
        } catch (Exception $ex) {
            $error = 'Error saving settings: ' . $ex->getMessage();
        }
    }
}

// Load current values
$mapEnabled  = getSetting('school_map_enabled', '0');
$embedUrl    = getSetting('school_map_embed_url', '');
$latitude    = getSetting('school_latitude', '');
$longitude   = getSetting('school_longitude', '');
$landmark    = getSetting('school_landmark', '');
$schoolName  = getSetting('school_name', '');
$schoolAddr  = getSetting('school_address', '');
$schoolPhone = getSetting('school_phone', '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-geo-alt me-2"></i>School Location</h4>
            <p class="text-muted mb-0">Manage the Google Map section shown on the homepage.</p>
        </div>
        <span class="badge <?= $mapEnabled === '1' ? 'bg-success' : 'bg-secondary' ?> fs-6 px-3 py-2 rounded-pill">
            <i class="bi <?= $mapEnabled === '1' ? 'bi-check-circle' : 'bi-x-circle' ?> me-1"></i>
            <?= $mapEnabled === '1' ? 'Enabled' : 'Disabled' ?>
        </span>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="row g-4">
            <!-- Left Column: Settings -->
            <div class="col-lg-7">
                <!-- Enable Toggle -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-body p-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="mapEnabled" name="school_map_enabled" value="1" <?= $mapEnabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="mapEnabled">Show Map Section on Homepage</label>
                        </div>
                    </div>
                </div>

                <!-- Google Maps Embed URL -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold mb-0"><i class="bi bi-map me-2 text-primary"></i>Google Maps Embed</h6>
                    </div>
                    <div class="card-body p-4">
                        <label class="form-label fw-semibold" for="embedUrl">Embed URL</label>
                        <textarea class="form-control" id="embedUrl" name="school_map_embed_url" rows="3" placeholder="https://www.google.com/maps/embed?pb=..." oninput="updateMapPreview()"><?= e($embedUrl) ?></textarea>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>How to get this:</strong> Open <a href="https://maps.google.com" target="_blank" rel="noopener">Google Maps</a>, search your school, click <strong>Share → Embed a map</strong>, copy the <code>src="..."</code> URL from the iframe code.
                        </div>
                    </div>
                </div>

                <!-- Coordinates & Landmark -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold mb-0"><i class="bi bi-pin-map me-2 text-primary"></i>Coordinates & Landmark</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="latitude">Latitude</label>
                                <input type="text" class="form-control" id="latitude" name="school_latitude" value="<?= e($latitude) ?>" placeholder="e.g. 28.6139">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="longitude">Longitude</label>
                                <input type="text" class="form-control" id="longitude" name="school_longitude" value="<?= e($longitude) ?>" placeholder="e.g. 77.2090">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="landmark">Nearby Landmark</label>
                                <input type="text" class="form-control" id="landmark" name="school_landmark" value="<?= e($landmark) ?>" placeholder="e.g. Near City Bus Stand, Opposite Town Hall">
                            </div>
                        </div>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Coordinates are used for the <strong>"Get Directions"</strong> button. Right-click on Google Maps and click the coordinates to copy them.
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-5 py-2">
                    <i class="bi bi-check-lg me-2"></i>Save Location Settings
                </button>
            </div>

            <!-- Right Column: Live Preview -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold mb-0"><i class="bi bi-eye me-2 text-primary"></i>Map Preview</h6>
                    </div>
                    <div class="card-body p-4">
                        <div id="mapPreviewContainer" style="border-radius:12px;overflow:hidden;background:#e9ecef;min-height:250px;display:flex;align-items:center;justify-content:center;">
                            <?php if ($embedUrl): ?>
                                <iframe id="mapPreviewFrame" src="<?= e($embedUrl) ?>" width="100%" height="250" style="border:0;border-radius:12px;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            <?php else: ?>
                                <div id="mapPlaceholder" class="text-center text-muted p-4">
                                    <i class="bi bi-map" style="font-size:3rem;opacity:0.3;"></i>
                                    <p class="mt-2 mb-0">Paste an embed URL above to see a live preview</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Info Preview Card -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold mb-0"><i class="bi bi-card-text me-2 text-primary"></i>Info Card Preview</h6>
                    </div>
                    <div class="card-body p-4">
                        <div style="background:linear-gradient(135deg,#f8fafc,#f0f4ff);border-radius:12px;padding:1.25rem;">
                            <h6 class="fw-bold mb-2"><?= e($schoolName ?: 'School Name') ?></h6>
                            <p class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?= e($schoolAddr ?: 'School address will appear here') ?></p>
                            <?php if ($landmark): ?>
                            <p class="text-muted small mb-1"><i class="bi bi-signpost-2 me-1"></i><?= e($landmark) ?></p>
                            <?php endif; ?>
                            <?php if ($schoolPhone): ?>
                            <p class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i><?= e($schoolPhone) ?></p>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" disabled>
                                <i class="bi bi-cursor me-1"></i>Get Directions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function updateMapPreview() {
    const url = document.getElementById('embedUrl').value.trim();
    const container = document.getElementById('mapPreviewContainer');
    if (url) {
        container.innerHTML = '<iframe id="mapPreviewFrame" src="' + url.replace(/"/g, '&quot;') + '" width="100%" height="250" style="border:0;border-radius:12px;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    } else {
        container.innerHTML = '<div class="text-center text-muted p-4"><i class="bi bi-map" style="font-size:3rem;opacity:0.3;"></i><p class="mt-2 mb-0">Paste an embed URL above to see a live preview</p></div>';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>