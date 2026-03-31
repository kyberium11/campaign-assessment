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

    // Skip header row
    fgetcsv($handle); 

    $imported = 0;
    
    try {
        $sqlInsert = "INSERT INTO campaign_metrics 
            (metrics_id, campaign, month_yr, speed_mobile, speed_desktop, speed_avg, leads, ranking, traffic, engagement, conversion) 
            VALUES 
            (:metrics_id, :campaign, :month_yr, :speed_mobile, :speed_desktop, :speed_avg, :leads, :ranking, :traffic, :engagement, :conversion)
            ON DUPLICATE KEY UPDATE 
            campaign=:campaign, month_yr=:month_yr, speed_mobile=:speed_mobile, speed_desktop=:speed_desktop, speed_avg=:speed_avg, leads=:leads, ranking=:ranking, traffic=:traffic, engagement=:engagement, conversion=:conversion";
        
        $stmtInsert = $pdo->prepare($sqlInsert);

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) < 11) continue;

            $metrics_id = trim($row[0]);
            if (empty($metrics_id)) continue;

            $speed_avg = floatval(str_replace('%', '', $row[5] ?? 0)) / 100;
            $speed_mobile = floatval(str_replace('%', '', $row[3] ?? 0)) / 100;
            $speed_desktop = floatval(str_replace('%', '', $row[4] ?? 0)) / 100;

            $stmtInsert->execute([
                ':metrics_id'   => $metrics_id,
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
            $imported++;
        }
        fclose($handle);
        echo json_encode(['success' => true, 'message' => "Import complete. Processed $imported records."]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
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
        require_once 'functions.php';
        
        $stmt = $pdo->prepare("SELECT * FROM campaign_metrics WHERE id = :id");
        $stmt->execute([':id' => $metrics_id]);
        $curr = $stmt->fetch();

        if (!$curr) {
            echo json_encode(['success' => false, 'message' => 'Metric not found']);
            exit;
        }

        // Get all metrics for lookup
        $metricsStmt = $pdo->query("SELECT * FROM campaign_metrics");
        $allMetrics = $metricsStmt->fetchAll();
        $lookup = [];
        foreach ($allMetrics as $m) {
            $lookup[$m['metrics_id']] = $m;
        }

        $campaign = $curr['campaign'];
        $dateStr = $curr['month_yr'];
        
        $prevMonthID = getPrevMonthID($campaign, $dateStr);
        $prevYearID = getPrevYearID($campaign, $dateStr);

        $prevMonth = $lookup[$prevMonthID] ?? null;
        $prevYear = $lookup[$prevYearID] ?? null;

        $curData = [
            $curr['speed_avg'],
            $curr['leads'],
            $curr['ranking'],
            $curr['traffic'],
            timeToSeconds($curr['engagement']),
            $curr['conversion']
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

        $sql = "INSERT INTO campaign_assessments 
            (metrics_row_id, record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label, status_class)
            VALUES 
            (:mid, :rid, :c, :d, :s, :l, :r, :t, :e, :conv, :h, :lbl, :cls)
            ON DUPLICATE KEY UPDATE 
            speed_score=:s, leads_score=:l, rankings_score=:r, traffic_score=:t, engagement_score=:e, conversion_score=:conv, health_score=:h, assessment_label=:lbl, status_class=:cls";
        
        $stmtIns = $pdo->prepare($sql);
        $stmtIns->execute([
            ':mid' => $curr['id'],
            ':rid' => $campaign . ' : ' . date('F-Y', strtotime($dateStr)),
            ':c' => $campaign,
            ':d' => date('Y-m-d', strtotime($dateStr)),
            ':s' => $speed, ':l' => $leads, ':r' => $rank, ':t' => $traffic, ':e' => $engagement, ':conv' => $conversion, ':h' => $avg, ':lbl' => $label, ':cls' => $class
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'run_full_assessment') {
    try {
        require_once 'functions.php';
        
        $stmt = $pdo->query("SELECT * FROM campaign_metrics");
        $allMetrics = $stmt->fetchAll();
        
        $lookup = [];
        foreach ($allMetrics as $m) {
            $lookup[$m['metrics_id']] = $m;
        }

        $sql = "INSERT INTO campaign_assessments 
            (metrics_row_id, record_id, campaign_code, assessment_date, speed_score, leads_score, rankings_score, traffic_score, engagement_score, conversion_score, health_score, assessment_label, status_class)
            VALUES 
            (:mid, :rid, :c, :d, :s, :l, :r, :t, :e, :conv, :h, :lbl, :cls)
            ON DUPLICATE KEY UPDATE 
            speed_score=:s, leads_score=:l, rankings_score=:r, traffic_score=:t, engagement_score=:e, conversion_score=:conv, health_score=:h, assessment_label=:lbl, status_class=:cls";
        $stmtIns = $pdo->prepare($sql);

        $count = 0;
        foreach ($allMetrics as $m) {
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

        echo json_encode(['success' => true, 'message' => "Automated assessment complete for $count campaigns."]);
    } catch (Exception $e) {
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
