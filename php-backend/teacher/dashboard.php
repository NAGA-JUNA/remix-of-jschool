<?php
require_once __DIR__.'/../includes/auth.php';
requireTeacher();
$db = getDB();
$uid = currentUserId();

// KPI Stats
$myNotifs = $db->prepare("SELECT COUNT(*) FROM notifications WHERE posted_by=?"); $myNotifs->execute([$uid]); $myNotifs = $myNotifs->fetchColumn();
$myGallery = $db->prepare("SELECT COUNT(*) FROM gallery_items WHERE uploaded_by=?"); $myGallery->execute([$uid]); $myGallery = $myGallery->fetchColumn();
$pendingNotifs = $db->prepare("SELECT COUNT(*) FROM notifications WHERE posted_by=? AND status='pending'"); $pendingNotifs->execute([$uid]); $pendingNotifs = $pendingNotifs->fetchColumn();
$approvedNotifs = $db->prepare("SELECT COUNT(*) FROM notifications WHERE posted_by=? AND status='approved'"); $approvedNotifs->execute([$uid]); $approvedNotifs = $approvedNotifs->fetchColumn();
$pendingGallery = $db->prepare("SELECT COUNT(*) FROM gallery_items WHERE uploaded_by=? AND status='pending'"); $pendingGallery->execute([$uid]); $pendingGallery = $pendingGallery->fetchColumn();
$attendanceToday = $db->prepare("SELECT COUNT(*) FROM attendance WHERE marked_by=? AND date=CURDATE()"); $attendanceToday->execute([$uid]); $attendanceToday = $attendanceToday->fetchColumn();

// Recent notifications
$recentNotifs = $db->prepare("SELECT title, status, created_at FROM notifications WHERE posted_by=? ORDER BY created_at DESC LIMIT 5");
$recentNotifs->execute([$uid]);
$recentNotifs = $recentNotifs->fetchAll();

$pageTitle = 'Teacher Dashboard';
require_once __DIR__.'/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-megaphone-fill"></i></div>
                <div><div class="fs-4 fw-bold"><?= $myNotifs ?></div><small class="text-muted">My Notifications</small></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-images"></i></div>
                <div><div class="fs-4 fw-bold"><?= $myGallery ?></div><small class="text-muted">Gallery Uploads</small></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="fs-4 fw-bold"><?= $pendingNotifs ?></div><small class="text-muted">Pending Approval</small></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info-subtle text-info"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="fs-4 fw-bold"><?= $approvedNotifs ?></div><small class="text-muted">Approved</small></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-danger-subtle text-danger"><i class="bi bi-camera-fill"></i></div>
                <div><div class="fs-4 fw-bold"><?= $pendingGallery ?></div><small class="text-muted">Gallery Pending</small></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-secondary-subtle text-secondary"><i class="bi bi-check2-square"></i></div>
                <div><div class="fs-4 fw-bold"><?= $attendanceToday ?></div><small class="text-muted">Attendance Today</small></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="/teacher/attendance.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-square me-1"></i>Mark Attendance</a>
                    <a href="/teacher/exams.php" class="btn btn-outline-success btn-sm"><i class="bi bi-journal-text me-1"></i>Enter Marks</a>
                    <a href="/teacher/post-notification.php" class="btn btn-outline-info btn-sm"><i class="bi bi-megaphone me-1"></i>Post Notification</a>
                    <a href="/teacher/upload-gallery.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-camera me-1"></i>Upload Photo</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2" style="color:var(--brand-primary)"></i>Recent Submissions</div>
            <div class="card-body p-0">
                <?php if (empty($recentNotifs)): ?>
                    <p class="text-muted p-3 mb-0">No submissions yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentNotifs as $n): ?>
                            <tr>
                                <td><?= e($n['title']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($n['status']) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'warning'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= e(ucfirst($n['status'])) ?></span>
                                </td>
                                <td><small class="text-muted"><?= date('d M Y', strtotime($n['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>