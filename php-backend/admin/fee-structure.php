<?php
$pageTitle = 'Fee Structure';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// ── DELETE ──
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM fee_components WHERE fee_structure_id=?")->execute([$id]);
    $db->prepare("DELETE FROM fee_structures WHERE id=?")->execute([$id]);
    auditLog('delete_fee_structure', 'fee_structure', $id);
    setFlash('success', 'Fee structure deleted.');
    header('Location: /admin/fee-structure.php');
    exit;
}

// ── TOGGLE VISIBILITY ──
if (isset($_GET['toggle']) && isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE fee_structures SET is_visible = NOT is_visible, updated_at=NOW() WHERE id=?")->execute([$id]);
    auditLog('toggle_fee_visibility', 'fee_structure', $id);
    setFlash('success', 'Visibility updated.');
    header('Location: /admin/fee-structure.php');
    exit;
}

// ── CREATE / UPDATE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $fid = (int)($_POST['fee_id'] ?? 0);
    $class = trim($_POST['class'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $is_visible = isset($_POST['is_visible']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    $comp_names = $_POST['comp_name'] ?? [];
    $comp_amounts = $_POST['comp_amount'] ?? [];
    $comp_freqs = $_POST['comp_frequency'] ?? [];
    $comp_optional = $_POST['comp_optional'] ?? [];

    if (!$class || !$academic_year) {
        setFlash('error', 'Class and Academic Year are required.');
    } else {
        // Check duplicate
        $dupSql = "SELECT id FROM fee_structures WHERE class=? AND academic_year=?";
        $dupParams = [$class, $academic_year];
        if ($fid) { $dupSql .= " AND id!=?"; $dupParams[] = $fid; }
        $dup = $db->prepare($dupSql);
        $dup->execute($dupParams);
        if ($dup->fetch()) {
            setFlash('error', 'A fee structure for this class and year already exists.');
        } else {
            if ($fid) {
                $db->prepare("UPDATE fee_structures SET class=?, academic_year=?, is_visible=?, notes=?, updated_at=NOW() WHERE id=?")
                   ->execute([$class, $academic_year, $is_visible, $notes, $fid]);
                $db->prepare("DELETE FROM fee_components WHERE fee_structure_id=?")->execute([$fid]);
                auditLog('update_fee_structure', 'fee_structure', $fid);
            } else {
                $db->prepare("INSERT INTO fee_structures (class, academic_year, is_visible, notes, created_by, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())")
                   ->execute([$class, $academic_year, $is_visible, $notes, currentUserId()]);
                $fid = (int)$db->lastInsertId();
                auditLog('create_fee_structure', 'fee_structure', $fid);
            }
            // Insert components
            $order = 0;
            foreach ($comp_names as $i => $cname) {
                $cname = trim($cname);
                $camount = floatval($comp_amounts[$i] ?? 0);
                $cfreq = $comp_freqs[$i] ?? 'yearly';
                $copt = isset($comp_optional[$i]) ? 1 : 0;
                if ($cname && $camount >= 0) {
                    $db->prepare("INSERT INTO fee_components (fee_structure_id, component_name, amount, frequency, is_optional, display_order) VALUES (?,?,?,?,?,?)")
                       ->execute([$fid, $cname, $camount, $cfreq, $copt, $order++]);
                }
            }
            setFlash('success', 'Fee structure saved.');
        }
    }
    header('Location: /admin/fee-structure.php');
    exit;
}

// ── LOAD EDIT DATA ──
$editFee = null;
$editComponents = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM fee_structures WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editFee = $stmt->fetch();
    if ($editFee) {
        $cstmt = $db->prepare("SELECT * FROM fee_components WHERE fee_structure_id=? ORDER BY display_order ASC");
        $cstmt->execute([$editFee['id']]);
        $editComponents = $cstmt->fetchAll();
    }
}

// ── LIST ALL ──
$fees = $db->query("SELECT fs.*, u.name as creator_name, (SELECT COUNT(*) FROM fee_components WHERE fee_structure_id=fs.id) as comp_count, (SELECT SUM(amount) FROM fee_components WHERE fee_structure_id=fs.id) as total_amount FROM fee_structures fs LEFT JOIN users u ON fs.created_by=u.id ORDER BY fs.academic_year DESC, fs.class ASC")->fetchAll();

$classes = ['LKG','UKG','Class 1','Class 2','Class 3','Class 4','Class 5','Class 6','Class 7','Class 8','Class 9','Class 10','Class 11','Class 12'];
$frequencies = ['one-time'=>'One-time','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'];
$presets = ['Admission Fee','Tuition Fee','Term Fee','Transport Fee','Books & Uniform','Activity/Lab Fee'];

$ef = $editFee ?? [];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.comp-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; }
.comp-row input, .comp-row select { font-size: .82rem; }
.comp-row .comp-name { flex: 2; min-width: 140px; }
.comp-row .comp-amount { flex: 1; min-width: 90px; }
.comp-row .comp-freq { flex: 1; min-width: 100px; }
.comp-row .comp-opt { flex: 0 0 auto; }
.comp-row .comp-del { flex: 0 0 auto; }
.preset-btns { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.75rem; }
.preset-btns .btn { font-size: .72rem; padding: .2rem .55rem; }
.vis-badge { font-size: .7rem; }
</style>

<div class="row g-3">
    <!-- LEFT: Form -->
    <div class="col-lg-5">
        <div class="card border-0 rounded-3">
            <div class="card-header bg-white border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-cash-stack me-1"></i><?= $editFee ? 'Edit' : 'Add' ?> Fee Structure</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="feeForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="fee_id" value="<?= $ef['id'] ?? 0 ?>">

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label">Class *</label>
                            <select name="class" class="form-select form-select-sm" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c ?>" <?= ($ef['class'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Academic Year *</label>
                            <input type="text" name="academic_year" class="form-control form-control-sm" required placeholder="e.g. 2025-2026" value="<?= e($ef['academic_year'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional remarks"><?= e($ef['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_visible" class="form-check-input" <?= ($ef['is_visible'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label">Visible on public website</label>
                    </div>

                    <hr>
                    <h6 class="fw-semibold" style="font-size:.85rem;"><i class="bi bi-list-ul me-1"></i>Fee Components</h6>

                    <!-- Preset buttons -->
                    <div class="preset-btns">
                        <?php foreach ($presets as $p): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="addPreset('<?= $p ?>')"><?= $p ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div id="compContainer">
                        <?php if ($editComponents): foreach ($editComponents as $i => $ec): ?>
                        <div class="comp-row">
                            <input type="text" name="comp_name[]" class="form-control form-control-sm comp-name" value="<?= e($ec['component_name']) ?>" placeholder="Component name" required>
                            <input type="number" name="comp_amount[]" class="form-control form-control-sm comp-amount" value="<?= $ec['amount'] ?>" step="0.01" min="0" placeholder="Amount" required>
                            <select name="comp_frequency[]" class="form-select form-select-sm comp-freq">
                                <?php foreach ($frequencies as $fk => $fv): ?>
                                <option value="<?= $fk ?>" <?= $ec['frequency'] === $fk ? 'selected' : '' ?>><?= $fv ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="comp-opt form-check"><input type="checkbox" name="comp_optional[<?= $i ?>]" class="form-check-input" <?= $ec['is_optional'] ? 'checked' : '' ?> title="Optional"><label class="form-check-label" style="font-size:.7rem;">Opt</label></div>
                            <button type="button" class="btn btn-sm btn-outline-danger comp-del py-0 px-1" onclick="this.closest('.comp-row').remove()"><i class="bi bi-x"></i></button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>

                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addCompRow()"><i class="bi bi-plus-lg me-1"></i>Add Component</button>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><?= $editFee ? 'Update' : 'Save' ?> Fee Structure</button>
                        <?php if ($editFee): ?>
                        <a href="/admin/fee-structure.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: List -->
    <div class="col-lg-7">
        <div class="card border-0 rounded-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0">All Fee Structures (<?= count($fees) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Class</th><th>Year</th><th>Components</th><th>Total (₹)</th><th>Visible</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($fees)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No fee structures yet. Create one using the form.</td></tr>
                        <?php else: foreach ($fees as $f): ?>
                            <tr>
                                <td style="font-size:.85rem;"><?= e($f['class']) ?></td>
                                <td style="font-size:.85rem;"><?= e($f['academic_year']) ?></td>
                                <td><span class="badge bg-light text-dark"><?= $f['comp_count'] ?></span></td>
                                <td style="font-size:.85rem;">₹<?= number_format($f['total_amount'] ?? 0, 2) ?></td>
                                <td>
                                    <a href="/admin/fee-structure.php?toggle=<?= $f['id'] ?>&csrf_token=<?= csrfToken() ?>" class="vis-badge badge <?= $f['is_visible'] ? 'bg-success' : 'bg-secondary' ?>"><?= $f['is_visible'] ? 'Yes' : 'No' ?></a>
                                </td>
                                <td>
                                    <a href="/admin/fee-structure.php?edit=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="/admin/fee-structure.php?delete=<?= $f['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Delete this fee structure and all its components?')" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let compIdx = <?= count($editComponents) ?>;
function addCompRow(name='', amount='', freq='yearly') {
    const idx = compIdx++;
    const html = `<div class="comp-row">
        <input type="text" name="comp_name[]" class="form-control form-control-sm comp-name" value="${name}" placeholder="Component name" required>
        <input type="number" name="comp_amount[]" class="form-control form-control-sm comp-amount" value="${amount}" step="0.01" min="0" placeholder="Amount" required>
        <select name="comp_frequency[]" class="form-select form-select-sm comp-freq">
            <option value="one-time" ${freq==='one-time'?'selected':''}>One-time</option>
            <option value="monthly" ${freq==='monthly'?'selected':''}>Monthly</option>
            <option value="quarterly" ${freq==='quarterly'?'selected':''}>Quarterly</option>
            <option value="yearly" ${freq==='yearly'?'selected':''}>Yearly</option>
        </select>
        <div class="comp-opt form-check"><input type="checkbox" name="comp_optional[${idx}]" class="form-check-input" title="Optional"><label class="form-check-label" style="font-size:.7rem;">Opt</label></div>
        <button type="button" class="btn btn-sm btn-outline-danger comp-del py-0 px-1" onclick="this.closest('.comp-row').remove()"><i class="bi bi-x"></i></button>
    </div>`;
    document.getElementById('compContainer').insertAdjacentHTML('beforeend', html);
}
function addPreset(name) {
    const freqMap = {'Admission Fee':'one-time','Tuition Fee':'monthly','Term Fee':'quarterly','Transport Fee':'monthly','Books & Uniform':'yearly','Activity/Lab Fee':'yearly'};
    addCompRow(name, '', freqMap[name] || 'yearly');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>