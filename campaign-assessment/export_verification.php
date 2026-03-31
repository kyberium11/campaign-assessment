<?php
require_once 'db.php';

$outputFile = 'verification_results_Feb2026.csv';

try {
    $stmt = $pdo->prepare("SELECT record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label 
                           FROM campaign_assessments 
                           WHERE assessment_date = '2026-02-01'");
    $stmt->execute();
    $results = $stmt->fetchAll();

    $handle = fopen($outputFile, 'w');
    fputcsv($handle, ['Record ID', 'Campaign Code', 'Date 1', 'Website Speed', 'Leads', 'Rankings', 'Traffic', 'Engagement', 'Conversion', 'Health Score', 'Campaign Assessment']);

    foreach ($results as $row) {
        // Format date to match user's CSV (February 1, 2026)
        $row['assessment_date'] = date('F j, Y', strtotime($row['assessment_date']));
        fputcsv($handle, $row);
    }
    fclose($handle);
    echo "Exported February 2026 results to $outputFile.<br>";

} catch (Exception $e) {
    die("Export failed: " . $e->getMessage());
}
