<?php
require_once 'db.php';
require_once 'functions.php';

// Fetch all metrics to build a lookup map
try {
    $stmt = $pdo->query("SELECT * FROM campaign_metrics ORDER BY id DESC");
    $allMetrics = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

/**
 * Build a lookup map: [campaign][YYYY-MM] => row
 */
$lookup = [];
foreach ($allMetrics as $m) {
    $dateKey = normalizeDate($m['month_yr']);
    $lookup[$m['campaign']][$dateKey] = $m;
}

$results = [];

// Process assessment for each record
foreach ($allMetrics as $curr) {
    $campaign = $curr['campaign'];
    $currDate = normalizeDate($curr['month_yr']);
    
    $prevMonthDate = getLastMonth($curr['month_yr']);
    $prevYearDate = getLastYear($curr['month_yr']);
    
    $prevMonth = $lookup[$campaign][$prevMonthDate] ?? null;
    $prevYear = $lookup[$campaign][$prevYearDate] ?? null;

    // 1. Website Speed Score
    $speedScore = 3; // default
    if ($prevMonth) {
        $speedScore = calculateSpeedScore(
            $curr['speed_mobile'], $curr['speed_desktop'],
            $prevMonth['speed_mobile'], $prevMonth['speed_desktop']
        );
    }

    // 2. Ranking Score
    $rankingScore = 3;
    if ($prevMonth) {
        $rankingScore = calculateRankingScore($curr['ranking'], $prevMonth['ranking']);
    }

    // 3. Leads Score
    $leadsScore = 3;
    if ($prevYear) {
        $leadsScore = calculateLeadsScore($curr['leads'], $prevYear['leads']);
    }

    // 4. Traffic Score
    $trafficScore = 3;
    if ($prevYear) {
        $trafficScore = calculateDiffPercentScore($curr['traffic'], $prevYear['traffic']);
    }

    // 5. Engagement Score
    // Note: engagement is currently a string 0:00. Convert to seconds for calculation.
    function timeToSeconds($t) {
        $parts = explode(':', $t);
        if (count($parts) < 2) return 0;
        return ($parts[0] * 60) + $parts[1];
    }
    
    $engagementScore = 3;
    if ($prevYear) {
        $currEng = timeToSeconds($curr['engagement']);
        $prevEng = timeToSeconds($prevYear['engagement']);
        $engagementScore = calculateDiffPercentScore($currEng, $prevEng);
    }

    // 6. Conversion Score
    $convScore = calculateConversionScore($curr['leads'], $curr['traffic']);

    // Health Score (Average)
    $allScores = [$speedScore, $leadsScore, $rankingScore, $trafficScore, $engagementScore, $convScore];
    $healthScore = array_sum($allScores) / count($allScores);
    list($healthLabel, $healthClass) = getHealthLabel($healthScore);

    $results[] = [
        'record_id' => $curr['metrics_id'] . ' : ' . date('F Y', strtotime($curr['month_yr'])),
        'campaign_code' => $campaign,
        'date' => date('F d, Y', strtotime($curr['month_yr'])),
        'speed' => $speedScore,
        'leads' => $leadsScore,
        'rankings' => $rankingScore,
        'traffic' => $trafficScore,
        'engagement' => $engagementScore,
        'conversion' => $convScore,
        'health' => number_format($healthScore, 2),
        'assessment' => $healthLabel,
        'assessment_class' => $healthClass
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Assessment (Automated)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav>
        <a href="index.php">Campaign Metrics</a>
        <a href="assessment.php" class="active">Campaign Assessment (Automated)</a>
    </nav>

    <header style="padding: 0 20px;">
        <h1>Campaign Assessment (Automated)</h1>
    </header>

    <div class="table-container" style="margin: 20px; border-radius: 6px; border: 1px solid var(--border-color);">
        <table>
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>Campaign Code</th>
                    <th>Date</th>
                    <th class="col-center"># Website Speed</th>
                    <th class="col-center"># Leads</th>
                    <th class="col-center"># Rankings</th>
                    <th class="col-center"># Traffic</th>
                    <th class="col-center"># Engagement</th>
                    <th class="col-center"># Conversion</th>
                    <th class="col-center">Health Score</th>
                    <th>Campaign Assessment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $res): ?>
                <tr>
                    <td style="color: #57606a; font-size: 11px;"><?= $res['record_id'] ?></td>
                    <td><span class="campaign-badge"><?= $res['campaign_code'] ?></span></td>
                    <td><?= $res['date'] ?></td>
                    <td class="col-center"><?= $res['speed'] ?></td>
                    <td class="col-center"><?= $res['leads'] ?></td>
                    <td class="col-center"><?= $res['rankings'] ?></td>
                    <td class="col-center"><?= $res['traffic'] ?></td>
                    <td class="col-center"><?= $res['engagement'] ?></td>
                    <td class="col-center"><?= $res['conversion'] ?></td>
                    <td class="col-center" style="font-weight: 600;"><?= $res['health'] ?></td>
                    <td><span class="status-badge <?= $res['assessment_class'] ?>"><?= $res['assessment'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
