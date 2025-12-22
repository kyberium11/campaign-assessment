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


echo json_encode(['success' => false, 'message' => 'Invalid action']);
