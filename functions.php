<?php

/**
 * Helper to parse time string (MM:SS) to seconds
 */
function timeToSeconds($timeStr) {
    if (empty($timeStr)) return 0;
    $parts = explode(':', $timeStr);
    if (count($parts) == 1) return (int)$parts[0];
    return ($parts[0] * 60) + $parts[1];
}

/**
 * Normalizes date string to Month-Year format used in IDs
 */
function getMetricID($campaign, $dateStr) {
    $date = date_create($dateStr);
    if (!$date) return '';
    return $campaign . '-' . date_format($date, 'F-Y');
}

/**
 * Gets the Metric ID for previous month
 */
function getPrevMonthID($campaign, $dateStr) {
    $date = date_create($dateStr);
    if (!$date) return '';
    date_modify($date, '-1 month');
    return $campaign . '-' . date_format($date, 'F-Y');
}

/**
 * Gets the Metric ID for previous year
 */
function getPrevYearID($campaign, $dateStr) {
    $date = date_create($dateStr);
    if (!$date) return '';
    date_modify($date, '-1 year');
    return $campaign . '-' . date_format($date, 'F-Y');
}

/**
 * Airtable 'comparison' logic
 * Returns percentage change or absolute value if oldData is 0
 */
function comparison($newData, $oldData) {
    // Exact replication of JS logic: 
    // let computation = Math.round(newData * 100);
    // if (oldData != 0 && newData != null) { ... }
    
    $computation = round($newData * 100);
    
    // In JS, null != 0 is true. In PHP, null != 0 is false.
    // To match JS, we check if $oldData is not zero and not null.
    if ($oldData !== null && $oldData != 0 && $newData !== null) {
        return round((($newData - $oldData) / $oldData) * 100);
    } elseif ($oldData === 0 || $oldData === "0" || $oldData === 0.0) {
        return round($newData);
    } elseif ($oldData === null) {
        // This matches the JS 'computation' default before the if blocks
        return $computation;
    }
    
    return $computation;
}

/**
 * Airtable 'between' logic
 */
function between($score, $r1, $r2, $r3, $r4, $r5, $r6, $r7, $r8) {
    if ($score === null) return 404;
    
    if ($score <= $r1) return 1;
    if ($score >= $r2 && $score <= $r3) return 2;
    if ($score >= $r4 && $score <= $r5) return 3;
    if ($score >= $r6 && $score <= $r7) return 4;
    if ($score >= $r8) return 5;
    
    return 3; // Fallback
}

/**
 * Health Score Label
 */
function getHealthLabel($score) {
    if ($score < 2) return ["Poor Performance", "status-poor"];
    if ($score <= 2.9) return ["Stable", "status-stable"];
    if ($score <= 3.7) return ["Meets Expectations", "status-meets"];
    if ($score <= 4.2) return ["Exceeds Expectations", "status-exceeds"];
    return ["Excellent Performance", "status-excellent"];
}

/**
 * Runs automated assessment for a list of metric rows.
 * @param array $metricsRows Array of metric objects from the database
 * @param PDO $pdo Shared PDO connection
 * @return int Number of rows processed
 */
function runAutomatedAssessmentForMetrics($metricsRows, $pdo) {
    // 1. Build lookup table for easy access to previous months/years
    $stmtAll = $pdo->query("SELECT * FROM campaign_metrics");
    $allMetrics = $stmtAll->fetchAll();
    $lookup = [];
    foreach ($allMetrics as $m) {
        $mid = $m['campaign'] . '-' . date('F-Y', strtotime($m['month_yr']));
        $lookup[$mid] = $m;
    }

    $sql = "INSERT INTO campaign_assessments 
        (metrics_row_id, record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label, status_class)
        VALUES 
        (:mid, :rid, :c, :d, :s, :l, :r, :t, :e, :conv, :h, :lbl, :cls)
        ON DUPLICATE KEY UPDATE 
        speed_score=:s, leads_score=:l, rankings_score=:r, traffic_score=:t, engagement_score=:e, conversion_score=:conv, health_score=:h, assessment_label=:lbl, status_class=:cls";
    $stmtIns = $pdo->prepare($sql);

    $count = 0;
    foreach ($metricsRows as $m) {
        $campaign = $m['campaign'];
        $dateStr = $m['month_yr'];
        
        $prevMonthID = getPrevMonthID($campaign, $dateStr);
        $prevYearID = getPrevYearID($campaign, $dateStr);

        $prevMonth = $lookup[$prevMonthID] ?? null;
        $prevYear = $lookup[$prevYearID] ?? null;

        $curData = [
            $m['speed_avg'],
            $m['leads'],
            $m['ranking'],
            $m['traffic'],
            timeToSeconds($m['engagement']),
            $m['conversion']
        ];

        $prevData = [
            $prevYear ? $prevYear['speed_avg'] : null,
            $prevYear ? $prevYear['leads'] : null,
            $prevMonth ? $prevMonth['ranking'] : null,
            $prevYear ? $prevYear['traffic'] : null,
            $prevYear ? timeToSeconds($prevYear['engagement']) : null,
            $prevYear ? $prevYear['conversion'] : null
        ];

        $speed = between(round($curData[0] * 100), 49, 50, 63, 64, 76, 77, 89, 90);
        $leads = between(comparison($curData[1], $prevData[1]), -50, -49, -16, -15, 0, 1, 10, 11);
        $rank  = between(comparison($curData[2], $prevData[2]), -50, -49, -6, -5, 5, 6, 20, 21);
        $traffic = between(comparison($curData[3], $prevData[3]), -50, -49, -16, -15, 0, 1, 10, 11);
        $engagement = between($curData[4], 30, 31, 60, 61, 90, 91, 120, 121);
        $conversion = between(comparison($curData[5], $prevData[5]), 1, 1.1, 2, 2.1, 3, 3.1, 5, 5.5);

        $avg = ($speed + $leads + $rank + $traffic + $engagement + $conversion) / 6;
        list($label, $class) = getHealthLabel($avg);

        $stmtIns->execute([
            ':mid' => $m['id'],
            ':rid' => $campaign . ' : ' . date('F-Y', strtotime($dateStr)),
            ':c' => $campaign,
            ':d' => date('Y-m-d', strtotime($dateStr)),
            ':s' => $speed, ':l' => $leads, ':r' => $rank, ':t' => $traffic, ':e' => $engagement, ':conv' => $conversion, ':h' => $avg, ':lbl' => $label, ':cls' => $class
        ]);
        $count++;
    }
    
    return $count;
}
