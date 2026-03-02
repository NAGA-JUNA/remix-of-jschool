<?php
$pageTitle = 'Students';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
if (isset($_GET['delete']) && verifyCsrf()) { $db->prepare("DELETE FROM students WHERE id=?")->execute([(int)$_GET['delete']]); auditLog('delete_student','student',(int)$_GET['delete']); setFlash('success','Deleted.'); header('Location: /admin/students.php'); exit; }
$search=trim($_GET['search']??'');$classFilter=$_GET['class']??'';$statusFilter=$_GET['status']??'active';$page=max(1,(int)($_GET['page']??1));
$where=[];$params=[];
if($search){$where[]="(s.name LIKE ? OR s.admission_no LIKE ?)";$params[]="%$search%";$params[]="%$search%";}
if($classFilter){$where[]="s.class=?";$params[]=$classFilter;}
if($statusFilter){$where[]="s.status=?";$params[]=$statusFilter;}
$w=$where?'WHERE '.implode(' AND ',$where):'';
$total=$db->prepare("SELECT COUNT(*) FROM students s $w");$total->execute($params);$total=$total->fetchColumn();
$p=paginate($total,25,$page);
$stmt=$db->prepare("SELECT * FROM students s $w ORDER BY created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");$stmt->execute($params);$students=$stmt->fetchAll();
$classes=$db->query("SELECT DISTINCT class FROM students ORDER BY class+0")->fetchAll(PDO::FETCH_COLUMN);
require_once __DIR__.'/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <span class="text-muted" style="font-size:.85rem"><?=$total?> student(s)</span>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload me-1"></i>Import</button>
    <a href="/admin/reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export</a>
    <a href="/admin/student-form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Student</a>
  </div>
</div>
<div class="card border-0 rounded-3 mb-3"><div class="card-body py-2"><form class="row g-2 align-items-end" method="GET"><div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?=e($search)?>"></div><div class="col-md-2"><select name="class" class="form-select form-select-sm"><option value="">All Classes</option><?php foreach($classes as $c):?><option value="<?=e($c)?>" <?=$classFilter===$c?'selected':''?>><?=e($c)?></option><?php endforeach;?></select></div><div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All</option><?php foreach(['active','inactive','alumni','tc_issued'] as $st):?><option value="<?=$st?>" <?=$statusFilter===$st?'selected':''?>><?=ucfirst($st)?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-sm btn-dark w-100">Filter</button></div><div class="col-md-2"><a href="/admin/students.php" class="btn btn-sm btn-outline-secondary w-100">Clear</a></div></form></div></div>
<div class="card border-0 rounded-3"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Adm No</th><th>Name</th><th>Class</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if(empty($students)):?><tr><td colspan="6" class="text-center text-muted py-4">No students</td></tr>
<?php else:foreach($students as $s):
  $photoUrl = $s['photo'] ? (str_starts_with($s['photo'], '/uploads/') ? $s['photo'] : '/uploads/photos/'.$s['photo']) : '';
?><tr>
  <td style="font-size:.85rem" class="fw-medium"><?=e($s['admission_no'])?></td>
  <td style="font-size:.85rem">
    <div class="d-flex align-items-center gap-2">
      <?php if($photoUrl):?><img src="<?=$photoUrl?>" class="rounded-circle" style="width:32px;height:32px;object-fit:cover" alt=""><?php else:?><i class="bi bi-person-circle text-muted" style="font-size:1.5rem"></i><?php endif;?>
      <?=e($s['name'])?>
    </div>
  </td>
  <td style="font-size:.85rem"><?=e($s['class'])?><?=$s['section']?'-'.e($s['section']):''?></td>
  <td style="font-size:.85rem"><?=e($s['phone']??'-')?></td>
  <td><?php $c=['active'=>'success','inactive'=>'secondary','alumni'=>'info','tc_issued'=>'warning'];?><span class="badge bg-<?=$c[$s['status']]??'light'?>-subtle text-<?=$c[$s['status']]??'dark'?>"><?=ucfirst($s['status'])?></span></td>
  <td>
    <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 btn-view-student" data-bs-toggle="modal" data-bs-target="#studentModal"
      data-id="<?=$s['id']?>"
      data-name="<?=e($s['name'])?>"
      data-admission_no="<?=e($s['admission_no'])?>"
      data-photo="<?=e($photoUrl)?>"
      data-status="<?=e($s['status'])?>"
      data-class="<?=e($s['class'])?>"
      data-section="<?=e($s['section']??'')?>"
      data-roll_no="<?=e($s['roll_no']??'')?>"
      data-father_name="<?=e($s['father_name']??'')?>"
      data-mother_name="<?=e($s['mother_name']??'')?>"
      data-dob="<?=e($s['dob']??'')?>"
      data-gender="<?=e($s['gender']??'')?>"
      data-blood_group="<?=e($s['blood_group']??'')?>"
      data-category="<?=e($s['category']??'')?>"
      data-aadhar="<?=e($s['aadhar_no']??'')?>"
      data-phone="<?=e($s['phone']??'')?>"
      data-email="<?=e($s['email']??'')?>"
      data-address="<?=e($s['address']??'')?>"
      data-admission_date="<?=e($s['admission_date']??'')?>"
    ><i class="bi bi-eye"></i></button>
    <a href="/admin/student-form.php?id=<?=$s['id']?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-pencil"></i></a>
    <a href="/admin/students.php?delete=<?=$s['id']?>&csrf_token=<?=csrfToken()?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
  </td>
</tr>
<?php endforeach;endif;?></tbody></table></div></div></div>
<?=paginationHtml($p,'/admin/students.php?'.http_build_query(array_filter(['search'=>$search,'class'=>$classFilter,'status'=>$statusFilter])))?>

<!-- Student Profile Modal -->
<div class="modal fade" id="studentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0 rounded-3">
  <div class="modal-header border-0" style="background:var(--brand-primary-light)">
    <div class="d-flex align-items-center gap-3">
      <div id="sm-photo-wrap">
        <i class="bi bi-person-circle text-primary" style="font-size:3rem" id="sm-avatar-icon"></i>
        <img id="sm-avatar-img" class="rounded-circle d-none" style="width:64px;height:64px;object-fit:cover" alt="">
      </div>
      <div>
        <h5 class="mb-0 fw-bold" id="sm-name"></h5>
        <small class="text-muted" id="sm-admission_no"></small>
        <span class="badge ms-2" id="sm-status"></span>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="row g-4">
      <div class="col-md-6">
        <h6 class="fw-semibold mb-3" style="color:var(--brand-primary)"><i class="bi bi-person me-2"></i>Personal Info</h6>
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted" style="width:40%">Father's Name</td><td class="fw-medium" id="sm-father_name"></td></tr>
          <tr><td class="text-muted">Mother's Name</td><td class="fw-medium" id="sm-mother_name"></td></tr>
          <tr><td class="text-muted">DOB</td><td class="fw-medium" id="sm-dob"></td></tr>
          <tr><td class="text-muted">Gender</td><td class="fw-medium" id="sm-gender"></td></tr>
          <tr><td class="text-muted">Blood Group</td><td class="fw-medium" id="sm-blood_group"></td></tr>
          <tr><td class="text-muted">Category</td><td class="fw-medium" id="sm-category"></td></tr>
          <tr><td class="text-muted">Aadhar No</td><td class="fw-medium" id="sm-aadhar"></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <h6 class="fw-semibold mb-3" style="color:var(--brand-primary)"><i class="bi bi-mortarboard me-2"></i>Academic Info</h6>
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted" style="width:40%">Class</td><td class="fw-medium" id="sm-class"></td></tr>
          <tr><td class="text-muted">Roll No</td><td class="fw-medium" id="sm-roll_no"></td></tr>
          <tr><td class="text-muted">Admission Date</td><td class="fw-medium" id="sm-admission_date"></td></tr>
        </table>
        <h6 class="fw-semibold mb-3 mt-4" style="color:var(--brand-primary)"><i class="bi bi-telephone me-2"></i>Contact</h6>
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted" style="width:40%">Phone</td><td class="fw-medium" id="sm-phone"></td></tr>
          <tr><td class="text-muted">Email</td><td class="fw-medium" id="sm-email"></td></tr>
          <tr><td class="text-muted">Address</td><td class="fw-medium" id="sm-address"></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="modal-footer border-0">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    <a id="sm-edit-link" href="#" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
  </div>
</div></div></div>

<script>
document.querySelectorAll('.btn-view-student').forEach(btn => {
  btn.addEventListener('click', function() {
    const d = this.dataset;
    document.getElementById('sm-name').textContent = d.name;
    document.getElementById('sm-admission_no').textContent = d.admission_no;
    const statusEl = document.getElementById('sm-status');
    statusEl.textContent = d.status.charAt(0).toUpperCase() + d.status.slice(1);
    const sc = {active:'success',inactive:'secondary',alumni:'info',tc_issued:'warning'};
    statusEl.className = 'badge ms-2 bg-'+(sc[d.status]||'light')+'-subtle text-'+(sc[d.status]||'dark');
    if (d.photo) { document.getElementById('sm-avatar-img').src = d.photo; document.getElementById('sm-avatar-img').classList.remove('d-none'); document.getElementById('sm-avatar-icon').classList.add('d-none'); }
    else { document.getElementById('sm-avatar-img').classList.add('d-none'); document.getElementById('sm-avatar-icon').classList.remove('d-none'); }
    ['father_name','mother_name','dob','gender','blood_group','category','aadhar','roll_no','admission_date','phone','email','address'].forEach(k => {
      const el = document.getElementById('sm-'+k);
      if(el) el.textContent = d[k] || '-';
    });
    document.getElementById('sm-class').textContent = d.class + (d.section ? '-'+d.section : '');
    document.getElementById('sm-edit-link').href = '/admin/student-form.php?id='+d.id;
  });
});
</script>
<style>
@media print {
  .sidebar, .sidebar-overlay, .top-bar, .content-area > *:not(#studentModal) { display: none !important; }
  .main-content { margin-left: 0 !important; }
  .modal { position: static !important; display: block !important; }
  .modal-dialog { max-width: 100% !important; margin: 0 !important; }
  .modal-content { border: none !important; box-shadow: none !important; }
  .modal-footer { display: none !important; }
  .modal-backdrop { display: none !important; }
  body { background: #fff !important; }
}
</style>
<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-0 rounded-3">
  <div class="modal-header border-0">
    <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i>Import Students</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <!-- Step 1: Upload -->
    <div id="import-step1">
      <div class="alert alert-info py-2" style="font-size:.85rem">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Instructions:</strong>
        <ul class="mb-0 mt-1 ps-3">
          <li>CSV format only</li>
          <li>First row must be column headers</li>
          <li><strong>admission_no</strong> &amp; <strong>name</strong> are required</li>
          <li>Duplicate admission numbers will be skipped</li>
        </ul>
      </div>
      <a href="/admin/sample-students-csv.php" class="btn btn-outline-success btn-sm mb-3"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Download Sample CSV</a>
      <div class="mb-3">
        <label class="form-label fw-medium">Select CSV File</label>
        <input type="file" class="form-control" id="importFile" accept=".csv">
      </div>
    </div>
    <!-- Step 2: Progress -->
    <div id="import-step2" class="d-none text-center py-4">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <h6 class="fw-semibold">Processing...</h6>
      <div class="progress mt-3" style="height:8px"><div class="progress-bar progress-bar-striped progress-bar-animated" id="importProgress" style="width:10%"></div></div>
      <small class="text-muted mt-2 d-block" id="importStatusText">Uploading file...</small>
    </div>
    <!-- Step 3: Results -->
    <div id="import-step3" class="d-none">
      <div class="text-center mb-3">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h5 class="fw-bold mt-2">Import Complete</h5>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-4"><div class="card border-0 bg-success bg-opacity-10 text-center p-2"><h4 class="mb-0 text-success" id="res-added">0</h4><small class="text-muted">Added</small></div></div>
        <div class="col-4"><div class="card border-0 bg-warning bg-opacity-10 text-center p-2"><h4 class="mb-0 text-warning" id="res-skipped">0</h4><small class="text-muted">Skipped</small></div></div>
        <div class="col-4"><div class="card border-0 bg-danger bg-opacity-10 text-center p-2"><h4 class="mb-0 text-danger" id="res-failed">0</h4><small class="text-muted">Failed</small></div></div>
      </div>
      <div id="res-errors" class="d-none">
        <h6 class="fw-semibold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Errors</h6>
        <div class="border rounded p-2" style="max-height:150px;overflow-y:auto;font-size:.8rem" id="res-errors-list"></div>
      </div>
    </div>
  </div>
  <div class="modal-footer border-0">
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="importCancelBtn">Cancel</button>
    <button type="button" class="btn btn-primary btn-sm" id="importUploadBtn" disabled><i class="bi bi-upload me-1"></i>Upload &amp; Process</button>
    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="importMoreBtn">Import More</button>
  </div>
</div></div></div>

<script>
(function(){
  const fileInput = document.getElementById('importFile');
  const uploadBtn = document.getElementById('importUploadBtn');
  const cancelBtn = document.getElementById('importCancelBtn');
  const moreBtn = document.getElementById('importMoreBtn');
  const step1 = document.getElementById('import-step1');
  const step2 = document.getElementById('import-step2');
  const step3 = document.getElementById('import-step3');
  const progress = document.getElementById('importProgress');

  fileInput.addEventListener('change', () => { uploadBtn.disabled = !fileInput.files.length; });

  function resetModal() {
    step1.classList.remove('d-none'); step2.classList.add('d-none'); step3.classList.add('d-none');
    uploadBtn.classList.remove('d-none'); uploadBtn.disabled = true; moreBtn.classList.add('d-none');
    cancelBtn.textContent = 'Cancel'; fileInput.value = '';
    progress.style.width = '10%';
  }

  document.getElementById('importModal').addEventListener('hidden.bs.modal', resetModal);
  moreBtn.addEventListener('click', resetModal);

  uploadBtn.addEventListener('click', function() {
    if (!fileInput.files.length) return;
    step1.classList.add('d-none'); step2.classList.remove('d-none');
    uploadBtn.classList.add('d-none');

    const fd = new FormData();
    fd.append('csv_file', fileInput.files[0]);

    progress.style.width = '30%';
    document.getElementById('importStatusText').textContent = 'Processing records...';

    let prog = 30;
    const iv = setInterval(() => { if (prog < 85) { prog += 5; progress.style.width = prog+'%'; } }, 300);

    fetch('/admin/import-students.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        clearInterval(iv);
        progress.style.width = '100%';
        setTimeout(() => {
          step2.classList.add('d-none'); step3.classList.remove('d-none');
          moreBtn.classList.remove('d-none'); cancelBtn.textContent = 'Close';
          document.getElementById('res-added').textContent = data.added || 0;
          document.getElementById('res-skipped').textContent = data.skipped || 0;
          document.getElementById('res-failed').textContent = data.failed || 0;
          if (data.errors && data.errors.length) {
            document.getElementById('res-errors').classList.remove('d-none');
            document.getElementById('res-errors-list').innerHTML = data.errors.map(e => '<div class="text-danger">'+e+'</div>').join('');
          } else {
            document.getElementById('res-errors').classList.add('d-none');
          }
        }, 500);
      })
      .catch(err => {
        clearInterval(iv);
        step2.classList.add('d-none'); step1.classList.remove('d-none');
        uploadBtn.classList.remove('d-none');
        alert('Import failed: ' + err.message);
      });
  });
})();
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>