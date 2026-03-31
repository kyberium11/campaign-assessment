<?php
require_once 'db.php';

// Path to metrics CSV
$csvFile = 'Campaign Metrics-All Campaign Metrics.csv';

if (!file_exists($csvFile)) {
    die("CSV file not found: $csvFile");
}

try {
    // Clear the table to ensure clean import
    $pdo->exec("TRUNCATE TABLE campaign_metrics");
    echo "Table 'campaign_metrics' truncated.<br>";

    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle); // Skip header

    $insertSql = "INSERT INTO campaign_metrics 
        (metrics_id, campaign, month_yr, speed_mobile, speed_desktop, speed_avg, leads, ranking, traffic, engagement, conversion) 
        VALUES 
        (:metrics_id, :campaign, :month_yr, :speed_mobile, :speed_desktop, :speed_avg, :leads, :ranking, :traffic, :engagement, :conversion)";
    
    $stmt = $pdo->prepare($insertSql);

    $count = 0;
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 11) continue;

        // Parse Speed percentage to decimal
        $speed_mobile = floatval(str_replace('%', '', $row[3] ?? 0)) / 100;
        $speed_desktop = floatval(str_replace('%', '', $row[4] ?? 0)) / 100;
        $speed_avg = floatval(str_replace('%', '', $row[5] ?? 0)) / 100;

        $stmt->execute([
            ':metrics_id'   => $row[0],
            ':campaign'     => $row[1],
            ':month_yr'     => $row[2],
            ':speed_mobile'  => $speed_mobile,
            ':speed_desktop' => $speed_desktop,
            ':speed_avg'     => $speed_avg,
            ':leads'        => (int)str_replace(',', '', $row[6] ?? 0),
            ':ranking'      => (int)str_replace(',', '', $row[7] ?? 0),
            ':traffic'      => (int)str_replace(',', '', $row[8] ?? 0),
            ':engagement'   => $row[9],
            ':conversion'   => (float)($row[10] ?? 0)
        ]);
        $count++;
    }
    fclose($handle);
    echo "Imported $count rows from CSV into 'campaign_metrics'.<br>";

} catch (Exception $e) {
    die("Import failed: " . $e->getMessage());
}
