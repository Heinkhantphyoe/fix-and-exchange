<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
$statement = mysqli_prepare($conn, "SELECT v.volunteer_id, v.approval_status, u.full_name, u.email, u.tp_number FROM volunteer_profiles v JOIN users u ON u.user_id = v.user_id ORDER BY FIELD(v.approval_status, 'Pending', 'Approved', 'Rejected'), v.volunteer_id DESC"); mysqli_stmt_execute($statement); $applications = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC); mysqli_stmt_close($statement);
require __DIR__ . '/../../includes/header.php';
?>
<section class="section"><div class="container"><h1>Volunteer Applications</h1><p>Review students who applied to become repair volunteers.</p><div class="table-responsive"><table class="table"><thead><tr><th>Applicant</th><th>TP number</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if (!$applications): ?><tr><td colspan="4">No applications found.</td></tr><?php else: foreach ($applications as $application): ?><tr><td><?= htmlspecialchars($application['full_name'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($application['email'], ENT_QUOTES, 'UTF-8') ?></small></td><td><?= htmlspecialchars($application['tp_number'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($application['approval_status'], ENT_QUOTES, 'UTF-8') ?></td><td><a class="btn btn-sm btn-outline-primary" href="volunteer-details.php?volunteer_id=<?= (int) $application['volunteer_id'] ?>">Review</a></td></tr><?php endforeach; endif; ?></tbody></table></div></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
