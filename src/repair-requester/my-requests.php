<?php
// My Requests: View and track all submitted repair jobs for the logged-in student.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/repair-helpers.php';

$userId = (int) current_user()['user_id'];
$status = $_GET['status'] ?? 'All';
$validStatuses = ['All', 'Open', 'Verified', 'In Progress', 'Completed', 'Cancelled'];

if (!in_array($status, $validStatuses, true)) {
    $status = 'All';
}

// Fetch count summaries
$countStmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
     FROM repair_requests
     WHERE requester_id = ?"
);
mysqli_stmt_bind_param($countStmt, 'i', $userId);
mysqli_stmt_execute($countStmt);
$counts = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt)) ?: [
    'total' => 0,
    'open_count' => 0,
    'in_progress_count' => 0,
    'completed_count' => 0,
];
mysqli_stmt_close($countStmt);

// Fetch requests with volunteer assignment info and primary thumbnail
$sql = "SELECT r.*, c.category_name,
        a.assignment_id, a.progress_status, a.completed_at,
        vu.user_id AS volunteer_user_id, vu.full_name AS volunteer_name,
        (SELECT image_path FROM repair_images WHERE request_id = r.request_id ORDER BY repair_image_id ASC LIMIT 1) AS thumbnail_path
    FROM repair_requests r
    JOIN categories c ON c.category_id = r.category_id
    LEFT JOIN repair_assignments a ON a.request_id = r.request_id
    LEFT JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id
    LEFT JOIN users vu ON vu.user_id = v.user_id
    WHERE r.requester_id = ? AND (? = 'All' OR r.status = ?)
    ORDER BY r.request_id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'iss', $userId, $status, $status);
mysqli_stmt_execute($stmt);
$requests = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section repair-page">
    <div class="container">
        <?php show_repair_requester_nav('my-requests'); ?>

        <div class="repair-header-row">
            <div>
                <p class="repair-kicker">STUDENT REPAIRS</p>
                <h1>My Repair Requests</h1>
                <p>Track diagnosis, volunteer updates, and handover for your campus items.</p>
            </div>
            <a class="btn btn-primary" href="create-request.php">
                <i class="fa-solid fa-plus-circle" aria-hidden="true"></i> Request a Repair
            </a>
        </div>

        <!-- Filter tabs -->
        <nav class="repair-tabs" aria-label="Repair status filter">
            <?php foreach ($validStatuses as $tab): ?>
                <a class="repair-tab-item <?= $status === $tab ? 'is-active' : '' ?>" href="?status=<?= urlencode($tab) ?>">
                    <?= escape_html($tab) ?>
                    <?php if ($tab === 'All'): ?>
                        (<?= (int) $counts['total'] ?>)
                    <?php elseif ($tab === 'Open'): ?>
                        (<?= (int) $counts['open_count'] ?>)
                    <?php elseif ($tab === 'In Progress'): ?>
                        (<?= (int) $counts['in_progress_count'] ?>)
                    <?php elseif ($tab === 'Completed'): ?>
                        (<?= (int) $counts['completed_count'] ?>)
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$requests): ?>
            <div class="repair-empty-state">
                <i class="fa-solid fa-clipboard-question" aria-hidden="true"></i>
                <h3>No repair requests found</h3>
                <p>
                    <?= $status === 'All'
                        ? 'You have not submitted any items for repair yet. Need something fixed before replacing it?'
                        : 'You do not have any requests with status "' . escape_html($status) . '".' ?>
                </p>
                <a class="btn btn-primary" href="create-request.php">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Submit a New Request
                </a>
            </div>
        <?php else: ?>
            <div class="repair-request-list">
                <?php foreach ($requests as $item): ?>
                    <?php
                    $reqStatus = $item['status'];
                    $badgeClass = 'repair-badge-open';
                    $badgeIcon = 'fa-clock';

                    if ($reqStatus === 'Verified') {
                        $badgeClass = 'repair-badge-verified';
                        $badgeIcon = 'fa-circle-check';
                    } elseif ($reqStatus === 'In Progress') {
                        $badgeClass = 'repair-badge-in-progress';
                        $badgeIcon = 'fa-screwdriver-wrench';
                    } elseif ($reqStatus === 'Completed') {
                        $badgeClass = 'repair-badge-completed';
                        $badgeIcon = 'fa-circle-check';
                    } elseif ($reqStatus === 'Cancelled') {
                        $badgeClass = 'repair-badge-cancelled';
                        $badgeIcon = 'fa-ban';
                    }
                    ?>
                    <article class="repair-item-row">
                        <div class="repair-item-left">
                            <?php if ($item['thumbnail_path']): ?>
                                <img class="repair-thumb-img" src="/fix-and-exchange/<?= escape_html($item['thumbnail_path']) ?>" alt="<?= escape_html($item['title']) ?>">
                            <?php else: ?>
                                <div class="repair-no-image">
                                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                                    <span>No photo</span>
                                </div>
                            <?php endif; ?>

                            <div class="repair-item-meta">
                                <h2>
                                    <a href="request-details.php?request_id=<?= (int) $item['request_id'] ?>">
                                        <?= escape_html($item['title']) ?>
                                    </a>
                                </h2>
                                <div class="repair-item-submeta">
                                    <span><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= escape_html($item['category_name']) ?></span>
                                    <span>&bull;</span>
                                    <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= date('M j, Y', strtotime($item['created_at'])) ?></span>
                                    <?php if ($item['volunteer_name']): ?>
                                        <span>&bull;</span>
                                        <span><i class="fa-solid fa-user-gear" aria-hidden="true"></i> Volunteer: <?= escape_html($item['volunteer_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="repair-item-right">
                            <span class="repair-badge <?= $badgeClass ?>">
                                <i class="fa-solid <?= $badgeIcon ?>" aria-hidden="true"></i> <?= escape_html($reqStatus) ?>
                            </span>

                            <?php if ($item['progress_status'] && $reqStatus === 'In Progress'): ?>
                                <span class="repair-badge repair-badge-verified" style="font-size: 11px;">
                                    <?= escape_html($item['progress_status']) ?>
                                </span>
                            <?php endif; ?>

                            <div class="btn-group">
                                <a class="btn btn-sm btn-outline-primary" href="request-details.php?request_id=<?= (int) $item['request_id'] ?>">
                                    Details
                                </a>

                                <?php if ($reqStatus === 'In Progress'): ?>
                                    <a class="btn btn-sm btn-primary" href="../messages/repair.php?request_id=<?= (int) $item['request_id'] ?>">
                                        <i class="fa-solid fa-comments" aria-hidden="true"></i> Chat
                                    </a>
                                <?php elseif (in_array($reqStatus, ['Open', 'Verified'], true)): ?>
                                    <a class="btn btn-sm btn-secondary" href="create-request.php?request_id=<?= (int) $item['request_id'] ?>">
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
