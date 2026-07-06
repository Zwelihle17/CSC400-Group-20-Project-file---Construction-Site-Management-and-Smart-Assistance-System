<?php
/* ================================================================
   php/ai_report.php  —  AI Automated Daily Report Generator
   POST params: site_name, date
   Reads today's attendance + assigned tasks for the site,
   sends to Claude, returns a pre-filled report draft.
   ================================================================ */
require_once '../config.php';
requireRole(['foreman','admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Invalid method']); exit();
}

$site = trim($_POST['site_name'] ?? $_SESSION['site'] ?? '');
$date = trim($_POST['date']      ?? date('Y-m-d'));

if (!$site) {
    echo json_encode(['success'=>false,'error'=>'Site name required']); exit();
}

$pdo  = getDBConnection();
$uid  = $_SESSION['user_id'];
$name = $_SESSION['full_name'];

/* ── Gather real site data for the report ── */
$dataText = "Generate a professional daily construction site report for:\n";
$dataText .= "Foreman: $name\nSite: $site\nDate: $date\n\n";

// Attendance for this site today
$att = $pdo->prepare("
    SELECT u.full_name, u.role, a.status, a.notes
    FROM attendance a JOIN users u ON a.employee_id=u.id
    WHERE u.site_assigned=? AND a.date=?
");
$att->execute([$site, $date]);
$attRows = $att->fetchAll();
if ($attRows) {
    $present = array_filter($attRows, fn($r) => $r['status']==='present');
    $absent  = array_filter($attRows, fn($r) => $r['status']==='absent');
    $late    = array_filter($attRows, fn($r) => $r['status']==='late');
    $dataText .= "ATTENDANCE ({$date}):\n";
    $dataText .= "  Present (".count($present)."): ".implode(', ', array_column(array_values($present),'full_name'))."\n";
    if ($absent)  $dataText .= "  Absent (".count($absent)."): ".implode(', ', array_column(array_values($absent),'full_name'))."\n";
    if ($late)    $dataText .= "  Late (".count($late)."): ".implode(', ', array_column(array_values($late),'full_name'))."\n";
    $dataText .= "\n";
} else {
    $dataText .= "ATTENDANCE: No attendance recorded for today.\n\n";
}

// Tasks assigned for today on this site
$tasks = $pdo->prepare("
    SELECT u.full_name, s.task_description, s.shift_start, s.shift_end
    FROM schedules s JOIN users u ON s.worker_id=u.id
    WHERE s.site_name=? AND s.shift_date=?
");
$tasks->execute([$site, $date]);
$taskRows = $tasks->fetchAll();
if ($taskRows) {
    $dataText .= "TASKS ASSIGNED TODAY:\n";
    foreach ($taskRows as $t) {
        $dataText .= "  - {$t['full_name']}: {$t['task_description']} ({$t['shift_start']}-{$t['shift_end']})\n";
    }
    $dataText .= "\n";
}

// Recent safety issues on this site
$saf = $pdo->prepare("
    SELECT sr.severity, sr.inspection_findings, sr.unsafe_conditions, sr.status
    FROM safety_reports sr
    WHERE sr.site_name=? AND sr.status != 'resolved'
    ORDER BY sr.created_at DESC LIMIT 3
");
$saf->execute([$site]);
$safRows = $saf->fetchAll();
if ($safRows) {
    $dataText .= "OPEN SAFETY ISSUES:\n";
    foreach ($safRows as $s) {
        $dataText .= "  - [{$s['severity']}] {$s['inspection_findings']}\n";
        if ($s['unsafe_conditions']) $dataText .= "    Unsafe: {$s['unsafe_conditions']}\n";
    }
    $dataText .= "\n";
}

// Previous report for context
$prev = $pdo->prepare("
    SELECT progress_update, resource_usage, equipment_needs
    FROM daily_reports WHERE site_name=?
    ORDER BY report_date DESC LIMIT 1
");
$prev->execute([$site]);
$prevRow = $prev->fetch();
if ($prevRow) {
    $dataText .= "PREVIOUS REPORT CONTEXT:\n";
    $dataText .= "  Progress: {$prevRow['progress_update']}\n";
    $dataText .= "  Resources used: {$prevRow['resource_usage']}\n";
    if ($prevRow['equipment_needs']) $dataText .= "  Equipment needs: {$prevRow['equipment_needs']}\n";
    $dataText .= "\n";
}

$dataText .= "Based on the above, write a professional daily site activity report with THREE sections:\n";
$dataText .= "1. PROGRESS UPDATE: What work was likely done today based on tasks and attendance.\n";
$dataText .= "2. RESOURCE USAGE: Materials and labour utilisation based on workers present and tasks.\n";
$dataText .= "3. EQUIPMENT NEEDS: Any equipment requirements or issues to flag.\n";
$dataText .= "Be specific, professional, and concise. Use paragraph form, not bullet points. ";
$dataText .= "Return ONLY the three sections clearly labelled, no extra text.";

// Call Claude
$apiKey = 'YOUR_ANTHROPIC_API_KEY';
$result = callClaudeReport($apiKey, $dataText);
echo json_encode($result);

function callClaudeReport(string $apiKey, string $prompt): array {
    if ($apiKey === 'YOUR_ANTHROPIC_API_KEY') {
        return [
            'success' => false,
            'error'   => 'api_key_not_set',
            'message' => 'Claude API key not configured. Open php/ai_report.php and set your API key.'
        ];
    }

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 800,
        'messages'   => [['role'=>'user','content'=>$prompt]]
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: '.$apiKey,
            'anthropic-version: 2023-06-01',
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $response === false) {
        return ['success'=>false,'error'=>'offline',
                'message'=>'AI report generator requires an internet connection. You can still write the report manually.'];
    }

    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        return ['success'=>false,'error'=>'api_error',
                'message'=>($data['error']['message'] ?? "API error ($httpCode)")];
    }

    $data  = json_decode($response, true);
    $reply = $data['content'][0]['text'] ?? '';
    if (!$reply) return ['success'=>false,'error'=>'empty','message'=>'AI returned no content.'];

    // Parse the three sections
    $progress  = extractSection($reply, 'PROGRESS UPDATE');
    $resources = extractSection($reply, 'RESOURCE USAGE');
    $equipment = extractSection($reply, 'EQUIPMENT NEEDS');

    return [
        'success'   => true,
        'progress'  => $progress,
        'resources' => $resources,
        'equipment' => $equipment,
        'raw'       => $reply
    ];
}

function extractSection(string $text, string $label): string {
    // Try to extract text after a section header
    $pattern = '/' . preg_quote($label, '/') . '\s*:?\s*\n?(.*?)(?=\n[A-Z\s]+:|$)/si';
    if (preg_match($pattern, $text, $m)) {
        return trim($m[1]);
    }
    return '';
}
?>
