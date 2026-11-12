<?php
// Admin Overview Dashboard: Real-time platform metrics, alerts, and recent campus activity.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

// Fetch summary metrics
$metricStmt = mysqli_query(
    $conn,
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'Student') AS student_count,
        (SELECT COUNT(*) FROM item_listings WHERE status = 'Available') AS available_listings,
        (SELECT COUNT(*) FROM repair_requests WHERE status IN ('Open', 'Verified', 'In Progress')) AS active_repairs,
        (SELECT COUNT(*) FROM volunteer_profiles WHERE approval_status = 'Pending') AS pending_applications,
        (SELECT COUNT(*) FROM reported_issues WHERE status IN ('Pending', 'Investigating')) AS unresolved_reports,
        (SELECT COUNT(*) FROM reported_issues WHERE status = 'Pending') AS pending_reports,
        (SELECT COUNT(*) FROM reported_issues WHERE status = 'Investigating') AS investigating_reports,
        (SELECT COUNT(*) FROM repair_requests r LEFT JOIN repair_assignments a ON a.request_id = r.request_id WHERE r.status IN ('Open', 'Verified') AND a.assignment_id IS NULL) AS unassigned_repairs"
);
$metrics = mysqli_fetch_assoc($metricStmt) ?: [];

$studentCount = (int) ($metrics['student_count'] ?? 0);
$availableListingCount = (int) ($metrics['available_listings'] ?? 0);
$activeRepairCount = (int) ($metrics['active_repairs'] ?? 0);
$pendingApplicationCount = (int) ($metrics['pending_applications'] ?? 0);
$unresolvedReportCount = (int) ($metrics['unresolved_reports'] ?? 0);
$pendingReportCount = (int) ($metrics['pending_reports'] ?? 0);
$investigatingReportCount = (int) ($metrics['investigating_reports'] ?? 0);
$unassignedRepairCount = (int) ($metrics['unassigned_repairs'] ?? 0);

// Fetch recent activity across platform
$recentActivity = [];

// Recent listings
$rListings = mysqli_query($conn, "SELECT l.title, u.full_name, l.created_at FROM item_listings l JOIN users u ON u.user_id = l.seller_id ORDER BY l.created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($rListings)) {
    $recentActivity[] = [
        'title' => 'New listing: ' . $row['title'],
        'detail' => 'Posted by ' . $row['full_name'] . ' · ' . date('M j, g:i a', strtotime($row['created_at'])),
        'time' => strtotime($row['created_at']),
        'icon' => 'fa-box-archive',
    ];
}

// Recent repairs
$rRepairs = mysqli_query($conn, "SELECT r.title, u.full_name, r.created_at FROM repair_requests r JOIN users u ON u.user_id = r.requester_id ORDER BY r.created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($rRepairs)) {
    $recentActivity[] = [
        'title' => 'Repair requested: ' . $row['title'],
        'detail' => 'Submitted by ' . $row['full_name'] . ' · ' . date('M j, g:i a', strtotime($row['created_at'])),
        'time' => strtotime($row['created_at']),
        'icon' => 'fa-wrench',
    ];
}

// Recent reports
$rReports = mysqli_query($conn, "SELECT rep.reason, il.title, rep.created_at FROM reported_issues rep JOIN item_listings il ON il.listing_id = rep.listing_id ORDER BY rep.created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($rReports)) {
    $recentActivity[] = [
        'title' => 'Report filed: ' . $row['title'],
        'detail' => 'Reason: ' . $row['reason'] . ' · ' . date('M j, g:i a', strtotime($row['created_at'])),
        'time' => strtotime($row['created_at']),
        'icon' => 'fa-flag',
    ];
}

// Sort all activities by timestamp descending
usort($recentActivity, static fn($a, $b) => $b['time'] <=> $a['time']);
$recentActivity = array_slice($recentActivity, 0, 6);

render_admin_header('Admin Dashboard', 'dashboard');
?>

<main class="dashboardMain">
    <!-- welcome banner -->
    <section class="welcomeBanner">
        <div>
            <p class="bannerLabel">Administrator dashboard</p>
            <h1>Admin Dashboard</h1>
            <p>Monitor campus listings, repair requests, reported content and student accounts.</p>
        </div>
        <a class="statisticsLink" href="adminStatistics.php">
            <i class="fa-solid fa-chart-line" aria-hidden="true"></i> View Statistics
        </a>
    </section>

    <!-- dashboard summary, 5 quick cards -->
    <section class="dashboardSummary">
        <h2>Campus Overview</h2>
        <div class="summaryGrid">
            <!-- Users card -->
            <a class="summaryCard" href="adminUsers.php">
                <span class="summaryIcon" aria-hidden="true">
                    <i class="fa-solid fa-users"></i>
                </span>
                <div>
                    <strong><?= $studentCount ?></strong>
                    <span>Student accounts</span>
                </div>
            </a>

            <!-- Listings card -->
            <a class="summaryCard" href="adminListings.php">
                <span class="summaryIcon listingIcon" aria-hidden="true">
                    <i class="fa-solid fa-box-archive"></i>
                </span>
                <div>
                    <strong><?= $availableListingCount ?></strong>
                    <span>Available Listings</span>
                </div>
            </a>

            <!-- Repair Requests card -->
            <a class="summaryCard" href="adminRepairs.php">
                <span class="summaryIcon repairIcon" aria-hidden="true">
                    <i class="fa-solid fa-wrench"></i>
                </span>
                <div>
                    <strong><?= $activeRepairCount ?></strong>
                    <span>Active Repair Requests</span>
                </div>
            </a>

            <!-- Volunteer applications card -->
            <a class="summaryCard" href="adminUsers.php?view=applications">
                <span class="summaryIcon applicationIcon" aria-hidden="true">
                    <i class="fa-solid fa-clipboard-check"></i>
                </span>
                <div>
                    <strong><?= $pendingApplicationCount ?></strong>
                    <span>Pending Applications</span>
                </div>
            </a>

            <!-- Reports card -->
            <a class="summaryCard" href="adminReports.php">
                <span class="summaryIcon reportIcon" aria-hidden="true">
                    <i class="fa-solid fa-flag"></i>
                </span>
                <div>
                    <strong><?= $unresolvedReportCount ?></strong>
                    <span>Unresolved Reports</span>
                </div>
            </a>
        </div>
    </section>

    <!-- dashboard panels -->
    <div class="dashboardPanels">
        <!-- needs attention card -->
        <section class="dashboardPanel">
            <h2>Needs Attention</h2>
            <p class="attentionIntro">Items that may need an administrator's review.</p>

            <ul class="attentionList">
                <li>
                    <div class="attentionText">
                        <span class="summaryIcon reportIcon" aria-hidden="true">
                            <i class="fa-solid fa-flag"></i>
                        </span>
                        <div>
                            <strong>Unresolved listing reports</strong>
                            <small><?= $pendingReportCount ?> pending &middot; <?= $investigatingReportCount ?> investigating</small>
                        </div>
                    </div>
                    <a href="adminReports.php">Review reports</a>
                </li>

                <li>
                    <div class="attentionText">
                        <span class="summaryIcon applicationIcon" aria-hidden="true">
                            <i class="fa-solid fa-users"></i>
                        </span>
                        <div>
                            <strong>Volunteer applications</strong>
                            <small><?= $pendingApplicationCount ?> awaiting approval</small>
                        </div>
                    </div>
                    <a href="adminUsers.php?view=applications">Review volunteers</a>
                </li>

                <li>
                    <div class="attentionText">
                        <span class="summaryIcon repairIcon" aria-hidden="true">
                            <i class="fa-solid fa-wrench"></i>
                        </span>
                        <div>
                            <strong>Unassigned repair requests</strong>
                            <small><?= $unassignedRepairCount ?> without a volunteer</small>
                        </div>
                    </div>
                    <a href="adminRepairs.php?assignment=Unassigned">View requests</a>
                </li>
            </ul>
        </section>

        <!-- recent activity card -->
        <section class="dashboardPanel">
            <h2>Recent Activity</h2>
            <p class="attentionIntro">Latest updates across Fix &amp; Exchange.</p>

            <?php if (empty($recentActivity)): ?>
                <p>Recent activity will appear here once campus data is created.</p>
            <?php else: ?>
                <ul class="attentionList">
                    <?php foreach ($recentActivity as $act): ?>
                        <li>
                            <div class="attentionText">
                                <span class="summaryIcon" aria-hidden="true">
                                    <i class="fa-solid <?= escape_html($act['icon']) ?>"></i>
                                </span>
                                <div>
                                    <strong><?= escape_html($act['title']) ?></strong>
                                    <small><?= escape_html($act['detail']) ?></small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php render_admin_footer(); ?>
