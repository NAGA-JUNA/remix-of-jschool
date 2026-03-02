<?php
$pageTitle = 'Events Management';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/file-handler.php';
$db = getDB();

// ── Auto-complete past active events ──
$db->exec("UPDATE events SET status='completed' WHERE start_date < CURDATE() AND status='active'");

// ── Handle Actions ──
$action = $_GET['action'] ?? 'list';
$editEvent = null;

// ── DELETE ──
if ($action === 'delete' && isset($_GET['id'])) {
    $token = $_GET['csrf_token'] ?? '';
    if (hash_equals(csrfToken(), $token)) {
        $id = (int)$_GET['id'];
        // Get poster path for cleanup
        $stmt = $db->prepare("SELECT poster FROM events WHERE id=?");
        $stmt->execute([$id]);
        $poster = $stmt->fetchColumn();
        if ($poster) {
            FileHandler::deleteFile(__DIR__ . '/../' . $poster);
        }
        $db->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
        auditLog('delete_event', 'event', $id);
        setFlash('success', 'Event deleted successfully.');
    }
    header('Location: /admin/events.php');
    exit;
}

// ── REMOVE POSTER ──
if ($action === 'remove_poster' && isset($_GET['id'])) {
    $token = $_GET['csrf_token'] ?? '';
    if (hash_equals(csrfToken(), $token)) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT poster FROM events WHERE id=?");
        $stmt->execute([$id]);
        $poster = $stmt->fetchColumn();
        if ($poster) {
            FileHandler::deleteFile(__DIR__ . '/../' . $poster);
            $db->prepare("UPDATE events SET poster=NULL WHERE id=?")->execute([$id]);
        }
        setFlash('success', 'Poster removed.');
    }
    header('Location: /admin/events.php?action=edit&id=' . (int)$_GET['id']);
    exit;
}

// ── SAVE (Add/Edit) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $eid = (int)($_POST['event_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?: null;
    $startTime = $_POST['start_time'] ?: null;
    $endTime = $_POST['end_time'] ?: null;
    $location = trim($_POST['location'] ?? '');
    $type = $_POST['type'] ?? 'activity';
    $status = $_POST['status'] ?? 'active';
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    // Validate
    if (!$title || !$startDate) {
        setFlash('error', 'Title and start date are required.');
        header('Location: /admin/events.php?action=' . ($eid ? "edit&id=$eid" : 'add'));
        exit;
    }

    // Validate end_date >= start_date
    if ($endDate && $endDate < $startDate) {
        setFlash('error', 'End date must be on or after start date.');
        header('Location: /admin/events.php?action=' . ($eid ? "edit&id=$eid" : 'add'));
        exit;
    }

    // Validate enums
    $allowedTypes = ['sports', 'cultural', 'exam', 'holiday', 'activity', 'academic', 'meeting', 'other'];
    $allowedStatuses = ['active', 'draft', 'cancelled', 'completed'];
    if (!in_array($type, $allowedTypes)) $type = 'activity';
    if (!in_array($status, $allowedStatuses)) $status = 'active';

    // Handle poster upload
    $posterPath = null;
    if (!empty($_FILES['poster']['name']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $upload = FileHandler::uploadImage($_FILES['poster'], 'events', 'event_', 5);
        if ($upload['success']) {
            $posterPath = $upload['path'];
            // Delete old poster if editing
            if ($eid) {
                $stmt = $db->prepare("SELECT poster FROM events WHERE id=?");
                $stmt->execute([$eid]);
                $oldPoster = $stmt->fetchColumn();
                if ($oldPoster) FileHandler::deleteFile(__DIR__ . '/../' . $oldPoster);
            }
        } else {
            setFlash('error', $upload['error']);
            header('Location: /admin/events.php?action=' . ($eid ? "edit&id=$eid" : 'add'));
            exit;
        }
    }

    if ($eid) {
        // Update
        $sql = "UPDATE events SET title=?, description=?, start_date=?, end_date=?, start_time=?, end_time=?, location=?, type=?, status=?, is_public=?, is_featured=?";
        $params = [$title, $description, $startDate, $endDate, $startTime, $endTime, $location, $type, $status, $isPublic, $isFeatured];
        if ($posterPath) {
            $sql .= ", poster=?";
            $params[] = $posterPath;
        }
        $sql .= " WHERE id=?";
        $params[] = $eid;
        $db->prepare($sql)->execute($params);
        auditLog('update_event', 'event', $eid, "Updated: $title");
        setFlash('success', 'Event updated successfully.');
    } else {
        // Insert
        $sql = "INSERT INTO events (title, description, start_date, end_date, start_time, end_time, location, type, status, is_public, is_featured, poster, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute([$title, $description, $startDate, $endDate, $startTime, $endTime, $location, $type, $status, $isPublic, $isFeatured, $posterPath, currentUserId()]);
        $newId = (int)$db->lastInsertId();
        auditLog('create_event', 'event', $newId, "Created: $title");
        setFlash('success', 'Event created successfully.');
    }
    header('Location: /admin/events.php');
    exit;
}

// ── Load edit data ──
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM events WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
    $editEvent = $stmt->fetch();
    if (!$editEvent) {
        setFlash('error', 'Event not found.');
        header('Location: /admin/events.php');
        exit;
    }
}

// ── Stats ──
$totalEvents = (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$activeEvents = (int)$db->query("SELECT COUNT(*) FROM events WHERE status='active'")->fetchColumn();
$upcomingEvents = (int)$db->query("SELECT COUNT(*) FROM events WHERE start_date >= CURDATE() AND status IN ('active','draft')")->fetchColumn();
$featuredEvents = (int)$db->query("SELECT COUNT(*) FROM events WHERE is_featured=1")->fetchColumn();

// ── Filters & Pagination (for list view) ──
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $filterType = $_GET['type'] ?? '';
    $filterStatus = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;

    $where = "WHERE 1=1";
    $params = [];
    if ($search) { $where .= " AND title LIKE ?"; $params[] = "%$search%"; }
    if ($filterType) { $where .= " AND type=?"; $params[] = $filterType; }
    if ($filterStatus) { $where .= " AND status=?"; $params[] = $filterStatus; }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM events $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pag = paginate($total, $perPage, $page);

    $baseUrl = '/admin/events.php?' . http_build_query(array_filter(['search' => $search, 'type' => $filterType, 'status' => $filterStatus]));

    $dataStmt = $db->prepare("SELECT * FROM events $where ORDER BY start_date DESC LIMIT $perPage OFFSET {$pag['offset']}");
    $dataStmt->execute($params);
    $events = $dataStmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- ═══════════ LIST VIEW ═══════════ -->

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:var(--brand-primary-light);color:var(--brand-primary);font-size:1.1rem;"><i class="bi bi-calendar-event"></i></div>
                    <div>
                        <div class="fw-bold" style="font-size:1.3rem;"><?= $totalEvents ?></div>
                        <div class="text-muted" style="font-size:0.72rem;">Total Events</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(34,197,94,0.1);color:#22c55e;font-size:1.1rem;"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="fw-bold" style="font-size:1.3rem;"><?= $activeEvents ?></div>
                        <div class="text-muted" style="font-size:0.72rem;">Active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(59,130,246,0.1);color:#3b82f6;font-size:1.1rem;"><i class="bi bi-calendar-plus"></i></div>
                    <div>
                        <div class="fw-bold" style="font-size:1.3rem;"><?= $upcomingEvents ?></div>
                        <div class="text-muted" style="font-size:0.72rem;">Upcoming</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(245,158,11,0.1);color:#f59e0b;font-size:1.1rem;"><i class="bi bi-star"></i></div>
                    <div>
                        <div class="fw-bold" style="font-size:1.3rem;"><?= $featuredEvents ?></div>
                        <div class="text-muted" style="font-size:0.72rem;">Featured</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="card border-0 rounded-3 mb-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="/admin/events.php?action=add" class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Add Event</a>
            <form class="d-flex flex-wrap gap-2 ms-auto align-items-center" method="GET">
                <input type="text" name="search" class="form-control form-control-sm" style="width:180px;" placeholder="Search events..." value="<?= e($search) ?>">
                <select name="type" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Types</option>
                    <?php foreach (['sports','cultural','exam','holiday','activity','academic','meeting','other'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select form-select-sm" style="width:130px;">
                    <option value="">All Status</option>
                    <?php foreach (['active','draft','cancelled','completed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search || $filterType || $filterStatus): ?>
                <a href="/admin/events.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Events Table -->
<div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
    <div class="card-body p-0">
        <?php if (empty($events)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-x display-4 text-muted"></i>
            <p class="text-muted mt-2">No events found.</p>
            <a href="/admin/events.php?action=add" class="btn btn-primary btn-sm">Create First Event</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="font-size:0.78rem;color:var(--text-muted);">
                        <th style="width:50px;"></th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-center">Public</th>
                        <th class="text-center">Featured</th>
                        <th class="text-center">Views</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $today = date('Y-m-d');
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                foreach ($events as $ev):
                    $statusColors = ['active'=>'success','draft'=>'warning','cancelled'=>'danger','completed'=>'info'];
                    $typeColors = ['sports'=>'#22c55e','cultural'=>'#a855f7','exam'=>'#f59e0b','holiday'=>'#ef4444','activity'=>'#3b82f6','academic'=>'#06b6d4','meeting'=>'#64748b','other'=>'#94a3b8'];
                    $isToday = $ev['start_date'] === $today;
                    $isTomorrow = $ev['start_date'] === $tomorrow;
                ?>
                <tr>
                    <!-- Poster Thumbnail -->
                    <td>
                        <?php if ($ev['poster']): ?>
                        <img src="/<?= e($ev['poster']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:8px;background:var(--brand-primary-light);color:var(--brand-primary);font-size:1rem;"><i class="bi bi-calendar-event"></i></div>
                        <?php endif; ?>
                    </td>
                    <!-- Title -->
                    <td>
                        <div class="fw-semibold" style="font-size:0.85rem;"><?= e($ev['title']) ?></div>
                        <?php if ($ev['location']): ?><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($ev['location']) ?></small><?php endif; ?>
                        <?php if ($isToday): ?><span class="badge bg-success ms-1" style="font-size:0.6rem;">Today</span><?php endif; ?>
                        <?php if ($isTomorrow): ?><span class="badge bg-info ms-1" style="font-size:0.6rem;">Tomorrow</span><?php endif; ?>
                    </td>
                    <!-- Date -->
                    <td style="font-size:0.82rem;">
                        <?= date('d M Y', strtotime($ev['start_date'])) ?>
                        <?php if ($ev['end_date'] && $ev['end_date'] !== $ev['start_date']): ?>
                        <br><small class="text-muted">to <?= date('d M Y', strtotime($ev['end_date'])) ?></small>
                        <?php endif; ?>
                        <?php if ($ev['start_time']): ?>
                        <br><small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($ev['start_time'])) ?><?php if ($ev['end_time']): ?> – <?= date('h:i A', strtotime($ev['end_time'])) ?><?php endif; ?></small>
                        <?php endif; ?>
                    </td>
                    <!-- Type Badge -->
                    <td><span class="badge rounded-pill" style="background:<?= $typeColors[$ev['type']] ?? '#94a3b8' ?>;font-size:0.7rem;"><?= ucfirst(e($ev['type'])) ?></span></td>
                    <!-- Status Badge -->
                    <td><span class="badge bg-<?= $statusColors[$ev['status']] ?? 'secondary' ?>" style="font-size:0.7rem;"><?= ucfirst(e($ev['status'])) ?></span></td>
                    <!-- Public Toggle -->
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input" type="checkbox" <?= $ev['is_public'] ? 'checked' : '' ?> onchange="toggleEvent(<?= $ev['id'] ?>,'toggle_public',this)" style="cursor:pointer;">
                        </div>
                    </td>
                    <!-- Featured Star -->
                    <td class="text-center">
                        <button class="btn btn-sm p-0 border-0" onclick="toggleEvent(<?= $ev['id'] ?>,'toggle_featured',this)" title="Toggle Featured">
                            <i class="bi bi-star<?= $ev['is_featured'] ? '-fill text-warning' : ' text-muted' ?>" style="font-size:1.1rem;"></i>
                        </button>
                    </td>
                    <!-- Views -->
                    <td class="text-center"><span class="text-muted" style="font-size:0.8rem;"><?= number_format($ev['views']) ?></span></td>
                    <!-- Actions -->
                    <td>
                        <a href="/admin/events.php?action=edit&id=<?= $ev['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="/admin/events.php?action=delete&id=<?= $ev['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" title="Delete" onclick="return confirm('Delete this event permanently?')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= paginationHtml($pag, $baseUrl) ?>
        <?php endif; ?>
    </div>
</div>

<!-- AJAX Toggle Script -->
<script>
function toggleEvent(id, action, el) {
    fetch('/admin/ajax/event-actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=' + action + '&id=' + id + '&csrf_token=<?= csrfToken() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (action === 'toggle_featured') {
                const icon = el.querySelector('i');
                icon.className = data.value ? 'bi bi-star-fill text-warning' : 'bi bi-star text-muted';
            }
        } else {
            alert(data.message || 'Error');
            if (action === 'toggle_public') el.checked = !el.checked;
        }
    })
    .catch(() => {
        alert('Network error');
        if (action === 'toggle_public') el.checked = !el.checked;
    });
}
</script>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- ═══════════ ADD / EDIT FORM ═══════════ -->
<?php $ev = $editEvent ?? []; $isEdit = !empty($ev); ?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="/admin/events.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <h5 class="fw-bold mb-0"><?= $isEdit ? 'Edit' : 'Add New' ?> Event</h5>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="eventForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="event_id" value="<?= $ev['id'] ?? 0 ?>">

                    <!-- Title -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="255" value="<?= e($ev['title'] ?? '') ?>" placeholder="Event title">
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Event description..."><?= e($ev['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Dates -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required value="<?= e($ev['start_date'] ?? '') ?>" id="startDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= e($ev['end_date'] ?? '') ?>" id="endDate">
                            <small class="text-muted">Leave empty for single-day events</small>
                        </div>
                    </div>

                    <!-- Times -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="<?= e($ev['start_time'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="<?= e($ev['end_time'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" name="location" class="form-control" maxlength="255" value="<?= e($ev['location'] ?? '') ?>" placeholder="e.g. School Auditorium">
                    </div>

                    <!-- Type & Status -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select">
                                <?php foreach (['sports','cultural','exam','holiday','activity','academic','meeting','other'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($ev['type'] ?? 'activity') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['active'=>'Active','draft'=>'Draft','cancelled'=>'Cancelled','completed'=>'Completed'] as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($ev['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Poster Upload -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Poster</label>
                        <input type="file" name="poster" class="form-control" accept="image/jpeg,image/png,image/webp" id="posterInput">
                        <small class="text-muted">JPG, PNG, or WebP. Max 5MB.</small>
                        <?php if (!empty($ev['poster'])): ?>
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <img src="/<?= e($ev['poster']) ?>" alt="Current poster" style="width:80px;height:60px;object-fit:cover;border-radius:8px;">
                            <a href="/admin/events.php?action=remove_poster&id=<?= $ev['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove poster?')"><i class="bi bi-trash me-1"></i>Remove</a>
                        </div>
                        <?php endif; ?>
                        <div id="posterPreview" class="mt-2" style="display:none;">
                            <img id="posterPreviewImg" alt="Preview" style="max-width:200px;max-height:150px;border-radius:8px;object-fit:cover;">
                        </div>
                    </div>

                    <!-- Toggles -->
                    <div class="d-flex gap-4 mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_public" id="isPublic" <?= ($ev['is_public'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isPublic">Public</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" <?= ($ev['is_featured'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isFeatured">Featured</label>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Event' : 'Create Event' ?></button>
                        <a href="/admin/events.php" class="btn btn-outline-secondary rounded-pill px-3">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar Tips -->
    <div class="col-lg-4">
        <div class="card border-0 rounded-3" style="background:var(--bg-card);box-shadow:var(--shadow-sm);">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning me-1"></i>Tips</h6>
                <ul class="list-unstyled mb-0" style="font-size:0.82rem;color:var(--text-muted);">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i>Use <strong>Draft</strong> to save without publishing</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i><strong>Featured</strong> events appear prominently on the public page</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i>Past active events auto-mark as <strong>Completed</strong></li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-1"></i>Upload a poster image for visual appeal</li>
                    <li><i class="bi bi-check-circle text-success me-1"></i>Set end date for multi-day events</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Client-side Validation -->
<script>
document.getElementById('posterInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('posterPreview');
    const img = document.getElementById('posterPreviewImg');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) { img.src = ev.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
});

document.getElementById('eventForm')?.addEventListener('submit', function(e) {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    if (end && end < start) {
        e.preventDefault();
        alert('End date must be on or after start date.');
    }
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>