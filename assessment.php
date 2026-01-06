<?php
require_once 'db.php';
require_once 'functions.php';

$view = $_GET['view'] ?? 'current'; // current, previous, all, campaign
$campaign_filter = $_GET['campaign'] ?? '';
$is_embedded = isset($_GET['embed']) && $_GET['embed'] == '1';

// Track if we fell back from current month
$fallback_to_all = false;

$sql = "SELECT * FROM campaign_assessments";
$params = [];

if ($view === 'current') {
    $sql .= " WHERE DATE_FORMAT(assessment_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')";
} elseif ($view === 'previous') {
    $sql .= " WHERE DATE_FORMAT(assessment_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m')";
} elseif ($view === 'campaign' && !empty($campaign_filter)) {
    $sql .= " WHERE campaign_code = :c";
    $params[':c'] = $campaign_filter;
}

$sql .= " ORDER BY assessment_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Fallback: If current month view returns no results, show all data instead
    if ($view === 'current' && empty($results)) {
        $fallback_to_all = true;
        $sqlAll = "SELECT * FROM campaign_assessments ORDER BY assessment_date DESC";
        $stmtAll = $pdo->query($sqlAll);
        $results = $stmtAll->fetchAll();
    }

    // Get unique campaigns for dropdown
    $campStmt = $pdo->query("SELECT DISTINCT campaign_code FROM campaign_assessments ORDER BY campaign_code");
    $campaigns = $campStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get metrics rows NOT yet in assessments (for Add Data modal)
    $metricsStmt = $pdo->query("SELECT id, metrics_id, campaign, month_yr FROM campaign_metrics 
                               WHERE id NOT IN (SELECT metrics_row_id FROM campaign_assessments)
                               ORDER BY month_yr DESC LIMIT 100");
    $availableMetrics = $metricsStmt->fetchAll();

} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Assessment</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
</head>
<body class="assessment-page <?= $is_embedded ? 'embedded' : '' ?>">

    <nav class="tabs">
        <a href="index.php" class="tab-item">Campaign Metrics</a>
        <a href="assessment.php" class="tab-item active">Campaign Assessment (Automated)</a>
    </nav>

    <div class="sub-tabs">
        <div class="container">
            <a href="?view=current" class="<?= ($view === 'current' && !$fallback_to_all) ? 'active' : '' ?>">Current Month</a>
            <a href="?view=previous" class="<?= $view === 'previous' ? 'active' : '' ?>">Previous Month</a>
            <a href="?view=all" class="<?= ($view === 'all' || $fallback_to_all) ? 'active' : '' ?>">All Data<?= $fallback_to_all ? ' (No current month data)' : '' ?></a>
            <div class="campaign-selector">
                <form method="GET" style="display:inline;">
                    <input type="hidden" name="view" value="campaign">
                    <select name="campaign" onchange="this.form.submit()">
                        <option value="">Per Campaign...</option>
                        <?php foreach($campaigns as $c): ?>
                            <option value="<?= $c ?>" <?= $campaign_filter === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <button class="btn-add" id="btnAddAssessment" style="margin-left:auto;">+ Add Assessment</button>
        </div>
    </div>

    <div class="table-container" style="margin: 20px; border-radius: 6px;">
        <table id="assessmentTable">
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>Campaign Code</th>
                    <th>Date</th>
                    <th class="col-center"># Website Speed</th>
                    <th class="col-center"># Leads</th>
                    <th class="col-center"># Rankings</th>
                    <th class="col-center"># Traffic</th>
                    <th class="col-center"># Engagement</th>
                    <th class="col-center"># Conversion</th>
                    <th class="col-center">Health Score</th>
                    <th>Campaign Assessment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $res): ?>
                <tr data-id="<?= $res['id'] ?>">
                    <td style="color: #57606a; font-size: 11px;"><?= htmlspecialchars($res['record_id']) ?></td>
                    <td><span class="campaign-badge"><?= htmlspecialchars($res['campaign_code']) ?></span></td>
                    <td><?= date('M d, Y', strtotime($res['assessment_date'])) ?></td>
                    <td class="editable col-center" data-col="speed_score"><?= $res['speed_score'] ?></td>
                    <td class="editable col-center" data-col="leads_score"><?= $res['leads_score'] ?></td>
                    <td class="editable col-center" data-col="rankings_score"><?= $res['rankings_score'] ?></td>
                    <td class="editable col-center" data-col="traffic_score"><?= $res['traffic_score'] ?></td>
                    <td class="editable col-center" data-col="engagement_score"><?= $res['engagement_score'] ?></td>
                    <td class="editable col-center" data-col="conversion_score"><?= $res['conversion_score'] ?></td>
                    <td class="col-center health-cell" style="font-weight: 600;"><?= number_format($res['health_score'], 2) ?></td>
                    <td><span class="status-badge <?= $res['status_class'] ?> label-cell"><?= $res['assessment_label'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal for Adding Assessment from Metrics -->
    <div class="modal-overlay" id="addAssessmentModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Add Assessment From Metrics</h2>
                <button class="modal-close" id="btnCloseAssessmentModal">&times;</button>
            </div>
            <div style="padding: 20px;">
                <p>Select a entry from Campaign Metrics to calculate its assessment:</p>
                <div class="metrics-list" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); margin-top: 10px;">
                    <?php if (empty($availableMetrics)): ?>
                        <p style="padding: 10px;">No pending metric data found.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f6f8fa;">
                                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Campaign</th>
                                    <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Month</th>
                                    <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($availableMetrics as $m): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= $m['campaign'] ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?= $m['month_yr'] ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: right;">
                                        <button class="btn-add" onclick="createAssessment(<?= $m['id'] ?>)">Add & Calc</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assessment_script.js?v=<?= filemtime('assessment_script.js') ?>"></script>
</body>
</html>
