<?php
// Sustainability Dashboard: Campus environmental metrics, student eco-points, and gamification tiers.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/sustainability-helpers.php';

$user = current_user();
$userId = (int) $user['user_id'];

// Get user points and eco tier
$userSummary = get_user_points_summary($conn, $userId);
$userPoints = (int) $userSummary['total_points'];
$ecoTier = calculate_eco_tier($userPoints);

// Get campus environmental impact metrics
$campusMetrics = get_campus_sustainability_metrics($conn);

// Get top 3 eco champions for teaser
$topStmt = mysqli_prepare(
    $conn,
    "SELECT user_id, full_name, tp_number, academic_program, total_points
     FROM users
     WHERE role = 'Student' AND total_points > 0
     ORDER BY total_points DESC, user_id ASC
     LIMIT 3"
);
mysqli_stmt_execute($topStmt);
$topChampions = mysqli_fetch_all(mysqli_stmt_get_result($topStmt), MYSQLI_ASSOC);
mysqli_stmt_close($topStmt);

// Get recent personal points transactions (last 4)
$recentStmt = mysqli_prepare(
    $conn,
    "SELECT pt.*
     FROM points_transactions pt
     WHERE pt.user_id = ?
     ORDER BY pt.transaction_id DESC
     LIMIT 4"
);
mysqli_stmt_bind_param($recentStmt, 'i', $userId);
mysqli_stmt_execute($recentStmt);
$recentTransactions = mysqli_fetch_all(mysqli_stmt_get_result($recentStmt), MYSQLI_ASSOC);
mysqli_stmt_close($recentStmt);

// Calculate percentage breakdown for personal points
$totalPts = max(1, $userPoints);
$repairPercent = round(($userSummary['repair_points'] / $totalPts) * 100);
$giveawayPercent = round(($userSummary['giveaway_points'] / $totalPts) * 100);
$exchangePercent = round(($userSummary['exchange_points'] / $totalPts) * 100);
$salePercent = round(($userSummary['sale_points'] / $totalPts) * 100);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section sust-page">
    <div class="container">
        <?php show_sustainability_nav('dashboard'); ?>

        <!-- Eco Hero -->
        <div class="sust-hero">
            <p class="sust-kicker"><i class="fa-solid fa-leaf" aria-hidden="true"></i> APU Circular Campus Impact</p>
            <h1>Campus Sustainability &amp; Rewards</h1>
            <p>
                Every repaired gadget, shared textbook, and exchanged dorm essential diverts waste from landfills.
                Track your real-world carbon offset, level up your Eco Tier, and earn co-curricular recognition!
            </p>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a class="btn btn-primary" href="points.php">
                    <i class="fa-solid fa-award" aria-hidden="true"></i> View My Points &amp; Badges
                </a>
                <a class="btn btn-outline-primary" style="background: rgba(255,255,255,0.15); border-color: #fff; color: #fff;" href="leaderboard.php">
                    <i class="fa-solid fa-trophy" aria-hidden="true"></i> View Campus Leaderboard
                </a>
            </div>
        </div>

        <!-- Personal Gamification Tier Banner -->
        <div class="sust-tier-banner">
            <div class="sust-tier-icon-wrap" style="border-color: <?= escape_html($ecoTier['color']) ?>; color: <?= escape_html($ecoTier['color']) ?>;">
                <i class="fa-solid <?= escape_html($ecoTier['badge']) ?>" aria-hidden="true"></i>
            </div>
            <div class="sust-tier-info">
                <div class="sust-tier-name">
                    <span><?= escape_html($ecoTier['name']) ?></span>
                    <span class="sust-tier-tag">Level <?= (int) $ecoTier['level'] ?></span>
                    <span style="font-size: 15px; font-weight: normal; color: #52645a; margin-left: auto;">
                        <strong><?= $userPoints ?></strong> Eco Points Earned
                    </span>
                </div>
                <p class="sust-tier-desc"><?= escape_html($ecoTier['description']) ?></p>

                <div class="sust-progress-wrap">
                    <div class="sust-progress-labels">
                        <span>Progress to next tier</span>
                        <span>
                            <?php if ($ecoTier['points_to_next'] > 0): ?>
                                <strong><?= (int) $ecoTier['points_to_next'] ?> pts</strong> to <?= escape_html($ecoTier['next_tier']) ?>
                            <?php else: ?>
                                <strong><?= escape_html($ecoTier['next_tier']) ?></strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="sust-progress-bar">
                        <div class="sust-progress-fill" style="width: <?= (int) $ecoTier['progress_percent'] ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campus Environmental Impact Stats -->
        <div class="sust-stat-grid">
            <div class="sust-stat-card">
                <div class="sust-stat-icon green">
                    <i class="fa-solid fa-recycle" aria-hidden="true"></i>
                </div>
                <div class="sust-stat-content">
                    <strong><?= number_format((float) $campusMetrics['waste_diverted_kg'], 1) ?> kg</strong>
                    <span>Landfill Waste Diverted</span>
                </div>
            </div>

            <div class="sust-stat-card">
                <div class="sust-stat-icon teal">
                    <i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>
                </div>
                <div class="sust-stat-content">
                    <strong><?= number_format((float) $campusMetrics['co2_saved_kg'], 1) ?> kg</strong>
                    <span>CO&sub2; Emissions Avoided</span>
                </div>
            </div>

            <div class="sust-stat-card">
                <div class="sust-stat-icon amber">
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                </div>
                <div class="sust-stat-content">
                    <strong><?= (int) ($campusMetrics['total_reused_items'] + $campusMetrics['total_repaired_items']) ?></strong>
                    <span>Total Items Saved</span>
                </div>
            </div>

            <div class="sust-stat-card">
                <div class="sust-stat-icon blue">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </div>
                <div class="sust-stat-content">
                    <strong><?= (int) $campusMetrics['participating_students'] ?></strong>
                    <span>Active Green Students</span>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Layout -->
        <div class="sust-grid-layout">
            <!-- Left Column: Personal Contribution & Recent Activity -->
            <div>
                <!-- Points Breakdown Card -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; font-size: 18px; color: #123c2a;">
                            <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> My Points Breakdown
                        </h2>
                        <a href="points.php" style="font-size: 13px; color: #1c7049; font-weight: 600;">Full Details &rarr;</a>
                    </div>
                    <div class="card-body">
                        <?php if ($userPoints === 0): ?>
                            <p class="text-muted" style="margin: 0; text-align: center; padding: 16px 0;">
                                You haven't earned eco-points yet. Post a giveaway, exchange an item, or complete a repair to start earning!
                            </p>
                        <?php else: ?>
                            <div class="sust-breakdown-row">
                                <div class="sust-breakdown-header">
                                    <span><i class="fa-solid fa-screwdriver-wrench" style="color: #8e44ad;"></i> Repair Volunteering</span>
                                    <span><strong><?= (int) $userSummary['repair_points'] ?> pts</strong> (<?= $repairPercent ?>%)</span>
                                </div>
                                <div class="sust-breakdown-bar">
                                    <div class="sust-breakdown-fill repair" style="width: <?= $repairPercent ?>%;"></div>
                                </div>
                            </div>

                            <div class="sust-breakdown-row">
                                <div class="sust-breakdown-header">
                                    <span><i class="fa-solid fa-gift" style="color: #27ae60;"></i> Free Giveaways</span>
                                    <span><strong><?= (int) $userSummary['giveaway_points'] ?> pts</strong> (<?= $giveawayPercent ?>%)</span>
                                </div>
                                <div class="sust-breakdown-bar">
                                    <div class="sust-breakdown-fill giveaway" style="width: <?= $giveawayPercent ?>%;"></div>
                                </div>
                            </div>

                            <div class="sust-breakdown-row">
                                <div class="sust-breakdown-header">
                                    <span><i class="fa-solid fa-arrow-right-arrow-left" style="color: #16a085;"></i> Item Exchanges</span>
                                    <span><strong><?= (int) $userSummary['exchange_points'] ?> pts</strong> (<?= $exchangePercent ?>%)</span>
                                </div>
                                <div class="sust-breakdown-bar">
                                    <div class="sust-breakdown-fill exchange" style="width: <?= $exchangePercent ?>%;"></div>
                                </div>
                            </div>

                            <div class="sust-breakdown-row">
                                <div class="sust-breakdown-header">
                                    <span><i class="fa-solid fa-tag" style="color: #2980b9;"></i> Pre-loved Sales</span>
                                    <span><strong><?= (int) $userSummary['sale_points'] ?> pts</strong> (<?= $salePercent ?>%)</span>
                                </div>
                                <div class="sust-breakdown-bar">
                                    <div class="sust-breakdown-fill sale" style="width: <?= $salePercent ?>%;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activities Card -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; font-size: 18px; color: #123c2a;">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Recent Eco Activities
                        </h2>
                        <a href="activity.php" style="font-size: 13px; color: #1c7049; font-weight: 600;">View History &rarr;</a>
                    </div>
                    <div class="card-body">
                        <?php if (!$recentTransactions): ?>
                            <p class="text-muted" style="margin: 0; text-align: center; padding: 20px 0;">
                                No recent points transactions found.
                            </p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($recentTransactions as $tx): ?>
                                    <?php
                                    $src = $tx['source_type'];
                                    $badgeClass = 'sust-source-' . strtolower($src);
                                    ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #fbfdfb; border: 1px solid #e1ece5; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="sust-source-badge <?= $badgeClass ?>">
                                                <?= escape_html($src) ?>
                                            </span>
                                            <span style="font-size: 13px; color: #405548;">
                                                Action completed (Ref #<?= (int) $tx['reference_id'] ?>)
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <span class="sust-points-awarded">+<?= (int) $tx['points_awarded'] ?> pts</span>
                                            <div style="font-size: 11px; color: #7a8e82;">
                                                <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Campus Top Champions & Actions -->
            <div>
                <!-- Leaderboard Teaser Card -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; font-size: 18px; color: #123c2a;">
                            <i class="fa-solid fa-trophy" aria-hidden="true" style="color: #eab308;"></i> Top Campus Champions
                        </h2>
                        <a href="leaderboard.php" style="font-size: 13px; color: #1c7049; font-weight: 600;">Leaderboard &rarr;</a>
                    </div>
                    <div class="card-body">
                        <?php if (!$topChampions): ?>
                            <p class="text-muted" style="margin: 0; text-align: center; padding: 14px 0;">
                                No student points recorded yet. Be the first to earn points!
                            </p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($topChampions as $i => $champ): ?>
                                    <?php
                                    $medals = ['🥇', '🥈', '🥉'];
                                    $medal = $medals[$i] ?? ('#' . ($i + 1));
                                    ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: #fbfdfb; border: 1px solid #e1ece5; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-size: 20px;"><?= $medal ?></span>
                                            <div>
                                                <div style="font-weight: 600; color: #123c2a; font-size: 14px;">
                                                    <?= escape_html($champ['full_name']) ?>
                                                </div>
                                                <div style="font-size: 11px; color: #62776a;">
                                                    <?= escape_html($champ['tp_number']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <span style="font-weight: 700; color: #1c7049; font-size: 15px;">
                                            <?= (int) $champ['total_points'] ?> <small style="font-weight: normal; font-size: 11px;">pts</small>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- How to Earn Points Card -->
                <div class="card" style="background: #fbfdfb; border: 1px solid #d6e3da; margin-bottom: 24px;">
                    <div class="card-body">
                        <h3 style="margin-top: 0; color: #123c2a; font-size: 16px;">
                            <i class="fa-solid fa-lightbulb" aria-hidden="true" style="color: #27ae60;"></i> How to Earn Green Points
                        </h3>
                        <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #43594b; line-height: 1.6;">
                            <li><strong>Repair an item:</strong> +25 points (Volunteer)</li>
                            <li><strong>Free item giveaway:</strong> +20 points</li>
                            <li><strong>Exchange an item:</strong> +15 points</li>
                            <li><strong>Sell pre-loved item:</strong> +10 points</li>
                        </ul>
                    </div>
                </div>

                <!-- APU SDG Card -->
                <div class="card" style="background: #f0f7f3; border: 1px solid #c2e2ce;">
                    <div class="card-body" style="font-size: 13px; color: #284734; line-height: 1.5;">
                        <h4 style="margin: 0 0 6px; color: #124d2d; font-size: 14px;">
                            <i class="fa-solid fa-earth-americas" aria-hidden="true"></i> UN SDG Goal 12 Aligned
                        </h4>
                        Fix &amp; Exchange supports the United Nations Sustainable Development Goal 12: Responsible Consumption and Production within the APU campus community.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
