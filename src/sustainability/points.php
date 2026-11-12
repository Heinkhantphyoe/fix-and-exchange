<?php
// Points & Badges: Display student points balance, gamification tiers, and reward criteria.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/sustainability-helpers.php';

$user = current_user();
$userId = (int) $user['user_id'];

// Get user points and eco tier
$summary = get_user_points_summary($conn, $userId);
$userPoints = (int) $summary['total_points'];
$ecoTier = calculate_eco_tier($userPoints);

// Count of activities by type
$countsStmt = mysqli_prepare(
    $conn,
    "SELECT
        SUM(CASE WHEN source_type = 'Repair' THEN 1 ELSE 0 END) AS repair_count,
        SUM(CASE WHEN source_type = 'Giveaway' THEN 1 ELSE 0 END) AS giveaway_count,
        SUM(CASE WHEN source_type = 'Exchange' THEN 1 ELSE 0 END) AS exchange_count,
        SUM(CASE WHEN source_type = 'Sale' THEN 1 ELSE 0 END) AS sale_count
     FROM points_transactions
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($countsStmt, 'i', $userId);
mysqli_stmt_execute($countsStmt);
$typeCounts = mysqli_fetch_assoc(mysqli_stmt_get_result($countsStmt)) ?: [
    'repair_count' => 0,
    'giveaway_count' => 0,
    'exchange_count' => 0,
    'sale_count' => 0,
];
mysqli_stmt_close($countsStmt);

// Define all 4 tiers for display
$allTiers = [
    [
        'level' => 1,
        'name' => 'Eco Starter',
        'points' => '0 – 49 pts',
        'badge' => 'fa-seedling',
        'color' => '#4a7c59',
        'desc' => 'Welcome to campus circular living. Post or claim your first item to kick off your eco journey.',
        'reward' => 'APU Green Community Membership',
    ],
    [
        'level' => 2,
        'name' => 'Green Contributor',
        'points' => '50 – 149 pts',
        'badge' => 'fa-leaf',
        'color' => '#2e8b57',
        'desc' => 'Regularly extending the lifecycle of dorm gear and study materials across campus.',
        'reward' => 'Official Co-Curricular Eco Badge',
    ],
    [
        'level' => 3,
        'name' => 'Eco Guardian',
        'points' => '150 – 299 pts',
        'badge' => 'fa-tree',
        'color' => '#1c7049',
        'desc' => 'High-impact sustainability advocate with consistent repairs, exchanges, or giveaways.',
        'reward' => 'APU Sustainability Leadership Commendation',
    ],
    [
        'level' => 4,
        'name' => 'Planet Hero',
        'points' => '300+ pts',
        'badge' => 'fa-earth-americas',
        'color' => '#0c4d29',
        'desc' => 'Campus champion leading electronic waste diversion and peer repair master.',
        'reward' => 'Distinguished Campus Green Ambassador Honor',
    ],
];

require __DIR__ . '/../../includes/header.php';
?>

<section class="section sust-page">
    <div class="container">
        <?php show_sustainability_nav('points'); ?>

        <div class="repair-header-row">
            <div>
                <p class="sust-kicker">REWARDS &amp; GAMIFICATION</p>
                <h1>Sustainability Points &amp; Badges</h1>
                <p>Earn points for every sustainable choice and unlock campus co-curricular recognition tiers.</p>
            </div>
            <a class="btn btn-primary" href="activity.php">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> View Transaction Log
            </a>
        </div>

        <!-- Current Tier Card -->
        <div class="sust-tier-banner">
            <div class="sust-tier-icon-wrap" style="border-color: <?= escape_html($ecoTier['color']) ?>; color: <?= escape_html($ecoTier['color']) ?>;">
                <i class="fa-solid <?= escape_html($ecoTier['badge']) ?>" aria-hidden="true"></i>
            </div>
            <div class="sust-tier-info">
                <div class="sust-tier-name">
                    <span><?= escape_html($ecoTier['name']) ?></span>
                    <span class="sust-tier-tag">Level <?= (int) $ecoTier['level'] ?></span>
                    <span style="font-size: 16px; font-weight: normal; color: #52645a; margin-left: auto;">
                        Current Total: <strong style="color: #1c7049; font-size: 20px;"><?= $userPoints ?></strong> pts
                    </span>
                </div>
                <p class="sust-tier-desc"><?= escape_html($ecoTier['description']) ?></p>

                <div class="sust-progress-wrap">
                    <div class="sust-progress-labels">
                        <span>Tier Progress</span>
                        <span>
                            <?php if ($ecoTier['points_to_next'] > 0): ?>
                                <strong><?= (int) $ecoTier['points_to_next'] ?> points needed</strong> for next level
                            <?php else: ?>
                                <strong>Highest Tier Unlocked!</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="sust-progress-bar">
                        <div class="sust-progress-fill" style="width: <?= (int) $ecoTier['progress_percent'] ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earning Activities Grid -->
        <div class="repair-workflow-section">
            <h2 class="repair-section-title">Points by Sustainable Action</h2>
            <div class="repair-steps-grid">
                <!-- Repair Card -->
                <div class="repair-step-card" style="border-top: 4px solid #8e44ad;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <span class="sust-source-badge sust-source-repair">Repair Volunteer</span>
                        <strong style="color: #8e44ad; font-size: 18px;">+25 pts</strong>
                    </div>
                    <h3 style="margin: 14px 0 6px;">Appliance &amp; Tech Repair</h3>
                    <p>Earned as an approved volunteer completing item diagnosis and restoration.</p>
                    <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e1ece5; display: flex; justify-content: space-between; font-size: 12px; color: #55695d;">
                        <span>Completed: <strong><?= (int) $typeCounts['repair_count'] ?></strong></span>
                        <span>Points: <strong style="color: #123c2a;"><?= (int) $summary['repair_points'] ?></strong></span>
                    </div>
                </div>

                <!-- Giveaway Card -->
                <div class="repair-step-card" style="border-top: 4px solid #27ae60;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <span class="sust-source-badge sust-source-giveaway">Free Giveaway</span>
                        <strong style="color: #27ae60; font-size: 18px;">+20 pts</strong>
                    </div>
                    <h3 style="margin: 14px 0 6px;">Zero-Waste Sharing</h3>
                    <p>Give away unneeded study materials or dorm essentials for free to peers.</p>
                    <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e1ece5; display: flex; justify-content: space-between; font-size: 12px; color: #55695d;">
                        <span>Given away: <strong><?= (int) $typeCounts['giveaway_count'] ?></strong></span>
                        <span>Points: <strong style="color: #123c2a;"><?= (int) $summary['giveaway_points'] ?></strong></span>
                    </div>
                </div>

                <!-- Exchange Card -->
                <div class="repair-step-card" style="border-top: 4px solid #16a085;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <span class="sust-source-badge sust-source-exchange">Item Exchange</span>
                        <strong style="color: #16a085; font-size: 18px;">+15 pts</strong>
                    </div>
                    <h3 style="margin: 14px 0 6px;">Campus Barter Trade</h3>
                    <p>Exchange books, electronics, or equipment directly with other students.</p>
                    <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e1ece5; display: flex; justify-content: space-between; font-size: 12px; color: #55695d;">
                        <span>Exchanged: <strong><?= (int) $typeCounts['exchange_count'] ?></strong></span>
                        <span>Points: <strong style="color: #123c2a;"><?= (int) $summary['exchange_points'] ?></strong></span>
                    </div>
                </div>

                <!-- Sale Card -->
                <div class="repair-step-card" style="border-top: 4px solid #2980b9;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <span class="sust-source-badge sust-source-sale">Pre-Loved Sale</span>
                        <strong style="color: #2980b9; font-size: 18px;">+10 pts</strong>
                    </div>
                    <h3 style="margin: 14px 0 6px;">Second-Hand Resale</h3>
                    <p>Sell affordable used goods within the campus community instead of dumping.</p>
                    <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e1ece5; display: flex; justify-content: space-between; font-size: 12px; color: #55695d;">
                        <span>Sold: <strong><?= (int) $typeCounts['sale_count'] ?></strong></span>
                        <span>Points: <strong style="color: #123c2a;"><?= (int) $summary['sale_points'] ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Roadmap & Badges Section -->
        <section class="repair-workflow-section" aria-labelledby="roadmap-title">
            <h2 class="repair-section-title" id="roadmap-title">Gamification Tier Roadmap</h2>
            <div class="repair-steps-grid">
                <?php foreach ($allTiers as $tier): ?>
                    <?php
                    $isCurrent = ($ecoTier['level'] === $tier['level']);
                    $isUnlocked = ($ecoTier['level'] >= $tier['level']);
                    ?>
                    <div class="repair-step-card" style="<?= $isCurrent ? 'border: 2px solid #1c7049; background: #fbfefc;' : '' ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span style="font-size: 12px; font-weight: 700; color: <?= escape_html($tier['color']) ?>;">
                                LEVEL <?= (int) $tier['level'] ?>
                            </span>
                            <?php if ($isCurrent): ?>
                                <span class="sust-tier-tag">Current Level</span>
                            <?php elseif ($isUnlocked): ?>
                                <span class="repair-badge repair-badge-completed"><i class="fa-solid fa-check"></i> Unlocked</span>
                            <?php else: ?>
                                <span class="repair-badge repair-badge-cancelled"><i class="fa-solid fa-lock"></i> Locked</span>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #eef6f1; display: flex; align-items: center; justify-content: center; color: <?= escape_html($tier['color']) ?>; font-size: 18px;">
                                <i class="fa-solid <?= escape_html($tier['badge']) ?>" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 16px; color: #123c2a;"><?= escape_html($tier['name']) ?></h3>
                                <small style="color: #63776b;"><?= escape_html($tier['points']) ?></small>
                            </div>
                        </div>

                        <p style="font-size: 13px; color: #52645a; line-height: 1.45;"><?= escape_html($tier['desc']) ?></p>

                        <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e1ece5;">
                            <span style="font-size: 11px; text-transform: uppercase; color: #7a8e82; display: block; margin-bottom: 2px;">Recognition:</span>
                            <strong style="font-size: 12px; color: #1c7049;"><?= escape_html($tier['reward']) ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Co-Curricular & Certification Note -->
        <div class="card" style="border-left: 4px solid #1c7049; margin-top: 20px;">
            <div class="card-body">
                <h3 style="margin-top: 0; color: #123c2a; font-size: 16px;">
                    <i class="fa-solid fa-graduation-cap" aria-hidden="true" style="color: #1c7049;"></i> University Co-Curricular Certification
                </h3>
                <p style="margin: 0; color: #3c5244; font-size: 14px; line-height: 1.5;">
                    Your green points are recorded into your official student activity transcript. Students reaching <strong>Eco Guardian</strong> or <strong>Planet Hero</strong> status are eligible for annual Sustainability Merit Certificates endorsed by the campus Student Services and Makerspace committee.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
