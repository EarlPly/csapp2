<?php
session_start();
require_once 'db.php';

// Check Login
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// Fetch History Data (Last 50 entries)
try {
    $sql = "SELECT * FROM export_history ORDER BY export_date DESC LIMIT 50";
    $stmt = $pdo->query($sql);
    $historyLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching history: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Export History Log</h2>
            <a href="index.php" class="btn btn-secondary">
                &larr; Back to Dashboard
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($historyLog)): ?>
                    <p class="text-muted text-center my-3">No export history found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Format</th>
                                    <th>Records Exported</th>
                                    <th>Columns Included</th>
                                    <th>User ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historyLog as $row): ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime($row['export_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['export_format'] == 'csv' ? 'success' : ($row['export_format'] == 'pdf' ? 'danger' : 'primary') ?>">
                                                <?= strtoupper(htmlspecialchars($row['export_format'])) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($row['record_count']) ?></td>
                                        <td>
                                            <?php 
                                                $cols = json_decode($row['exported_columns'], true);
                                                if (empty($cols) || in_array('all', $cols)) {
                                                    echo '<span class="text-muted">All Columns</span>';
                                                } else {
                                                    echo implode(', ', array_map('ucfirst', $cols));
                                                }
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>