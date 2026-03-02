<?php
require_once __DIR__.'/../includes/auth.php';
requireTeacher();
$db = getDB();
$uid = currentUserId();

$classes = $db->query("SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);

$selectedClass = $_POST['class'] ?? $_GET['class'] ?? '';
$examName = $_POST['exam_name'] ?? $_GET['exam_name'] ?? '';
$subject = $_POST['subject'] ?? $_GET['subject'] ?? '';
$maxMarks = (int)($_POST['max_marks'] ?? $_GET['max_marks'] ?? 100);
$students = [];
$existingResults = [];

if ($selectedClass) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, roll_number FROM students WHERE class=? AND status='active' ORDER BY roll_number, first_name");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    if ($examName && $subject) {
        $stmt2 = $db->prepare("SELECT student_id, marks_obtained, grade FROM exam_results WHERE class=? AND exam_name=? AND subject=?");
        $stmt2->execute([$selectedClass, $examName, $subject]);
        while ($row = $stmt2->fetch()) {
            $existingResults[$row['student_id']] = $row;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks']) && verifyCsrf()) {
    $class = $_POST['class'] ?? '';
    $exam = $_POST['exam_name'] ?? '';
    $subj = $_POST['subject'] ?? '';
    $max = (int)($_POST['max_marks'] ?? 100);
    $marks = $_POST['marks'] ?? [];

    if ($class && $exam && $subj && !empty($marks)) {
        $del = $db->prepare("DELETE FROM exam_results WHERE class=? AND exam_name=? AND subject=?");
        $del->execute([$class, $exam, $subj]);

        $insert = $db->prepare("INSERT INTO exam_results (student_id, class, exam_name, subject, max_marks, marks_obtained, grade, entered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $count = 0;
        foreach ($marks as $studentId => $obtained) {
            if ($obtained === '' || $obtained === null) continue;
            $obtained = (float)$obtained;
            $pct = $max > 0 ? ($obtained / $max) * 100 : 0;
            $grade = match(true) {
                $pct >= 90 => 'A+', $pct >= 80 => 'A', $pct >= 70 => 'B+',
                $pct >= 60 => 'B', $pct >= 50 => 'C', $pct >= 40 => 'D', default => 'F'
            };
            $insert->execute([(int)$studentId, $class, $exam, $subj, $max, $obtained, $grade, $uid]);
            $count++;
        }
        auditLog('enter_exam_results', 'exam_results', null, "Exam: $exam, Subject: $subj, Class: $class, Entries: $count");
        setFlash('success', "Exam results saved — $count entries recorded.");
        header("Location: /teacher/exams.php?class=" . urlencode($class) . "&exam_name=" . urlencode($exam) . "&subject=" . urlencode($subj) . "&max_marks=$max");
        exit;
    } else {
        setFlash('error', 'Please fill all fields.');
    }
}

$pageTitle = 'Exam Results';
require_once __DIR__.'/../includes/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Class</label>
                <select name="class" class="form-select" required>
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= e($c) ?>" <?= $selectedClass === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Exam Name</label>
                <input type="text" name="exam_name" class="form-control" value="<?= e($examName) ?>" placeholder="e.g. Mid Term 2025" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Subject</label>
                <input type="text" name="subject" class="form-control" value="<?= e($subject) ?>" placeholder="e.g. Math" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Max Marks</label>
                <input type="number" name="max_marks" class="form-control" value="<?= $maxMarks ?>" min="1" max="1000">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Load</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass && $examName && $subject && !empty($students)): ?>
<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="save_marks" value="1">
    <input type="hidden" name="class" value="<?= e($selectedClass) ?>">
    <input type="hidden" name="exam_name" value="<?= e($examName) ?>">
    <input type="hidden" name="subject" value="<?= e($subject) ?>">
    <input type="hidden" name="max_marks" value="<?= $maxMarks ?>">

    <div class="card">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-journal-text me-2"></i><?= e($examName) ?> — <?= e($subject) ?> (Class <?= e($selectedClass) ?>, Max: <?= $maxMarks ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Roll No</th><th>Student Name</th><th style="width:120px">Marks</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $i => $s):
                        $existing = $existingResults[$s['id']] ?? null;
                    ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= e($s['roll_number'] ?? '—') ?></strong></td>
                            <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td>
                                <input type="number" name="marks[<?= $s['id'] ?>]" class="form-control form-control-sm"
                                    value="<?= $existing ? e($existing['marks_obtained']) : '' ?>"
                                    min="0" max="<?= $maxMarks ?>" step="0.5" placeholder="—">
                            </td>
                            <td>
                                <?php if ($existing): ?>
                                    <span class="badge bg-<?= $existing['grade'] === 'F' ? 'danger' : ($existing['grade'][0] === 'A' ? 'success' : 'primary') ?>">
                                        <?= e($existing['grade']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Results</button>
            <small class="text-muted ms-2">Grades are auto-calculated on save.</small>
        </div>
    </div>
</form>
<?php elseif ($selectedClass && $examName && $subject && empty($students)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No active students in class <?= e($selectedClass) ?>.</div>
<?php endif; ?>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
