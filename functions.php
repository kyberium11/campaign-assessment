<?php

/**
 * Normalizes date string to YYYY-MM
 */
function normalizeDate($dateStr) {
    if (empty($dateStr)) return '';
    $date = date_create($dateStr);
    return $date ? date_format($date, 'Y-m') : '';
}

/**
 * Gets the date string for "last month" given a date string
 */
function getLastMonth($dateStr) {
    $date = date_create($dateStr);
    if (!$date) return '';
    date_modify($date, '-1 month');
    return date_format($date, 'Y-m');
}

/**
 * Gets the date string for "last year" given a date string
 */
function getLastYear($dateStr) {
    $date = date_create($dateStr);
    if (!$date) return '';
    date_modify($date, '-1 year');
    return date_format($date, 'Y-m');
}

/**
 * Website Speed Scoring
 * Computation: Percentage change of (Mobile*0.6 + Desktop*0.4) this month vs last month
 */
function calculateSpeedScore($currentMobile, $currentDesktop, $prevMobile, $prevDesktop) {
    $currentWeighted = ($currentMobile * 0.6) + ($currentDesktop * 0.4);
    $prevWeighted = ($prevMobile * 0.6) + ($prevDesktop * 0.4);

    if ($prevWeighted == 0) return 3; // Neutral if no prev data

    $change = ($currentWeighted / $prevWeighted);
    
    if ($change >= 0.95) return 5;
    if ($change >= 0.85) return 4;
    if ($change >= 0.75) return 3;
    if ($change >= 0.50) return 2;
    return 1;
}

/**
 * Ranking Score (Keywords change vs last month)
 */
function calculateRankingScore($curr, $prev) {
    $diff = $curr - $prev;
    if ($diff >= 20) return 5;
    if ($diff >= 10) return 4;
    if ($diff >= -10) return 3;
    if ($diff >= -19) return 2;
    return 1;
}

/**
 * Leads Score (Change vs last year)
 */
function calculateLeadsScore($curr, $prev) {
    $diff = $curr - $prev;
    if ($diff >= 20) return 5;
    if ($diff >= 10) return 4;
    if ($diff >= -10) return 3;
    if ($diff >= -19) return 2;
    return 1;
}

/**
 * Traffic/Engagement Score (% Change vs last year)
 */
function calculateDiffPercentScore($curr, $prev) {
    if ($prev == 0) return 3;
    $percentChange = (($curr - $prev) / $prev);
    
    if ($percentChange >= 0.20) return 5;
    if ($percentChange >= 0.10) return 4;
    if ($percentChange >= -0.10) return 3;
    if ($percentChange >= -0.19) return 2;
    return 1;
}

/**
 * Conversion Score (Leads/Traffic * 100)
 */
function calculateConversionScore($leads, $traffic) {
    if ($traffic == 0) return 0;
    $conv = ($leads / $traffic) * 100;
    
    if ($conv >= 5) return 5;
    if ($conv >= 4) return 4;
    if ($conv >= 3) return 3;
    if ($conv >= 2) return 2;
    if ($conv >= 1) return 1;
    return 0;
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
