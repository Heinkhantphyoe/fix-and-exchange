<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_approved_volunteer();
$userId = (int) current_user()['user_id'];

$statement = mysqli_prepare($conn, "SELECT volunteer_id FROM volunteer_profiles WHERE user_id = ? AND approval_status = 'Approved' LIMIT 1");
mysqli_stmt_bind_param($statement, 'i', $userId); mysqli_stmt_execute($statement);
$volunteer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
$volunteerId = (int) $volunteer['volunteer_id'];

$statement = mysqli_prepare($conn, "SELECT
    (SELECT COUNT(*) FROM repair_requests r LEFT JOIN repair_assignments a ON a.request_id = r.request_id WHERE r.status IN ('Open', 'Verified') AND a.assignment_id IS NULL) AS available_jobs,
    (SELECT COUNT(*) FROM repair_assignments WHERE volunteer_id = ? AND progress_status = 'Accepted') AS accepted_jobs,
    (SELECT COUNT(*) FROM repair_assignments WHERE volunteer_id = ? AND progress_status IN ('Diagnosing', 'Repairing')) AS active_jobs,
    (SELECT COUNT(*) FROM repair_assignments WHERE volunteer_id = ? AND progress_status = 'Completed') AS completed_jobs");
mysqli_stmt_bind_param($statement, 'iii', $volunteerId, $volunteerId, $volunteerId); mysqli_stmt_execute($statement);
$counts = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);

$statement = mysqli_prepare($conn, "SELECT a.assignment_id, a.progress_status, r.title, c.category_name, u.full_name AS requester_name
    FROM repair_assignments a JOIN repair_requests r ON r.request_id = a.request_id JOIN categories c ON c.category_id = r.category_id JOIN users u ON u.user_id = r.requester_id
    WHERE a.volunteer_id = ? ORDER BY a.assignment_id DESC");
mysqli_stmt_bind_param($statement, 'i', $volunteerId); mysqli_stmt_execute($statement);
$assignments = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC); mysqli_stmt_close($statement);
require __DIR__ . '/../../includes/header.php';
?>
<section class="section volunteer-page"><div class="container">
    <div class="volunteer-heading"><div><p class="volunteer-kicker">REPAIR VOLUNTEER</p><h1>Volunteer Dashboard</h1><p>Choose a repair job and help another student repair before replacing.</p></div><a class="btn btn-primary" href="browse-jobs.php">Browse available jobs</a></div>
    <nav class="volunteer-nav"><a class="is-active" href="dashboard.php">Dashboard</a><a href="browse-jobs.php">Available jobs</a><a href="apply.php">Application</a><a href="../account/home.php">Student dashboard</a></nav>
    <div class="volunteer-stat-grid"><div class="volunteer-stat"><strong><?= (int) $counts['available_jobs'] ?></strong><span>Available jobs</span></div><div class="volunteer-stat"><strong><?= (int) $counts['accepted_jobs'] ?></strong><span>Accepted</span></div><div class="volunteer-stat"><strong><?= (int) $counts['active_jobs'] ?></strong><span>In progress</span></div><div class="volunteer-stat"><strong><?= (int) $counts['completed_jobs'] ?></strong><span>Completed</span></div></div>
    <section class="volunteer-panel"><div class="volunteer-panel-heading"><h2>My repair jobs</h2><a href="browse-jobs.php">Find a job</a></div><?php if (!$assignments): ?><p class="empty-state">You have not accepted a repair job yet.</p><?php else: ?><div class="volunteer-job-list"><?php foreach ($assignments as $assignment): ?><article class="volunteer-job-row"><div><h3><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($assignment['category_name'], ENT_QUOTES, 'UTF-8') ?> · Requested by <?= htmlspecialchars($assignment['requester_name'], ENT_QUOTES, 'UTF-8') ?></p></div><span class="volunteer-badge"><?= htmlspecialchars($assignment['progress_status'], ENT_QUOTES, 'UTF-8') ?></span><a class="btn btn-sm btn-outline-primary" href="job-console.php?assignment_id=<?= (int) $assignment['assignment_id'] ?>">Open job</a></article><?php endforeach; ?></div><?php endif; ?></section>
</div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
