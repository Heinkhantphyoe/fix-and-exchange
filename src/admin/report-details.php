<?php
// Admin Report Details alias
$reportId = (int) ($_GET['report_id'] ?? 0);
if ($reportId > 0) {
    header("Location: adminReports.php?report=$reportId#reportDetails");
    exit;
}
require __DIR__ . '/adminReports.php';
