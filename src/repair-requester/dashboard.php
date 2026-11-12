<?php
// Repair Requester Dashboard: Summary of personal repair jobs, active diagnostics, and quick actions.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/repair-helpers.php';

$userId = (int) current_user()['user_id'];

// Get counts
$statsStmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total_requests,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status IN ('Open', 'Verified') THEN 1 ELSE 0 END) AS open_count
     FROM repair_requests
     WHERE requester_id = ?"
);
mysqli_stmt_bind_param($statsStmt, 'i', $userId);
mysqli_stmt_execute($statsStmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($statsStmt)) ?: [
    'total_requests' => 0,
    'in_progress_count' => 0,
    'completed_count' => 0,
    'open_count' => 0,
];
mysqli_stmt_close($statsStmt);

// Get active in-progress repairs
$activeStmt = mysqli_prepare(
    $conn,
    "SELECT r.request_id, r.title, r.created_at, c.category_name,
            a.assignment_id, a.progress_status, a.assigned_at,
            vu.full_name AS volunteer_name, vu.tp_number AS volunteer_tp,
            (SELECT image_path FROM repair_images WHERE request_id = r.request_id ORDER BY repair_image_id ASC LIMIT 1) AS image_path
     FROM repair_requests r
     JOIN categories c ON c.category_id = r.category_id
     JOIN repair_assignments a ON a.request_id = r.request_id
     JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id
     JOIN users vu ON vu.user_id = v.user_id
     WHERE r.requester_id = ? AND r.status = 'In Progress'
     ORDER BY a.assigned_at DESC"
);
mysqli_stmt_bind_param($activeStmt, 'i', $userId);
mysqli_stmt_execute($activeStmt);
$activeRepairs = mysqli_fetch_all(mysqli_stmt_get_result($activeStmt), MYSQLI_ASSOC);
mysqli_stmt_close($activeStmt);

// Get recent requests (last 5)
$recentStmt = mysqli_prepare(
    $conn,
    "SELECT r.request_id, r.title, r.status, r.created_at, c.category_name,
            (SELECT image_path FROM repair_images WHERE request_id = r.request_id ORDER BY repair_image_id ASC LIMIT 1) AS image_path
     FROM repair_requests r
     JOIN categories c ON c.category_id = r.category_id
     WHERE r.requester_id = ?
     ORDER BY r.request_id DESC
     LIMIT 5"
);
mysqli_stmt_bind_param($recentStmt, 'i', $userId);
mysqli_stmt_execute($recentStmt);
$recentRequests = mysqli_fetch_all(mysqli_stmt_get_result($recentStmt), MYSQLI_ASSOC);
mysqli_stmt_close($recentStmt);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section repair-page">
    <div class="container">
        <?php show_repair_requester_nav('dashboard'); ?>

        <div class="repair-header-row">
            <div>
                <p class="repair-kicker">REPAIR REQUESTER</p>
                <h1>Requester Dashboard</h1>
                <p>Welcome back! Monitor your active repair jobs, communicate with volunteers, and track item fixes.</p>
            </div>
            <a class="btn btn-primary" href="create-request.php">
                <i class="fa-solid fa-plus-circle" aria-hidden="true"></i> Request a Repair
            </a>
        </div>

        <!-- Stat Cards -->
        <div class="repair-stat-grid">
            <div class="repair-stat-card">
                <div class="repair-stat-icon green">
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['total_requests'] ?></strong>
                    <span>Total Submitted</span>
                </div>
            </div>

            <div class="repair-stat-card">
                <div class="repair-stat-icon orange">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['in_progress_count'] ?></strong>
                    <span>In Progress</span>
                </div>
            </div>

            <div class="repair-stat-card">
                <div class="repair-stat-icon blue">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['completed_count'] ?></strong>
                    <span>Successfully Repaired</span>
                </div>
            </div>

            <div class="repair-stat-card">
                <div class="repair-stat-icon purple">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                </div>
                <div class="repair-stat-content">
                    <strong><?= (int) $stats['open_count'] ?></strong>
                    <span>Awaiting Volunteers</span>
                </div>
            </div>
        </div>

        <!-- Active In-Progress Repairs Alert & Highlights -->
        <?php if ($activeRepairs): ?>
            <section class="repair-workflow-section" aria-labelledby="active-repairs-title">
                <h2 class="repair-section-title" id="active-repairs-title">
                    <i class="fa-solid fa-bolt" aria-hidden="true" style="color: #e09214;"></i> Active Repairs in Progress
                </h2>
                <div class="repair-request-list">
                    <?php foreach ($activeRepairs as $active): ?>
                        <article class="repair-item-row" style="border-left: 4px solid #e09214;">
                            <div class="repair-item-left">
                                <?php if ($active['image_path']): ?>
                                    <img class="repair-thumb-img" src="/fix-and-exchange/<?= escape_html($active['image_path']) ?>" alt="<?= escape_html($active['title']) ?>">
                                <?php else: ?>
                                    <div class="repair-no-image">
                                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                                        <span>No photo</span>
                                    </div>
                                <?php endif; ?>

                                <div class="repair-item-meta">
                                    <h2>
                                        <a href="request-details.php?request_id=<?= (int) $active['request_id'] ?>">
                                            <?= escape_html($active['title']) ?>
                                        </a>
                                    </h2>
                                    <div class="repair-item-submeta">
                                        <span><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= escape_html($active['category_name']) ?></span>
                                        <span>&bull;</span>
                                        <span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Volunteer: <strong><?= escape_html($active['volunteer_name']) ?></strong></span>
                                        <span>&bull;</span>
                                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i> Started: <?= date('M j, Y', strtotime($active['assigned_at'])) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="repair-item-right">
                                <span class="repair-badge repair-badge-in-progress">
                                    <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <?= escape_html($active['progress_status']) ?>
                                </span>
                                <a class="btn btn-sm btn-primary" href="../messages/repair.php?request_id=<?= (int) $active['request_id'] ?>">
                                    <i class="fa-solid fa-comments" aria-hidden="true"></i> Chat
                                </a>
                                <a class="btn btn-sm btn-outline-primary" href="request-details.php?request_id=<?= (int) $active['request_id'] ?>">
                                    Details
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Recent Requests & Quick Actions Grid -->
        <div class="repair-details-layout">
            <div class="repair-details-main">
                <div class="repair-card">
                    <div class="repair-card-header">
                        <h2>Recent Repair Requests</h2>
                        <a href="my-requests.php" style="font-size: 13px; color: #1c7049; font-weight: 600;">View All (<?= (int) $stats['total_requests'] ?>) &rarr;</a>
                    </div>

                    <?php if (!$recentRequests): ?>
                        <p class="text-muted" style="margin: 0; padding: 20px 0; text-align: center;">
                            You haven't submitted any repair requests yet.
                        </p>
                    <?php else: ?>
                        <div class="repair-recent-list">
                            <?php foreach ($recentRequests as $rec): ?>
                                <?php
                                $s = $rec['status'];
                                $badgeClass = 'repair-badge-open';
                                if ($s === 'Verified') $badgeClass = 'repair-badge-verified';
                                elseif ($s === 'In Progress') $badgeClass = 'repair-badge-in-progress';
                                elseif ($s === 'Completed') $badgeClass = 'repair-badge-completed';
                                elseif ($s === 'Cancelled') $badgeClass = 'repair-badge-cancelled';
                                ?>
                                <div class="repair-recent-row">
                                    <div class="repair-recent-row-left">
                                        <?php if ($rec['image_path']): ?>
                                            <img class="repair-recent-row-thumb" src="/fix-and-exchange/<?= escape_html($rec['image_path']) ?>" alt="<?= escape_html($rec['title']) ?>">
                                        <?php else: ?>
                                            <div class="repair-recent-no-photo">
                                                <i class="fa-solid fa-wrench" aria-hidden="true"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="repair-recent-row-info">
                                            <a class="repair-recent-row-title" href="request-details.php?request_id=<?= (int) $rec['request_id'] ?>">
                                                <?= escape_html($rec['title']) ?>
                                            </a>
                                            <div class="repair-recent-row-meta">
                                                <?= escape_html($rec['category_name']) ?> &bull; <?= date('M j, Y', strtotime($rec['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="repair-recent-row-right">
                                        <span class="repair-badge <?= $badgeClass ?>"><?= escape_html($s) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="repair-details-sidebar">
                <!-- Quick Navigation Links -->
                <div class="repair-card">
                    <div class="repair-card-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a class="btn btn-primary btn-block" href="create-request.php">
                            <i class="fa-solid fa-plus-circle" aria-hidden="true"></i> Submit New Request
                        </a>
                        <a class="btn btn-outline-primary btn-block" href="repair-hub.php">
                            <i class="fa-solid fa-compass" aria-hidden="true"></i> Explore Repair Hub
                        </a>
                        <a class="btn btn-outline-primary btn-block" href="../repair-volunteer/apply.php">
                            <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Apply to Volunteer
                        </a>
                        <a class="btn btn-secondary btn-block" href="../account/home.php">
                            <i class="fa-solid fa-house-user" aria-hidden="true"></i> Back to Campus Home
                        </a>
                    </div>
                </div>

                <!-- Guidelines Card -->
                <div class="card" style="background: #fbfdfb; border: 1px solid #d6e3da;">
                    <div class="card-body">
                        <h4 style="margin-top: 0; color: #123c2a; font-size: 15px;">
                            <i class="fa-solid fa-shield-check" aria-hidden="true" style="color: #1c7049;"></i> Repair Requester Tips
                        </h4>
                        <p style="margin: 0; font-size: 13px; color: #52665a; line-height: 1.5;">
                            When meeting volunteers for handover on campus, arrange to meet at safe public spots like the APU Library entrance or Atrium. Verify serial numbers and inspect the device together during collection.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
