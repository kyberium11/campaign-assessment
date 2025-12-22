<?php
require_once 'db.php';

try {
    // 1. Create Table
    $sql = "CREATE TABLE IF NOT EXISTS campaign_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metrics_id VARCHAR(255) NOT NULL,
        campaign VARCHAR(255) NOT NULL,
        month_yr VARCHAR(50), 
        speed_mobile DECIMAL(5, 2),
        speed_desktop DECIMAL(5, 2),
        speed_avg DECIMAL(5, 2),
        leads INT DEFAULT 0,
        ranking INT DEFAULT 0,
        traffic INT DEFAULT 0,
        engagement VARCHAR(50),
        conversion DECIMAL(10, 2)
    )";
    $pdo->exec($sql);
    echo "Table 'campaign_metrics' created successfully.<br>";

    // 2. Import Data from data.php
    if (file_exists('data.php')) {
        require_once 'data.php';
        $data = getCampaignMetrics();

        // Check if table is empty to avoid duplicates on refresh
        $stmt = $pdo->query("SELECT COUNT(*) FROM campaign_metrics");
        if ($stmt->fetchColumn() == 0) {
            $insertSql = "INSERT INTO campaign_metrics 
                (metrics_id, campaign, month_yr, speed_mobile, speed_desktop, speed_avg, leads, ranking, traffic, engagement, conversion) 
                VALUES 
                (:metrics_id, :campaign, :month_yr, :speed_mobile, :speed_desktop, :speed_avg, :leads, :ranking, :traffic, :engagement, :conversion)";
            
            $stmt = $pdo->prepare($insertSql);

            $count = 0;
            foreach ($data as $row) {
                $stmt->execute([
                    ':metrics_id' => $row['metrics_id'],
                    ':campaign' => $row['campaign'],
                    ':month_yr' => $row['month_yr'],
                    ':speed_mobile' => $row['speed_mobile'], // Already decimal 0.XX
                    ':speed_desktop' => $row['speed_desktop'],
                    ':speed_avg' => $row['speed_avg'],
                    ':leads' => $row['leads'],
                    ':ranking' => $row['ranking'],
                    ':traffic' => $row['traffic'],
                    ':engagement' => $row['engagement'],
                    ':conversion' => $row['conversion']
                ]);
                $count++;
            }
            echo "Imported $count rows into database.<br>";
        } else {
            echo "Table already has data. Skipping import.<br>";
        }
    } else {
        echo "data.php not found. No data imported.<br>";
    }

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
