<?php
// Statistics & Analytics: Track monthly campus recirculations, repairs, and eco impact.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

// Build the last 12 reporting months using the campus time zone
$campusTimeZone = new DateTimeZone('Asia/Kuala_Lumpur');
$currentMonth = new DateTimeImmutable('first day of this month midnight', $campusTimeZone);
$reportingMonths = [];

for ($offset = 0; $offset < 12; $offset++) {
    $month = $currentMonth->modify("-$offset months");
    $reportingMonths[$month->format('Y-m')] = $month;
}

$period = $_GET['period'] ?? '';
if (!is_string($period) || !isset($reportingMonths[$period])) {
    $period = $currentMonth->format('Y-m');
}

$periodStart = $reportingMonths[$period];
$periodLabel = $periodStart->format('F Y');

$startStr = $periodStart->format('Y-m-01 00:00:00');
$endStr = $periodStart->modify('+1 month')->format('Y-m-01 00:00:00');

$activities = [
    'Sale' => ['label' => 'Sales', 'completed' => 0, 'points' => 0],
    'Exchange' => ['label' => 'Exchanges', 'completed' => 0, 'points' => 0],
    'Giveaway' => ['label' => 'Giveaways', 'completed' => 0, 'points' => 0],
    'Repair' => ['label' => 'Repairs', 'completed' => 0, 'points' => 0],
];

// Query real completed repairs
$repStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*), COALESCE(SUM(points_value), 0)
     FROM repair_assignments
     WHERE progress_status = 'Completed' AND (completed_at >= ? AND completed_at < ?)"
);
mysqli_stmt_bind_param($repStmt, 'ss', $startStr, $endStr);
mysqli_stmt_execute($repStmt);
mysqli_stmt_bind_result($repStmt, $repairCount, $repairPoints);
mysqli_stmt_fetch($repStmt);
mysqli_stmt_close($repStmt);

$activities['Repair']['completed'] = (int) $repairCount;
$activities['Repair']['points'] = (int) $repairPoints;

// Query real marketplace activity
foreach (['Sale', 'Exchange', 'Giveaway'] as $type) {
    $typeStmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*),
            (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions WHERE source_type = ? AND created_at >= ? AND created_at < ?) AS points
         FROM item_listings
         WHERE listing_type = ? AND status = 'Completed' AND created_at >= ? AND created_at < ?"
    );
    mysqli_stmt_bind_param($typeStmt, 'ssssss', $type, $startStr, $endStr, $type, $startStr, $endStr);
    mysqli_stmt_execute($typeStmt);
    mysqli_stmt_bind_result($typeStmt, $tCount, $tPts);
    mysqli_stmt_fetch($typeStmt);
    mysqli_stmt_close($typeStmt);

    $activities[$type]['completed'] = (int) $tCount;
    $activities[$type]['points'] = (int) $tPts;
}

// Distinct participating students
$participatingStudents = 0;
$partStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(DISTINCT user_id) FROM points_transactions WHERE created_at >= ? AND created_at < ?"
);
mysqli_stmt_bind_param($partStmt, 'ss', $startStr, $endStr);
mysqli_stmt_execute($partStmt);
mysqli_stmt_bind_result($partStmt, $partCount);
mysqli_stmt_fetch($partStmt);
mysqli_stmt_close($partStmt);
$participatingStudents = (int) $partCount;

// Totals and chart scales
$completedActivities = 0;
$itemsRecirculated = 0;
$pointsAwarded = 0;
$highestCount = 0;

foreach ($activities as $sourceType => $activity) {
    $completed = (int) $activity['completed'];
    $completedActivities += $completed;
    $pointsAwarded += (int) $activity['points'];
    $highestCount = max($highestCount, $completed);

    if ($sourceType !== 'Repair') {
        $itemsRecirculated += $completed;
    }
}

$chartStep = max(5, (int) ceil($highestCount / 20) * 5);
$chartMax = max(20, $chartStep * 4);

render_admin_header('Sustainability & Activity Statistics', 'statistics');
?>

<main class="dashboardMain">
    <div class="statisticsPageHeading">
        <div>
            <p class="statisticsPageLabel">Administrator</p>
            <h1>Sustainability and Activity Statistics</h1>
            <p>Track monthly campus item reuse, repair completions, and student engagement.</p>
        </div>

        <form class="statisticsPeriod" action="adminStatistics.php" method="get">
            <div class="statisticsMonth">
                <label for="reportingPeriod">Reporting period</label>
                <select id="reportingPeriod" name="period">
                    <?php foreach ($reportingMonths as $value => $month): ?>
                        <option value="<?= escape_html($value) ?>" <?= $period === $value ? 'selected' : '' ?>>
                            <?= $month->format('F Y') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="statisticsButton statisticsApplyButton" type="submit">Apply period</button>
        </form>
    </div>

    <!-- Metrics Cards -->
    <div class="statisticsMetrics">
        <div class="summaryCard">
            <span class="summaryIcon" aria-hidden="true">
                <i class="fa-solid fa-check"></i>
            </span>
            <div>
                <strong><?= number_format($completedActivities) ?></strong>
                <span>Completed activities</span>
            </div>
        </div>

        <div class="summaryCard">
            <span class="summaryIcon listingIcon" aria-hidden="true">
                <i class="fa-solid fa-arrows-rotate"></i>
            </span>
            <div>
                <strong><?= number_format($itemsRecirculated) ?></strong>
                <span>Items recirculated</span>
            </div>
        </div>

        <div class="summaryCard">
            <span class="summaryIcon repairIcon" aria-hidden="true">
                <i class="fa-solid fa-users"></i>
            </span>
            <div>
                <strong><?= number_format($participatingStudents) ?></strong>
                <span>Participating students</span>
            </div>
        </div>

        <div class="summaryCard">
            <span class="summaryIcon applicationIcon" aria-hidden="true">
                <i class="fa-solid fa-leaf"></i>
            </span>
            <div>
                <strong><?= number_format($pointsAwarded) ?></strong>
                <span>Points awarded</span>
            </div>
        </div>
    </div>

    <!-- Chart & Activity Table -->
    <div class="dashboardPanels statisticsPanels">
        <section class="dashboardPanel">
            <h2>Completed Activities by Type</h2>
            <p class="attentionIntro"><?= escape_html($periodLabel) ?></p>

            <div class="statisticsChart">
                <?php foreach ($activities as $sourceType => $activity): ?>
                    <?php
                    $barWidth = round((int) $activity['completed'] / $chartMax * 100, 2);
                    ?>
                    <span><?= escape_html($activity['label']) ?></span>
                    <div class="statisticsTrack" aria-hidden="true">
                        <span class="statisticsBar <?= strtolower($sourceType) ?>" style="width: <?= $barWidth ?>%"></span>
                    </div>
                    <strong><?= number_format((int) $activity['completed']) ?></strong>
                <?php endforeach; ?>

                <div class="statisticsAxis" aria-hidden="true">
                    <?php for ($tick = 0; $tick <= 4; $tick++): ?>
                        <span><?= number_format($tick * $chartStep) ?></span>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <section class="dashboardPanel">
            <h2>Activity Breakdown</h2>

            <table class="statisticsTable">
                <thead>
                    <tr>
                        <th scope="col">Activity</th>
                        <th scope="col">Completed</th>
                        <th scope="col">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $sourceType => $activity): ?>
                        <tr>
                            <th scope="row">
                                <span class="statisticsDot <?= strtolower($sourceType) ?>" aria-hidden="true"></span>
                                <?= escape_html($activity['label']) ?>
                            </th>
                            <td><?= number_format((int) $activity['completed']) ?></td>
                            <td><?= number_format((int) $activity['points']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</main>

<?php render_admin_footer(); ?>
