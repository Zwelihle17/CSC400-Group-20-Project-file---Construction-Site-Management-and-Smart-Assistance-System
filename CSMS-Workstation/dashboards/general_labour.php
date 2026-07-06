<?php
/* ================================================================
   dashboards/general_labour.php  —  Worker Dashboard v3
   ================================================================ */
$pageTitle = 'Worker Portal';
$navItems  = [
    ['id'=>'overview',   'label'=>'Overview'],
    ['id'=>'schedule',   'label'=>'My Schedule'],
    ['id'=>'complaint',  'label'=>'Log Complaint'],
    ['id'=>'requests',   'label'=>'My Requests'],
    ['id'=>'attendance', 'label'=>'My Attendance'],
];
require_once '../php/layout.php';

/* ── POST handler ── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='submit_complaint') {
    $text = trim($_POST['complaint_text']);
    if ($text) {
        $pdo->prepare("INSERT INTO complaints(worker_id,complaint_text)VALUES(?,?)")->execute([$uid,$text]);
        foreach($pdo->query("SELECT id FROM users WHERE role IN('foreman','hr') AND is_active=1")->fetchAll() as $r) {
            $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
                ->execute([$uid,$r['id'],"Worker Complaint / Request","Worker $uName submitted: $text"]);
        }
        logAction($uid,"GL_SUBMIT_COMPLAINT","Text: ".mb_strimwidth($text,0,80));
        header("Location: general_labour.php"); exit();
    }
}

/* ── Data ── */
$today = date('Y-m-d');

$upcoming = $pdo->prepare("
    SELECT s.*,u.full_name AS assigned_by
    FROM schedules s JOIN users u ON s.created_by=u.id
    WHERE s.worker_id=? AND s.shift_date>=CURDATE()
    ORDER BY s.shift_date ASC LIMIT 15
");
$upcoming->execute([$uid]); $upcoming=$upcoming->fetchAll();

$past = $pdo->prepare("
    SELECT s.*,u.full_name AS assigned_by
    FROM schedules s JOIN users u ON s.created_by=u.id
    WHERE s.worker_id=? AND s.shift_date<CURDATE()
    ORDER BY s.shift_date DESC LIMIT 10
");
$past->execute([$uid]); $past=$past->fetchAll();

$myComplaints = $pdo->prepare("SELECT * FROM complaints WHERE worker_id=? ORDER BY created_at DESC LIMIT 20");
$myComplaints->execute([$uid]); $myComplaints=$myComplaints->fetchAll();

$myAtt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id=? ORDER BY date DESC LIMIT 20");
$myAtt->execute([$uid]); $myAtt=$myAtt->fetchAll();

/* Team on my site for quick message */
$myTeam = $pdo->prepare("SELECT id,full_name,role FROM users WHERE site_assigned=? AND role IN('foreman','hr','safety_officer') AND is_active=1 ORDER BY role");
$myTeam->execute([$uSite]); $myTeam=$myTeam->fetchAll();

$upcomingCount = count($upcoming);
$openComp      = count(array_filter($myComplaints,fn($c)=>$c['status']==='open'));
$presentDays   = count(array_filter($myAtt,fn($a)=>$a['status']==='present'));

$attBadge  = ['present'=>'b-green','absent'=>'b-red','late'=>'b-orange'];
$compBadge = ['open'=>'b-red','in_progress'=>'b-amber','resolved'=>'b-green'];
$roleLbl   = ['foreman'=>'Foreman','hr'=>'Human Resources','safety_officer'=>'Safety Officer'];
?>

<!-- OVERVIEW -->
<section class="page-section" id="overview">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Worker Overview</div>
    <div class="section-sub">Welcome, <?= htmlspecialchars($uName) ?> — <?= date('D, d M Y') ?></div>
  </div>
  <img src="../images/background.jpg" class="img-banner" alt="Construction site">
  <div class="stats-grid">
    <div class="stat">
      <div class="stat-label">Upcoming Shifts</div>
      <div class="stat-value"><?= $upcomingCount ?></div>
      <div class="stat-icon"><?= icon('circle-dashed-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Days Present</div>
      <div class="stat-value"><?= $presentDays ?></div>
      <div class="stat-icon"><?= icon('report-analytics','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Open Complaints</div>
      <div class="stat-value"><?= $openComp ?></div>
      <div class="stat-icon"><?= icon('messages','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Messages</div>
      <div class="stat-value"><?= $unread ?></div>
      <div class="stat-icon"><?= icon('send-2','36') ?></div>
    </div>
  </div>

  <!-- Next shift highlight -->
  <?php if ($upcoming): $nxt=$upcoming[0]; ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Next Upcoming Shift</span></div>
    <div class="card-body">
      <div style="display:flex;gap:30px;flex-wrap:wrap;align-items:center;">
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Date</div>
          <div style="font-family:'Bebas Neue',sans-serif;font-size:26px;letter-spacing:1px;color:var(--amber)"><?= date('D, d M Y',strtotime($nxt['shift_date'])) ?></div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Time</div>
          <div style="font-family:'Bebas Neue',sans-serif;font-size:26px;letter-spacing:1px;"><?= date('H:i',strtotime($nxt['shift_start'])) ?> – <?= date('H:i',strtotime($nxt['shift_end'])) ?></div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Site</div>
          <div style="font-size:16px;font-weight:600;">📍 <?= htmlspecialchars($nxt['site_name']) ?></div>
        </div>
        <?php if($nxt['task_description']): ?>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Task</div>
          <div style="font-size:13px;color:var(--text2);">🔧 <?= htmlspecialchars($nxt['task_description']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- My team quick contact -->
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('user-search','15') ?> My Team at <?= htmlspecialchars($uSite) ?></span></div>
    <div class="card-body">
      <?php if(empty($myTeam)): ?>
      <p style="font-size:13px;color:var(--muted);">No team members found at your site.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach($myTeam as $t): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--card2);border-radius:3px;border:1px solid var(--border);">
          <div>
            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($t['full_name']) ?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= $roleLbl[$t['role']] ?? $t['role'] ?></div>
          </div>
          <button class="btn btn-outline btn-sm" onclick="openCompose(<?= $t['id'] ?>)"><?= icon('send-2','13') ?> Message</button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</section>

<!-- MY SCHEDULE -->
<section class="page-section" id="schedule">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">My Schedule</div>
    <div class="section-sub">Upcoming and past shifts assigned to you</div>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Upcoming Shifts (<?= $upcomingCount ?>)</span></div>
    <div class="card-body">
      <?php if(empty($upcoming)): ?>
      <div style="text-align:center;color:var(--muted);padding:20px 0;font-size:13px;">No upcoming shifts assigned yet.</div>
      <?php else: ?>
      <?php foreach($upcoming as $s): ?>
      <div class="shift-card">
        <div class="shift-date"><?= date('D, d M Y',strtotime($s['shift_date'])) ?></div>
        <div class="shift-row">
          <span>🕒 <span class="shift-val"><?= date('H:i',strtotime($s['shift_start'])) ?> – <?= date('H:i',strtotime($s['shift_end'])) ?></span></span>
          <span>📍 <span class="shift-val"><?= htmlspecialchars($s['site_name']) ?></span></span>
          <?php if($s['task_description']): ?><span>🔧 <?= htmlspecialchars($s['task_description']) ?></span><?php endif; ?>
          <span style="color:var(--muted);">Assigned by: <?= htmlspecialchars($s['assigned_by']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> Past Shifts</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Site</th><th>Time</th><th>Task</th></tr></thead>
        <tbody>
          <?php foreach($past as $s): ?>
          <tr>
            <td><?= date('d M Y',strtotime($s['shift_date'])) ?></td>
            <td><?= htmlspecialchars($s['site_name']) ?></td>
            <td><?= date('H:i',strtotime($s['shift_start'])) ?> – <?= date('H:i',strtotime($s['shift_end'])) ?></td>
            <td><?= htmlspecialchars($s['task_description']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($past)): ?><tr><td colspan="4" class="empty">No past shifts.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- LOG COMPLAINT -->
<section class="page-section" id="complaint">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Log Complaint / Request</div>
    <div class="section-sub">Your Foreman and HR will be notified automatically</div>
  </div>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('messages','15') ?> Submit Complaint or Request</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="submit_complaint">
          <div class="form-group">
            <label class="f-label">Describe Your Complaint or Request</label>
            <textarea class="f-textarea" name="complaint_text" rows="7"
              placeholder="Examples:&#10;• Missing or damaged PPE equipment&#10;• Unpaid overtime hours&#10;• Unsafe working conditions&#10;• Request for schedule change&#10;• Workplace issue or concern"
              required></textarea>
          </div>
          <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Submit Complaint</button>
          <p style="font-size:11px;color:var(--muted);margin-top:10px;">Your Foreman and HR will be notified and will respond.</p>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('user-search','15') ?> Quick Contact</span></div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">Send a private direct message to a specific team member.</p>
        <button class="btn btn-amber" onclick="openCompose()" style="width:100%;margin-bottom:20px;"><?= icon('send-2','14') ?> Compose New Message</button>
        <div style="border-top:1px solid var(--border);padding-top:16px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;">Your Site Team</div>
          <?php foreach($myTeam as $t): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
            <div>
              <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars($t['full_name']) ?></div>
              <div style="font-size:11px;color:var(--muted);"><?= $roleLbl[$t['role']] ?? $t['role'] ?></div>
            </div>
            <button class="btn btn-outline btn-sm" onclick="openCompose(<?= $t['id'] ?>)"><?= icon('send-2','12') ?></button>
          </div>
          <?php endforeach; ?>
          <?php if(empty($myTeam)): ?><p style="font-size:12px;color:var(--muted);">No team members found.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</section>

<!-- MY REQUESTS -->
<section class="page-section" id="requests">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">My Requests &amp; Complaints</div>
    <div class="section-sub">Track the status of all your submitted complaints and requests</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> All Submissions</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Complaint / Request</th><th>Status</th><th>Response</th></tr></thead>
        <tbody>
          <?php foreach($myComplaints as $c): ?>
          <tr>
            <td><?= date('d M Y',strtotime($c['created_at'])) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($c['complaint_text'],0,100,'…')) ?></td>
            <td><span class="badge <?= $compBadge[$c['status']] ?>"><?= str_replace('_',' ',$c['status']) ?></span></td>
            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['response']??'Awaiting response…') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($myComplaints)): ?><tr><td colspan="4" class="empty">No complaints submitted yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- MY ATTENDANCE -->
<section class="page-section" id="attendance">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">My Attendance</div>
    <div class="section-sub">Your personal attendance record</div>
  </div>
  <img src="../images/dashboard_background.jpg" class="img-banner" alt="Helmets">
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Attendance Record</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach($myAtt as $a): ?>
          <tr>
            <td><?= $a['date'] ?></td>
            <td><span class="badge <?= $attBadge[$a['status']] ?>"><?= $a['status'] ?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($a['notes']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($myAtt)): ?><tr><td colspan="3" class="empty">No attendance records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<?php require_once '../php/layout_end.php'; ?>
