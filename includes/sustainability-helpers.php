<?php
// Shared functions and helpers for the sustainability module.

if (!function_exists('escape_html')) {
    function escape_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Award sustainability points atomically and idempotently.
 */
function award_sustainability_points(mysqli $conn, int $userId, int $points, string $sourceType, int $referenceId): bool
{
    if ($points <= 0 || $userId <= 0) {
        return false;
    }

    $validSources = ['Sale', 'Giveaway', 'Exchange', 'Repair'];
    if (!in_array($sourceType, $validSources, true)) {
        return false;
    }

    $statement = mysqli_prepare(
        $conn,
        "INSERT INTO points_transactions (user_id, points_awarded, source_type, reference_id)
         SELECT ?, ?, ?, ?
         FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM points_transactions
             WHERE user_id = ? AND source_type = ? AND reference_id = ?
         )"
    );

    if (!$statement) {
        return false;
    }

    mysqli_stmt_bind_param($statement, 'iisiiisi', $userId, $points, $sourceType, $referenceId, $userId, $sourceType, $referenceId);
    mysqli_stmt_execute($statement);
    $inserted = mysqli_stmt_affected_rows($statement) > 0;
    mysqli_stmt_close($statement);

    if ($inserted) {
        $updateStmt = mysqli_prepare($conn, 'UPDATE users SET total_points = total_points + ? WHERE user_id = ?');
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'ii', $points, $userId);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
    }

    return $inserted;
}

/**
 * Calculate the user's gamified Eco Tier based on points.
 */
function calculate_eco_tier(int $points): array
{
    if ($points >= 300) {
        return [
            'level' => 4,
            'name' => 'Planet Hero',
            'badge' => 'fa-earth-americas',
            'color' => '#0c4d29',
            'min_points' => 300,
            'next_tier' => 'Maximum Tier Achieved!',
            'points_to_next' => 0,
            'progress_percent' => 100,
            'description' => 'A master of circular economy and campus repair champion.',
        ];
    }

    if ($points >= 150) {
        $needed = 300 - $points;
        $range = 300 - 150;
        $earned = $points - 150;
        $percent = min(100, max(0, round(($earned / $range) * 100)));
        return [
            'level' => 3,
            'name' => 'Eco Guardian',
            'badge' => 'fa-tree',
            'color' => '#1c7049',
            'min_points' => 150,
            'next_tier' => 'Planet Hero (300 pts)',
            'points_to_next' => $needed,
            'progress_percent' => $percent,
            'description' => 'Consistently reduces campus e-waste and supports peers.',
        ];
    }

    if ($points >= 50) {
        $needed = 150 - $points;
        $range = 150 - 50;
        $earned = $points - 50;
        $percent = min(100, max(0, round(($earned / $range) * 100)));
        return [
            'level' => 2,
            'name' => 'Green Contributor',
            'badge' => 'fa-leaf',
            'color' => '#2e8b57',
            'min_points' => 50,
            'next_tier' => 'Eco Guardian (150 pts)',
            'points_to_next' => $needed,
            'progress_percent' => $percent,
            'description' => 'Actively gives pre-loved items a second life on campus.',
        ];
    }

    $needed = 50 - $points;
    $percent = min(100, max(0, round(($points / 50) * 100)));
    return [
        'level' => 1,
        'name' => 'Eco Starter',
        'badge' => 'fa-seedling',
        'color' => '#4a7c59',
        'min_points' => 0,
        'next_tier' => 'Green Contributor (50 pts)',
        'points_to_next' => max(0, $needed),
        'progress_percent' => $percent,
        'description' => 'Embarking on the sustainable campus journey.',
    ];
}

/**
 * Fetch detailed personal sustainability summary.
 */
function get_user_points_summary(mysqli $conn, int $userId): array
{
    $statement = mysqli_prepare(
        $conn,
        "SELECT
            u.total_points,
            (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions WHERE user_id = u.user_id AND source_type = 'Repair') AS repair_points,
            (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions WHERE user_id = u.user_id AND source_type = 'Giveaway') AS giveaway_points,
            (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions WHERE user_id = u.user_id AND source_type = 'Exchange') AS exchange_points,
            (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions WHERE user_id = u.user_id AND source_type = 'Sale') AS sale_points,
            (SELECT COUNT(*) FROM points_transactions WHERE user_id = u.user_id) AS total_activities
         FROM users u
         WHERE u.user_id = ?
         LIMIT 1"
    );

    if (!$statement) {
        return [
            'total_points' => 0,
            'repair_points' => 0,
            'giveaway_points' => 0,
            'exchange_points' => 0,
            'sale_points' => 0,
            'total_activities' => 0,
        ];
    }

    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)) ?: [
        'total_points' => 0,
        'repair_points' => 0,
        'giveaway_points' => 0,
        'exchange_points' => 0,
        'sale_points' => 0,
        'total_activities' => 0,
    ];
    mysqli_stmt_close($statement);

    return $summary;
}

/**
 * Fetch campus-wide environmental impact stats.
 */
function get_campus_sustainability_metrics(mysqli $conn): array
{
    $query = "SELECT
        (SELECT COALESCE(SUM(points_awarded), 0) FROM points_transactions) AS campus_points,
        (SELECT COUNT(*) FROM points_transactions) AS total_impact_actions,
        (SELECT COUNT(DISTINCT user_id) FROM points_transactions) AS participating_students,
        (SELECT COUNT(*) FROM repair_requests WHERE status = 'Completed') AS total_repaired_items,
        (SELECT COUNT(*) FROM item_listings WHERE status = 'Completed') AS total_reused_items";

    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result) ?: [
        'campus_points' => 0,
        'total_impact_actions' => 0,
        'participating_students' => 0,
        'total_repaired_items' => 0,
        'total_reused_items' => 0,
    ];

    // Estimates:
    // ~1.6 kg waste diverted per reused item, ~2.2 kg per repaired item.
    // ~3.4 kg CO2 avoided per reused item, ~5.2 kg per repaired electronics.
    $reused = (int) $data['total_reused_items'];
    $repaired = (int) $data['total_repaired_items'];

    $data['waste_diverted_kg'] = round(($reused * 1.6) + ($repaired * 2.2), 1);
    $data['co2_saved_kg'] = round(($reused * 3.4) + ($repaired * 5.2), 1);

    return $data;
}

/**
 * Render the sustainability sub-navigation bar.
 */
function show_sustainability_nav(string $active = ''): void
{
    $links = [
        'dashboard' => ['<i class="fa-solid fa-leaf" aria-hidden="true"></i> Eco Dashboard', '/fix-and-exchange/src/sustainability/dashboard.php'],
        'points' => ['<i class="fa-solid fa-award" aria-hidden="true"></i> Points &amp; Badges', '/fix-and-exchange/src/sustainability/points.php'],
        'activity' => ['<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Activity Log', '/fix-and-exchange/src/sustainability/activity.php'],
        'leaderboard' => ['<i class="fa-solid fa-ranking-star" aria-hidden="true"></i> Leaderboard', '/fix-and-exchange/src/sustainability/leaderboard.php'],
    ];
    ?>
    <nav class="sust-subnav" aria-label="Sustainability sub-navigation">
        <?php foreach ($links as $key => $link): ?>
            <a class="sust-subnav-link <?= $active === $key ? 'is-active' : '' ?>" href="<?= $link[1] ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
                <?= $link[0] ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
    $flash = get_flash();
    if ($flash):
    ?>
        <div class="alert alert-<?= escape_html($flash['type']) ?>" role="status">
            <?= escape_html($flash['message']) ?>
        </div>
    <?php
    endif;
}
