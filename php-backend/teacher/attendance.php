<?php
require_once __DIR__.'/../includes/auth.php';
requireTeacher();
$db = getDB();
$uid = currentUserId();

// Get distinct classes from students
$classes = $db->query("SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);

$selectedClass = $_POST['class'] ?? $_GET['class'] ?? '';
$selectedDate = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');
$students = [];
$existingAttendance = [];

if ($selectedClass) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, roll_number FROM students WHERE class=? AND status='active' ORDER BY roll_number, first_name");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    // Check existing attendance
    $stmt2 = $db->prepare("SELECT student_id, status FROM attendance WHERE class=? AND date=?");
    $stmt2->execute([$selectedClass, $selectedDate]);
    while ($row = $stmt2->fetch()) {
        $existingAttendance[$row['student_id']] = $row['status'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance']) && verifyCsrf()) {
    $class = $_POST['class'] ?? '';
    $date = $_POST['date'] ?? '';
    $statuses = $_POST['status'] ?? [];

    if ($class && $date && !empty($statuses)) {
        $del = $db->prepare("DELETE FROM attendance WHERE class=? AND date=?");
        $del->execute([$class, $date]);

        $insert = $db->prepare("INSERT INTO attendance (student_id, class, date, status, marked_by) VALUES (?, ?, ?, ?, ?)");
        $present = 0; $absent = 0; $late = 0;
        foreach ($statuses as $studentId => $status) {
            $insert->execute([(int)$studentId, $class, $date, $status, $uid]);
            if ($status === 'present') $present++;
            elseif ($status === 'absent') $absent++;
            else $late++;
        }

        auditLog('mark_attendance', 'attendance', null, "Class: $class, Date: $date, P:$present A:$absent L:$late");
        setFlash('success', "Attendance saved — Present: $present, Absent: $absent, Late: $late");
        header("Location: /teacher/attendance.php?class=" . urlencode($class) . "&date=" . urlencode($date));
        exit;
    }
}

$pageTitle = 'Mark Attendance';
require_once __DIR__.'/../includes/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Class</label>
                <select name="class" class="form-select" required onchange="this.form.submit()">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= e($c) ?>" <?= $selectedClass === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Load Students</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass && !empty($students)): ?>
<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="class" value="<?= e($selectedClass) ?>">
    <input type="hidden" name="date" value="<?= e($selectedDate) ?>">

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-people-fill me-2"></i>Class <?= e($selectedClass) ?> — <?= date('d M Y', strtotime($selectedDate)) ?> (<?= count($students) ?> students)</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="markAll('present')">All Present</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="markAll('absent')">All Absent</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Roll No</th><th>Student Name</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $i => $s):
                        $existing = $existingAttendance[$s['id']] ?? 'present';
                    ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= e($s['roll_number'] ?? '—') ?></strong></td>
                            <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="status[<?= $s['id'] ?>]" id="p<?= $s['id'] ?>" value="present" <?= $existing === 'present' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success" for="p<?= $s['id'] ?>">Present</label>
                                    <input type="radio" class="btn-check" name="status[<?= $s['id'] ?>]" id="a<?= $s['id'] ?>" value="absent" <?= $existing === 'absent' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-danger" for="a<?= $s['id'] ?>">Absent</label>
                                    <input type="radio" class="btn-check" name="status[<?= $s['id'] ?>]" id="l<?= $s['id'] ?>" value="late" <?= $existing === 'late' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-warning" for="l<?= $s['id'] ?>">Late</label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Attendance</button>
        </div>
    </div>
</form>

<script>
function markAll(status) {
    document.querySelectorAll(`input[value="${status}"]`).forEach(r => r.checked = true);
}
</script>
<?php elseif ($selectedClass && empty($students)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No active students found in class <?= e($selectedClass) ?>.</div>
<?php endif; ?>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
