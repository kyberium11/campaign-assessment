<?php
require_once 'db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'update_cell') {
    $id = $_POST['id'] ?? null;
    $column = $_POST['column'] ?? null;
    $value = $_POST['value'] ?? null;

    // Whitelist allowed columns to prevent SQL injection via column name
    $allowedColumns = [
        'metrics_id', 'campaign', 'month_yr', 'speed_mobile', 
        'speed_desktop', 'speed_avg', 'leads', 'ranking', 
        'traffic', 'engagement', 'conversion'
    ];

    if (!$id || !in_array($column, $allowedColumns)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Special handling if needed (e.g., removing % for db storage)
    // Assuming frontend sends raw value, but we might need to sanitize percentages if they still have %
    if (in_array($column, ['speed_mobile', 'speed_desktop', 'speed_avg'])) {
        $value = floatval(str_replace('%', '', $value)) / 100;
    }

    try {
        $stmt = $pdo->prepare("UPDATE campaign_metrics SET $column = :value WHERE id = :id");
        $stmt->execute([':value' => $value, ':id' => $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'create_row') {
    // Extract fields
    $fields = [
        'metrics_id', 'campaign', 'month_yr', 'speed_mobile', 
        'speed_desktop', 'speed_avg', 'leads', 'ranking', 
        'traffic', 'engagement', 'conversion'
    ];
    
    $data = [];
    $placeholders = [];
    $sqlCols = [];

    foreach ($fields as $field) {
        $val = $_POST[$field] ?? null;
        
        // Sanitize percentages
        if (in_array($field, ['speed_mobile', 'speed_desktop', 'speed_avg'])) {
             $val = floatval(str_replace('%', '', $val)) / 100;
        }

        $data[":$field"] = $val;
        $placeholders[] = ":$field";
        $sqlCols[] = $field;
    }

    try {
        $sql = "INSERT INTO campaign_metrics (" . implode(',', $sqlCols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_row') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM campaign_metrics WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Row not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'import_csv') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed or no file selected']);
        exit;
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    if ($handle === FALSE) {
        echo json_encode(['success' => false, 'message' => 'Could not open CSV file']);
        exit;
    }

    // Skip header row if necessary. Uncomment if CSV has headers.
    // fgetcsv($handle); 

    $imported = 0;
    $skipped = 0;
    $errors = 0;

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM campaign_metrics WHERE metrics_id = :metrics_id");
    
    // Prepared statement for insert
    $sqlInsert = "INSERT INTO campaign_metrics 
        (metrics_id, campaign, month_yr, speed_mobile, speed_desktop, speed_avg, leads, ranking, traffic, engagement, conversion) 
        VALUES 
        (:metrics_id, :campaign, :month_yr, :speed_mobile, :speed_desktop, :speed_avg, :leads, :ranking, :traffic, :engagement, :conversion)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // Adjust column mapping based on Expected CSV Format
        // Assuming CSV order: Metrics_ID, Campaign, MonthYr, Mobile%, Desktop%, Avg%, Leads, Ranking, Traffic, Engagement, Conversion
        
        // Basic validation: ensure at least metrics_id exists
        if (!isset($row[0]) || empty($row[0])) {
            continue; 
        }

        $metrics_id = trim($row[0]);
        
        // Check for duplicate
        $stmtCheck->execute([':metrics_id' => $metrics_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        try {
            // Parse Values
            $campaign = $row[1] ?? '';
            $month_yr = $row[2] ?? '';
            
            // Handle percentages (remove % and divide by 100)
            $speed_mobile  = floatval(str_replace(['%', ' '], '', $row[3] ?? '0')) / 100;
            $speed_desktop = floatval(str_replace(['%', ' '], '', $row[4] ?? '0')) / 100;
            $speed_avg     = floatval(str_replace(['%', ' '], '', $row[5] ?? '0')) / 100;
            
            // Handle numbers (remove commas)
            $leads    = intval(str_replace(',', '', $row[6] ?? '0'));
            $ranking  = intval(str_replace(',', '', $row[7] ?? '0'));
            $traffic  = intval(str_replace(',', '', $row[8] ?? '0'));
            
            $engagement = $row[9] ?? '0:00';
            $conversion = floatval($row[10] ?? 0);

            $stmtInsert->execute([
                ':metrics_id' => $metrics_id,
                ':campaign' => $campaign,
                ':month_yr' => $month_yr,
                ':speed_mobile' => $speed_mobile,
                ':speed_desktop' => $speed_desktop,
                ':speed_avg' => $speed_avg,
                ':leads' => $leads,
                ':ranking' => $ranking,
                ':traffic' => $traffic,
                ':engagement' => $engagement,
                ':conversion' => $conversion,
            ]);
            $imported++;

        } catch (Exception $e) {
            $errors++;
        }
    }

    fclose($handle);
    echo json_encode([
        'success' => true, 
        'message' => "Import complete. Imported: $imported, Skipped (Duplicate): $skipped, Errors: $errors"
    ]);
    exit;
}

// Handle pasted spreadsheet data
if ($action === 'import_paste') {
    $rawData = $_POST['data'] ?? '';
    $rows = json_decode($rawData, true);

    if (!is_array($rows) || empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'No valid data received']);
        exit;
    }

    $imported = 0;
    $skipped = 0;
    $errors = 0;

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM campaign_metrics WHERE metrics_id = :metrics_id");
    
    $sqlInsert = "INSERT INTO campaign_metrics 
        (metrics_id, campaign, month_yr, speed_mobile, speed_desktop, speed_avg, leads, ranking, traffic, engagement, conversion) 
        VALUES 
        (:metrics_id, :campaign, :month_yr, :speed_mobile, :speed_desktop, :speed_avg, :leads, :ranking, :traffic, :engagement, :conversion)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    foreach ($rows as $row) {
        $metrics_id = trim($row['metrics_id'] ?? '');
        
        if (empty($metrics_id)) {
            continue;
        }

        // Check for duplicate
        $stmtCheck->execute([':metrics_id' => $metrics_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        try {
            // Parse values - similar to CSV import
            $campaign = $row['campaign'] ?? '';
            $month_yr = $row['month_yr'] ?? '';
            
            // Handle percentages (remove % and divide by 100)
            $speed_mobile  = floatval(str_replace(['%', ' '], '', $row['speed_mobile'] ?? '0')) / 100;
            $speed_desktop = floatval(str_replace(['%', ' '], '', $row['speed_desktop'] ?? '0')) / 100;
            $speed_avg     = floatval(str_replace(['%', ' '], '', $row['speed_avg'] ?? '0')) / 100;
            
            // Handle numbers (remove commas)
            $leads    = intval(str_replace(',', '', $row['leads'] ?? '0'));
            $ranking  = intval(str_replace(',', '', $row['ranking'] ?? '0'));
            $traffic  = intval(str_replace(',', '', $row['traffic'] ?? '0'));
            
            $engagement = $row['engagement'] ?? '0:00';
            $conversion = floatval($row['conversion'] ?? 0);

            $stmtInsert->execute([
                ':metrics_id' => $metrics_id,
                ':campaign' => $campaign,
                ':month_yr' => $month_yr,
                ':speed_mobile' => $speed_mobile,
                ':speed_desktop' => $speed_desktop,
                ':speed_avg' => $speed_avg,
                ':leads' => $leads,
                ':ranking' => $ranking,
                ':traffic' => $traffic,
                ':engagement' => $engagement,
                ':conversion' => $conversion,
            ]);
            $imported++;

        } catch (Exception $e) {
            $errors++;
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => "Paste import complete. Imported: $imported, Skipped (Duplicate): $skipped, Errors: $errors"
    ]);
    exit;
}

// --- Assessment Actions ---

if ($action === 'create_assessment') {
    $metrics_id = $_POST['metrics_row_id'] ?? null;
    if (!$metrics_id) {
        echo json_encode(['success' => false, 'message' => 'Metric ID required']);
        exit;
    }

    try {
        // Fetch current metric and neighbors for calculation
        $stmt = $pdo->prepare("SELECT * FROM campaign_metrics WHERE id = :id");
        $stmt->execute([':id' => $metrics_id]);
        $curr = $stmt->fetch();

        if (!$curr) {
            echo json_encode(['success' => false, 'message' => 'Metric not found']);
            exit;
        }

        require_once 'functions.php';
        
        $campaign = $curr['campaign'];
        $prevMonthDate = getLastMonth($curr['month_yr']);
        $prevYearDate = getLastYear($curr['month_yr']);

        $stmtPrev = $pdo->prepare("SELECT * FROM campaign_metrics WHERE campaign = :c AND month_yr LIKE :d LIMIT 1");
        $stmtPrev->execute([':c' => $campaign, ':d' => "%$prevMonthDate%"]);
        $prevMonth = $stmtPrev->fetch();

        $stmtYear = $pdo->prepare("SELECT * FROM campaign_metrics WHERE campaign = :c AND month_yr LIKE :d LIMIT 1");
        $stmtYear->execute([':c' => $campaign, ':d' => "%$prevYearDate%"]);
        $prevYear = $stmtYear->fetch();

        // Standard Calculations
        $speedScore = $prevMonth ? calculateSpeedScore($curr['speed_mobile'], $curr['speed_desktop'], $prevMonth['speed_mobile'], $prevMonth['speed_desktop']) : 3;
        $rankingScore = $prevMonth ? calculateRankingScore($curr['ranking'], $prevMonth['ranking']) : 3;
        $leadsScore = $prevYear ? calculateLeadsScore($curr['leads'], $prevYear['leads']) : 3;
        $trafficScore = $prevYear ? calculateDiffPercentScore($curr['traffic'], $prevYear['traffic']) : 3;
        
        $currEng = (function($t) { $parts = explode(':', $t); return count($parts) < 2 ? 0 : ($parts[0] * 60) + $parts[1]; })($curr['engagement']);
        $prevEng = $prevYear ? (function($t) { $parts = explode(':', $t); return count($parts) < 2 ? 0 : ($parts[0] * 60) + $parts[1]; })($prevYear['engagement']) : 0;
        $engagementScore = $prevYear ? calculateDiffPercentScore($currEng, $prevEng) : 3;
        $convScore = calculateConversionScore($curr['leads'], $curr['traffic']);

        $avg = ($speedScore + $leadsScore + $rankingScore + $trafficScore + $engagementScore + $convScore) / 6;
        list($label, $class) = getHealthLabel($avg);

        $sql = "INSERT INTO campaign_assessments 
            (metrics_row_id, record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label, status_class)
            VALUES 
            (:mid, :rid, :c, :d, :s, :l, :r, :t, :e, :conv, :h, :lbl, :cls)
            ON DUPLICATE KEY UPDATE 
            speed_score=:s, leads_score=:l, rankings_score=:r, traffic_score=:t, engagement_score=:e, conversion_score=:conv, health_score=:h, assessment_label=:lbl, status_class=:cls";
        
        $stmtIns = $pdo->prepare($sql);
        $stmtIns->execute([
            ':mid' => $curr['id'],
            ':rid' => $campaign . ' : ' . date('F-Y', strtotime($curr['month_yr'])),
            ':c' => $campaign,
            ':d' => date('Y-m-d', strtotime($curr['month_yr'])),
            ':s' => $speedScore, ':l' => $leadsScore, ':r' => $rankingScore, ':t' => $trafficScore, ':e' => $engagementScore, ':conv' => $convScore, ':h' => $avg, ':lbl' => $label, ':cls' => $class
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_assessment_cell') {
    $id = $_POST['id'] ?? null;
    $column = $_POST['column'] ?? null;
    $value = $_POST['value'] ?? null;

    $allowed = ['speed_score', 'leads_score', 'rankings_score', 'traffic_score', 'engagement_score', 'conversion_score'];
    if (!$id || !in_array($column, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid update']);
        exit;
    }

    try {
        // Update the cell
        $stmt = $pdo->prepare("UPDATE campaign_assessments SET $column = :v WHERE id = :id");
        $stmt->execute([':v' => $value, ':id' => $id]);

        // Recalculate health score & label
        $stmtRow = $pdo->prepare("SELECT * FROM campaign_assessments WHERE id = :id");
        $stmtRow->execute([':id' => $id]);
        $row = $stmtRow->fetch();
        
        $avg = ($row['speed_score'] + $row['leads_score'] + $row['rankings_score'] + $row['traffic_score'] + $row['engagement_score'] + $row['conversion_score']) / 6;
        require_once 'functions.php';
        list($label, $class) = getHealthLabel($avg);

        $stmtUpd = $pdo->prepare("UPDATE campaign_assessments SET health_score = :h, assessment_label = :l, status_class = :c WHERE id = :id");
        $stmtUpd->execute([':h' => $avg, ':l' => $label, ':c' => $class, ':id' => $id]);

        echo json_encode(['success' => true, 'health' => number_format($avg, 2), 'label' => $label, 'class' => $class]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_assessment') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM campaign_assessments WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
