<?php
// Report Queue Management: Investigate, resolve, or dismiss reported listings.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

$adminUser = current_user();
$adminId = (int) $adminUser['user_id'];

// Handle POST actions for reports
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $reportStatus = $_POST['report_status'] ?? '';
    $listingAction = $_POST['listing_action'] ?? '';

    if ($reportId > 0) {
        if ($listingAction === 'remove') {
            // Fetch listing ID from report
            $repStmt = mysqli_prepare($conn, "SELECT listing_id FROM reported_issues WHERE report_id = ?");
            mysqli_stmt_bind_param($repStmt, 'i', $reportId);
            mysqli_stmt_execute($repStmt);
            $repData = mysqli_fetch_assoc(mysqli_stmt_get_result($repStmt));
            mysqli_stmt_close($repStmt);

            if ($repData) {
                $targetListingId = (int) $repData['listing_id'];
                mysqli_begin_transaction($conn);
                try {
                    // Remove listing
                    $delStmt = mysqli_prepare($conn, "UPDATE item_listings SET status = 'Removed' WHERE listing_id = ?");
                    mysqli_stmt_bind_param($delStmt, 'i', $targetListingId);
                    mysqli_stmt_execute($delStmt);
                    mysqli_stmt_close($delStmt);

                    // Cancel pending offers
                    $cStmt = mysqli_prepare($conn, "UPDATE exchange_offers SET status = 'Cancelled' WHERE listing_id = ? AND status IN ('Pending', 'Accepted')");
                    mysqli_stmt_bind_param($cStmt, 'i', $targetListingId);
                    mysqli_stmt_execute($cStmt);
                    mysqli_stmt_close($cStmt);

                    // Mark report as Resolved
                    $rStmt = mysqli_prepare($conn, "UPDATE reported_issues SET status = 'Resolved', handled_by = ?, resolved_at = NOW() WHERE report_id = ?");
                    mysqli_stmt_bind_param($rStmt, 'ii', $adminId, $reportId);
                    mysqli_stmt_execute($rStmt);
                    mysqli_stmt_close($rStmt);

                    mysqli_commit($conn);
                    redirect_message("adminReports.php?report=$reportId#reportDetails", "Listing #L-$targetListingId removed and report #R-$reportId marked as Resolved.", 'success');
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    redirect_message("adminReports.php?report=$reportId#reportDetails", "Failed to resolve and remove listing: " . $e->getMessage(), 'danger');
                }
            }
        } elseif (in_array($reportStatus, ['Investigating', 'Resolved', 'Dismissed'], true)) {
            $isClosing = in_array($reportStatus, ['Resolved', 'Dismissed'], true);
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE reported_issues
                 SET status = ?, handled_by = ?, resolved_at = " . ($isClosing ? "NOW()" : "NULL") . "
                 WHERE report_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'sii', $reportStatus, $adminId, $reportId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message("adminReports.php?report=$reportId#reportDetails", "Report #R-$reportId status updated to $reportStatus.", 'success');
        }
    }
}

// Load reports joined with listing, reporter, and admin details
$reports = [];
$repSql = "SELECT rep.*, il.title AS listing_title, il.status AS listing_status, il.price, il.listing_type,
    u.full_name AS reporter_name, u.tp_number AS reporter_tp, u.email AS reporter_email,
    admin_user.full_name AS handler_name
    FROM reported_issues rep
    JOIN item_listings il ON il.listing_id = rep.listing_id
    JOIN users u ON u.user_id = rep.reporter_id
    LEFT JOIN users admin_user ON admin_user.user_id = rep.handled_by
    ORDER BY FIELD(rep.status, 'Pending', 'Investigating', 'Resolved', 'Dismissed'), rep.report_id DESC";

$repRes = mysqli_query($conn, $repSql);
while ($row = mysqli_fetch_assoc($repRes)) {
    $reports[] = $row;
}

$reportStatuses = [
    'unresolved' => 'Unresolved',
    'Pending' => 'Pending',
    'Investigating' => 'Investigating',
    'Resolved' => 'Resolved',
    'Dismissed' => 'Dismissed',
];

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!is_string($status) || !isset($reportStatuses[$status])) {
    $status = '';
}

$listingId = filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$reportId = filter_input(INPUT_GET, 'report', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedReport = null;
$filteredReports = [];
$pendingCount = 0;
$investigatingCount = 0;

foreach ($reports as $report) {
    if ($report['status'] === 'Pending') {
        $pendingCount++;
    } elseif ($report['status'] === 'Investigating') {
        $investigatingCount++;
    }

    if ((int) $report['report_id'] === $reportId) {
        $selectedReport = $report;
    }

    $reference = sprintf('#R-%03d', (int) $report['report_id']);
    $searchText = $reference . ' #L-' . $report['listing_id'] . ' ' . $report['listing_title'] . ' ' . $report['reporter_name'] . ' ' . $report['reason'];
    $isUnresolved = in_array($report['status'], ['Pending', 'Investigating'], true);
    $matchesSearch = $search === '' || stripos($searchText, $search) !== false;
    $matchesStatus = $status === '' || $status === $report['status'] || ($status === 'unresolved' && $isUnresolved);
    $matchesListing = $listingId === 0 || $listingId === (int) $report['listing_id'];

    if ($matchesSearch && $matchesStatus && $matchesListing) {
        $filteredReports[] = $report;
    }
}

$hasFilters = $search !== '' || $status !== '' || $listingId !== 0;
$resultsUrl = 'adminReports.php?' . http_build_query([
    'q' => $search,
    'status' => $status,
    'listing' => $listingId,
]);

render_admin_header('Report Queue Management', 'reports');
?>

<main class="dashboardMain">
    <div class="reportsPageHeading">
        <div>
            <p class="reportsPageLabel">Administrator</p>
            <h1>Reported Content Queue</h1>
            <p>Review student reports about misleading, broken, or inappropriate marketplace listings.</p>
        </div>

        <div class="reportsTotals">
            <span class="summaryIcon" aria-hidden="true">
                <i class="fa-solid fa-flag"></i>
            </span>
            <div>
                <strong><?= $pendingCount + $investigatingCount ?> unresolved</strong>
                <small><?= $pendingCount ?> pending &middot; <?= $investigatingCount ?> investigating</small>
            </div>
        </div>
    </div>

    <!-- Filters form -->
    <form class="reportsFilters" action="adminReports.php" method="get">
        <div class="reportsSearch">
            <label for="reportSearch">Search reports</label>
            <input id="reportSearch" type="search" name="q" value="<?= escape_html($search) ?>" placeholder="Report ID, #L-ID, title, student or reason">
        </div>

        <div class="reportsFilter">
            <label for="reportStatus">Queue status</label>
            <select id="reportStatus" name="status">
                <option value="">All reports</option>
                <?php foreach ($reportStatuses as $value => $label): ?>
                    <option value="<?= escape_html($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= escape_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($listingId > 0): ?>
            <input type="hidden" name="listing" value="<?= $listingId ?>">
        <?php endif; ?>

        <button class="reportsButton reportsApplyButton" type="submit">Apply filters</button>
        <a class="reportsButton" href="adminReports.php">Clear filters</a>
    </form>

    <!-- Detailed report review card -->
    <?php if ($reportId): ?>
        <section class="dashboardPanel adminDetailPanel" id="reportDetails">
            <div class="adminDetailHeader">
                <div class="adminDetailTitle">
                    <span class="summaryIcon reportIcon" aria-hidden="true">
                        <i class="fa-solid fa-flag"></i>
                    </span>

                    <?php if ($selectedReport): ?>
                        <h2>Report <?= sprintf('#R-%03d', (int) $selectedReport['report_id']) ?></h2>
                        <span class="reportStatusBadge <?= escape_html(strtolower($selectedReport['status'])) ?>">
                            <?= escape_html($selectedReport['status']) ?>
                        </span>
                    <?php else: ?>
                        <h2>Report unavailable</h2>
                    <?php endif; ?>
                </div>

                <a class="reportsButton" href="<?= escape_html($resultsUrl) ?>#reportResults">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to results
                </a>
            </div>

            <?php if ($selectedReport): ?>
                <div class="adminDetailBody">
                    <div>
                        <div class="adminDetailText">
                            <h3>Reason for Report</h3>
                            <p class="reportReason" style="white-space: pre-line; background: #fdf5f5; border: 1px solid #fed7d7; padding: 14px; border-radius: 6px; color: #9b2c2c;">
                                <?= escape_html($selectedReport['reason']) ?>
                            </p>
                        </div>

                        <dl class="adminDetailFacts">
                            <div>
                                <dt>Reported Listing</dt>
                                <dd>
                                    <a href="adminListings.php?listing=<?= (int) $selectedReport['listing_id'] ?>#listingDetails" style="font-weight: 600; color: #123c2a;">
                                        <?= escape_html($selectedReport['listing_title']) ?>
                                    </a>
                                    <small class="reportReference">#L-<?= (int) $selectedReport['listing_id'] ?> &bull; Status: <?= escape_html($selectedReport['listing_status']) ?></small>
                                </dd>
                            </div>
                            <div>
                                <dt>Reported By</dt>
                                <dd><?= escape_html($selectedReport['reporter_name']) ?> (<?= escape_html($selectedReport['reporter_tp']) ?>)</dd>
                            </div>
                            <div>
                                <dt>Submitted</dt>
                                <dd><?= date('d M Y, g:i a', strtotime($selectedReport['created_at'])) ?></dd>
                            </div>
                            <?php if (!empty($selectedReport['handler_name'])): ?>
                                <div>
                                    <dt>Handled By</dt>
                                    <dd><?= escape_html($selectedReport['handler_name']) ?></dd>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($selectedReport['resolved_at'])): ?>
                                <div>
                                    <dt>Closed On</dt>
                                    <dd><?= date('d M Y, g:i a', strtotime($selectedReport['resolved_at'])) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <div class="adminDetailSidebar">
                        <?php if (in_array($selectedReport['status'], ['Pending', 'Investigating'], true)): ?>
                            <h3>Review Actions</h3>
                            <p style="font-size: 13px; color: #52645a; margin-bottom: 12px;">
                                Take action on this report or escalate to listing removal.
                            </p>

                            <form method="post" style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                                <input type="hidden" name="report_id" value="<?= (int) $selectedReport['report_id'] ?>">

                                <?php if ($selectedReport['status'] === 'Pending'): ?>
                                    <button class="reportsButton reportStartReview" type="submit" name="report_status" value="Investigating" style="background: #2563eb; color: #fff;">
                                        <i class="fa-solid fa-magnifying-glass"></i> Start investigation
                                    </button>
                                <?php endif; ?>

                                <button class="reportsButton" type="submit" name="report_status" value="Resolved" style="background: #1c7049; color: #fff;" onclick="return confirm('Mark this report as Resolved?');">
                                    <i class="fa-solid fa-check"></i> Resolve report
                                </button>

                                <button class="reportsButton" type="submit" name="report_status" value="Dismissed" style="color: #64748b;" onclick="return confirm('Dismiss this report?');">
                                    <i class="fa-solid fa-xmark"></i> Dismiss report
                                </button>

                                <div class="adminDetailSidebarSection" style="margin-top: 16px; border-top: 1px solid #d6e3da; padding-top: 14px;">
                                    <h3>Enforce Takedown</h3>
                                    <p style="font-size: 13px; color: #52645a; margin-bottom: 8px;">
                                        Remove listing from marketplace and resolve this report simultaneously.
                                    </p>
                                    <button class="reportsButton reportRemoveButton" type="submit" name="listing_action" value="remove" style="color: #b91c1c;" onclick="return confirm('Remove listing #L-<?= (int) $selectedReport['listing_id'] ?> from the marketplace and resolve this report?');">
                                        <i class="fa-solid fa-ban" aria-hidden="true"></i> Remove listing &amp; resolve
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <h3>Review Complete</h3>
                            <p style="color: #166534; font-weight: 500;">
                                <i class="fa-solid fa-circle-check"></i> This report has been <?= escape_html(strtolower($selectedReport['status'])) ?>.
                            </p>
                        <?php endif; ?>

                        <div style="margin-top: 16px;">
                            <a class="reportsButton" href="adminListings.php?listing=<?= (int) $selectedReport['listing_id'] ?>#listingDetails">
                                <i class="fa-solid fa-box-archive"></i> Inspect full listing
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="adminDetailEmpty">This report could not be found.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Table of reports -->
    <section class="reportsResults" id="reportResults">
        <div class="reportsResultsHeading">
            <h2>Report Queue</h2>
            <span><?= count($filteredReports) ?> of <?= count($reports) ?> shown</span>
        </div>

        <?php if (empty($filteredReports)): ?>
            <p class="reportsEmpty"><?= $hasFilters ? 'No reports match your filters.' : 'No reports filed yet.' ?></p>
        <?php else: ?>
            <div class="reportsTableContainer">
                <table class="reportsTable">
                    <thead>
                        <tr>
                            <th scope="col">Report ID</th>
                            <th scope="col">Reported listing</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Reported by</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredReports as $rep): ?>
                            <tr>
                                <th scope="row"><?= sprintf('#R-%03d', (int) $rep['report_id']) ?></th>
                                <td>
                                    <span class="reportsMobileLabel">Reported listing</span>
                                    <a href="adminListings.php?listing=<?= (int) $rep['listing_id'] ?>#listingDetails" style="font-weight: 600; color: #123c2a;">
                                        <?= escape_html($rep['listing_title']) ?>
                                    </a>
                                    <small class="reportReference">#L-<?= (int) $rep['listing_id'] ?></small>
                                </td>
                                <td>
                                    <span class="reportsMobileLabel">Reason</span>
                                    <?= escape_html(mb_strimwidth($rep['reason'], 0, 45, '...')) ?>
                                </td>
                                <td>
                                    <span class="reportsMobileLabel">Reported by</span>
                                    <?= escape_html($rep['reporter_name']) ?>
                                </td>
                                <td>
                                    <span class="reportsMobileLabel">Submitted</span>
                                    <?= date('d M', strtotime($rep['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="reportsMobileLabel">Status</span>
                                    <span class="reportStatusBadge <?= escape_html(strtolower($rep['status'])) ?>">
                                        <?= escape_html($rep['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="reportsButton" href="<?= escape_html($resultsUrl . '&report=' . (int) $rep['report_id']) ?>#reportDetails">
                                        Review report
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php render_admin_footer(); ?>
