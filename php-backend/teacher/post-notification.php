<?php
require_once __DIR__ . '/../includes/auth.php';
requireTeacher();
require_once __DIR__ . '/../includes/file-handler.php';
$db = getDB();
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'general';
    $priority = $_POST['priority'] ?? 'normal';
    $category = $_POST['category'] ?? 'general';
    $tags = trim($_POST['tags'] ?? '');
    $targetAudience = $_POST['target_audience'] ?? 'all';
    $targetClass = $_POST['target_class'] ?? null;
    $targetSection = $_POST['target_section'] ?? null;
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $saveAs = $_POST['save_as'] ?? 'pending';
    $status = in_array($saveAs, ['draft', 'pending']) ? $saveAs : 'pending';

    if ($title && $content) {
        $stmt = $db->prepare("INSERT INTO notifications (title, content, type, priority, category, tags, target_audience, target_class, target_section, is_public, posted_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $type, $priority, $category, $tags, $targetAudience, $targetClass, $targetSection, $isPublic, $uid, $status]);
        $newId = (int)$db->lastInsertId();

        // Handle multi-attachments
        if (!empty($_FILES['attachments'])) {
            $allowedExts = ['pdf','doc','docx','jpg','jpeg','png','gif','zip','xlsx','pptx'];
            $maxSize = 10 * 1024 * 1024;
            $files = $_FILES['attachments'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts) || $files['size'][$i] > $maxSize) continue;
                $saveName = 'notif_' . $newId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/documents/' . $saveName;
                if (FileHandler::saveUploadedFile($files['tmp_name'][$i], $dest)) {
                    $ftype = in_array($ext, ['jpg','jpeg','png','gif']) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'document');
                    $db->prepare("INSERT INTO notification_attachments (notification_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?)")
                        ->execute([$newId, $files['name'][$i], $saveName, $ftype, $files['size'][$i], $uid]);
                }
            }
        }

        // Legacy single attachment support
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['pdf','doc','docx','jpg','jpeg','png','gif'];
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $filename = 'notif_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/documents/' . $filename;
                if (FileHandler::saveUploadedFile($_FILES['attachment']['tmp_name'], $dest)) {
                    $db->prepare("UPDATE notifications SET attachment=? WHERE id=?")->execute([$filename, $newId]);
                }
            }
        }

        auditLog('post_notification', 'notification', $newId, "Title: $title");
        setFlash('success', $status === 'draft' ? 'Notification saved as draft.' : 'Notification submitted for admin approval.');
        header('Location: /teacher/post-notification.php');
        exit;
    } else {
        setFlash('error', 'Title and content are required.');
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$total = $db->prepare("SELECT COUNT(*) FROM notifications WHERE posted_by=? AND is_deleted=0");
$total->execute([$uid]); $total = $total->fetchColumn();
$p = paginate($total, 15, $page);
$notifs = $db->prepare("SELECT id, title, type, priority, category, tags, target_audience, is_public, status, reject_reason, content, attachment, created_at FROM notifications WHERE posted_by=? AND is_deleted=0 ORDER BY created_at DESC LIMIT ? OFFSET ?");
$notifs->execute([$uid, $p['per_page'], $p['offset']]);
$notifs = $notifs->fetchAll();

$pageTitle = 'Post Notification';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-megaphone-fill me-2 text-primary"></i>New Notification</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200" placeholder="Enter notification title">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select">
                                <option value="general">General</option>
                                <option value="academic">Academic</option>
                                <option value="exam">Exam</option>
                                <option value="event">Event</option>
                                <option value="holiday">Holiday</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority</label>
                            <div class="d-flex gap-3 mt-2">
                                <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="normal" id="pNormal" checked><label class="form-check-label" for="pNormal">Normal</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="important" id="pImportant"><label class="form-check-label text-warning" for="pImportant">Important</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="urgent" id="pUrgent"><label class="form-check-label text-danger" for="pUrgent">Urgent</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" class="form-select">
                                <option value="general">General</option>
                                <option value="academic">Academic</option>
                                <option value="administrative">Administrative</option>
                                <option value="sports">Sports</option>
                                <option value="cultural">Cultural</option>
                                <option value="exam">Exam</option>
                                <option value="holiday">Holiday</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tags <span class="text-muted fw-normal">(comma-separated)</span></label>
                            <input type="text" name="tags" class="form-control" placeholder="e.g. exam, result">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Audience</label>
                        <select name="target_audience" class="form-select" id="teacherTarget" onchange="toggleTeacherTarget()">
                            <option value="all">All</option>
                            <option value="students">Students</option>
                            <option value="teachers">Teachers</option>
                            <option value="parents">Parents</option>
                            <option value="class">Specific Class</option>
                            <option value="section">Specific Section</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="tClassField">
                        <label class="form-label fw-semibold">Class</label>
                        <input type="text" name="target_class" class="form-control" placeholder="e.g. 10">
                    </div>
                    <div class="mb-3 d-none" id="tSectionField">
                        <label class="form-label fw-semibold">Section</label>
                        <input type="text" name="target_section" class="form-control" placeholder="e.g. A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="5" required maxlength="2000" placeholder="Write your notification..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Attachments <span class="text-muted fw-normal">(max 10MB each)</span></label>
                        <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.xlsx,.pptx">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_public" class="form-check-input" id="isPublic" value="1">
                        <label class="form-check-label" for="isPublic">Show on public website</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="save_as" value="draft" class="btn btn-outline-secondary flex-fill"><i class="bi bi-file-earmark me-1"></i>Save Draft</button>
                        <button type="submit" name="save_as" value="pending" class="btn btn-primary flex-fill"><i class="bi bi-send me-1"></i>Submit for Approval</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>My Submissions (<?= $total ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($notifs)): ?>
                    <p class="text-muted p-3 mb-0">No submissions yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Title</th><th>Category</th><th>Priority</th><th>Target</th><th>Public</th><th>Status</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        <?php
                        $prColors = ['normal'=>'secondary','important'=>'warning','urgent'=>'danger'];
                        $stColors = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','published'=>'primary','expired'=>'dark','rejected'=>'danger'];
                        foreach ($notifs as $n): ?>
                            <tr>
                                <td style="font-size:.85rem;max-width:150px" class="text-truncate"><?= e($n['title']) ?></td>
                                <td style="font-size:.78rem"><?= ucfirst(e($n['category'] ?? 'general')) ?></td>
                                <td><span class="badge bg-<?= $prColors[$n['priority'] ?? 'normal'] ?>-subtle text-<?= $prColors[$n['priority'] ?? 'normal'] ?>" style="font-size:.65rem;"><?= ucfirst($n['priority'] ?? 'normal') ?></span></td>
                                <td style="font-size:.8rem;"><?= ucfirst($n['target_audience'] ?? 'all') ?></td>
                                <td><?= $n['is_public'] ? '<i class="bi bi-globe text-success"></i>' : '<i class="bi bi-lock text-muted"></i>' ?></td>
                                <td><span class="badge bg-<?= $stColors[$n['status']] ?? 'secondary' ?>-subtle text-<?= $stColors[$n['status']] ?? 'secondary' ?>"><?= ucfirst($n['status']) ?></span></td>
                                <td><small><?= date('d M Y', strtotime($n['created_at'])) ?></small></td>
                                <td><button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="viewTeacherNotif(<?= $n['id'] ?>)"><i class="bi bi-eye"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($p['total_pages'] > 1): ?>
                <div class="card-footer bg-white"><?= paginationHtml($p, '/teacher/post-notification.php') ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="teacherViewModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Notification Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="teacherViewBody"></div>
    <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<script>
const teacherNotifs = <?= json_encode(array_map(function($n) {
    return ['id'=>$n['id'],'title'=>$n['title'],'content'=>$n['content'],'type'=>$n['type'],
        'priority'=>$n['priority']??'normal','target_audience'=>$n['target_audience']??'all',
        'category'=>$n['category']??'general','tags'=>$n['tags']??'',
        'status'=>$n['status'],'reject_reason'=>$n['reject_reason']??'',
        'attachment'=>$n['attachment']??'','created_at'=>$n['created_at']];
}, $notifs)) ?>;

function viewTeacherNotif(id) {
    const n = teacherNotifs.find(x => x.id == id);
    if (!n) return;
    const esc = s => { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; };
    const tags = n.tags ? n.tags.split(',').map(t=>`<span style="display:inline-block;background:#e0e7ff;color:#3730a3;font-size:.68rem;padding:2px 8px;border-radius:12px;margin:1px">${esc(t.trim())}</span>`).join('') : '';
    let html = `<h5 class="fw-bold">${esc(n.title)}</h5>
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <span class="badge bg-secondary">${esc(n.type)}</span>
            <span class="badge bg-${n.priority==='urgent'?'danger':n.priority==='important'?'warning':'secondary'}">${n.priority}</span>
            <span class="badge bg-light text-dark">${esc(n.category)}</span>
        </div>
        ${tags ? `<div class="mb-3">${tags}</div>` : ''}
        <div class="p-3 bg-light rounded mb-3" style="white-space:pre-wrap;">${esc(n.content)}</div>
        <small class="text-muted">Created: ${n.created_at}</small>`;
    if (n.status === 'rejected' && n.reject_reason) {
        html += `<div class="alert alert-danger mt-3 small mb-0"><i class="bi bi-x-circle me-1"></i><strong>Rejected:</strong> ${esc(n.reject_reason)}</div>`;
    }
    if (n.attachment) {
        html += `<div class="mt-3"><a href="/uploads/documents/${esc(n.attachment)}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip me-1"></i>View Attachment</a></div>`;
    }
    document.getElementById('teacherViewBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('teacherViewModal')).show();
}

function toggleTeacherTarget() {
    const v = document.getElementById('teacherTarget').value;
    document.getElementById('tClassField').classList.toggle('d-none', v !== 'class' && v !== 'section');
    document.getElementById('tSectionField').classList.toggle('d-none', v !== 'section');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>