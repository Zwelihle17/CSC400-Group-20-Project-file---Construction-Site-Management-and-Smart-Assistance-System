<?php
/* ================================================================
   php/ai_chat.php  —  AI Smart Assistant endpoint
   POST params: message (string)
   Fetches user's real data from DB, sends to Claude API,
   returns JSON { success, reply } or { success:false, error }
   Degrades gracefully when offline.
   ================================================================ */
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Invalid method']); exit();
}

$message = trim($_POST['message'] ?? '');
if (!$message) {
    echo json_encode(['success'=>false,'error'=>'No message provided']); exit();
}

$pdo  = getDBConnection();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['full_name'];
$site = $_SESSION['site'] ?? 'Unassigned';

/* ── Build context from DB so Claude knows real system data ── */
$context = buildUserContext($pdo, $uid, $role, $name, $site);

/* ── Call Claude API ── */
$apiKey = 'YOUR_ANTHROPIC_API_KEY'; // Replace with your actual key
$result = callClaude($apiKey, $context, $message);

echo json_encode($result);

/* ================================================================
   Build a context string with real data for this user
   ================================================================ */
function buildUserContext(PDO $pdo, int $uid, string $role, string $name, string $site): string {
    $today = date('Y-m-d');
    $ctx   = "You are an AI assistant embedded in CSMS (Construction Site Management System) for Eswatini's construction industry.\n";
    $ctx  .= "The logged-in user is: $name, Role: $role, Site: $site, Date: $today\n\n";

    try {
        if ($role === 'foreman' || $role === 'hr' || $role === 'admin') {
            // Attendance today on their site
            $att = $pdo->prepare("
                SELECT u.full_name, a.status
                FROM attendance a JOIN users u ON a.employee_id=u.id
                WHERE u.site_assigned=? AND a.date=?
            ");
            $att->execute([$site, $today]);
            $rows = $att->fetchAll();
            if ($rows) {
                $ctx .= "TODAY'S ATTENDANCE AT $site:\n";
                foreach ($rows as $r) $ctx .= "  - {$r['full_name']}: {$r['status']}\n";
                $ctx .= "\n";
            }
        }

        if ($role === 'foreman' || $role === 'site_manager' || $role === 'admin') {
            // Recent daily reports
            $reps = $pdo->prepare("
                SELECT dr.report_date, dr.site_name, dr.progress_update,
                       dr.resource_usage, dr.equipment_needs, dr.status, u.full_name
                FROM daily_reports dr JOIN users u ON dr.foreman_id=u.id
                WHERE dr.site_name=? ORDER BY dr.report_date DESC LIMIT 5
            ");
            $reps->execute([$site]);
            $rows = $reps->fetchAll();
            if ($rows) {
                $ctx .= "RECENT DAILY REPORTS FOR $site:\n";
                foreach ($rows as $r) {
                    $ctx .= "  [{$r['report_date']}] by {$r['full_name']} ({$r['status']}): {$r['progress_update']}\n";
                    if ($r['equipment_needs']) $ctx .= "    Equipment needs: {$r['equipment_needs']}\n";
                }
                $ctx .= "\n";
            }
        }

        if (in_array($role, ['safety_officer','site_manager','admin','foreman'])) {
            // Recent safety reports
            $saf = $pdo->prepare("
                SELECT sr.report_date, sr.severity, sr.status,
                       sr.inspection_findings, sr.unsafe_conditions, u.full_name
                FROM safety_reports sr JOIN users u ON sr.officer_id=u.id
                WHERE sr.site_name=? ORDER BY sr.report_date DESC LIMIT 5
            ");
            $saf->execute([$site]);
            $rows = $saf->fetchAll();
            if ($rows) {
                $ctx .= "RECENT SAFETY REPORTS FOR $site:\n";
                foreach ($rows as $r) {
                    $ctx .= "  [{$r['report_date']}] Severity: {$r['severity']}, Status: {$r['status']}\n";
                    $ctx .= "    Findings: {$r['inspection_findings']}\n";
                    if ($r['unsafe_conditions']) $ctx .= "    Unsafe: {$r['unsafe_conditions']}\n";
                }
                $ctx .= "\n";
            }
        }

        if ($role === 'hr' || $role === 'admin') {
            // Employee summary
            $emp = $pdo->query("SELECT role, COUNT(*) as cnt FROM users WHERE is_active=1 GROUP BY role");
            $rows = $emp->fetchAll();
            $ctx .= "WORKFORCE SUMMARY:\n";
            foreach ($rows as $r) $ctx .= "  - {$r['role']}: {$r['cnt']}\n";
            $ctx .= "\n";
            // Open complaints
            $comp = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetchColumn();
            $ctx .= "OPEN COMPLAINTS: $comp\n\n";
        }

        if ($role === 'general_labour') {
            // My upcoming shifts
            $shifts = $pdo->prepare("
                SELECT shift_date, shift_start, shift_end, site_name, task_description
                FROM schedules WHERE worker_id=? AND shift_date>=CURDATE()
                ORDER BY shift_date ASC LIMIT 5
            ");
            $shifts->execute([$uid]);
            $rows = $shifts->fetchAll();
            if ($rows) {
                $ctx .= "MY UPCOMING SHIFTS:\n";
                foreach ($rows as $r) {
                    $ctx .= "  [{$r['shift_date']}] {$r['shift_start']}-{$r['shift_end']} at {$r['site_name']}";
                    if ($r['task_description']) $ctx .= " — Task: {$r['task_description']}";
                    $ctx .= "\n";
                }
                $ctx .= "\n";
            }
        }

        // Unread messages count
        $unread = getUnreadCount($uid);
        $ctx .= "UNREAD MESSAGES: $unread\n\n";

    } catch (Exception $e) {
        $ctx .= "(Some context data unavailable)\n\n";
    }

    $ctx .= "Answer the user's question helpfully and concisely based on the above data. ";
    $ctx .= "If asked to do something outside your data (e.g. make real changes), explain you can only display/summarize information. ";
    $ctx .= "Keep responses brief and professional. Use bullet points where helpful.";

    return $ctx;
}

/* ================================================================
   Call the Claude API
   ================================================================ */
function callClaude(string $apiKey, string $systemPrompt, string $userMessage): array {
    if ($apiKey === 'YOUR_ANTHROPIC_API_KEY') {
        return [
            'success' => false,
            'error'   => 'api_key_not_set',
            'message' => 'Claude API key not configured. Open php/ai_chat.php and replace YOUR_ANTHROPIC_API_KEY with your actual key from console.anthropic.com'
        ];
    }

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 1024,
        'system'     => $systemPrompt,
        'messages'   => [
            ['role' => 'user', 'content' => $userMessage]
        ]
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // Network / connection error — treat as offline
    if ($curlErr || $response === false) {
        return [
            'success' => false,
            'error'   => 'offline',
            'message' => 'AI assistant requires an internet connection. The rest of the system continues working normally.'
        ];
    }

    $data = json_decode($response, true);

    // API error (bad key, rate limit, etc.)
    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? "API error (HTTP $httpCode)";
        // Detect common offline-related HTTP failures
        if (in_array($httpCode, [0, 503, 504])) {
            return ['success'=>false,'error'=>'offline','message'=>'AI assistant requires an internet connection.'];
        }
        return ['success'=>false,'error'=>'api_error','message'=>$errMsg];
    }

    $reply = $data['content'][0]['text'] ?? '';
    if (!$reply) {
        return ['success'=>false,'error'=>'empty','message'=>'No response from AI.'];
    }

    return ['success'=>true,'reply'=>$reply];
}
?>
