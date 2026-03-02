
<?php
/**
 * Admin Dashboard Logo Upload Section
 * Include this file in the Settings page after the Favicon section.
 * 
 * Usage: <?php include __DIR__ . '/settings-admin-logo-section.php'; ?>
 * 
 * Requires: getSetting() function and Bootstrap Icons available.
 */

// Local fallback for getSetting
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $pdo;
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }
}

$adminLogo = getSetting('admin_logo', '');
$adminLogoVer = getSetting('admin_logo_updated_at', time());
$adminLogoPreviewPath = '';
if ($adminLogo) {
    $adminLogoPreviewPath = (strpos($adminLogo, '/uploads/') === 0) ? $adminLogo : '/uploads/branding/' . $adminLogo;
}
?>

<!-- Admin Dashboard Logo Upload Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-layout-sidebar-inset text-primary"></i>
            Admin Dashboard Logo
        </h5>
        <small class="text-muted">This logo appears in the admin sidebar (top-left corner). Upload a separate logo for the admin panel.</small>
    </div>
    <div class="card-body p-4">
        <!-- Current Logo Preview -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Current Admin Logo</label>
            <div class="d-flex align-items-center gap-4 p-3 bg-light rounded-3">
                <?php if ($adminLogo): ?>
                    <div class="text-center">
                        <div style="width:60px;height:60px;background:#1e293b;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:8px;">
                            <img src="<?= htmlspecialchars($adminLogoPreviewPath) ?>?v=<?= htmlspecialchars($adminLogoVer) ?>" 
                                 alt="Admin Logo" style="max-width:100%;max-height:100%;object-fit:contain;" id="adminLogoPreview">
                        </div>
                        <small class="text-muted d-block mt-1">Sidebar</small>
                    </div>
                    <div class="text-center">
                        <div style="width:40px;height:40px;background:#1e293b;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:5px;">
                            <img src="<?= htmlspecialchars($adminLogoPreviewPath) ?>?v=<?= htmlspecialchars($adminLogoVer) ?>" 
                                 alt="Admin Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                        </div>
                        <small class="text-muted d-block mt-1">Collapsed</small>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteAdminLogo()">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <div style="width:60px;height:60px;background:#e2e8f0;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-image text-muted" style="font-size:1.5rem;"></i>
                        </div>
                        <small class="text-muted d-block mt-1">No logo set</small>
                    </div>
                    <div>
                        <p class="mb-0 text-muted">No admin logo uploaded. The school logo will be used as fallback.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Form -->
        <form id="adminLogoForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="adminLogoFile" class="form-label fw-semibold">Upload New Admin Logo</label>
                <input type="file" class="form-control" id="adminLogoFile" name="admin_logo" 
                       accept=".jpg,.jpeg,.png,.svg,.webp">
                <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    Recommended: Square format, minimum 100×100px. Accepted formats: JPG, PNG, SVG, WEBP. Max 5MB.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-cloud-upload me-1"></i>Upload Admin Logo
                </button>
            </div>
        </form>

        <!-- Upload Progress -->
        <div id="adminLogoProgress" class="mt-3" style="display:none;">
            <div class="progress" style="height:6px;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width:100%"></div>
            </div>
            <small class="text-muted mt-1 d-block">Uploading...</small>
        </div>

        <!-- Status Message -->
        <div id="adminLogoStatus" class="mt-3" style="display:none;"></div>
    </div>
</div>

<script>
document.getElementById('adminLogoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('adminLogoFile');
    if (!fileInput.files.length) {
        showAdminLogoStatus('Please select a file to upload.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('admin_logo', fileInput.files[0]);

    document.getElementById('adminLogoProgress').style.display = 'block';
    document.getElementById('adminLogoStatus').style.display = 'none';

    fetch('ajax/admin-logo-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('adminLogoProgress').style.display = 'none';
        if (data.success) {
            showAdminLogoStatus(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAdminLogoStatus(data.message, 'danger');
        }
    })
    .catch(err => {
        document.getElementById('adminLogoProgress').style.display = 'none';
        showAdminLogoStatus('Upload failed. Please try again.', 'danger');
    });
});

function deleteAdminLogo() {
    if (!confirm('Remove the admin dashboard logo? The school logo will be used as fallback.')) return;
    
    fetch('ajax/admin-logo-upload.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAdminLogoStatus(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAdminLogoStatus(data.message, 'danger');
        }
    });
}

function showAdminLogoStatus(msg, type) {
    const el = document.getElementById('adminLogoStatus');
    el.className = 'mt-3 alert alert-' + type;
    el.textContent = msg;
    el.style.display = 'block';
}
</script>