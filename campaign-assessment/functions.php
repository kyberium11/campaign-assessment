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
