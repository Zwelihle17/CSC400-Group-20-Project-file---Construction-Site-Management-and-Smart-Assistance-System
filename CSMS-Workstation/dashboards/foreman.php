<?php
/* ================================================================
   dashboards/foreman.php  —  Foreman Dashboard v3
   ================================================================ */
$pageTitle = 'Foreman';
$navItems  = [
    ['id'=>'overview',   'label'=>'Overview'],
    ['id'=>'report',     'label'=>'Submit Daily Report'],
    ['id'=>'attendance', 'label'=>'Attendance Log'],
    ['id'=>'tasks',      'label'=>'Assign Tasks'],
    ['id'=>'history',    'label'=>'Report History'],
];
require_once '../php/layout.php';

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'submit_report') {
        $site      = trim($_POST['site_name']);
        $dt        = $_POST['report_date'];
        $progress  = trim($_POST['progress_update']);
        $resources = trim($_POST['resource_usage']);
        $equipment = trim($_POST['equipment_needs']);
        $pdo->prepare("INSERT INTO daily_reports(foreman_id,site_name,report_date,progress_update,resource_usage,equipment_needs)VALUES(?,?,?,?,?,?)")
            ->execute([$uid,$site,$dt,$progress,$resources,$equipment]);
        foreach($pdo->query("SELECT id FROM users WHERE role='site_manager' AND is_active=1")->fetchAll() as $m) {
            $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
                ->execute([$uid,$m['id'],"New Daily Report — $site","Foreman $uName submitted a daily report for $site on $dt. Progress: $progress"]);
        }
        logAction($uid,"FOREMAN_SUBMIT_REPORT","Site: $site, Date: $dt");
        header("Location: foreman.php"); exit();
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
        logAction($uid,"FOREMAN_RECORD_ATT","Employee $eid: $status on $dt");
        header("Location: foreman.php"); exit();
    }

    if ($act === 'assign_task') {
        $wid  = intval($_POST['task_worker']);
        $task = trim($_POST['task_desc']);
        $dt   = $_POST['task_date'];
        $site = trim($_POST['task_site']);
        $pdo->prepare("INSERT INTO schedules(worker_id,site_name,shift_date,shift_start,shift_end,task_description,created_by)VALUES(?,?,?,'07:00','17:00',?,?)")
            ->execute([$wid,$site,$dt,$task,$uid]);
        $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
            ->execute([$uid,$wid,"Task Assigned by Foreman","You have been assigned a task at $site on $dt: $task"]);
        logAction($uid,"FOREMAN_ASSIGN_TASK","Worker $wid: $task at $site on $dt");
        header("Location: foreman.php"); exit();
    }
}

/* ── Data ── */
$today = date('Y-m-d');

$myRepsCount = $pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE foreman_id=?"); $myRepsCount->execute([$uid]); $myRepsCount=$myRepsCount->fetchColumn();
$approved    = $pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE foreman_id=? AND status='approved'"); $approved->execute([$uid]); $approved=$approved->fetchColumn();
$pending     = $pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE foreman_id=? AND status='pending'"); $pending->execute([$uid]); $pending=$pending->fetchColumn();

$myWorkers = $pdo->prepare("SELECT * FROM users WHERE site_assigned=? AND role='general_labour' AND is_active=1 ORDER BY full_name");
$myWorkers->execute([$uSite]); $myWorkers=$myWorkers->fetchAll();

$siteTeam = $pdo->prepare("SELECT * FROM users WHERE site_assigned=? AND is_active=1 ORDER BY full_name");
$siteTeam->execute([$uSite]); $siteTeam=$siteTeam->fetchAll();

$todayAtt = $pdo->prepare("SELECT a.*,u.full_name,u.employee_id FROM attendance a JOIN users u ON a.employee_id=u.id WHERE u.site_assigned=? AND a.date=?");
$todayAtt->execute([$uSite,$today]); $todayAtt=$todayAtt->fetchAll();

$allAtt = $pdo->prepare("SELECT a.*,u.full_name,u.employee_id FROM attendance a JOIN users u ON a.employee_id=u.id WHERE u.site_assigned=? ORDER BY a.date DESC LIMIT 40");
$allAtt->execute([$uSite]); $allAtt=$allAtt->fetchAll();

$history = $pdo->prepare("SELECT * FROM daily_reports WHERE foreman_id=? ORDER BY report_date DESC LIMIT 30");
$history->execute([$uid]); $history=$history->fetchAll();

$attBadge = ['present'=>'b-green','absent'=>'b-red','late'=>'b-orange'];
$appBadge = ['pending'=>'b-amber','approved'=>'b-green','rejected'=>'b-red'];
?>

<!-- OVERVIEW -->
<section class="page-section" id="overview">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Foreman Overview</div>
    <div class="section-sub">Site: <?= htmlspecialchars($uSite) ?> — <?= date('d M Y') ?></div>
  </div>
  <img src="../images/background.jpg" class="img-banner" alt="Site">
  <div class="stats-grid">
    <div class="stat">
      <div class="stat-label">Total Reports</div>
      <div class="stat-value"><?= $myRepsCount ?></div>
      <div class="stat-icon"><?= icon('library','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Approved</div>
      <div class="stat-value"><?= $approved ?></div>
      <div class="stat-icon"><?= icon('circle-dashed-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $pending ?></div>
      <div class="stat-icon"><?= icon('report-analytics','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Site Workers</div>
      <div class="stat-value"><?= count($myWorkers) ?></div>
      <div class="stat-icon"><?= icon('user-square-rounded','36') ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Today's Attendance — <?= $today ?></span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Worker</th><th>ID</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($todayAtt as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['full_name']) ?></td>
            <td><?= $a['employee_id'] ?></td>
            <td><span class="badge <?= $attBadge[$a['status']] ?>"><?= $a['status'] ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($todayAtt)): ?><tr><td colspan="3" class="empty">No attendance recorded today.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- SUBMIT REPORT -->
<section class="page-section" id="report">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Submit Daily Report</div>
    <div class="section-sub">Document today's progress and notify the Site Manager automatically</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('send-2','15') ?> New Daily Report</span></div>
    <div class="card-body">
      <!-- AI Report Generator button -->
      <button class="ai-report-btn" id="aiReportBtn" onclick="generateAIReport()" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9.5 14.5l-1.5 1.5"/><path d="M14.5 14.5l1.5 1.5"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M10 13a2 2 0 0 0 4 0v-3a2 2 0 0 0 -4 0v3"/><path d="M6 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M18 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6 9a9 9 0 0 0 12 0"/></svg>
        Generate AI Draft Report
      </button>
      <div class="ai-report-status" id="aiReportStatus"></div>
      <form method="POST">
        <input type="hidden" name="action" value="submit_report">
        <div class="form-row">
          <div class="form-group"><label class="f-label">Site Name</label><input class="f-input" name="site_name" value="<?= htmlspecialchars($uSite) ?>" required></div>
          <div class="form-group"><label class="f-label">Report Date</label><input class="f-input" type="date" name="report_date" value="<?= $today ?>" required></div>
        </div>
        <div class="form-group"><label class="f-label">Progress Update</label><textarea class="f-textarea" name="progress_update" placeholder="Describe the work completed today…" required></textarea></div>
        <div class="form-group"><label class="f-label">Resource Usage</label><textarea class="f-textarea" name="resource_usage" placeholder="Materials used, quantities, etc." required></textarea></div>
        <div class="form-group"><label class="f-label">Equipment Needs</label><textarea class="f-textarea" name="equipment_needs" placeholder="Equipment required or issues encountered…"></textarea></div>
        <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Submit &amp; Notify Manager</button>
      </form>
    </div>
  </div>
</div>
</section>

<!-- ATTENDANCE LOG -->
<section class="page-section" id="attendance">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Attendance Log</div>
    <div class="section-sub">Record and view attendance for workers on your site</div>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Record Attendance</span></div>
    <div class="card-body">
      <form method="POST" style="display:flex;gap:13px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="action" value="record_att">
        <div class="form-group" style="flex:2;min-width:180px;margin:0;"><label class="f-label">Worker</label>
          <select class="f-select" name="att_emp" required>
            <option value="">— Select —</option>
            <?php foreach($siteTeam as $w): ?>
            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['full_name']) ?> (<?= $w['employee_id'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="min-width:145px;margin:0;"><label class="f-label">Date</label><input class="f-input" type="date" name="att_date" value="<?= $today ?>"></div>
        <div class="form-group" style="min-width:120px;margin:0;"><label class="f-label">Status</label>
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
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> Site Attendance History</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Worker</th><th>ID</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach($allAtt as $a): ?>
          <tr>
            <td><?= $a['date'] ?></td>
            <td><?= htmlspecialchars($a['full_name']) ?></td>
            <td><?= $a['employee_id'] ?></td>
            <td><span class="badge <?= $attBadge[$a['status']] ?>"><?= $a['status'] ?></span></td>
            <td><?= htmlspecialchars($a['notes']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($allAtt)): ?><tr><td colspan="5" class="empty">No attendance records.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ASSIGN TASKS -->
<section class="page-section" id="tasks">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Assign Tasks</div>
    <div class="section-sub">Assign specific tasks to workers — they will be notified immediately</div>
  </div>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('square-rounded-plus','15') ?> New Task Assignment</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="assign_task">
          <div class="form-group"><label class="f-label">Worker</label>
            <select class="f-select" name="task_worker" required>
              <option value="">— Select Worker —</option>
              <?php foreach($myWorkers as $w): ?>
              <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['full_name']) ?> (<?= $w['employee_id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="f-label">Site</label><input class="f-input" name="task_site" value="<?= htmlspecialchars($uSite) ?>" required></div>
          <div class="form-group"><label class="f-label">Task Date</label><input class="f-input" type="date" name="task_date" value="<?= $today ?>" required></div>
          <div class="form-group"><label class="f-label">Task Description</label><textarea class="f-textarea" name="task_desc" placeholder="Describe the task in detail…" required></textarea></div>
          <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Assign &amp; Notify Worker</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('user-square-rounded','15') ?> My Site Workers</span></div>
      <div class="card-body-flush">
        <table class="tbl">
          <thead><tr><th>Name</th><th>ID</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($myWorkers as $w): ?>
            <tr>
              <td><?= htmlspecialchars($w['full_name']) ?></td>
              <td><?= $w['employee_id'] ?></td>
              <td><button class="btn btn-outline btn-sm" onclick="openCompose(<?= $w['id'] ?>)"><?= icon('send-2','13') ?></button></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($myWorkers)): ?><tr><td colspan="3" class="empty">No workers on this site.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</section>

<!-- REPORT HISTORY -->
<section class="page-section" id="history">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Report History</div>
    <div class="section-sub">All submitted daily reports and their approval status</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> My Submitted Reports</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Site</th><th>Progress Summary</th><th>Status</th><th>Manager Comment</th></tr></thead>
        <tbody>
          <?php foreach($history as $r): ?>
          <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= htmlspecialchars($r['site_name']) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($r['progress_update'],0,80,'…')) ?></td>
            <td><span class="badge <?= $appBadge[$r['status']] ?>"><?= $r['status'] ?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($r['manager_comment']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($history)): ?><tr><td colspan="5" class="empty">No reports submitted yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<?php require_once '../php/layout_end.php'; ?>
