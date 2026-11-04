<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_approved_volunteer();
$search = trim($_GET['search'] ?? ''); $categoryId = (int) ($_GET['category_id'] ?? 0);
$statement = mysqli_prepare($conn, 'SELECT category_id, category_name FROM categories ORDER BY category_name'); mysqli_stmt_execute($statement);
$categories = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC); mysqli_stmt_close($statement);
$statement = mysqli_prepare($conn, "SELECT r.request_id, r.title, r.problem_description, r.status, c.category_name, u.full_name AS requester_name
    FROM repair_requests r JOIN categories c ON c.category_id = r.category_id JOIN users u ON u.user_id = r.requester_id LEFT JOIN repair_assignments a ON a.request_id = r.request_id
    WHERE r.status IN ('Open', 'Verified') AND a.assignment_id IS NULL AND (? = 0 OR r.category_id = ?)
    AND (? = '' OR r.title LIKE CONCAT('%', ?, '%') OR r.problem_description LIKE CONCAT('%', ?, '%')) ORDER BY r.created_at DESC");
mysqli_stmt_bind_param($statement, 'iisss', $categoryId, $categoryId, $search, $search, $search); mysqli_stmt_execute($statement);
$jobs = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC); mysqli_stmt_close($statement);
require __DIR__ . '/../../includes/header.php';
?>
<section class="section volunteer-page"><div class="container">
    <div class="volunteer-heading"><div><p class="volunteer-kicker">REPAIR VOLUNTEER</p><h1>Available Repair Jobs</h1><p>Choose an open request that matches your skills.</p></div><a class="btn btn-outline-primary" href="dashboard.php">My dashboard</a></div>
    <nav class="volunteer-nav"><a href="dashboard.php">Dashboard</a><a class="is-active" href="browse-jobs.php">Available jobs</a><a href="apply.php">Application</a><a href="../account/home.php">Student dashboard</a></nav>
    <form class="volunteer-filters" method="get"><input class="form-control" type="search" name="search" placeholder="Search jobs" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"><select class="form-control" name="category_id"><option value="0">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['category_id'] ?>" <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Filter</button></form>
    <section class="volunteer-panel"><?php if (!$jobs): ?><p class="empty-state">No available repair jobs match your search.</p><?php else: ?><div class="volunteer-job-list"><?php foreach ($jobs as $job): ?><article class="volunteer-job-card"><div><span class="volunteer-badge"><?= htmlspecialchars($job['status'], ENT_QUOTES, 'UTF-8') ?></span><h2><?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($job['category_name'], ENT_QUOTES, 'UTF-8') ?> · Requested by <?= htmlspecialchars($job['requester_name'], ENT_QUOTES, 'UTF-8') ?></p><p><?= htmlspecialchars(mb_strimwidth($job['problem_description'], 0, 180, '...'), ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn btn-sm btn-primary" href="job-details.php?request_id=<?= (int) $job['request_id'] ?>">View job</a></article><?php endforeach; ?></div><?php endif; ?></section>
</div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
