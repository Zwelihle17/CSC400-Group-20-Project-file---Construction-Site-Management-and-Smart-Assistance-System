<?php
/* ================================================================
   dashboards/safety_officer.php  —  Safety Officer Dashboard v3
   ================================================================ */
$pageTitle = 'Safety Officer';
$navItems  = [
    ['id'=>'overview', 'label'=>'Overview'],
    ['id'=>'inspect',  'label'=>'Log Inspection'],
    ['id'=>'order',    'label'=>'Order Safety Materials'],
    ['id'=>'history',  'label'=>'Report History'],
];
require_once '../php/layout.php';

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'submit_safety') {
        $site     = trim($_POST['site_name']);
        $dt       = $_POST['report_date'];
        $findings = trim($_POST['findings']);
        $unsafe   = trim($_POST['unsafe_conditions']);
        $incident = trim($_POST['incident']);
        $severity = $_POST['severity'];
        $pdo->prepare("INSERT INTO safety_reports(officer_id,site_name,report_date,inspection_findings,unsafe_conditions,incident_description,severity)VALUES(?,?,?,?,?,?,?)")
            ->execute([$uid,$site,$dt,$findings,$unsafe,$incident,$severity]);

        if (in_array($severity,['high','critical'])) {
            foreach($pdo->query("SELECT id FROM users WHERE role IN('site_manager','foreman') AND is_active=1")->fetchAll() as $r) {
                $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
                    ->execute([$uid,$r['id'],"⚠️ ".strtoupper($severity)." Safety Alert — $site","Safety Officer $uName reported unsafe conditions at $site on $dt. Severity: $severity. Details: $unsafe"]);
            }
        }
        logAction($uid,"SO_SUBMIT_INSPECTION","Site: $site, Severity: $severity, Date: $dt");
        header("Location: safety_officer.php"); exit();
    }

    if ($act === 'order_safety') {
        $items  = trim($_POST['items']);
        $reason = trim($_POST['reason']);
        $pdo->prepare("INSERT INTO material_orders(requested_by,order_type,items,reason)VALUES(?,?,?,?)")
            ->execute([$uid,'safety',$items,$reason]);
        foreach($pdo->query("SELECT id FROM users WHERE role='site_manager' AND is_active=1")->fetchAll() as $m) {
            $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,subject,body)VALUES(?,?,?,?)")
                ->execute([$uid,$m['id'],"Safety Material Order Request","Safety Officer $uName requests: $items. Reason: $reason"]);
        }
        logAction($uid,"SO_ORDER_MATERIALS","Items: $items");
        header("Location: safety_officer.php"); exit();
    }
}

/* ── Data ── */
$totalInsp = $pdo->prepare("SELECT COUNT(*) FROM safety_reports WHERE officer_id=?"); $totalInsp->execute([$uid]); $totalInsp=$totalInsp->fetchColumn();
$openIssue = $pdo->prepare("SELECT COUNT(*) FROM safety_reports WHERE officer_id=? AND status='open'"); $openIssue->execute([$uid]); $openIssue=$openIssue->fetchColumn();
$critCount = $pdo->query("SELECT COUNT(*) FROM safety_reports WHERE severity='critical' AND status='open'")->fetchColumn();
$myOrders  = $pdo->prepare("SELECT COUNT(*) FROM material_orders WHERE requested_by=?"); $myOrders->execute([$uid]); $myOrders=$myOrders->fetchColumn();

$myReps = $pdo->prepare("SELECT * FROM safety_reports WHERE officer_id=? ORDER BY report_date DESC LIMIT 30");
$myReps->execute([$uid]); $myReps=$myReps->fetchAll();

$orderHist = $pdo->prepare("SELECT * FROM material_orders WHERE requested_by=? ORDER BY created_at DESC LIMIT 20");
$orderHist->execute([$uid]); $orderHist=$orderHist->fetchAll();

$sevBadge = ['low'=>'b-green','medium'=>'b-amber','high'=>'b-orange','critical'=>'b-red'];
$stBadge  = ['open'=>'b-red','in_progress'=>'b-amber','resolved'=>'b-green'];
$appBadge = ['pending'=>'b-amber','approved'=>'b-green','rejected'=>'b-red'];
?>

<!-- OVERVIEW -->
<section class="page-section" id="overview">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Safety Overview</div>
    <div class="section-sub">Safety Officer Dashboard — <?= date('d M Y') ?></div>
  </div>
  <img src="../images/dashboard_background.jpg" class="img-banner" alt="Safety helmets">

  <!-- AI SAFETY RISK PREDICTOR CARD -->
  <div class="ai-risk-card">
    <div class="ai-risk-head">
      <span class="ai-risk-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9.5 14.5l-1.5 1.5"/><path d="M14.5 14.5l1.5 1.5"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M10 13a2 2 0 0 0 4 0v-3a2 2 0 0 0 -4 0v3"/><path d="M6 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M18 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6 9a9 9 0 0 0 12 0"/></svg>
        AI Safety Risk Predictor
      </span>
      <button class="btn btn-outline btn-sm" onclick="loadRiskPredictor('<?= htmlspecialchars($uSite) ?>')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>
        Analyse
      </button>
    </div>
    <div class="ai-risk-body" id="riskPredictorBody">
      <div style="font-size:13px;color:var(--muted);text-align:center;padding:20px 0;">
        Click <strong>Analyse</strong> to run the AI safety risk assessment for <?= htmlspecialchars($uSite) ?>.
      </div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat">
      <div class="stat-label">Inspections Done</div>
      <div class="stat-value"><?= $totalInsp ?></div>
      <div class="stat-icon"><?= icon('circle-dashed-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Open Issues</div>
      <div class="stat-value"><?= $openIssue ?></div>
      <div class="stat-icon"><?= icon('report-analytics','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Critical Alerts</div>
      <div class="stat-value" style="color:var(--red)"><?= $critCount ?></div>
      <div class="stat-icon"><?= icon('settings-check','36') ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">Orders Placed</div>
      <div class="stat-value"><?= $myOrders ?></div>
      <div class="stat-icon"><?= icon('square-rounded-plus','36') ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> Recent Safety Reports</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Site</th><th>Severity</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach(array_slice($myReps,0,5) as $r): ?>
          <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= htmlspecialchars($r['site_name']) ?></td>
            <td><span class="badge <?= $sevBadge[$r['severity']] ?>"><?= $r['severity'] ?></span></td>
            <td><span class="badge <?= $stBadge[$r['status']] ?>"><?= str_replace('_',' ',$r['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($myReps)): ?><tr><td colspan="4" class="empty">No reports yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<!-- LOG INSPECTION -->
<section class="page-section" id="inspect">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Log Safety Inspection</div>
    <div class="section-sub">High / Critical severity automatically alerts the Site Manager and Foreman</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('circle-dashed-check','15') ?> New Inspection Report</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="submit_safety">
        <div class="form-row">
          <div class="form-group"><label class="f-label">Site Name</label><input class="f-input" name="site_name" value="<?= htmlspecialchars($uSite) ?>" required></div>
          <div class="form-group"><label class="f-label">Date</label><input class="f-input" type="date" name="report_date" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="form-group"><label class="f-label">Inspection Findings</label><textarea class="f-textarea" name="findings" placeholder="What was observed during the inspection?" required></textarea></div>
        <div class="form-group"><label class="f-label">Unsafe Conditions Identified</label><textarea class="f-textarea" name="unsafe_conditions" placeholder="Describe any hazards or unsafe conditions…"></textarea></div>
        <div class="form-group"><label class="f-label">Incident Description (if any)</label><textarea class="f-textarea" name="incident" placeholder="Describe incidents, accidents, or near-misses…"></textarea></div>
        <div class="form-group"><label class="f-label">Severity Level</label>
          <select class="f-select" name="severity" required>
            <option value="low">🟢 Low — Minor observations only</option>
            <option value="medium">🟡 Medium — Needs prompt attention</option>
            <option value="high">🟠 High — Immediate action required ⚠️</option>
            <option value="critical">🔴 Critical — Work must stop NOW 🚨</option>
          </select>
        </div>
        <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Submit Inspection Report</button>
        <span style="font-size:11px;color:var(--muted);margin-left:12px;">High/Critical alerts notify Site Manager &amp; Foreman instantly.</span>
      </form>
    </div>
  </div>
</div>
</section>

<!-- ORDER MATERIALS -->
<section class="page-section" id="order">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Order Safety Materials</div>
    <div class="section-sub">Request PPE, warning signs, first aid kits and other safety equipment</div>
  </div>
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('square-rounded-plus','15') ?> New Order Request</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="order_safety">
          <div class="form-group">
            <label class="f-label">Items Needed</label>
            <textarea class="f-textarea" name="items" rows="5"
              placeholder="e.g.&#10;• 20× Hard hats (yellow)&#10;• 50× Safety vests&#10;• 10× Warning cones&#10;• 5× First aid kits" required></textarea>
          </div>
          <div class="form-group">
            <label class="f-label">Justification / Reason</label>
            <textarea class="f-textarea" name="reason" placeholder="Why are these items needed? Reference any inspection findings…" required></textarea>
          </div>
          <button type="submit" class="btn btn-amber"><?= icon('send-2','14') ?> Submit Order Request</button>
          <p style="font-size:11px;color:var(--muted);margin-top:10px;">Site Manager will approve or reject this order.</p>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title"><?= icon('library','15') ?> My Order History</span></div>
      <div class="card-body-flush">
        <table class="tbl">
          <thead><tr><th>Items</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($orderHist as $o): ?>
            <tr>
              <td><?= htmlspecialchars(mb_strimwidth($o['items'],0,60,'…')) ?></td>
              <td><span class="badge <?= $appBadge[$o['status']] ?>"><?= $o['status'] ?></span></td>
              <td><?= date('d M Y',strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($orderHist)): ?><tr><td colspan="3" class="empty">No orders yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</section>

<!-- HISTORY -->
<section class="page-section" id="history">
<div class="section-pad">
  <div class="section-header">
    <div class="section-title">Inspection History</div>
    <div class="section-sub">All safety inspections and incident reports submitted</div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><?= icon('report-analytics','15') ?> All My Reports</span></div>
    <div class="card-body-flush">
      <table class="tbl">
        <thead><tr><th>Date</th><th>Site</th><th>Severity</th><th>Status</th><th>Findings</th><th>Incident</th></tr></thead>
        <tbody>
          <?php foreach($myReps as $r): ?>
          <tr>
            <td><?= $r['report_date'] ?></td>
            <td><?= htmlspecialchars($r['site_name']) ?></td>
            <td><span class="badge <?= $sevBadge[$r['severity']] ?>"><?= $r['severity'] ?></span></td>
            <td><span class="badge <?= $stBadge[$r['status']] ?>"><?= str_replace('_',' ',$r['status']) ?></span></td>
            <td><?= htmlspecialchars(mb_strimwidth($r['inspection_findings'],0,80,'…')) ?></td>
            <td><?= $r['incident_description'] ? '⚠️ '.htmlspecialchars(mb_strimwidth($r['incident_description'],0,50,'…')) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($myReps)): ?><tr><td colspan="6" class="empty">No inspections recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>

<?php require_once '../php/layout_end.php'; ?>
