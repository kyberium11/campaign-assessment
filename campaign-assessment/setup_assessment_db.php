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
    $pdo->exec("TRUNCATE TABLE campaign_assessments");
    echo "Table 'campaign_assessments' cleared.<br>";

    // Get all metrics
    $stmt = $pdo->query("SELECT * FROM campaign_metrics");
    $metrics = $stmt->fetchAll();

    // Map by metrics_id for easy lookups
    $lookup = [];
    foreach ($metrics as $m) {
        $lookup[$m['metrics_id']] = $m;
    }

    $insertStmt = $pdo->prepare("INSERT INTO campaign_assessments 
        (metrics_row_id, record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label, status_class)
        VALUES 
        (:metrics_id, :record_id, :campaign, :date, :speed, :leads, :rank, :traffic, :engagement, :conversion, :health, :label, :class)");

    $count = 0;
    foreach ($metrics as $m) {
        $campaign = $m['campaign'];
        $dateStr = $m['month_yr'];
        
        $prevMonthID = getPrevMonthID($campaign, $dateStr);
        $prevYearID = getPrevYearID($campaign, $dateStr);

        $prevMonth = $lookup[$prevMonthID] ?? null;
        $prevYear = $lookup[$prevYearID] ?? null;

        // Current data values
        $curData = [
            $m['speed_avg'],
            $m['leads'],
            $m['ranking'],
            $m['traffic'],
            timeToSeconds($m['engagement']),
            $m['conversion']
        ];

        // Previous data values (matching Airtable script logic)
        $prevData = [
            $prevYear ? $prevYear['speed_avg'] : null,
            $prevYear ? $prevYear['leads'] : null,
            $prevMonth ? $prevMonth['ranking'] : null,
            $prevYear ? $prevYear['traffic'] : null,
            $prevYear ? timeToSeconds($prevYear['engagement']) : null,
            $prevYear ? $prevYear['conversion'] : null
        ];

        // Scoring (Matching Airtable Script ranges)
        $speed = between(round($curData[0] * 100), 49, 50, 63, 64, 76, 77, 89, 90);
        $leads = between(comparison($curData[1], $prevData[1]), -50, -49, -16, -15, 0, 1, 10, 11);
        $rank  = between(comparison($curData[2], $prevData[2]), -50, -49, -6, -5, 5, 6, 20, 21);
        $traffic = between(comparison($curData[3], $prevData[3]), -50, -49, -16, -15, 0, 1, 10, 11);
        $engagement = between($curData[4], 30, 31, 60, 61, 90, 91, 120, 121);
        $conversion = between(comparison($curData[5], $prevData[5]), 1, 1.1, 2, 2.1, 3, 3.1, 5, 5.5);

        $avg = ($speed + $leads + $rank + $traffic + $engagement + $conversion) / 6;
        list($label, $class) = getHealthLabel($avg);

        $insertStmt->execute([
            ':metrics_id'   => $m['id'],
            ':record_id'    => $campaign . ' : ' . date('F-Y', strtotime($dateStr)),
            ':campaign'     => $campaign,
            ':date'         => date('Y-m-d', strtotime($dateStr)),
            ':speed'        => $speed,
            ':leads'        => $leads,
            ':rank'         => $rank,
            ':traffic'      => $traffic,
            ':engagement'   => $engagement,
            ':conversion'   => $conversion,
            ':health'       => $avg,
            ':label'        => $label,
            ':class'        => $class
        ]);
        $count++;
    }

    echo "Assessed $count campaigns and stored results.<br>";

} catch (Exception $e) {
    die("Assessment failed: " . $e->getMessage());
}
