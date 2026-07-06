<?php
/* ================================================================
   dashboards/admin.php  —  ADMINISTRATOR Dashboard
   Full system access: Users, All Reports, All Messages,
   System Logs, Broadcast, Site Overview
   ================================================================ */

$pageTitle = 'Admin Control Panel';
$navItems  = [
    ['id'=>'overview',  'label'=>'System Overview'],
    ['id'=>'users',     'label'=>'Manage Users'],
    ['id'=>'reports',   'label'=>'All Reports'],
    ['id'=>'safety',    'label'=>'Safety Reports'],
    ['id'=>'orders',    'label'=>'Material Orders'],
    ['id'=>'logs',      'label'=>'System Logs'],
    ['id'=>'broadcast', 'label'=>'Broadcast Message'],
];

require_once '../php/layout.php';
requireRole(['admin']);

/* ── Handle POST actions ── */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    /* Add user */
    if ($act === 'add_user') {
        $eid  = trim($_POST['emp_id']);
        $name = trim($_POST['emp_name']);
        $role = $_POST['emp_role'];
        $site = trim($_POST['emp_site']);
        $pw   = password_hash(trim($_POST['emp_pw']) ?: 'password123', PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO users(employee_id,full_name,role,password,site_assigned)VALUES(?,?,?,?,?)")
                ->execute([$eid,$name,$role,$pw,$site]);
            logAction($uid,"ADMIN_ADD_USER","Added $role: $name ($eid)");
            $flash = "ok:User $name ($eid) added successfully.";
        } catch(Exception $e) { $flash = "err:Employee ID '$eid' already exists."; }
    }

    /* Toggle user active/inactive */
    if ($act === 'toggle_user') {
        $tid    = intval($_POST['target_id']);
        $active = intval($_POST['active']);
        $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$active,$tid]);
        logAction($uid,"ADMIN_TOGGLE_USER","User ID $tid set active=$active");
        $flash = "ok:User status updated.";
    }

    /* Reset user password */
    if ($act === 'reset_pw') {
        $tid = intval($_POST['target_id']);
        $npw = password_hash('password123', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$npw,$tid]);
        logAction($uid,"ADMIN_RESET_PW","Reset password for user ID $tid");
        $flash = "ok:Password reset to 'password123'.";
    }

    /* Delete user */
    if ($act === 'delete_user') {
        $tid  = intval($_POST['target_id']);
        $uinf = $pdo->prepare("SELECT full_name,employee_id FROM users WHERE id=?");
        $uinf->execute([$tid]); $uinf=$uinf->fetch();
        // Soft delete — deactivate instead of DELETE to preserve FK integrity
        $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$tid]);
        logAction($uid,"ADMIN_DELETE_USER","Deactivated user ID $tid ({$uinf['full_name']})");
        $flash = "ok:User {$uinf['full_name']} deactivated.";
    }

    /* Broadcast message to all / role */
    if ($act === 'broadcast') {
        $subj   = trim($_POST['bc_subject']);
        $body   = trim($_POST['bc_body']);
        $toRole = $_POST['bc_role'];
        $q      = $toRole === 'all'
            ? "SELECT id FROM users WHERE id!=? AND is_active=1"
            : "SELECT id FROM users WHERE role=? AND id!=? AND is_active=1";
        $stmt   = $pdo->prepare($q);
        if ($toRole === 'all') $stmt->execute([$uid]);
        else $stmt->execute([$toRole,$uid]);
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ms = $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)");
        foreach ($recipients as $rid) $ms->execute([$uid,$rid,$subj,$body]);
        logAction($uid,"ADMIN_BROADCAST","To: $toRole | Subject: $subj | Recipients: ".count($recipients));
        $flash = "ok:Broadcast sent to ".count($recipients)." user(s).";
    }

    /* Approve/Reject daily report */
    if ($act === 'update_report') {
        $rid = intval($_POST['rid']); $st = $_POST['rstatus']; $cm = trim($_POST['rcomment']);
        $pdo->prepare("UPDATE daily_reports SET status=?,manager_comment=? WHERE id=?")->execute([$st,$cm,$rid]);
        $r = $pdo->prepare("SELECT foreman_id FROM daily_reports WHERE id=?"); $r->execute([$rid]); $r=$r->fetch();
        if($r) $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")->execute([$uid,$r['foreman_id'],"Report ".ucfirst($st),"Your daily report has been $st by Admin. Comment: $cm"]);
        header("Location: admin.php"); exit();
    }

    /* Approve/Reject material order */
    if ($act === 'update_order') {
        $oid = intval($_POST['oid']); $st = $_POST['ostatus']; $cm = trim($_POST['ocomment']);
        $pdo->prepare("UPDATE material_orders SET status=?,manager_comment=? WHERE id=?")->execute([$st,$cm,$oid]);
        $o = $pdo->prepare("SELECT requested_by FROM material_orders WHERE id=?"); $o->execute([$oid]); $o=$o->fetch();
        if($o) $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")->execute([$uid,$o['requested_by'],"Order ".ucfirst($st),"Your material order has been $st by Admin. Comment: $cm"]);
        header("Location: admin.php"); exit();
    }
}

/* ── Fetch system-wide stats ── */
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$totalMsgs     = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalReports  = $pdo->query("SELECT COUNT(*) FROM daily_reports")->fetchColumn();
$openSafety    = $pdo->query("SELECT COUNT(*) FROM safety_reports WHERE status='open'")->fetchColumn();
$pendingReps   = $pdo->query("SELECT COUNT(*) FROM daily_reports WHERE status='pending'")->fetchColumn();
$openComps     = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetchColumn();

/* ── All users ── */
$allUsersData = $pdo->query("SELECT * FROM users ORDER BY role,full_name")->fetchAll();

/* ── All daily reports ── */
$allReports = $pdo->query("
    SELECT dr.*,u.full_name AS fname FROM daily_reports dr
    JOIN users u ON dr.foreman_id=u.id ORDER BY dr.created_at DESC LIMIT 40
")->fetchAll();

/* ── All safety reports ── */
$safetyReps = $pdo->query("
    SELECT sr.*,u.full_name AS oname FROM safety_reports sr
    JOIN users u ON sr.officer_id=u.id ORDER BY sr.created_at DESC LIMIT 30
")->fetchAll();

/* ── All material orders ── */
$matOrders = $pdo->query("
    SELECT mo.*,u.full_name AS rname,u.role AS rrole FROM material_orders mo
    JOIN users u ON mo.requested_by=u.id ORDER BY mo.created_at DESC LIMIT 30
")->fetchAll();

/* ── System logs ── */
$sysLogs = $pdo->query("
    SELECT sl.*,u.full_name,u.employee_id FROM system_logs sl
    JOIN users u ON sl.user_id=u.id ORDER BY sl.created_at DESC LIMIT 60
")->fetchAll();

/* badge helpers */
$roleBadge = ['admin'=>'b-purple','hr'=>'b-blue','site_manager'=>'b-amber','foreman'=>'b-orange','safety_officer'=>'b-red','general_labour'=>'b-green'];
$appBadge  = ['pending'=>'b-amber','approved'=>'b-green','rejected'=>'b-red'];
$sevBadge  = ['low'=>'b-green','medium'=>'b-amber','high'=>'b-orange','critical'=>'b-red'];
$stBadge   = ['open'=>'b-red','in_progress'=>'b-amber','resolved'=>'b-green'];

[$fType,$fMsg] = $flash ? explode(':',$flash,2) : ['',''];
?>

<!-- ===================================================== OVERVIEW -->
<section class="page-section" id="overview">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">System Overview</div>
    <div class="section-sub">Full system visibility — Administrator access — <?= date('d M Y') ?></div>
  </div>
  <img src="../images/background.jpg" class="img-banner" alt="Site">
  <div class="stats-grid">
    <div class="stat">
      <div class="stat-label">Active Users</div>
      <div class="stat-value"><?= $totalUsers ?></div>
      <div class="stat-icon"><?= icon('user-square-rounded','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Daily Reports</div>
      <div class="stat-value"><?= $totalReports ?></div>
      <div class="stat-icon"><?= icon('library','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Pending Reports</div>
      <div class="stat-value"><?= $pendingReps ?></div>
      <div class="stat-icon"><?= icon('report-analytics','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Open Safety Issues</div>
      <div class="stat-value" style="color:var(--red)"><?= $openSafety ?></div>
      <div class="stat-icon"><?= icon('settings-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Total Messages</div>
      <div class="stat-value"><?= $totalMsgs ?></div>
      <div class="stat-icon"><?= icon('messages','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Open Complaints</div>
      <div class="stat-value"><?= $openComps ?></div>
      <div class="stat-icon"><?= icon('report-analytics','36') ?></div>
    </div>
  </div>

  <!-- Users by role summary -->
  <?php $byRole = $pdo->query("SELECT role,COUNT(*) AS cnt FROM users WHERE is_active=1 GROUP BY role")->fetchAll(); ?>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('user-search','15') ?> Users by Role</span></div>
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
      <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> Recent Logs</span></div>
      <div class="card-body-flush">
        <table class="tbl">
          <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach(array_slice($sysLogs,0,6) as $l): ?>
            <tr>
              <td><?= htmlspecialchars($l['full_name']) ?></td>
              <td style="font-size:11px;"><?= htmlspecialchars($l['action']) ?></td>
              <td style="font-size:11px;color:var(--muted);"><?= date('d M H:i',strtotime($l['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</section>

<!-- ===================================================== MANAGE USERS -->
<section class="page-section" id="users">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Manage Users</div>
    <div class="section-sub">Add, edit, deactivate, and reset passwords for all system users</div>
  </div>
  <?php if($fMsg): ?><div class="alert alert-<?= $fType==='ok'?'ok':'err' ?>"><?= htmlspecialchars($fMsg) ?></div><?php endif; ?>

  <!-- Add user form -->
  <div class="card" style="margin-bottom:22px;">
    <div class="card-head"><span class="card-title"><?= icon('user-plus','15') ?> Add New User</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_user">
        <div class="form-row">
          <div class="form-group"><label class="f-label">Employee ID</label><input class="f-input" name="emp_id" placeholder="e.g. FM002" required></div>
          <div class="form-group"><label class="f-label">Full Name</label><input class="f-input" name="emp_name" placeholder="Full name" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="f-label">Role</label>
            <select class="f-select" name="emp_role" required>
              <option value="admin">Admin</option>
              <option value="hr">Human Resources</option>
              <option value="site_manager">Site Manager</option>
              <option value="foreman">Foreman</option>
              <option value="safety_officer">Safety Officer</option>
              <option value="general_labour" selected>General Labour</option>
            </select>
          </div>
          <div class="form-group"><label class="f-label">Site Assigned</label><input class="f-input" name="emp_site" placeholder="e.g. Site A – Mbabane"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="f-label">Password (leave blank for 'password123')</label><input class="f-input" type="password" name="emp_pw" placeholder="Default: password123"></div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button type="submit" class="btn btn-amber"><?= icon('user-plus','14') ?> Add User</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- All users table -->
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('user-search','15') ?> All Users (<?= count($allUsersData) ?>)</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Site</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($allUsersData as $u): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($u['employee_id']) ?></td>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><span class="badge <?= $roleBadge[$u['role']] ?? 'b-gray' ?>"><?= str_replace('_',' ',$u['role']) ?></span></td>
            <td style="font-size:12px;"><?= htmlspecialchars($u['site_assigned'] ?? '—') ?></td>
            <td><span class="badge <?= $u['is_active']?'b-green':'b-red' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <?php if ($u['id'] != $uid): /* Can't modify yourself */ ?>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <!-- Message -->
                <button class="btn btn-outline btn-sm" onclick="openCompose(<?= $u['id'] ?>)"><?= icon('send-2','12') ?></button>
                <!-- Reset PW -->
                <form method="POST" style="display:inline;" onsubmit="return confirm('Reset password to password123?')">
                  <input type="hidden" name="action" value="reset_pw">
                  <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:rgba(59,130,246,.15);color:#3b82f6;border:1px solid rgba(59,130,246,.3);" title="Reset Password">🔑</button>
                </form>
                <!-- Toggle active -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_user">
                  <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="active" value="<?= $u['is_active']?0:1 ?>">
                  <button type="submit" class="btn btn-sm <?= $u['is_active']?'btn-red':'btn-green' ?>" title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                    <?= $u['is_active'] ? '⏸' : '▶' ?>
                  </button>
                </form>
              </div>
              <?php else: ?>
              <span style="font-size:11px;color:var(--muted);">(you)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ===================================================== ALL DAILY REPORTS -->
<section class="page-section" id="reports">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">All Daily Reports</div>
    <div class="section-sub">View, approve, or reject all foreman daily site reports</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> Daily Reports</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Foreman</th><th>Site</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($allReports as $r): ?>
          <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= htmlspecialchars($r['fname']) ?></td>
            <td><?= htmlspecialchars($r['site_name']) ?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['progress_update']) ?></td>
            <td><span class="badge <?= $appBadge[$r['status']] ?>"><?= $r['status'] ?></span></td>
            <td>
              <?php if($r['status']==='pending'): ?>
              <form method="POST" style="display:flex;gap:5px;align-items:center;">
                <input type="hidden" name="action" value="update_report">
                <input type="hidden" name="rid" value="<?= $r['id'] ?>">
                <input class="f-input" name="rcomment" placeholder="Comment" style="padding:4px 8px;font-size:11px;width:110px;">
                <button name="rstatus" value="approved" class="btn btn-green btn-sm">✓</button>
                <button name="rstatus" value="rejected" class="btn btn-red btn-sm">✗</button>
              </form>
              <?php else: ?><span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['manager_comment']??'—') ?></span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($allReports)): ?><tr><td colspan="6" class="empty">No reports yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ===================================================== SAFETY REPORTS -->
<section class="page-section" id="safety">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Safety Reports</div>
    <div class="section-sub">All safety inspections and incident reports across all sites</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> Safety Inspections</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Officer</th><th>Site</th><th>Severity</th><th>Status</th><th>Findings</th><th>Incident</th></tr></thead>
        <tbody>
          <?php foreach($safetyReps as $s): ?>
          <tr>
            <td><?= $s['report_date'] ?></td>
            <td><?= htmlspecialchars($s['oname']) ?></td>
            <td><?= htmlspecialchars($s['site_name']) ?></td>
            <td><span class="badge <?= $sevBadge[$s['severity']] ?>"><?= $s['severity'] ?></span></td>
            <td><span class="badge <?= $stBadge[$s['status']] ?>"><?= str_replace('_',' ',$s['status']) ?></span></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($s['inspection_findings']) ?></td>
            <td><?= $s['incident_description']?'⚠️':'—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($safetyReps)): ?><tr><td colspan="7" class="empty">No safety reports yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ===================================================== MATERIAL ORDERS -->
<section class="page-section" id="orders">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Material Orders</div>
    <div class="section-sub">All material and safety equipment order requests</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('square-rounded-plus','15') ?> All Orders</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Requested By</th><th>Type</th><th>Items</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($matOrders as $o): ?>
          <tr>
            <td><?= date('d M Y',strtotime($o['created_at'])) ?></td>
            <td><?= htmlspecialchars($o['rname']) ?> <span style="font-size:10px;color:var(--muted);">(<?= $o['rrole'] ?>)</span></td>
            <td><span class="badge <?= $o['order_type']==='safety'?'b-red':'b-blue' ?>"><?= $o['order_type'] ?></span></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($o['items']) ?></td>
            <td><span class="badge <?= $appBadge[$o['status']] ?>"><?= $o['status'] ?></span></td>
            <td>
              <?php if($o['status']==='pending'): ?>
              <form method="POST" style="display:flex;gap:5px;align-items:center;">
                <input type="hidden" name="action" value="update_order">
                <input type="hidden" name="oid" value="<?= $o['id'] ?>">
                <input class="f-input" name="ocomment" placeholder="Comment" style="padding:4px 8px;font-size:11px;width:110px;">
                <button name="ostatus" value="approved" class="btn btn-green btn-sm">✓</button>
                <button name="ostatus" value="rejected" class="btn btn-red btn-sm">✗</button>
              </form>
              <?php else: ?><span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($o['manager_comment']??'—') ?></span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($matOrders)): ?><tr><td colspan="6" class="empty">No orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ===================================================== SYSTEM LOGS -->
<section class="page-section" id="logs">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">System Logs</div>
    <div class="section-sub">Audit trail of all key actions performed in the system</div>
  </div>
  <img src="../images/dashboard_background.jpg" class="img-banner" alt="Admin">
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> Audit Logs (last 60)</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Timestamp</th><th>User</th><th>ID</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach($sysLogs as $l): ?>
          <tr>
            <td style="white-space:nowrap;font-size:12px;"><?= date('d M Y H:i:s',strtotime($l['created_at'])) ?></td>
            <td><?= htmlspecialchars($l['full_name']) ?></td>
            <td style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($l['employee_id']) ?></td>
            <td><span class="badge b-purple" style="font-size:9px;"><?= htmlspecialchars($l['action']) ?></span></td>
            <td style="font-size:12px;color:var(--text2);"><?= htmlspecialchars(mb_strimwidth($l['details']??'',0,80,'…')) ?></td>
            <td style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($l['ip_address']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($sysLogs)): ?><tr><td colspan="6" class="empty">No logs yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- ===================================================== BROADCAST -->
<section class="page-section" id="broadcast">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Broadcast Message</div>
    <div class="section-sub">Send a notification to all users or a specific role at once</div>
  </div>
  <?php if($fMsg && strpos($flash,'broadcast')!==false || $fMsg): ?>
  <div class="alert alert-<?= $fType==='ok'?'ok':'err' ?>"><?= htmlspecialchars($fMsg) ?></div>
  <?php endif; ?>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('send-2','15') ?> Send Broadcast</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="broadcast">
          <div class="form-group">
            <label class="f-label">Send To</label>
            <select class="f-select" name="bc_role" required>
              <option value="all">🌐 All Users</option>
              <option value="hr">HR Only</option>
              <option value="site_manager">Site Managers Only</option>
              <option value="foreman">Foremen Only</option>
              <option value="safety_officer">Safety Officers Only</option>
              <option value="general_labour">General Labour Only</option>
            </select>
          </div>
          <div class="form-group">
            <label class="f-label">Subject</label>
            <input class="f-input" name="bc_subject" placeholder="Broadcast subject" required>
          </div>
          <div class="form-group">
            <label class="f-label">Message Body</label>
            <textarea class="f-textarea" name="bc_body" rows="7" placeholder="Type your announcement or instruction…" required></textarea>
          </div>
          <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Send Broadcast</button>
          <p style="font-size:11px;color:var(--muted);margin-top:10px;">Each recipient will see this as a notification in their dashboard.</p>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('messages','15') ?> Broadcast Tips</span></div>
      <div class="card-body">
        <div style="font-size:13px;color:var(--text2);line-height:1.9;">
          <p style="margin-bottom:14px;">Use broadcast messages to:</p>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="padding:11px 14px;background:var(--card2);border-radius:3px;border-left:3px solid var(--amber);">📢 Announce site safety alerts to all workers</div>
            <div style="padding:11px 14px;background:var(--card2);border-radius:3px;border-left:3px solid var(--blue);">📋 Send policy updates to managers and HR</div>
            <div style="padding:11px 14px;background:var(--card2);border-radius:3px;border-left:3px solid var(--green);">✅ Confirm system updates or maintenance windows</div>
            <div style="padding:11px 14px;background:var(--card2);border-radius:3px;border-left:3px solid var(--red);">🚨 Issue emergency alerts to all staff</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</section>

<?php require_once '../php/layout_end.php'; ?>
