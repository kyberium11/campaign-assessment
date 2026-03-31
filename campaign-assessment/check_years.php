<?php
require 'db.php';
$stmt = $pdo->query("SELECT DISTINCT SUBSTRING_INDEX(month_yr, '/', -1) as year FROM campaign_metrics");
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Available years: " . implode(', ', $years);
