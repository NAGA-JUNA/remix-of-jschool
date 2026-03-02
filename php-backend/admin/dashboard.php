<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// ============ KPI Queries (with fallbacks) ============
try { $totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn(); } catch(Exception $e) { $totalStudents = 0; }
try { $totalTeachers = (int)$db->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn(); } catch(Exception $e) { $totalTeachers = 0; }
try { $totalAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn(); } catch(Exception $e) { $totalAdmissions = 0; }
try { $pendingAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status IN ('new','contacted') AND (is_deleted=0 OR is_deleted IS NULL)")->fetchColumn(); } catch(Exception $e) { $pendingAdmissions = 0; }
try { $totalEnquiries = (int)$db->query("SELECT COUNT(*) FROM enquiries")->fetchColumn(); } catch(Exception $e) { $totalEnquiries = 0; }
try { $revenueMonth = (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn(); } catch(Exception $e) { $revenueMonth = 0; }

// ============ Recruitment Overview ============
try { $totalApps = (int)$db->query("SELECT COUNT(*) FROM teacher_applications WHERE is_deleted=0")->fetchColumn(); } catch(Exception $e) { $totalApps = 0; }
try { $newApps = (int)$db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='new' AND is_deleted=0")->fetchColumn(); } catch(Exception $e) { $newApps = 0; }
try { $shortlistedApps = (int)$db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='shortlisted' AND is_deleted=0")->fetchColumn(); } catch(Exception $e) { $shortlistedApps = 0; }
try { $interviewApps = (int)$db->query("SELECT COUNT(*) FROM teacher_applications WHERE status='interview_scheduled' AND is_deleted=0")->fetchColumn(); } catch(Exception $e) { $interviewApps = 0; }
try { $activeOpenings = (int)$db->query("SELECT COUNT(*) FROM job_openings WHERE is_active=1")->fetchColumn(); } catch(Exception $e) { $activeOpenings = 0; }

// Recruitment pipeline counts
$recruitStatuses = ['new','reviewed','shortlisted','interview_scheduled','approved','rejected'];
$recruitCounts = [];
foreach ($recruitStatuses as $rs) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM teacher_applications WHERE status=? AND is_deleted=0");
        $stmt->execute([$rs]);
        $recruitCounts[$rs] = (int)$stmt->fetchColumn();
    } catch(Exception $e) { $recruitCounts[$rs] = 0; }
}
$recruitTotal = array_sum($recruitCounts) ?: 1;

// ============ Recent Applications ============
try {
    $recentApps = $db->query("SELECT a.full_name, a.status, a.created_at, a.application_id, 
        j.title as job_title 
        FROM teacher_applications a 
        LEFT JOIN job_openings j ON a.job_opening_id=j.id 
        WHERE a.is_deleted=0 
        ORDER BY a.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $recentApps = []; }

// ============ Admissions Pipeline ============
$pipelineStatuses = ['new','contacted','documents_verified','interview_scheduled','approved'];
$pipelineCounts = [];
foreach ($pipelineStatuses as $ps) {
    try {
        $pipelineCounts[$ps] = (int)$db->prepare("SELECT COUNT(*) FROM admissions WHERE status=? AND (is_deleted=0 OR is_deleted IS NULL)")->execute([$ps]) ? (int)$db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
        // Simpler approach
        $stmt = $db->prepare("SELECT COUNT(*) FROM admissions WHERE status=? AND (is_deleted=0 OR is_deleted IS NULL)");
        $stmt->execute([$ps]);
        $pipelineCounts[$ps] = (int)$stmt->fetchColumn();
    } catch(Exception $e) { $pipelineCounts[$ps] = 0; }
}
$pipelineTotal = array_sum($pipelineCounts) ?: 1;

// ============ Recent Activity ============
$activities = [];
try {
    $activities = $db->query("SELECT al.*, u.name as user_name FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// ============ System Info ============
$phpVersion = phpversion();
$dbOk = false;
try { $db->query("SELECT 1"); $dbOk = true; } catch(Exception $e) {}
$diskFree = @disk_free_space('/');
$diskTotal = @disk_total_space('/');
$diskUsedPct = ($diskTotal > 0) ? round((1 - $diskFree/$diskTotal) * 100) : 0;

// Time ago helper
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

// Activity icon map
function activityIcon($action) {
    if (str_contains($action, 'admission')) return 'bi-file-earmark-person text-purple';
    if (str_contains($action, 'student')) return 'bi-mortarboard text-primary';
    if (str_contains($action, 'teacher')) return 'bi-person-badge text-success';
    if (str_contains($action, 'login')) return 'bi-box-arrow-in-right text-info';
    if (str_contains($action, 'delete') || str_contains($action, 'archive')) return 'bi-trash text-danger';
    return 'bi-activity text-secondary';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ====== Dashboard KPI Cards ====== */
.dash-kpi {
    background: #fff;
    border-radius: 14px;
    padding: 1.25rem;
    border-left: 4px solid var(--kpi-color, #6366f1);
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: block;
    color: inherit;
    height: 100%;
}
.dash-kpi:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    color: inherit;
}
.dash-kpi .kpi-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    background: var(--kpi-bg, rgba(99,102,241,0.1));
    color: var(--kpi-color, #6366f1);
}
.dash-kpi .kpi-num {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
    margin-top: 0.75rem;
}
.dash-kpi .kpi-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}
.dash-kpi .kpi-trend {
    font-size: 0.75rem;
    font-weight: 600;
}
.kpi-trend.up { color: #22c55e; }
.kpi-trend.neutral { color: #9ca3af; }

/* ====== Section Cards ====== */
.dash-section {
    background: #fff;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 1.5rem;
}
.dash-section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ====== Pipeline Bar ====== */
.pipeline-bar {
    display: flex;
    border-radius: 10px;
    overflow: hidden;
    height: 36px;
    background: #f1f5f9;
}
.pipeline-segment {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 40px;
    transition: flex 0.5s ease;
}
.pipeline-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.75rem;
}
.pipeline-legend-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: #6b7280;
}
.pipeline-legend-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ====== Quick Action Tiles ====== */
.quick-action-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 0.5rem;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    color: #374151;
    font-size: 0.78rem;
    font-weight: 500;
    text-align: center;
    transition: all 0.25s ease;
}
.quick-action-tile:hover {
    background: #eef2ff;
    border-color: #818cf8;
    box-shadow: 0 4px 12px rgba(99,102,241,0.12);
    transform: translateY(-2px);
    color: #374151;
}
.quick-action-tile { position: relative; }
.quick-action-tile i {
    font-size: 1.5rem;
    color: #6366f1;
}
.qa-badge {
    position: absolute;
    top: 6px; right: 6px;
    background: #ef4444;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    min-width: 18px; height: 18px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 5px;
    line-height: 1;
    box-shadow: 0 1px 3px rgba(239,68,68,0.4);
    animation: badgePulse 2s infinite;
}
@keyframes badgePulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }

/* ====== Timeline ====== */
.timeline-item {
    display: flex;
    gap: 1rem;
    padding-bottom: 1.25rem;
    position: relative;
}
.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 32px;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.timeline-dot {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    z-index: 1;
}
.timeline-content {
    flex: 1;
    min-width: 0;
}
.timeline-action {
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f2937;
}
.timeline-meta {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* ====== Health Card ====== */
.health-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.85rem;
}
.health-row:last-child { border-bottom: none; }
.health-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
}
.health-dot.green { background: #22c55e; }
.health-dot.red { background: #ef4444; }
.health-dot.yellow { background: #f59e0b; }

/* ====== Recruit Cards ====== */
.recruit-card {
    padding: 1.15rem;
    border-radius: 12px;
    border-left: 4px solid var(--rc-color, #8b5cf6);
    background: #fff;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.recruit-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08), 0 0 0 1px var(--rc-color, #8b5cf6) inset;
}
.recruit-card .rc-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    background: var(--rc-bg, rgba(139,92,246,0.1));
    color: var(--rc-color, #8b5cf6);
}
.recruit-card .rc-num {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    margin-top: 0.6rem;
}
.recruit-card .rc-label {
    font-size: 0.78rem;
    color: #6b7280;
    margin-top: 2px;
}
.recruit-pipeline-bar {
    display: flex;
    border-radius: 10px;
    overflow: hidden;
    height: 32px;
    background: #f1f5f9;
    margin-top: 1rem;
}
.recruit-pipeline-bar .rp-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 600;
    min-width: 32px;
    transition: flex 0.5s ease;
}
.recruit-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

/* ====== Dark Mode ====== */
[data-bs-theme="dark"] .dash-kpi,
[data-bs-theme="dark"] .dash-section {
    background: #1e293b;
    box-shadow: 0 2px 12px rgba(0,0,0,0.2);
}
[data-bs-theme="dark"] .dash-kpi .kpi-label,
[data-bs-theme="dark"] .pipeline-legend-item,
[data-bs-theme="dark"] .recruit-card .rc-label {
    color: #94a3b8;
}
[data-bs-theme="dark"] .quick-action-tile {
    background: #1e293b;
    border-color: #334155;
    color: #e2e8f0;
}
[data-bs-theme="dark"] .quick-action-tile:hover {
    background: #2d3a52;
    border-color: #818cf8;
    color: #e2e8f0;
}
[data-bs-theme="dark"] .recruit-card {
    background: #1e293b;
}
[data-bs-theme="dark"] .recruit-card .rc-num { color: #f1f5f9; }
[data-bs-theme="dark"] .recruit-pipeline-bar {
    background: #334155;
}
[data-bs-theme="dark"] .pipeline-bar {
    background: #334155;
}
[data-bs-theme="dark"] .timeline-dot {
    background: #334155;
}
[data-bs-theme="dark"] .timeline-item:not(:last-child)::before {
    background: #334155;
}
[data-bs-theme="dark"] .timeline-action { color: #e2e8f0; }
[data-bs-theme="dark"] .health-row { border-color: #334155; }
[data-bs-theme="dark"] .dash-kpi .kpi-num { color: #f1f5f9; }

/* ====== Recent Applications List ====== */
.recent-app-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border-radius: 10px;
    transition: background 0.2s ease;
    text-decoration: none;
    color: inherit;
}
.recent-app-item:hover {
    background: #f1f5f9;
    color: inherit;
}
.recent-app-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: #fff;
    flex-shrink: 0;
}
.recent-app-info { flex: 1; min-width: 0; }
.recent-app-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.recent-app-position {
    font-size: 0.72rem;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.recent-app-badge {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 20px;
    white-space: nowrap;
    flex-shrink: 0;
}
.recent-app-time {
    font-size: 0.7rem;
    color: #9ca3af;
    white-space: nowrap;
    flex-shrink: 0;
}
[data-bs-theme="dark"] .recent-app-item:hover { background: #334155; }
[data-bs-theme="dark"] .recent-app-name { color: #f1f5f9; }
[data-bs-theme="dark"] .recent-app-position { color: #94a3b8; }

/* ====== Responsive ====== */
@media (max-width: 767.98px) {
    .dash-kpi .kpi-num { font-size: 1.4rem; }
    .dash-kpi { padding: 1rem; }
    .pipeline-legend { gap: 0.5rem; }
}
</style>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <a href="students.php" class="dash-kpi" style="--kpi-color:#3b82f6;--kpi-bg:rgba(59,130,246,0.1)">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i>12%</span>
            </div>
            <div class="kpi-num" data-count="<?= $totalStudents ?>">0</div>
            <div class="kpi-label">Total Students</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="teachers.php" class="dash-kpi" style="--kpi-color:#22c55e;--kpi-bg:rgba(34,197,94,0.1)">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-icon"><i class="bi bi-person-badge-fill"></i></div>
                <span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i>5%</span>
            </div>
            <div class="kpi-num" data-count="<?= $totalTeachers ?>">0</div>
            <div class="kpi-label">Total Teachers</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="admissions.php" class="dash-kpi" style="--kpi-color:#8b5cf6;--kpi-bg:rgba(139,92,246,0.1)">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-icon"><i class="bi bi-file-earmark-person-fill"></i></div>
                <span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i>18%</span>
            </div>
            <div class="kpi-num" data-count="<?= $totalAdmissions ?>">0</div>
            <div class="kpi-label">Total Admissions</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="admissions.php?status=new" class="dash-kpi" style="--kpi-color:#f97316;--kpi-bg:rgba(249,115,22,0.1)">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-icon"><i class="bi bi-clock-fill"></i></div>
                <span class="kpi-trend neutral"><i class="bi bi-dash"></i>0%</span>
            </div>
            <div class="kpi-num" data-count="<?= $pendingAdmissions ?>">0</div>
            <div class="kpi-label">Pending Admissions</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="enquiries.php" class="dash-kpi" style="--kpi-color:#14b8a6;--kpi-bg:rgba(20,184,166,0.1)">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-icon"><i class="bi bi-envelope-fill"></i></div>
                <span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i>8%</span>
            </div>
            <div class="kpi-num" data-count="<?= $totalEnquiries ?>">0</div>
            <div class="kpi-label">Total Enquiries</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="dash-kpi" style="--kpi-color:#10b981;--kpi-bg:rgba(16,185,129,0.1);cursor:default">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-icon"><i class="bi bi-currency-rupee"></i></div>
                <span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i></span>
            </div>
            <div class="kpi-num" data-count="<?= $revenueMonth ?>">0</div>
            <div class="kpi-label">Revenue This Month</div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Admissions Pipeline -->
        <div class="dash-section">
            <div class="dash-section-title"><i class="bi bi-funnel-fill text-purple"></i> Admissions Pipeline</div>
            <div class="pipeline-bar">
                <?php
                $pColors = ['new'=>'#3b82f6','contacted'=>'#06b6d4','documents_verified'=>'#8b5cf6','interview_scheduled'=>'#f59e0b','approved'=>'#22c55e'];
                $pLabels = ['new'=>'New','contacted'=>'Contacted','documents_verified'=>'Docs Verified','interview_scheduled'=>'Interview','approved'=>'Approved'];
                foreach ($pipelineStatuses as $ps):
                    $pct = round(($pipelineCounts[$ps] / $pipelineTotal) * 100);
                    if ($pct < 5 && $pipelineCounts[$ps] > 0) $pct = 5;
                ?>
                <div class="pipeline-segment" style="flex:<?= $pct ?>;background:<?= $pColors[$ps] ?>" title="<?= $pLabels[$ps] ?>: <?= $pipelineCounts[$ps] ?>">
                    <?= $pipelineCounts[$ps] ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pipeline-legend">
                <?php foreach ($pipelineStatuses as $ps): ?>
                <div class="pipeline-legend-item">
                    <span class="pipeline-legend-dot" style="background:<?= $pColors[$ps] ?>"></span>
                    <?= $pLabels[$ps] ?> (<?= $pipelineCounts[$ps] ?>)
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recruitment Overview -->
        <div class="dash-section">
            <div class="dash-section-title">
                <i class="bi bi-briefcase-fill text-purple"></i> Recruitment Overview
                <span class="badge bg-primary-subtle text-primary ms-auto" style="font-size:0.72rem">
                    <i class="bi bi-megaphone-fill me-1"></i><?= $activeOpenings ?> Active Opening<?= $activeOpenings != 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="recruit-card" style="--rc-color:#8b5cf6;--rc-bg:rgba(139,92,246,0.1)">
                        <div class="rc-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="rc-num" data-count="<?= $totalApps ?>">0</div>
                        <div class="rc-label">Total Applications</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="recruit-card" style="--rc-color:#3b82f6;--rc-bg:rgba(59,130,246,0.1)">
                        <div class="rc-icon"><i class="bi bi-inbox-fill"></i></div>
                        <div class="rc-num" data-count="<?= $newApps ?>">0</div>
                        <div class="rc-label">New / Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="recruit-card" style="--rc-color:#f59e0b;--rc-bg:rgba(245,158,11,0.1)">
                        <div class="rc-icon"><i class="bi bi-star-fill"></i></div>
                        <div class="rc-num" data-count="<?= $shortlistedApps ?>">0</div>
                        <div class="rc-label">Shortlisted</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="recruit-card" style="--rc-color:#14b8a6;--rc-bg:rgba(20,184,166,0.1)">
                        <div class="rc-icon"><i class="bi bi-camera-video-fill"></i></div>
                        <div class="rc-num" data-count="<?= $interviewApps ?>">0</div>
                        <div class="rc-label">Interviews</div>
                    </div>
                </div>
            </div>

            <!-- Recruitment Pipeline -->
            <?php
            $rColors = ['new'=>'#3b82f6','reviewed'=>'#06b6d4','shortlisted'=>'#f59e0b','interview_scheduled'=>'#14b8a6','approved'=>'#22c55e','rejected'=>'#ef4444'];
            $rLabels = ['new'=>'New','reviewed'=>'Reviewed','shortlisted'=>'Shortlisted','interview_scheduled'=>'Interview','approved'=>'Approved','rejected'=>'Rejected'];
            ?>
            <div class="recruit-pipeline-bar">
                <?php foreach ($recruitStatuses as $rs):
                    $pct = round(($recruitCounts[$rs] / $recruitTotal) * 100);
                    if ($pct < 5 && $recruitCounts[$rs] > 0) $pct = 5;
                ?>
                <div class="rp-seg" style="flex:<?= $pct ?>;background:<?= $rColors[$rs] ?>" title="<?= $rLabels[$rs] ?>: <?= $recruitCounts[$rs] ?>">
                    <?= $recruitCounts[$rs] ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pipeline-legend">
                <?php foreach ($recruitStatuses as $rs): ?>
                <div class="pipeline-legend-item">
                    <span class="pipeline-legend-dot" style="background:<?= $rColors[$rs] ?>"></span>
                    <?= $rLabels[$rs] ?> (<?= $recruitCounts[$rs] ?>)
                </div>
                <?php endforeach; ?>
            </div>

            <div class="recruit-actions">
                <a href="teacher-applications.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-right-circle me-1"></i>View All Applications
                </a>
                <a href="recruitment-settings.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-gear me-1"></i>Recruitment Settings
                </a>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="dash-section">
            <div class="dash-section-title"><i class="bi bi-list-check text-primary"></i> Recent Applications</div>
            <?php if (empty($recentApps)): ?>
                <p class="text-muted text-center py-3 mb-0">No applications yet</p>
            <?php else: ?>
                <?php
                $statusColors = [
                    'new'=>['bg'=>'#dbeafe','color'=>'#2563eb'],
                    'reviewed'=>['bg'=>'#cffafe','color'=>'#0891b2'],
                    'shortlisted'=>['bg'=>'#fef3c7','color'=>'#d97706'],
                    'interview_scheduled'=>['bg'=>'#ccfbf1','color'=>'#0d9488'],
                    'approved'=>['bg'=>'#dcfce7','color'=>'#16a34a'],
                    'rejected'=>['bg'=>'#fee2e2','color'=>'#dc2626']
                ];
                $avatarColors = ['#6366f1','#3b82f6','#8b5cf6','#14b8a6','#f59e0b','#ef4444','#22c55e','#06b6d4'];
                foreach ($recentApps as $i => $ra):
                    $initials = strtoupper(substr($ra['full_name'], 0, 1));
                    $avColor = $avatarColors[$i % count($avatarColors)];
                    $st = $ra['status'];
                    $stStyle = $statusColors[$st] ?? ['bg'=>'#f1f5f9','color'=>'#6b7280'];
                    $stLabel = ucwords(str_replace('_', ' ', $st));
                    $position = $ra['job_title'] ?: 'Teaching Position';
                ?>
                <a href="teacher-applications.php" class="recent-app-item">
                    <div class="recent-app-avatar" style="background:<?= $avColor ?>"><?= $initials ?></div>
                    <div class="recent-app-info">
                        <div class="recent-app-name"><?= htmlspecialchars($ra['full_name']) ?></div>
                        <div class="recent-app-position"><?= htmlspecialchars($position) ?></div>
                    </div>
                    <span class="recent-app-badge" style="background:<?= $stStyle['bg'] ?>;color:<?= $stStyle['color'] ?>"><?= $stLabel ?></span>
                    <span class="recent-app-time"><?= timeAgo($ra['created_at']) ?></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Activity Timeline -->
        <div class="dash-section">
            <div class="dash-section-title"><i class="bi bi-clock-history text-info"></i> Recent Activity</div>
            <?php if (empty($activities)): ?>
                <p class="text-muted text-center py-3 mb-0">No recent activity</p>
            <?php else: ?>
                <?php foreach ($activities as $act): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"><i class="bi <?= activityIcon($act['action']) ?>"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-action"><?= htmlspecialchars(ucwords(str_replace('_',' ',$act['action']))) ?></div>
                        <div class="timeline-meta">
                            <?= htmlspecialchars($act['user_name'] ?? 'System') ?> · <?= timeAgo($act['created_at']) ?>
                            <?php if (!empty($act['entity_type'])): ?>
                                · <span class="text-capitalize"><?= htmlspecialchars($act['entity_type']) ?></span>
                                <?php if (!empty($act['entity_id'])): ?>#<?= $act['entity_id'] ?><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

        <!-- Quick Actions -->
        <div class="dash-section">
            <div class="dash-section-title"><i class="bi bi-lightning-fill text-warning"></i> Quick Actions</div>
            <div class="row g-2">
                <div class="col-4"><a href="students.php?action=add" class="quick-action-tile"><i class="bi bi-person-plus-fill"></i>Add Student</a></div>
                <div class="col-4"><a href="teachers.php?action=add" class="quick-action-tile"><i class="bi bi-person-badge-fill"></i>Add Teacher</a></div>
                <div class="col-4"><a href="admissions.php" class="quick-action-tile"><i class="bi bi-file-earmark-check"></i>Review Admissions</a></div>
                <div class="col-4"><a href="teacher-applications.php" class="quick-action-tile"><i class="bi bi-briefcase-fill"></i>Review Applications<?php if($newApps > 0): ?><span class="qa-badge"><?= $newApps ?></span><?php endif; ?></a></div>
                <div class="col-4"><a href="notifications.php" class="quick-action-tile"><i class="bi bi-bell-fill"></i>Send Notification</a></div>
                <div class="col-4"><a href="reports.php" class="quick-action-tile"><i class="bi bi-file-earmark-bar-graph"></i>Generate Report</a></div>
                <div class="col-4"><a href="events.php?action=add" class="quick-action-tile"><i class="bi bi-calendar-plus-fill"></i>Add Event</a></div>
                <div class="col-4"><a href="settings.php?tab=backup" class="quick-action-tile"><i class="bi bi-cloud-arrow-up-fill"></i>Backup System</a></div>
            </div>
        </div>

        <!-- System Health -->
        <div class="dash-section">
            <div class="dash-section-title"><i class="bi bi-heart-pulse-fill text-danger"></i> System Health</div>
            <div class="health-row">
                <span><span class="health-dot green"></span> Server Status</span>
                <span class="badge bg-success-subtle text-success">Online</span>
            </div>
            <div class="health-row">
                <span><span class="health-dot <?= $dbOk ? 'green' : 'red' ?>"></span> Database</span>
                <span class="badge <?= $dbOk ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>"><?= $dbOk ? 'Connected' : 'Error' ?></span>
            </div>
            <div class="health-row">
                <span><i class="bi bi-clock me-1"></i> Last Backup</span>
                <span class="text-muted">—</span>
            </div>
            <div class="health-row">
                <span><i class="bi bi-hdd me-1"></i> Storage</span>
                <div style="width:100px">
                    <div class="progress" style="height:6px;border-radius:3px">
                        <div class="progress-bar <?= $diskUsedPct > 85 ? 'bg-danger' : ($diskUsedPct > 60 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= $diskUsedPct ?>%"></div>
                    </div>
                    <small class="text-muted" style="font-size:0.7rem"><?= $diskUsedPct ?>% used</small>
                </div>
            </div>
            <div class="health-row">
                <span><i class="bi bi-info-circle me-1"></i> Version</span>
                <span class="badge bg-primary-subtle text-primary">v3.4</span>
            </div>
            <div class="health-row">
                <span><i class="bi bi-filetype-php me-1"></i> PHP</span>
                <span class="text-muted"><?= $phpVersion ?></span>
            </div>
        </div>

    </div>
</div>

<!-- Animated Counters -->
<script>
(function() {
    function easeOutQuad(t) { return t * (2 - t); }

    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        var duration = 1000;
        var start = performance.now();

        function step(now) {
            var elapsed = now - start;
            var progress = Math.min(elapsed / duration, 1);
            var value = Math.floor(easeOutQuad(progress) * target);
            el.textContent = value.toLocaleString('en-IN');
            if (progress < 1) requestAnimationFrame(step);
            else el.textContent = target.toLocaleString('en-IN');
        }
        requestAnimationFrame(step);
    }

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    document.querySelectorAll('[data-count]').forEach(function(el) {
        observer.observe(el);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>