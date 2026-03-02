<?php
$pageTitle = 'Seat Capacity';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

$academicYear = getSetting('academic_year', date('Y').'-'.(date('Y')+1));

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $postAction = $_POST['post_action'] ?? 'save_seats';

    if ($postAction === 'add_class') {
        $newClass = trim($_POST['new_class'] ?? '');
        $newSeats = (int)($_POST['new_seats'] ?? 40);
        if ($newClass && $newSeats >= 0) {
            $exists = $db->prepare("SELECT id FROM class_seat_capacity WHERE class=? AND academic_year=?");
            $exists->execute([$newClass, $academicYear]);
            if ($exists->fetchColumn()) {
                setFlash('warning', "Class '$newClass' already exists for this academic year.");
            } else {
                $db->prepare("INSERT INTO class_seat_capacity (class, total_seats, academic_year, is_active) VALUES (?,?,?,1)")->execute([$newClass, $newSeats, $academicYear]);
                auditLog('seat_class_added', 'seat_capacity', null, "Class: $newClass, Year: $academicYear");
                setFlash('success', "Class '$newClass' added successfully.");
            }
        }
        header('Location: /admin/seat-capacity.php');
        exit;
    }

    if ($postAction === 'toggle_active') {
        $toggleClass = trim($_POST['toggle_class'] ?? '');
        $toggleTo = (int)($_POST['toggle_to'] ?? 1);
        if ($toggleClass) {
            $db->prepare("UPDATE class_seat_capacity SET is_active=? WHERE class=? AND academic_year=?")->execute([$toggleTo, $toggleClass, $academicYear]);
            auditLog('seat_class_toggled', 'seat_capacity', null, "Class: $toggleClass, Active: $toggleTo");
            setFlash('success', "Class '$toggleClass' " . ($toggleTo ? 'enabled' : 'disabled') . ".");
        }
        header('Location: /admin/seat-capacity.php');
        exit;
    }

    if ($postAction === 'delete_class') {
        $delClass = trim($_POST['delete_class'] ?? '');
        if ($delClass) {
            $db->prepare("DELETE FROM class_seat_capacity WHERE class=? AND academic_year=?")->execute([$delClass, $academicYear]);
            auditLog('seat_class_deleted', 'seat_capacity', null, "Class: $delClass, Year: $academicYear");
            setFlash('success', "Class '$delClass' removed.");
        }
        header('Location: /admin/seat-capacity.php');
        exit;
    }

    // Default: save seats
    $classes = $_POST['classes'] ?? [];
    $seats = $_POST['seats'] ?? [];
    
    foreach ($classes as $idx => $cls) {
        $cls = trim($cls);
        $s = (int)($seats[$idx] ?? 40);
        if (!$cls || $s < 0) continue;
        
        $existing = $db->prepare("SELECT id FROM class_seat_capacity WHERE class=? AND academic_year=?");
        $existing->execute([$cls, $academicYear]);
        
        if ($existing->fetchColumn()) {
            $db->prepare("UPDATE class_seat_capacity SET total_seats=? WHERE class=? AND academic_year=?")->execute([$s, $cls, $academicYear]);
        } else {
            $db->prepare("INSERT INTO class_seat_capacity (class, total_seats, academic_year) VALUES (?,?,?)")->execute([$cls, $s, $academicYear]);
        }
    }
    auditLog('seat_capacity_updated', 'seat_capacity', null, "Year: $academicYear");
    setFlash('success', 'Seat capacity updated.');
    header('Location: /admin/seat-capacity.php');
    exit;
}

// Load current capacity (dynamic from DB)
$capacityData = [];
$stmt = $db->prepare("SELECT * FROM class_seat_capacity WHERE academic_year=? ORDER BY CASE WHEN class REGEXP '^[0-9]+$' THEN CAST(class AS UNSIGNED) ELSE 999 END, class ASC");
$stmt->execute([$academicYear]);
while ($r = $stmt->fetch()) {
    $capacityData[$r['class']] = $r;
}

// If no classes exist yet, seed defaults 1-12
if (empty($capacityData)) {
    for ($i = 1; $i <= 12; $i++) {
        $db->prepare("INSERT INTO class_seat_capacity (class, total_seats, academic_year, is_active) VALUES (?,40,?,1)")->execute([(string)$i, $academicYear]);
        $capacityData[(string)$i] = ['class'=>(string)$i, 'total_seats'=>40, 'is_active'=>1];
    }
}

// Get filled counts
$filledCounts = [];
$fStmt = $db->query("SELECT class_applied, COUNT(*) as c FROM admissions WHERE status IN ('approved','converted') GROUP BY class_applied");
while ($r = $fStmt->fetch()) {
    $filledCounts[$r['class_applied']] = (int)$r['c'];
}

// Active students per class
$studentCounts = [];
$sStmt = $db->query("SELECT class, COUNT(*) as c FROM students WHERE status='active' GROUP BY class");
while ($r = $sStmt->fetch()) {
    $studentCounts[$r['class']] = (int)$r['c'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>Seat Capacity Management</h5>
        <small class="text-muted">Academic Year: <strong><?= e($academicYear) ?></strong></small>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClassModal"><i class="bi bi-plus-circle me-1"></i>Add New Class</button>
</div>

<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="post_action" value="save_seats">
    <div class="row g-3">
        <?php foreach ($capacityData as $cls => $cap):
            $totalSeats = (int)($cap['total_seats'] ?? 40);
            $isActive = isset($cap['is_active']) ? (int)$cap['is_active'] : 1;
            $filled = ($filledCounts[$cls] ?? 0) + ($studentCounts[$cls] ?? 0);
            $available = max(0, $totalSeats - $filled);
            $pct = $totalSeats > 0 ? round(($filled / $totalSeats) * 100) : 0;
            $barColor = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
        ?>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 h-100 <?= !$isActive ? 'opacity-50' : '' ?>" style="<?= !$isActive ? 'filter:grayscale(0.6)' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0"><?= is_numeric($cls) ? 'Class '.$cls : e($cls) ?></h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $barColor ?>-subtle text-<?= $barColor ?>"><?= $available ?> free</span>
                            <!-- Toggle switch -->
                            <form method="POST" class="d-inline m-0 p-0">
                                <?= csrfField() ?>
                                <input type="hidden" name="post_action" value="toggle_active">
                                <input type="hidden" name="toggle_class" value="<?= e($cls) ?>">
                                <input type="hidden" name="toggle_to" value="<?= $isActive ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-link p-0 m-0 border-0" title="<?= $isActive ? 'Disable this class' : 'Enable this class' ?>" style="font-size:1.1rem;line-height:1">
                                    <i class="bi <?= $isActive ? 'bi-toggle-on text-success' : 'bi-toggle-off text-secondary' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <input type="hidden" name="classes[]" value="<?= e($cls) ?>">
                    <div class="mb-2">
                        <label class="form-label mb-0" style="font-size:.72rem;color:var(--text-muted)">Total Seats</label>
                        <input type="number" name="seats[]" class="form-control form-control-sm" value="<?= $totalSeats ?>" min="0" max="500" <?= !$isActive ? 'disabled' : '' ?>>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:.72rem;color:var(--text-muted)">
                        <span>Filled: <?= $filled ?></span>
                        <span>Available: <?= $available ?></span>
                    </div>
                    <div class="progress mt-1" style="height:6px;">
                        <div class="progress-bar bg-<?= $barColor ?>" style="width:<?= min(100,$pct) ?>%"></div>
                    </div>
                    <?php if (!$isActive): ?>
                        <div class="text-center mt-2"><span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">Disabled — hidden from admission form</span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Seat Capacity</button>
    </div>
</form>

<!-- Add New Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="post_action" value="add_class">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add New Class</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" name="new_class" class="form-control" required placeholder="e.g. Nursery, LKG, UKG, 13" maxlength="20">
                        <div class="form-text">Enter a class name like Nursery, LKG, UKG, or a number.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Total Seats</label>
                        <input type="number" name="new_seats" class="form-control" value="40" min="0" max="500">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>Add Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>