<?php
/* ================================================================
   dashboards/hr.php  —  Human Resources Dashboard v3
   ================================================================ */
$pageTitle = 'Human Resources';
$navItems  = [
    ['id'=>'overview',   'label'=>'Overview'],
    ['id'=>'employees',  'label'=>'Employee Records'],
    ['id'=>'assign',     'label'=>'Assign Workers'],
    ['id'=>'attendance', 'label'=>'Attendance Logs'],
    ['id'=>'reports',    'label'=>'Workforce Reports'],
];
require_once '../php/layout.php';

/* ── POST handlers ── */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_employee') {
        $eid  = trim($_POST['new_emp_id']);
        $name = trim($_POST['new_name']);
        $role = $_POST['new_role'];
        $site = trim($_POST['new_site']);
        $pw   = password_hash('password123', PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO users(employee_id,full_name,role,password,site_assigned)VALUES(?,?,?,?,?)")
                ->execute([$eid,$name,$role,$pw,$site]);
            logAction($uid,"HR_ADD_EMPLOYEE","Added $role: $name ($eid)");
            $flash = "ok:Employee added. Default password: password123";
        } catch(Exception $e) { $flash = "err:Employee ID '$eid' already exists."; }
    }

    if ($act === 'assign_site') {
        $wid  = intval($_POST['worker_id']);
        $site = trim($_POST['site_name']);
        $pdo->prepare("UPDATE users SET site_assigned=? WHERE id=?")->execute([$site,$wid]);
        logAction($uid,"HR_ASSIGN_SITE","Worker ID $wid assigned to $site");
        $flash = "ok:Worker site updated.";
    }

    if ($act === 'assign_shift') {
        $wid   = intval($_POST['shift_worker']);
        $site  = trim($_POST['shift_site']);
        $dt    = $_POST['shift_date'];
        $start = $_POST['shift_start'];
        $end   = $_POST['shift_end'];
        $task  = trim($_POST['shift_task']);
        $pdo->prepare("INSERT INTO schedules(worker_id,site_name,shift_date,shift_start,shift_end,task_description,created_by)VALUES(?,?,?,?,?,?,?)")
            ->execute([$wid,$site,$dt,$start,$end,$task,$uid]);
        $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
            ->execute([$uid,$wid,"New Shift Assigned","You have been assigned a shift at $site on $dt from $start to $end. Task: $task"]);
        logAction($uid,"HR_ASSIGN_SHIFT","Worker $wid: $site on $dt");
        $flash = "ok:Shift assigned and worker notified.";
    }

    if ($act === 'record_att') {
        $eid    = intval($_POST['att_emp']);
        $dt     = $_POST['att_date'] ?: date('Y-m-d');
        $status = $_POST['att_status'];
        $note   = trim($_POST['att_note']);
        $chk    = $pdo->prepare("SELECT id FROM attendance WHERE employee_id=? AND date=?");
        $chk->execute([$eid,$dt]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE attendance SET status=?,notes=? WHERE employee_id=? AND date=?")->execute([$status,$note,$eid,$dt]);
        } else {
            $pdo->prepare("INSERT INTO attendance(employee_id,date,status,recorded_by,notes)VALUES(?,?,?,?,?)")->execute([$eid,$dt,$status,$uid,$note]);
        }
        logAction($uid,"HR_RECORD_ATT","Employee $eid: $status on $dt");
        header("Location: hr.php"); exit();
    }

    if ($act === 'resolve_complaint') {
        $cid    = intval($_POST['cid']);
        $worker = intval($_POST['cworker']);
        $resp   = trim($_POST['cresp']);
        $pdo->prepare("UPDATE complaints SET status='resolved',response=? WHERE id=?")->execute([$resp,$cid]);
        $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
            ->execute([$uid,$worker,"Complaint Resolved","Your complaint has been resolved. Response: $resp"]);
        logAction($uid,"HR_RESOLVE_COMPLAINT","Complaint $cid resolved");
        header("Location: hr.php"); exit();
    }
}

/* ── Fetch data ── */
$today        = date('Y-m-d');
$employees    = $pdo->query("SELECT * FROM users WHERE is_active=1 ORDER BY role,full_name")->fetchAll();
$totalEmp     = count($employees);
$pStmt        = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date=? AND status='present'"); $pStmt->execute([$today]); $presentToday=$pStmt->fetchColumn();
$openComp     = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetchColumn();
$schedCount   = $pdo->query("SELECT COUNT(*) FROM schedules WHERE shift_date>=CURDATE()")->fetchColumn();

$attLogs = $pdo->query("SELECT a.*,u.full_name,u.employee_id,u.role FROM attendance a JOIN users u ON a.employee_id=u.id ORDER BY a.date DESC,a.created_at DESC LIMIT 40")->fetchAll();

$complaints = $pdo->query("SELECT c.*,u.full_name AS wname,u.employee_id AS weid,u.id AS worker_id FROM complaints c JOIN users u ON c.worker_id=u.id ORDER BY c.created_at DESC LIMIT 30")->fetchAll();

$byRole = $pdo->query("SELECT role,COUNT(*) AS cnt FROM users WHERE is_active=1 GROUP BY role")->fetchAll();
$attSum = $pdo->query("SELECT status,COUNT(*) AS cnt FROM attendance GROUP BY status")->fetchAll();

$roleBadge = ['admin'=>'b-purple','hr'=>'b-blue','site_manager'=>'b-amber','foreman'=>'b-orange','safety_officer'=>'b-red','general_labour'=>'b-green'];
$attBadge  = ['present'=>'b-green','absent'=>'b-red','late'=>'b-orange'];
$compBadge = ['open'=>'b-red','in_progress'=>'b-amber','resolved'=>'b-green'];
[$fType,$fMsg] = $flash ? explode(':',$flash,2) : ['',''];
?>

<!-- OVERVIEW -->
<section class="page-section" id="overview">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">HR Overview</div>
    <div class="section-sub">Human Resources Dashboard — <?= date('d M Y') ?></div>
  </div>
  <img src="../images/dashboard_background.jpg" class="img-banner" alt="Team">
  <div class="stats-grid">
    <div class="stat">
      <div class="stat-label">Total Employees</div>
      <div class="stat-value"><?= $totalEmp ?></div>
      <div class="stat-icon"><?= icon('user-square-rounded','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Present Today</div>
      <div class="stat-value"><?= $presentToday ?></div>
      <div class="stat-icon"><?= icon('circle-dashed-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Active Schedules</div>
      <div class="stat-value"><?= $schedCount ?></div>
      <div class="stat-icon"><?= icon('library','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Open Complaints</div>
      <div class="stat-value"><?= $openComp ?></div>
      <div class="stat-icon"><?= icon('messages','36') ?></div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('messages','15') ?> Recent Worker Complaints</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Worker</th><th>Complaint</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach(array_slice($complaints,0,5) as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['wname']) ?> <span style="color:var(--muted);font-size:10px;">(<?= $c['weid'] ?>)</span></td>
            <td><?= htmlspecialchars(mb_strimwidth($c['complaint_text'],0,80,'…')) ?></td>
            <td><span class="badge <?= $compBadge[$c['status']] ?>"><?= str_replace('_',' ',$c['status']) ?></span></td>
            <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($complaints)): ?><tr><td colspan="4" class="empty">No complaints.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- EMPLOYEE RECORDS -->
<section class="page-section" id="employees">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Employee Records</div>
    <div class="section-sub">View and manage all employees</div>
  </div>
  <?php if($fMsg): ?><div class="alert alert-<?= $fType==='ok'?'ok':'err' ?>"><?= htmlspecialchars($fMsg) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><span class="card-title"><?= icon('user-plus','15') ?> Add New Employee</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_employee">
        <div class="form-row">
          <div class="form-group"><label class="f-label">Employee ID</label><input class="f-input" name="new_emp_id" placeholder="e.g. GL003" required></div>
          <div class="form-group"><label class="f-label">Full Name</label><input class="f-input" name="new_name" placeholder="Full name" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="f-label">Role</label>
            <select class="f-select" name="new_role">
              <option value="hr">Human Resources</option>
              <option value="site_manager">Site Manager</option>
              <option value="foreman">Foreman</option>
              <option value="safety_officer">Safety Officer</option>
              <option value="general_labour" selected>General Labour</option>
            </select>
          </div>
          <div class="form-group"><label class="f-label">Site Assigned</label><input class="f-input" name="new_site" placeholder="e.g. Site A – Mbabane"></div>
        </div>
        <button type="submit" class="btn btn-amber"><?= icon('user-plus','14') ?> Add Employee</button>
        <span style="font-size:11px;color:var(--muted);margin-left:12px;">Default password: <strong>password123</strong></span>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('user-search','15') ?> All Employees (<?= $totalEmp ?>)</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Site</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($employees as $e): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($e['employee_id']) ?></td>
            <td><?= htmlspecialchars($e['full_name']) ?></td>
            <td><span class="badge <?= $roleBadge[$e['role']] ?? 'b-gray' ?>"><?= str_replace('_',' ',$e['role']) ?></span></td>
            <td><?= htmlspecialchars($e['site_assigned'] ?? '—') ?></td>
            <td><button class="btn btn-outline btn-sm" onclick="openCompose(<?= $e['id'] ?>)"><?= icon('send-2','13') ?> Message</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ASSIGN WORKERS -->
<section class="page-section" id="assign">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Assign Workers</div>
    <div class="section-sub">Update site assignments and create shift schedules</div>
  </div>
  <?php if($fMsg && $act==='assign_site'||$act==='assign_shift'): ?><div class="alert alert-<?= $fType==='ok'?'ok':'err' ?>"><?= htmlspecialchars($fMsg) ?></div><?php endif; ?>

  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('user-plus','15') ?> Update Site Assignment</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="assign_site">
          <div class="form-group"><label class="f-label">Employee</label>
            <select class="f-select" name="worker_id" required>
              <option value="">— Select —</option>
              <?php foreach($employees as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="f-label">New Site</label><input class="f-input" name="site_name" placeholder="Site name" required></div>
          <button type="submit" class="btn btn-amber"><?= icon('circle-dashed-check','14') ?> Update Site</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('square-rounded-plus','15') ?> Create Shift Schedule</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="assign_shift">
          <div class="form-group"><label class="f-label">Worker</label>
            <select class="f-select" name="shift_worker" required>
              <option value="">— Select Worker —</option>
              <?php foreach($employees as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="f-label">Site</label><input class="f-input" name="shift_site" required></div>
          <div class="form-row">
            <div class="form-group"><label class="f-label">Date</label><input class="f-input" type="date" name="shift_date" required></div>
            <div class="form-group"><label class="f-label">Start</label><input class="f-input" type="time" name="shift_start" value="07:00"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="f-label">End</label><input class="f-input" type="time" name="shift_end" value="17:00"></div>
            <div class="form-group"><label class="f-label">Task</label><input class="f-input" name="shift_task" placeholder="e.g. Foundation work"></div>
          </div>
          <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Assign &amp; Notify</button>
        </form>
      </div>
    </div>
  </div>
</div>
</section>

<!-- ATTENDANCE -->
<section class="page-section" id="attendance">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Attendance Logs</div>
    <div class="section-sub">Record and view daily attendance across all sites</div>
  </div>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Record Attendance — <?= date('d M Y') ?></span></div>
    <div class="card-body">
      <form method="POST" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="action" value="record_att">
        <div class="form-group" style="flex:2;min-width:180px;margin:0;"><label class="f-label">Employee</label>
          <select class="f-select" name="att_emp" required>
            <option value="">— Select —</option>
            <?php foreach($employees as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['employee_id'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="min-width:145px;margin:0;"><label class="f-label">Date</label><input class="f-input" type="date" name="att_date" value="<?= $today ?>"></div>
        <div class="form-group" style="min-width:125px;margin:0;"><label class="f-label">Status</label>
          <select class="f-select" name="att_status">
            <option value="present">Present</option>
            <option value="absent">Absent</option>
            <option value="late">Late</option>
          </select>
        </div>
        <div class="form-group" style="flex:2;min-width:155px;margin:0;"><label class="f-label">Notes</label><input class="f-input" name="att_note" placeholder="Optional"></div>
        <button type="submit" class="btn btn-amber" style="margin-bottom:0;"><?= icon('circle-dashed-check','14') ?> Record</button>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> Recent Attendance Log</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Employee</th><th>Role</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach($attLogs as $a): ?>
          <tr>
            <td><?= $a['date'] ?></td>
            <td><?= htmlspecialchars($a['full_name']) ?> <span style="color:var(--muted);font-size:10px;">(<?= $a['employee_id'] ?>)</span></td>
            <td><?= str_replace('_',' ',$a['role']) ?></td>
            <td><span class="badge <?= $attBadge[$a['status']] ?>"><?= $a['status'] ?></span></td>
            <td><?= htmlspecialchars($a['notes'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($attLogs)): ?><tr><td colspan="5" class="empty">No records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- WORKFORCE REPORTS -->
<section class="page-section" id="reports">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Workforce Reports</div>
    <div class="section-sub">Summary statistics and complaint management</div>
  </div>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> Employees by Role</span></div>
      <div class="card-body-flush">
        <table class="tbl">
          <thead><tr><th>Role</th><th>Count</th></tr></thead>
          <tbody>
            <?php foreach($byRole as $r): ?>
            <tr><td><span class="badge <?= $roleBadge[$r['role']] ?? 'b-gray' ?>"><?= str_replace('_',' ',$r['role']) ?></span></td><td><?= $r['cnt'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Attendance Summary</span></div>
      <div class="card-body-flush">
        <table class="tbl">
          <thead><tr><th>Status</th><th>Count</th></tr></thead>
          <tbody>
            <?php foreach($attSum as $a): ?>
            <tr><td><span class="badge <?= $attBadge[$a['status']] ?>"><?= $a['status'] ?></span></td><td><?= $a['cnt'] ?></td></tr>
            <?php endforeach; ?>
            <?php if(empty($attSum)): ?><tr><td colspan="2" class="empty">No data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('messages','15') ?> All Worker Complaints</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Worker</th><th>Complaint</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($complaints as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['wname']) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($c['complaint_text'],0,80,'…')) ?></td>
            <td><span class="badge <?= $compBadge[$c['status']] ?>"><?= str_replace('_',' ',$c['status']) ?></span></td>
            <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
            <td>
              <?php if($c['status']!=='resolved'): ?>
              <form method="POST" style="display:inline-flex;gap:5px;align-items:center;">
                <input type="hidden" name="action" value="resolve_complaint">
                <input type="hidden" name="cid" value="<?= $c['id'] ?>">
                <input type="hidden" name="cworker" value="<?= $c['worker_id'] ?>">
                <input class="f-input" name="cresp" placeholder="Response" style="padding:4px 8px;font-size:11px;width:130px;">
                <button name="cstatus" value="resolved" class="btn btn-green btn-sm"><?= icon('circle-dashed-check','12') ?> Resolve</button>
              </form>
              <?php else: ?><span style="font-size:11px;color:var(--muted);">Resolved</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($complaints)): ?><tr><td colspan="5" class="empty">No complaints.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<?php require_once '../php/layout_end.php'; ?>
