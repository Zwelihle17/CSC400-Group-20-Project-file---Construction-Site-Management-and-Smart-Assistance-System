<?php
/* ================================================================
   dashboards/site_manager.php  —  Site Manager Dashboard v3
   ================================================================ */
$pageTitle = 'Site Manager';
$navItems  = [
    ['id'=>'overview', 'label'=>'Overview'],
    ['id'=>'reports',  'label'=>'Daily Reports'],
    ['id'=>'orders',   'label'=>'Material Orders'],
    ['id'=>'safety',   'label'=>'Safety Reports'],
    ['id'=>'sites',    'label'=>'Sites & Teams'],
];
require_once '../php/layout.php';

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'update_report') {
        $rid = intval($_POST['rid']); $st = $_POST['rstatus']; $cm = trim($_POST['rcomment']);
        $pdo->prepare("UPDATE daily_reports SET status=?,manager_comment=? WHERE id=?")->execute([$st,$cm,$rid]);
        $rep = $pdo->prepare("SELECT foreman_id FROM daily_reports WHERE id=?"); $rep->execute([$rid]); $rep=$rep->fetch();
        if ($rep) {
            $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
                ->execute([$uid,$rep['foreman_id'],"Report ".ucfirst($st),"Your daily report has been $st. Manager comment: $cm"]);
        }
        logAction($uid,"SM_UPDATE_REPORT","Report $rid → $st");
        header("Location: site_manager.php"); exit();
    }

    if ($act === 'update_order') {
        $oid = intval($_POST['oid']); $st = $_POST['ostatus']; $cm = trim($_POST['ocomment']);
        $pdo->prepare("UPDATE material_orders SET status=?,manager_comment=? WHERE id=?")->execute([$st,$cm,$oid]);
        $ord = $pdo->prepare("SELECT requested_by FROM material_orders WHERE id=?"); $ord->execute([$oid]); $ord=$ord->fetch();
        if ($ord) {
            $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
                ->execute([$uid,$ord['requested_by'],"Order ".ucfirst($st),"Your material order has been $st. Comment: $cm"]);
        }
        logAction($uid,"SM_UPDATE_ORDER","Order $oid → $st");
        header("Location: site_manager.php"); exit();
    }
}

/* ── Stats ── */
$pendingRep = $pdo->query("SELECT COUNT(*) FROM daily_reports WHERE status='pending'")->fetchColumn();
$openSafety = $pdo->query("SELECT COUNT(*) FROM safety_reports WHERE status='open'")->fetchColumn();
$pendingOrd = $pdo->query("SELECT COUNT(*) FROM material_orders WHERE status='pending'")->fetchColumn();
$siteCount  = $pdo->query("SELECT COUNT(DISTINCT site_assigned) FROM users WHERE site_assigned IS NOT NULL AND is_active=1")->fetchColumn();

/* ── Data ── */
$dailyReps = $pdo->query("
    SELECT dr.*,u.full_name AS fname FROM daily_reports dr
    JOIN users u ON dr.foreman_id=u.id ORDER BY dr.created_at DESC LIMIT 40
")->fetchAll();

$matOrders = $pdo->query("
    SELECT mo.*,u.full_name AS rname,u.role AS rrole FROM material_orders mo
    JOIN users u ON mo.requested_by=u.id ORDER BY mo.created_at DESC LIMIT 30
")->fetchAll();

$safetyReps = $pdo->query("
    SELECT sr.*,u.full_name AS oname FROM safety_reports sr
    JOIN users u ON sr.officer_id=u.id ORDER BY sr.created_at DESC LIMIT 30
")->fetchAll();

$sites = $pdo->query("
    SELECT site_assigned AS site, COUNT(*) AS cnt,
           GROUP_CONCAT(full_name ORDER BY full_name SEPARATOR ', ') AS members
    FROM users WHERE site_assigned IS NOT NULL AND is_active=1
    GROUP BY site_assigned
")->fetchAll();

$appBadge = ['pending'=>'b-amber','approved'=>'b-green','rejected'=>'b-red'];
$sevBadge = ['low'=>'b-green','medium'=>'b-amber','high'=>'b-orange','critical'=>'b-red'];
$stBadge  = ['open'=>'b-red','in_progress'=>'b-amber','resolved'=>'b-green'];
?>

<!-- OVERVIEW -->
<section class="page-section" id="overview">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Site Manager Overview</div>
    <div class="section-sub">Oversee all sites, reports and orders — <?= date('d M Y') ?></div>
  </div>
  <img src="../images/background.jpg" class="img-banner" alt="Construction site">
  <div class="stats-grid">
    <div class="stat">
      <div class="stat-label">Active Sites</div>
      <div class="stat-value"><?= $siteCount ?></div>
      <div class="stat-icon"><?= icon('settings-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Pending Reports</div>
      <div class="stat-value"><?= $pendingRep ?></div>
      <div class="stat-icon"><?= icon('library','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Safety Issues</div>
      <div class="stat-value" style="color:var(--red)"><?= $openSafety ?></div>
      <div class="stat-icon"><?= icon('report-analytics','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Pending Orders</div>
      <div class="stat-value"><?= $pendingOrd ?></div>
      <div class="stat-icon"><?= icon('square-rounded-plus','36') ?></div>
    </div>
  </div>

  <!-- Pending reports quick view -->
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> Pending Reports Awaiting Review</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Foreman</th><th>Site</th><th>Status</th></tr></thead>
        <tbody>
          <?php $pending = array_filter($dailyReps, fn($r)=>$r['status']==='pending'); ?>
          <?php foreach(array_slice($pending,0,5) as $r): ?>
          <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= htmlspecialchars($r['fname']) ?></td>
            <td><?= htmlspecialchars($r['site_name']) ?></td>
            <td><span class="badge b-amber">Pending</span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($pending)): ?><tr><td colspan="4" class="empty">No pending reports.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- DAILY REPORTS -->
<section class="page-section" id="reports">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Daily Reports</div>
    <div class="section-sub">Review, approve or reject foreman site activity reports</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('library','15') ?> All Daily Reports</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Foreman</th><th>Site</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($dailyReps as $r): ?>
          <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= htmlspecialchars($r['fname']) ?></td>
            <td><?= htmlspecialchars($r['site_name']) ?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['progress_update']) ?></td>
            <td><span class="badge <?= $appBadge[$r['status']] ?>"><?= $r['status'] ?></span></td>
            <td>
              <?php if($r['status']==='pending'): ?>
              <form method="POST" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="action" value="update_report">
                <input type="hidden" name="rid" value="<?= $r['id'] ?>">
                <input class="f-input" name="rcomment" placeholder="Comment" style="padding:4px 8px;font-size:11px;width:115px;">
                <button name="rstatus" value="approved" class="btn btn-green btn-sm"><?= icon('circle-dashed-check','12') ?> ✓</button>
                <button name="rstatus" value="rejected" class="btn btn-red btn-sm">✗</button>
              </form>
              <?php else: ?>
              <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['manager_comment']??'—') ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($dailyReps)): ?><tr><td colspan="6" class="empty">No reports yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- MATERIAL ORDERS -->
<section class="page-section" id="orders">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Material Orders</div>
    <div class="section-sub">Approve or reject safety and general material requests</div>
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
            <td><?= htmlspecialchars($o['rname']) ?> <span style="font-size:10px;color:var(--muted);">(<?= str_replace('_',' ',$o['rrole']) ?>)</span></td>
            <td><span class="badge <?= $o['order_type']==='safety'?'b-red':'b-blue' ?>"><?= $o['order_type'] ?></span></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($o['items']) ?></td>
            <td><span class="badge <?= $appBadge[$o['status']] ?>"><?= $o['status'] ?></span></td>
            <td>
              <?php if($o['status']==='pending'): ?>
              <form method="POST" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="action" value="update_order">
                <input type="hidden" name="oid" value="<?= $o['id'] ?>">
                <input class="f-input" name="ocomment" placeholder="Comment" style="padding:4px 8px;font-size:11px;width:115px;">
                <button name="ostatus" value="approved" class="btn btn-green btn-sm"><?= icon('circle-dashed-check','12') ?> ✓</button>
                <button name="ostatus" value="rejected" class="btn btn-red btn-sm">✗</button>
              </form>
              <?php else: ?>
              <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($o['manager_comment']??'—') ?></span>
              <?php endif; ?>
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

<!-- SAFETY REPORTS -->
<section class="page-section" id="safety">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Safety Reports</div>
    <div class="section-sub">All safety inspections and incident reports from officers</div>
  </div>

  <!-- AI Safety Risk Predictor -->
  <div class="ai-risk-card" style="margin-bottom:22px;">
    <div class="ai-risk-head">
      <span class="ai-risk-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9.5 14.5l-1.5 1.5"/><path d="M14.5 14.5l1.5 1.5"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M10 13a2 2 0 0 0 4 0v-3a2 2 0 0 0 -4 0v3"/><path d="M6 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M18 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6 9a9 9 0 0 0 12 0"/></svg>
        AI Safety Risk Predictor
      </span>
      <div style="display:flex;gap:8px;align-items:center;">
        <select id="riskSiteSelect" class="f-select" style="padding:5px 10px;font-size:11px;width:180px;">
          <?php foreach($sites as $s): ?>
          <option value="<?= htmlspecialchars($s['site']) ?>"><?= htmlspecialchars($s['site']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" onclick="loadRiskPredictor(document.getElementById('riskSiteSelect').value)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
          Analyse
        </button>
      </div>
    </div>
    <div class="ai-risk-body" id="riskPredictorBody">
      <div style="font-size:13px;color:var(--muted);text-align:center;padding:20px 0;">
        Select a site and click <strong>Analyse</strong> to run the AI safety risk assessment.
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> All Safety Reports</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Officer</th><th>Site</th><th>Severity</th><th>Status</th><th>Findings</th></tr></thead>
        <tbody>
          <?php foreach($safetyReps as $s): ?>
          <tr>
            <td><?= $s['report_date'] ?></td>
            <td><?= htmlspecialchars($s['oname']) ?></td>
            <td><?= htmlspecialchars($s['site_name']) ?></td>
            <td><span class="badge <?= $sevBadge[$s['severity']] ?>"><?= $s['severity'] ?></span></td>
            <td><span class="badge <?= $stBadge[$s['status']] ?>"><?= str_replace('_',' ',$s['status']) ?></span></td>
            <td><?= htmlspecialchars(mb_strimwidth($s['inspection_findings'],0,100,'…')) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($safetyReps)): ?><tr><td colspan="6" class="empty">No safety reports yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- SITES & TEAMS -->
<section class="page-section" id="sites">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Sites &amp; Teams</div>
    <div class="section-sub">Overview of all active sites and assigned personnel</div>
  </div>
  <img src="../images/dashboard_background.jpg" class="img-banner" alt="Helmets">
  <div class="two-col">
    <?php foreach($sites as $s): ?>
    <div class="card">
      <div class="card-head">
        <span class="card-title"><?= icon('settings-check','15') ?> <?= htmlspecialchars($s['site']) ?></span>
        <span class="badge b-blue"><?= $s['cnt'] ?> members</span>
      </div>
      <div class="card-body">
        <div style="font-size:13px;color:var(--text2);line-height:1.9;"><?= htmlspecialchars($s['members']) ?></div>
        <div style="margin-top:14px;">
          <button class="btn btn-outline btn-sm" onclick="openCompose()"><?= icon('send-2','13') ?> Message Team</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($sites)): ?>
    <div class="card"><div class="card-body empty">No sites configured yet.</div></div>
    <?php endif; ?>
  </div>
</div>
</section>

<?php require_once '../php/layout_end.php'; ?>
