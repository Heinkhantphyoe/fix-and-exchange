<?php
// Activity Log: Comprehensive audit trail of sustainability points transactions.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/sustainability-helpers.php';

$userId = (int) current_user()['user_id'];
$sourceType = $_GET['type'] ?? 'All';
$validTypes = ['All', 'Repair', 'Giveaway', 'Exchange', 'Sale'];

if (!in_array($sourceType, $validTypes, true)) {
    $sourceType = 'All';
}

// Fetch totals
$totalsStmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(points_awarded), 0) AS total_pts
     FROM points_transactions
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($totalsStmt, 'i', $userId);
mysqli_stmt_execute($totalsStmt);
$totals = mysqli_fetch_assoc(mysqli_stmt_get_result($totalsStmt)) ?: ['total_count' => 0, 'total_pts' => 0];
mysqli_stmt_close($totalsStmt);

// Fetch transaction history joined with context details
$sql = "SELECT pt.*,
        rr.request_id, rr.title AS repair_title,
        il.listing_id, il.title AS listing_title
    FROM points_transactions pt
    LEFT JOIN repair_assignments ra ON ra.assignment_id = pt.reference_id AND pt.source_type = 'Repair'
    LEFT JOIN repair_requests rr ON rr.request_id = ra.request_id
    LEFT JOIN exchange_offers eo ON eo.offer_id = pt.reference_id AND pt.source_type IN ('Sale', 'Giveaway', 'Exchange')
    LEFT JOIN item_listings il ON il.listing_id = eo.listing_id
    WHERE pt.user_id = ? AND (? = 'All' OR pt.source_type = ?)
    ORDER BY pt.transaction_id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'iss', $userId, $sourceType, $sourceType);
mysqli_stmt_execute($stmt);
$transactions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section sust-page">
    <div class="container">
        <?php show_sustainability_nav('activity'); ?>

        <div class="repair-header-row">
            <div>
                <p class="sust-kicker">AUDIT TRAIL</p>
                <h1>Sustainability Activity Log</h1>
                <p>Complete history of all points earned from peer repairs, exchanges, and pre-loved item handovers.</p>
            </div>
            <a class="btn btn-primary" href="points.php">
                <i class="fa-solid fa-award" aria-hidden="true"></i> Points &amp; Badges
            </a>
        </div>

        <!-- Filter tabs -->
        <nav class="repair-tabs" aria-label="Activity filter">
            <?php foreach ($validTypes as $type): ?>
                <a class="repair-tab-item <?= $sourceType === $type ? 'is-active' : '' ?>" href="?type=<?= urlencode($type) ?>">
                    <?= escape_html($type) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$transactions): ?>
            <div class="repair-empty-state">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                <h3>No activity recorded</h3>
                <p>
                    <?= $sourceType === 'All'
                        ? 'You do not have any points transactions yet. Start volunteering or sharing items to begin your sustainability log!'
                        : 'No points transactions found under "' . escape_html($sourceType) . '".' ?>
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a class="btn btn-primary" href="../buyer-seller/marketplace.php">
                        <i class="fa-solid fa-store"></i> Browse Marketplace
                    </a>
                    <a class="btn btn-outline-primary" href="../repair-requester/repair-hub.php">
                        <i class="fa-solid fa-screwdriver-wrench"></i> Repair Hub
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrap card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Action Type</th>
                            <th>Reference Item</th>
                            <th style="text-align: right;">Points Awarded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                            $src = $tx['source_type'];
                            $badgeClass = 'sust-source-' . strtolower($src);

                            $itemTitle = '';
                            $itemLink = '';

                            if ($src === 'Repair' && !empty($tx['repair_title'])) {
                                $itemTitle = $tx['repair_title'];
                                $itemLink = '../repair-requester/request-details.php?request_id=' . (int) $tx['request_id'];
                            } elseif (in_array($src, ['Sale', 'Giveaway', 'Exchange'], true) && !empty($tx['listing_title'])) {
                                $itemTitle = $tx['listing_title'];
                                $itemLink = '../buyer-seller/item-details.php?listing_id=' . (int) $tx['listing_id'];
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= date('M j, Y', strtotime($tx['created_at'])) ?></strong><br>
                                    <small class="text-muted"><?= date('g:i a', strtotime($tx['created_at'])) ?></small>
                                </td>
                                <td>
                                    <span class="sust-source-badge <?= $badgeClass ?>">
                                        <?php if ($src === 'Repair'): ?>
                                            <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                                        <?php elseif ($src === 'Giveaway'): ?>
                                            <i class="fa-solid fa-gift" aria-hidden="true"></i>
                                        <?php elseif ($src === 'Exchange'): ?>
                                            <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <?= escape_html($src) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($itemTitle && $itemLink): ?>
                                        <a href="<?= escape_html($itemLink) ?>" style="color: #123c2a; font-weight: 600;">
                                            <?= escape_html($itemTitle) ?>
                                        </a>
                                        <br><small class="text-muted">Ref #<?= (int) $tx['reference_id'] ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Activity Ref #<?= (int) $tx['reference_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <span class="sust-points-awarded">+<?= (int) $tx['points_awarded'] ?> pts</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #52645a;">
                <span>Showing <?= count($transactions) ?> entries</span>
                <span>Lifetime Points Earned: <strong style="color: #1c7049;"><?= (int) $totals['total_pts'] ?> pts</strong></span>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
