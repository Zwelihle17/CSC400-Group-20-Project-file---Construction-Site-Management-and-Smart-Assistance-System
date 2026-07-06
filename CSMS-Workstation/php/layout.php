<?php
/* ================================================================
   php/layout.php  —  Shared sidebar + topbar + modals
   Each dashboard defines $pageTitle and $navItems before including.
   ================================================================ */
require_once '../config.php';
require_once '../php/icons.php';
requireLogin();

$uid    = $_SESSION['user_id'];
$uName  = $_SESSION['full_name'];
$uRole  = $_SESSION['role'];
$uSite  = $_SESSION['site'] ?? 'Unassigned';
$unread = getUnreadCount($uid);

$pdo = getDBConnection();

/* Notifications */
$notifStmt = $pdo->prepare("
    SELECT m.id,m.subject,m.body,m.is_read,m.sent_at,
           m.attachment_name, m.attachment_path,
           u.full_name AS sender_name
    FROM messages m JOIN users u ON m.sender_id=u.id
    WHERE m.receiver_id=? ORDER BY m.sent_at DESC LIMIT 30
");
$notifStmt->execute([$uid]);
$notifications = $notifStmt->fetchAll();

/* All users for compose dropdown */
$allUsers = $pdo->query("SELECT id,full_name,employee_id,role FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();

$roleLabels = [
    'admin'          => 'Administrator',
    'hr'             => 'Human Resources',
    'site_manager'   => 'Site Manager',
    'foreman'        => 'Foreman',
    'safety_officer' => 'Safety Officer',
    'general_labour' => 'General Labour',
];

/* Icon mapping for each navItem id → which SVG to use */
$sectionIcons = [
    'overview'   => 'settings-check',
    'employees'  => 'user-square-rounded',
    'assign'     => 'user-plus',
    'attendance' => 'circle-dashed-check',
    'reports'    => 'report-analytics',
    'report'     => 'send-2',
    'history'    => 'library',
    'orders'     => 'library',
    'safety'     => 'report-analytics',
    'sites'      => 'settings-check',
    'tasks'      => 'circle-dashed-check',
    'inspect'    => 'circle-dashed-check',
    'order'      => 'square-rounded-plus',
    'schedule'   => 'circle-dashed-check',
    'complaint'  => 'messages',
    'requests'   => 'library',
    'users'      => 'user-search',
    'logs'       => 'report-analytics',
    'system'     => 'settings-check',
    'broadcast'  => 'send-2',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ConstructPro — <?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="../css/ai.css">
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-main">ConstructPro</div>
    <div class="sb-logo-sub">Site Management System</div>
  </div>

  <div class="sb-user">
    <div class="sb-user-row">
      <!-- user-circle icon as avatar -->
      <div class="sb-avatar"><?= icon('user-circle','22') ?></div>
      <div class="sb-user-info">
        <div class="sb-user-name"><?= htmlspecialchars($uName) ?></div>
        <div class="sb-user-role"><?= htmlspecialchars($roleLabels[$uRole] ?? $uRole) ?></div>
        <!-- user-pin icon for site location -->
        <div class="site-pin-badge">
          <?= icon('user-pin','13') ?>
          <?= htmlspecialchars($uSite ?? 'Unassigned') ?>
        </div>
      </div>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="sb-section">Navigation</div>
    <?php foreach ($navItems as $item):
        $ico = $sectionIcons[$item['id']] ?? 'settings-check';
    ?>
    <a class="nav-link" href="#" data-section="<?= $item['id'] ?>" data-label="<?= htmlspecialchars($item['label']) ?>">
      <span class="nav-icon"><?= icon($ico, '18') ?></span>
      <?= htmlspecialchars($item['label']) ?>
    </a>
    <?php endforeach; ?>

    <div class="sb-section" style="margin-top:6px;">Messaging</div>
    <a class="nav-link" href="#" onclick="openCompose();return false;">
      <span class="nav-icon"><?= icon('send-2','18') ?></span>
      Send Message
    </a>

    <div class="sb-section" style="margin-top:6px;">Intelligence</div>
    <a class="nav-link ai-link" href="#" id="aiPanelBtn" onclick="toggleAIPanel();return false;">
      <span class="nav-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
          <path d="M9.5 14.5l-1.5 1.5"/><path d="M14.5 14.5l1.5 1.5"/>
          <path d="M9 9h.01"/><path d="M15 9h.01"/>
          <path d="M10 13a2 2 0 0 0 4 0v-3a2 2 0 0 0 -4 0v3"/>
          <path d="M6 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>
          <path d="M18 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>
          <path d="M6 9a9 9 0 0 0 12 0"/>
        </svg>
      </span>
      AI Assistant
      <span class="ai-badge">Beta</span>
    </a>
  </nav>

  <div class="sb-bottom">
    <a href="../php/logout.php" class="btn-logout">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M9 12h12l-3 -3"/><path d="M18 15l3 -3"/></svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-title" id="topbarSection"><?= htmlspecialchars($pageTitle) ?></div>
    <div class="topbar-right">
      <?php if ($uRole === 'admin'): ?>
      <span class="admin-badge"><?= icon('settings-check','12') ?> Admin</span>
      <?php endif; ?>
      <span class="topbar-date"><?= date('D, d M Y') ?></span>
      <button class="notif-bell" id="bellBtn" onclick="toggleDrawer()" title="Notifications">
        <?= icon('messages','18') ?>
        <span class="notif-count" id="bellBadge" <?= $unread===0?'style="display:none"':'' ?>><?= $unread ?></span>
      </button>
    </div>
  </div>

  <!-- ===== NOTIFICATION DRAWER ===== -->
  <div class="notif-drawer" id="notifDrawer">
    <div class="nd-head">
      <span class="nd-title"><?= icon('messages','16') ?> Notifications</span>
      <button class="nd-close" onclick="closeDrawer()">×</button>
    </div>
    <div class="nd-list">
      <?php if (empty($notifications)): ?>
        <div class="nd-empty">No messages yet.</div>
      <?php else: ?>
        <?php foreach ($notifications as $n):
          $isUnread  = !$n['is_read'];
          $preview   = mb_strimwidth(strip_tags($n['body']), 0, 85, '…');
          $sentAt    = date('d M Y, H:i', strtotime($n['sent_at']));
          $jsSub     = addslashes($n['subject']);
          $jsFrom    = addslashes($n['sender_name']);
          $jsBody    = addslashes($n['body']);
          $jsDate    = addslashes($sentAt);
          $jsAttUrl  = !empty($n['attachment_path']) ? addslashes('../' . $n['attachment_path']) : '';
          $jsAttName = !empty($n['attachment_name']) ? addslashes($n['attachment_name']) : '';
        ?>
        <div class="nd-item <?= $isUnread?'unread':'' ?>" id="ndItem-<?= $n['id'] ?>"
             onclick="openMessage(<?= $n['id'] ?>,'<?= $jsSub ?>','<?= $jsFrom ?>','<?= $jsDate ?>','<?= $jsBody ?>','<?= $jsAttUrl ?>','<?= $jsAttName ?>')">
          <div class="nd-subject"><?= htmlspecialchars($n['subject']) ?></div>
          <div class="nd-from">From: <?= htmlspecialchars($n['sender_name']) ?>
            <?php if (!empty($n['attachment_name'])): ?>
            &nbsp;<span style="font-size:10px;color:var(--amber);">📎 <?= htmlspecialchars($n['attachment_name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="nd-preview"><?= htmlspecialchars($preview) ?></div>
          <div class="nd-time"><?= $sentAt ?></div>
          <?php if ($isUnread): ?><div class="nd-dot"></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- AI SMART ASSISTANT PANEL -->
  <div class="ai-panel" id="aiPanel" data-name="<?= htmlspecialchars($uName) ?>" data-role="<?= htmlspecialchars($roleLabels[$uRole] ?? $uRole) ?>">
    <div class="ai-panel-head">
      <span class="ai-panel-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9.5 14.5l-1.5 1.5"/><path d="M14.5 14.5l1.5 1.5"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M10 13a2 2 0 0 0 4 0v-3a2 2 0 0 0 -4 0v3"/><path d="M6 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M18 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6 9a9 9 0 0 0 12 0"/></svg>
        AI Assistant
      </span>
      <button class="ai-panel-close" onclick="closeAIPanel()">x</button>
    </div>
    <div class="ai-chat-messages" id="aiMessages"></div>
    <?php
    $quickPrompts=[
      'admin'         =>['Show system summary','How many users?','Any open safety issues?','Recent activity'],
      'hr'            =>['Attendance today','Open complaints count','Workforce breakdown','Draft shift reminder'],
      'site_manager'  =>['Pending reports','Safety alerts on my site','Today site activity','Who submitted reports?'],
      'foreman'       =>['Who is present today?','Summarise last report','Equipment issues','Draft progress update'],
      'safety_officer'=>['My site risk level','Open safety issues','Recent inspections','Draft safety alert'],
      'general_labour'=>['My next shift','Days present this month','Draft a complaint','My assigned tasks'],
    ];
    $chips=$quickPrompts[$uRole]??['What can you help me with?'];
    ?>
    <div class="ai-quick-prompts">
      <?php foreach($chips as $chip): ?>
      <span class="ai-chip" onclick="usePrompt('<?= addslashes($chip) ?>')">
        <?= htmlspecialchars($chip) ?>
      </span>
      <?php endforeach; ?>
    </div>
    <div class="ai-input-bar">
      <textarea class="ai-input" id="aiInput" placeholder="Ask anything about your site, reports, attendance..." rows="1"></textarea>
      <button class="ai-send-btn" id="aiSendBtn" onclick="sendAIMessage()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4.698 4.034l16.302 7.966l-16.302 7.966a.503 .503 0 0 1 -.546 -.124a.555 .555 0 0 1 -.12 -.568l2.468 -7.274l-2.468 -7.274a.555 .555 0 0 1 .12 -.568a.503 .503 0 0 1 .546 -.124"/><path d="M6.5 12h14.5"/></svg>
        Send
      </button>
    </div>
  </div>

  <!-- PAGE SECTIONS -->
  <div style="flex:1;">
