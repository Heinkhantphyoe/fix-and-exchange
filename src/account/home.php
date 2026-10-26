<?php
// Show the campus homepage.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_login();
$user = current_user();

$userId = (int) $user['user_id'];

$volunteerStatement = mysqli_prepare(
    $conn,
    'SELECT approval_status
     FROM volunteer_profiles
     WHERE user_id = ?
     LIMIT 1'
);
mysqli_stmt_bind_param($volunteerStatement, 'i', $userId);
mysqli_stmt_execute($volunteerStatement);
$volunteerProfile = mysqli_fetch_assoc(mysqli_stmt_get_result($volunteerStatement)) ?: null;
mysqli_stmt_close($volunteerStatement);

$summaryStatement = mysqli_prepare(
    $conn,
    "SELECT total_points,
            (SELECT COUNT(*) FROM item_listings WHERE seller_id = users.user_id) AS listing_count,
            (SELECT COUNT(*) FROM repair_requests WHERE requester_id = users.user_id) AS repair_count,
            (SELECT COUNT(*) FROM exchange_offers WHERE buyer_id = users.user_id) AS offer_count
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($summaryStatement, 'i', $userId);
mysqli_stmt_execute($summaryStatement);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summaryStatement)) ?: [
    'total_points' => 0,
    'listing_count' => 0,
    'repair_count' => 0,
    'offer_count' => 0,
];
mysqli_stmt_close($summaryStatement);

$listingStatement = mysqli_prepare(
    $conn,
    'SELECT title, listing_type, status, created_at
     FROM item_listings
     WHERE seller_id = ?
     ORDER BY created_at DESC
     LIMIT 3'
);
mysqli_stmt_bind_param($listingStatement, 'i', $userId);
mysqli_stmt_execute($listingStatement);
$recentListings = mysqli_stmt_get_result($listingStatement)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($listingStatement);

$repairStatement = mysqli_prepare(
    $conn,
    'SELECT title, status, created_at
     FROM repair_requests
     WHERE requester_id = ?
     ORDER BY created_at DESC
     LIMIT 3'
);
mysqli_stmt_bind_param($repairStatement, 'i', $userId);
mysqli_stmt_execute($repairStatement);
$recentRepairs = mysqli_stmt_get_result($repairStatement)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($repairStatement);

require __DIR__ . '/../../includes/header.php';
?>

<section class="account-dashboard section">
    <div class="container">
        <div class="dashboard-welcome">
            <div>
                <p class="dashboard-eyebrow">STUDENT DASHBOARD</p>
                <h1>Welcome, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
                <p>Keep useful items moving and track your sustainability activity.</p>
            </div>
            <a class="btn btn-primary" href="/fix-and-exchange/src/buyer-seller/marketplace.php">
                <i class="fa-solid fa-store" aria-hidden="true"></i> Browse marketplace
            </a>
        </div>

        <div class="dashboard-stat-grid">
            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon points-icon"><i class="fa-solid fa-leaf" aria-hidden="true"></i></span>
                <div><strong><?= (int) $summary['total_points'] ?></strong><span>Sustainability points</span></div>
            </div>
            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon listing-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                <div><strong><?= (int) $summary['listing_count'] ?></strong><span>My listings</span></div>
            </div>
            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon repair-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                <div><strong><?= (int) $summary['repair_count'] ?></strong><span>Repair requests</span></div>
            </div>
            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon offer-icon"><i class="fa-solid fa-handshake" aria-hidden="true"></i></span>
                <div><strong><?= (int) $summary['offer_count'] ?></strong><span>Offers sent</span></div>
            </div>
        </div>

        <div class="dashboard-section-heading">
            <h2>Quick actions</h2>
        </div>
        <div class="dashboard-action-grid">
            <a href="/fix-and-exchange/src/buyer-seller/create-listing.php"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Post an item</span><small>Sell, exchange, or give away</small></a>
            <a href="/fix-and-exchange/src/repair-requester/create-request.php"><i class="fa-solid fa-wrench" aria-hidden="true"></i><span>Request a repair</span><small>Repair before replacing</small></a>
            <?php if (($volunteerProfile['approval_status'] ?? null) === 'Approved'): ?>
                <a href="/fix-and-exchange/src/repair-volunteer/dashboard.php"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i><span>Volunteer dashboard</span><small>Manage your repair jobs</small></a>
            <?php else: ?>
                <a href="/fix-and-exchange/src/repair-volunteer/apply.php"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i><span>Become a volunteer</span><small>Share your repair skills</small></a>
            <?php endif; ?>
            <a href="/fix-and-exchange/src/sustainability/points.php"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>View my activity</span><small>See points and progress</small></a>
        </div>

        <div class="dashboard-columns">
            <section class="dashboard-panel" aria-labelledby="listings-heading">
                <div class="dashboard-panel-heading">
                    <h2 id="listings-heading">My recent listings</h2>
                    <a href="/fix-and-exchange/src/buyer-seller/my-listings.php">View all</a>
                </div>
                <?php if (!$recentListings): ?>
                    <p class="dashboard-empty">You have not posted an item yet.</p>
                <?php else: ?>
                    <div class="dashboard-list">
                        <?php foreach ($recentListings as $listing): ?>
                            <div class="dashboard-list-row">
                                <span class="dashboard-row-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></span>
                                <div><strong><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($listing['listing_type'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($listing['status'], ENT_QUOTES, 'UTF-8') ?></small></div>
                                <time datetime="<?= htmlspecialchars($listing['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= date('M j', strtotime($listing['created_at'])) ?></time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-panel" aria-labelledby="repairs-heading">
                <div class="dashboard-panel-heading">
                    <h2 id="repairs-heading">My repair requests</h2>
                    <a href="/fix-and-exchange/src/repair-requester/my-requests.php">View all</a>
                </div>
                <?php if (!$recentRepairs): ?>
                    <p class="dashboard-empty">You have not submitted a repair request yet.</p>
                <?php else: ?>
                    <div class="dashboard-list">
                        <?php foreach ($recentRepairs as $repair): ?>
                            <div class="dashboard-list-row">
                                <span class="dashboard-row-icon repair-row-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                                <div><strong><?= htmlspecialchars($repair['title'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($repair['status'], ENT_QUOTES, 'UTF-8') ?></small></div>
                                <time datetime="<?= htmlspecialchars($repair['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= date('M j', strtotime($repair['created_at'])) ?></time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
