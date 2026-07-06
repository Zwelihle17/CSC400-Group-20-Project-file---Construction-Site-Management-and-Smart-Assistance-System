<?php
/* ================================================================
   php/ai_safety.php  —  Safety Risk Predictor
   GET param: site (optional, defaults to user's site)
   Analyses safety_reports data and returns:
   - risk_level: low / medium / high / critical
   - risk_score: 0-100
   - summary: plain-English explanation
   - recommendations: array of action items
   Degrades gracefully when offline.
   ================================================================ */
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$pdo  = getDBConnection();
$site = trim($_GET['site'] ?? $_POST['site'] ?? $_SESSION['site'] ?? '');

if (!$site) {
    echo json_encode(['success'=>false,'error'=>'No site specified']); exit();
}

/* ── Collect raw safety data for this site ── */
// All safety reports for this site
$allReports = $pdo->prepare("
    SELECT severity, status, report_date, inspection_findings,
           unsafe_conditions, incident_description
    FROM safety_reports
    WHERE site_name = ?
    ORDER BY report_date DESC
    LIMIT 30
");
$allReports->execute([$site]);
$reports = $allReports->fetchAll();

// Count by severity
$counts = ['low'=>0,'medium'=>0,'high'=>0,'critical'=>0];
$open   = 0;
$recent = 0; // last 7 days
$sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
foreach ($reports as $r) {
    $counts[$r['severity']] = ($counts[$r['severity']] ?? 0) + 1;
    if ($r['status'] !== 'resolved') $open++;
    if ($r['report_date'] >= $sevenDaysAgo) $recent++;
}

$totalReports = count($reports);

/* ── Rule-based local risk score (works offline too) ── */
$localScore = calculateLocalRisk($counts, $open, $recent, $totalReports);

/* ── Build analysis text for Claude ── */
$analysisText  = "Analyse this construction site safety data and assess the risk level.\n\n";
$analysisText .= "SITE: $site\n";
$analysisText .= "ANALYSIS DATE: " . date('Y-m-d') . "\n\n";
$analysisText .= "SAFETY REPORT SUMMARY (last 30 reports):\n";
$analysisText .= "  Total reports: $totalReports\n";
$analysisText .= "  Critical severity: {$counts['critical']}\n";
$analysisText .= "  High severity: {$counts['high']}\n";
$analysisText .= "  Medium severity: {$counts['medium']}\n";
$analysisText .= "  Low severity: {$counts['low']}\n";
$analysisText .= "  Currently open/unresolved: $open\n";
$analysisText .= "  Reports in last 7 days: $recent\n\n";

if ($reports) {
    $analysisText .= "MOST RECENT FINDINGS:\n";
    foreach (array_slice($reports, 0, 5) as $r) {
        $analysisText .= "  [{$r['report_date']}][{$r['severity']}] {$r['inspection_findings']}\n";
        if ($r['unsafe_conditions'])    $analysisText .= "    Unsafe: {$r['unsafe_conditions']}\n";
        if ($r['incident_description']) $analysisText .= "    Incident: {$r['incident_description']}\n";
    }
}

$analysisText .= "\nRespond ONLY with valid JSON in exactly this format (no extra text):\n";
$analysisText .= '{"risk_level":"low|medium|high|critical","risk_score":0-100,"summary":"2-3 sentence plain English summary","recommendations":["action 1","action 2","action 3"]}';

/* ── Try Claude API ── */
$apiKey = 'YOUR_ANTHROPIC_API_KEY';
$aiResult = callClaudeSafety($apiKey, $analysisText);

if ($aiResult['success']) {
    echo json_encode(array_merge(['success'=>true, 'ai'=>true], $aiResult['data']));
} else {
    // Fallback: return the rule-based local analysis
    echo json_encode([
        'success'         => true,
        'ai'              => false,  // flags that this is local analysis, not AI
        'offline_message' => $aiResult['message'] ?? '',
        'risk_level'      => $localScore['risk_level'],
        'risk_score'      => $localScore['risk_score'],
        'summary'         => $localScore['summary'],
        'recommendations' => $localScore['recommendations'],
    ]);
}

/* ================================================================
   Rule-based local risk calculation — works 100% offline
   ================================================================ */
function calculateLocalRisk(array $counts, int $open, int $recent, int $total): array {
    // Weighted score
    $score  = 0;
    $score += $counts['critical'] * 35;
    $score += $counts['high']     * 20;
    $score += $counts['medium']   * 8;
    $score += $counts['low']      * 2;
    $score += $open               * 5;
    $score += $recent             * 10;
    $score  = min(100, $score);

    if ($score >= 70)      $level = 'critical';
    elseif ($score >= 45)  $level = 'high';
    elseif ($score >= 20)  $level = 'medium';
    else                   $level = 'low';

    $summaries = [
        'low'      => "This site shows low safety risk. Routine monitoring is sufficient. Continue standard safety inspections.",
        'medium'   => "This site shows moderate safety risk with $open unresolved issue(s). Prompt attention is recommended.",
        'high'     => "This site shows HIGH safety risk. There are {$counts['high']} high-severity findings and $open unresolved issues. Immediate action required.",
        'critical' => "CRITICAL safety risk detected. {$counts['critical']} critical finding(s) recorded. Work should be reviewed before continuing.",
    ];

    $recs = [];
    if ($counts['critical'] > 0) $recs[] = "Immediately address all $counts[critical] critical safety finding(s)";
    if ($counts['high'] > 0)     $recs[] = "Schedule urgent review of $counts[high] high-severity issue(s) this week";
    if ($open > 3)               $recs[] = "Resolve $open outstanding safety issues — do not allow them to accumulate";
    if ($recent > 2)             $recs[] = "High report frequency this week — consider a site-wide safety briefing";
    if (empty($recs))            $recs[] = "Maintain current inspection schedule and document findings consistently";
    $recs[] = "Ensure all workers have current PPE and have reviewed site safety procedures";

    return [
        'risk_level'      => $level,
        'risk_score'      => $score,
        'summary'         => $summaries[$level],
        'recommendations' => array_slice($recs, 0, 4),
    ];
}

/* ================================================================
   Call Claude API for AI-enhanced analysis
   ================================================================ */
function callClaudeSafety(string $apiKey, string $prompt): array {
    if ($apiKey === 'YOUR_ANTHROPIC_API_KEY') {
        return ['success'=>false,'error'=>'api_key_not_set','message'=>'API key not configured'];
    }

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 600,
        'messages'   => [['role'=>'user','content'=>$prompt]]
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
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
        return ['success'=>false,'error'=>'offline','message'=>'Internet required for AI analysis. Showing local analysis instead.'];
    }
    if ($httpCode !== 200) {
        return ['success'=>false,'error'=>'api_error','message'=>"API error ($httpCode)"];
    }

    $data  = json_decode($response, true);
    $reply = trim($data['content'][0]['text'] ?? '');
    if (!$reply) return ['success'=>false,'error'=>'empty','message'=>'No AI response'];

    // Parse JSON from Claude's response
    // Strip markdown code fences if present
    $reply = preg_replace('/^```json\s*|\s*```$/m', '', $reply);
    $parsed = json_decode($reply, true);
    if (!$parsed || !isset($parsed['risk_level'])) {
        return ['success'=>false,'error'=>'parse_error','message'=>'Could not parse AI response'];
    }

    return ['success'=>true,'data'=>$parsed];
}
?>
