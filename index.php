<?php
require_once 'db.php';

$is_embedded = isset($_GET['embed']) && $_GET['embed'] == '1';

try {
    $stmt = $pdo->query("SELECT * FROM campaign_metrics");
    $metrics = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Metrics</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
</head>
<body class="<?= $is_embedded ? 'embedded' : '' ?>">
<?php if (!$is_embedded): ?>
    <nav class="tabs">
        <a href="index.php" class="tab-item active">Campaign Metrics</a>
        <a href="assessment.php" class="tab-item">Campaign Assessment (Automated)</a>
    </nav>
    <header>
        <h1>Campaign Metrics</h1>
        <div class="header-actions">
            <input type="file" id="csvInput" accept=".csv" style="display: none;">
            <button class="btn-secondary" id="btnImport">Import CSV</button>
            <button class="btn-add" id="btnAdd">+ Add Data</button>
        </div>
    </header>
<?php endif; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Metrics_ID</th>
                    <th>Record ID</th>
                    <th>Campaign</th>
                    <th>Month & Yr</th>
                    <th class="col-percentage">Website Speed (Mobile)</th>
                    <th class="col-percentage">Website Speed (Desktop)</th>
                    <th class="col-percentage">Website Speed</th>
                    <th class="col-number">Leads</th>
                    <th class="col-number">Ranking</th>
                    <th class="col-number">Traffic</th>
                    <th class="col-center">Engagement</th>
                    <th class="col-decimal">Conversion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metrics as $row): ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td class="editable" data-col="metrics_id"><?= htmlspecialchars($row['metrics_id']) ?></td>
                    <td class="col-center" style="font-size: 11px; color: #57606a;">
                        <?= htmlspecialchars($row['campaign']) ?> : <?= date('F-Y', strtotime($row['month_yr'])) ?>
                    </td>
                    <td class="editable" data-col="campaign"><?= htmlspecialchars($row['campaign']) ?></td>
                    <td class="editable" data-col="month_yr"><?= htmlspecialchars($row['month_yr']) ?></td>
                    <td class="editable col-percentage" data-col="speed_mobile"><?= ($row['speed_mobile'] * 100) ?>%</td>
                    <td class="editable col-percentage" data-col="speed_desktop"><?= ($row['speed_desktop'] * 100) ?>%</td>
                    <td class="editable col-percentage" data-col="speed_avg"><?= ($row['speed_avg'] * 100) ?>%</td>
                    <td class="editable col-number" data-col="leads"><?= number_format($row['leads']) ?></td>
                    <td class="editable col-number" data-col="ranking"><?= number_format($row['ranking']) ?></td>
                    <td class="editable col-number" data-col="traffic"><?= number_format($row['traffic']) ?></td>
                    <td class="editable col-center" data-col="engagement"><?= htmlspecialchars($row['engagement']) ?></td>
                    <td class="editable col-decimal" data-col="conversion"><?= number_format($row['conversion'], 1) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Add Campaign Metrics</h2>
                <button class="modal-close" id="btnCloseModal">&times;</button>
            </div>
            <form id="addForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Metrics ID</label>
                        <input type="text" name="metrics_id" required>
                    </div>
                    <div class="form-group">
                        <label>Campaign</label>
                        <input type="text" name="campaign" required>
                    </div>
                    <div class="form-group">
                        <label>Month & Yr</label>
                        <input type="text" name="month_yr" placeholder="MM/DD/YYYY">
                    </div>
                    <div class="form-group">
                        <label>Mobile Speed (%)</label>
                        <input type="number" step="0.01" name="speed_mobile">
                    </div>
                    <div class="form-group">
                        <label>Desktop Speed (%)</label>
                        <input type="number" step="0.01" name="speed_desktop">
                    </div>
                    <div class="form-group">
                        <label>Avg Speed (%)</label>
                        <input type="number" step="0.01" name="speed_avg">
                    </div>
                    <div class="form-group">
                        <label>Leads</label>
                        <input type="number" name="leads">
                    </div>
                    <div class="form-group">
                        <label>Ranking</label>
                        <input type="number" name="ranking">
                    </div>
                    <div class="form-group">
                        <label>Traffic</label>
                        <input type="number" name="traffic">
                    </div>
                    <div class="form-group">
                        <label>Engagement</label>
                        <input type="text" name="engagement" placeholder="0:00">
                    </div>
                    <div class="form-group">
                        <label>Conversion</label>
                        <input type="number" step="0.1" name="conversion">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="btnSaveAndKeep">Save</button>
                    <button type="submit" class="btn-add">Save & Close</button>
                </div>
            </form>
        </div>
    <script src="script.js?v=<?= filemtime('script.js') ?>"></script>
</body>
</html>
