<?php
require_once 'db.php';
require_once 'functions.php';

try {
    // 1. Create campaign_assessments table
    $sql = "CREATE TABLE IF NOT EXISTS campaign_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metrics_row_id INT NOT NULL,
        record_id VARCHAR(255),
        campaign_code VARCHAR(100),
        assessment_date DATE,
        speed_score INT,
        leads_score INT,
        rankings_score INT,
        traffic_score INT,
        engagement_score INT,
        conversion_score INT,
        health_score DECIMAL(5,2),
        assessment_label VARCHAR(100),
        status_class VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_metrics (metrics_row_id),
        FOREIGN KEY (metrics_row_id) REFERENCES campaign_metrics(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Table 'campaign_assessments' created successfully.<br>";

    // 2. Refresh/Migrate data based on current campaign_metrics
    $stmt = $pdo->query("SELECT * FROM campaign_metrics");
    $allMetrics = $stmt->fetchAll();

    // Map metrics for lookup (same logic as before)
    $lookup = [];
    foreach ($allMetrics as $m) {
        $dateKey = normalizeDate($m['month_yr']);
        $lookup[$m['campaign']][$dateKey] = $m;
    }

    $stmtInsert = $pdo->prepare("INSERT IGNORE INTO campaign_assessments 
        (metrics_row_id, record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label, status_class)
        VALUES 
        (:metrics_id, :record_id, :campaign, :date, :speed, :leads, :rank, :traffic, :engagement, :conversion, :health, :label, :class)");

    $count = 0;
    foreach ($allMetrics as $curr) {
        $campaign = $curr['campaign'];
        $prevMonthDate = getLastMonth($curr['month_yr']);
        $prevYearDate = getLastYear($curr['month_yr']);
        
        $prevMonth = $lookup[$campaign][$prevMonthDate] ?? null;
        $prevYear = $lookup[$campaign][$prevYearDate] ?? null;

        // Perform calculations (re-using functions.php)
        $speedScore = $prevMonth ? calculateSpeedScore($curr['speed_mobile'], $curr['speed_desktop'], $prevMonth['speed_mobile'], $prevMonth['speed_desktop']) : 3;
        $rankingScore = $prevMonth ? calculateRankingScore($curr['ranking'], $prevMonth['ranking']) : 3;
        $leadsScore = $prevYear ? calculateLeadsScore($curr['leads'], $prevYear['leads']) : 3;
        $trafficScore = $prevYear ? calculateDiffPercentScore($curr['traffic'], $prevYear['traffic']) : 3;
        
        // Inline timeToSeconds for migration
        $currEng = (function($t) {
            $parts = explode(':', $t);
            return count($parts) < 2 ? 0 : ($parts[0] * 60) + $parts[1];
        })($curr['engagement']);
        
        $prevEng = $prevYear ? (function($t) {
            $parts = explode(':', $t);
            return count($parts) < 2 ? 0 : ($parts[0] * 60) + $parts[1];
        })($prevYear['engagement']) : 0;
        
        $engagementScore = $prevYear ? calculateDiffPercentScore($currEng, $prevEng) : 3;
        $convScore = calculateConversionScore($curr['leads'], $curr['traffic']);

        $avg = ($speedScore + $leadsScore + $rankingScore + $trafficScore + $engagementScore + $convScore) / 6;
        list($label, $class) = getHealthLabel($avg);

        $stmtInsert->execute([
            ':metrics_id' => $curr['id'],
            ':record_id' => $campaign . ' : ' . date('F-Y', strtotime($curr['month_yr'])),
            ':campaign' => $campaign,
            ':date' => date('Y-m-d', strtotime($curr['month_yr'])),
            ':speed' => $speedScore,
            ':leads' => $leadsScore,
            ':rank' => $rankingScore,
            ':traffic' => $trafficScore,
            ':engagement' => $engagementScore,
            ':conversion' => $convScore,
            ':health' => $avg,
            ':label' => $label,
            ':class' => $class
        ]);
        $count += $stmtInsert->rowCount();
    }

    echo "Migration complete. $count assessments generated and stored in database.";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
