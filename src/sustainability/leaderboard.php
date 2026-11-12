<?php
// Leaderboard: Campus-wide student sustainability rankings, podium highlights, and eco achievements.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/sustainability-helpers.php';

$user = current_user();
$userId = (int) $user['user_id'];

// Get user's current points
$userStmt = mysqli_prepare($conn, 'SELECT total_points FROM users WHERE user_id = ?');
mysqli_stmt_bind_param($userStmt, 'i', $userId);
mysqli_stmt_execute($userStmt);
$userData = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);

$currentUserPoints = (int) ($userData['total_points'] ?? 0);

// Calculate user's rank among students
$userRank = null;
if ($currentUserPoints > 0) {
    $rankStmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) + 1 AS student_rank
         FROM users
         WHERE role = 'Student' AND total_points > ?"
    );
    mysqli_stmt_bind_param($rankStmt, 'i', $currentUserPoints);
    mysqli_stmt_execute($rankStmt);
    $userRank = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($rankStmt))['student_rank'] ?? 1);
    mysqli_stmt_close($rankStmt);
}

// Fetch all ranked students (top 50)
$leaderboardStmt = mysqli_prepare(
    $conn,
    "SELECT user_id, full_name, tp_number, academic_program, total_points
     FROM users
     WHERE role = 'Student' AND total_points > 0
     ORDER BY total_points DESC, user_id ASC
     LIMIT 50"
);
mysqli_stmt_execute($leaderboardStmt);
$rankings = mysqli_fetch_all(mysqli_stmt_get_result($leaderboardStmt), MYSQLI_ASSOC);
mysqli_stmt_close($leaderboardStmt);

$podium = array_slice($rankings, 0, 3);
$otherRankings = array_slice($rankings, 3);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section sust-page">
    <div class="container">
        <?php show_sustainability_nav('leaderboard'); ?>

        <div class="repair-header-row">
            <div>
                <p class="sust-kicker">CAMPUS RANKINGS</p>
                <h1>Campus Sustainability Leaderboard</h1>
                <p>Recognizing the students leading circular economy, reuse, and peer repair initiatives at APU.</p>
            </div>
            <a class="btn btn-primary" href="points.php">
                <i class="fa-solid fa-award" aria-hidden="true"></i> My Points &amp; Badges
            </a>
        </div>

        <!-- Logged-in User Rank Callout -->
        <div class="sust-user-rank-banner">
            <div>
                <i class="fa-solid fa-user-tag" aria-hidden="true" style="color: #1c7049; margin-right: 6px;"></i>
                Your Campus Standing:
                <?php if ($currentUserPoints > 0 && $userRank): ?>
                    <strong>Rank #<?= $userRank ?></strong> with <strong><?= $currentUserPoints ?> Eco Points</strong>
                <?php else: ?>
                    <strong>Unranked (0 Points)</strong> &ndash; Complete a repair or giveaway to get on the board!
                <?php endif; ?>
            </div>
            <a href="points.php" style="font-weight: 600; color: #1c7049; text-decoration: none;">
                How to Earn &rarr;
            </a>
        </div>

        <?php if (!$rankings): ?>
            <div class="repair-empty-state">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                <h3>No campus scores recorded yet</h3>
                <p>Be the very first student to earn points and claim the #1 spot on the leaderboard!</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a class="btn btn-primary" href="../repair-requester/create-request.php">
                        <i class="fa-solid fa-screwdriver-wrench"></i> Request a Repair
                    </a>
                    <a class="btn btn-outline-primary" href="../buyer-seller/create-listing.php">
                        <i class="fa-solid fa-plus"></i> Post an Item
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Podium Grid for Top 3 -->
            <div class="sust-podium-grid">
                <!-- 2nd Place -->
                <?php if (isset($podium[1])): ?>
                    <?php $tier2 = calculate_eco_tier((int) $podium[1]['total_points']); ?>
                    <div class="sust-podium-card second">
                        <div class="sust-podium-medal">🥈</div>
                        <span class="sust-podium-label">2nd Place</span>
                        <h3 class="sust-podium-name"><?= escape_html($podium[1]['full_name']) ?></h3>
                        <p class="sust-podium-program"><?= escape_html($podium[1]['academic_program'] ?: 'Student') ?></p>
                        <span class="sust-podium-points"><?= (int) $podium[1]['total_points'] ?></span>
                        <span class="sust-podium-label">Points</span>
                        <div style="margin-top: 10px;">
                            <span class="sust-tier-tag"><?= escape_html($tier2['name']) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>

                <!-- 1st Place -->
                <?php if (isset($podium[0])): ?>
                    <?php $tier1 = calculate_eco_tier((int) $podium[0]['total_points']); ?>
                    <div class="sust-podium-card first">
                        <div class="sust-podium-medal">🥇</div>
                        <span class="sust-podium-label" style="color: #b45309; font-weight: 700;">Champion</span>
                        <h3 class="sust-podium-name" style="font-size: 19px;"><?= escape_html($podium[0]['full_name']) ?></h3>
                        <p class="sust-podium-program"><?= escape_html($podium[0]['academic_program'] ?: 'Student') ?></p>
                        <span class="sust-podium-points" style="font-size: 28px; color: #145934;"><?= (int) $podium[0]['total_points'] ?></span>
                        <span class="sust-podium-label">Points</span>
                        <div style="margin-top: 10px;">
                            <span class="sust-tier-tag" style="background: #fef08a; color: #854d0e;"><?= escape_html($tier1['name']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 3rd Place -->
                <?php if (isset($podium[2])): ?>
                    <?php $tier3 = calculate_eco_tier((int) $podium[2]['total_points']); ?>
                    <div class="sust-podium-card third">
                        <div class="sust-podium-medal">🥉</div>
                        <span class="sust-podium-label">3rd Place</span>
                        <h3 class="sust-podium-name"><?= escape_html($podium[2]['full_name']) ?></h3>
                        <p class="sust-podium-program"><?= escape_html($podium[2]['academic_program'] ?: 'Student') ?></p>
                        <span class="sust-podium-points"><?= (int) $podium[2]['total_points'] ?></span>
                        <span class="sust-podium-label">Points</span>
                        <div style="margin-top: 10px;">
                            <span class="sust-tier-tag"><?= escape_html($tier3['name']) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
            </div>

            <!-- Full Leaderboard Table -->
            <div class="table-wrap card">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 70px; text-align: center;">Rank</th>
                            <th>Student Name</th>
                            <th>TP Number</th>
                            <th>Programme / Intake</th>
                            <th>Tier Level</th>
                            <th style="text-align: right;">Total Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rankings as $index => $row): ?>
                            <?php
                            $rank = $index + 1;
                            $isUserRow = ((int) $row['user_id'] === $userId);
                            $tier = calculate_eco_tier((int) $row['total_points']);

                            $medalIcon = '';
                            if ($rank === 1) $medalIcon = '🥇 ';
                            elseif ($rank === 2) $medalIcon = '🥈 ';
                            elseif ($rank === 3) $medalIcon = '🥉 ';
                            ?>
                            <tr style="<?= $isUserRow ? 'background: #edf7f0; font-weight: 600;' : '' ?>">
                                <td style="text-align: center; font-size: 15px;">
                                    <strong><?= $medalIcon ?>#<?= $rank ?></strong>
                                </td>
                                <td>
                                    <?= escape_html($row['full_name']) ?>
                                    <?php if ($isUserRow): ?>
                                        <span class="badge badge-primary" style="font-size: 10px; margin-left: 6px;">You</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #63776b;"><?= escape_html($row['tp_number']) ?></td>
                                <td style="color: #63776b;"><?= escape_html($row['academic_program'] ?: '—') ?></td>
                                <td>
                                    <span class="sust-tier-tag">
                                        <i class="fa-solid <?= escape_html($tier['badge']) ?>" aria-hidden="true"></i>
                                        <?= escape_html($tier['name']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <strong style="color: #1c7049; font-size: 15px;"><?= (int) $row['total_points'] ?></strong> pts
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
