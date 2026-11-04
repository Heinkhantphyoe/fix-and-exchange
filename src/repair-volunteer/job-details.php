<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_approved_volunteer();
require __DIR__ . '/../../includes/buyer-seller.php';
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0); $userId = (int) current_user()['user_id']; $errors = [];
$statement = mysqli_prepare($conn, "SELECT r.*, c.category_name, u.full_name AS requester_name FROM repair_requests r JOIN categories c ON c.category_id = r.category_id JOIN users u ON u.user_id = r.requester_id WHERE r.request_id = ? LIMIT 1");
mysqli_stmt_bind_param($statement, 'i', $requestId); mysqli_stmt_execute($statement); $job = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
if (!$job) { http_response_code(404); exit('Repair job not found.'); }
if ((int) $job['requester_id'] === $userId) { http_response_code(403); exit('You cannot volunteer for your own repair request.'); }
$statement = mysqli_prepare($conn, 'SELECT * FROM repair_images WHERE request_id = ? ORDER BY repair_image_id'); mysqli_stmt_bind_param($statement, 'i', $requestId); mysqli_stmt_execute($statement); $images = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC); mysqli_stmt_close($statement);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    mysqli_begin_transaction($conn);
    try {
        $statement = mysqli_prepare($conn, 'SELECT status, requester_id FROM repair_requests WHERE request_id = ? FOR UPDATE'); mysqli_stmt_bind_param($statement, 'i', $requestId); mysqli_stmt_execute($statement); $savedJob = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
        $statement = mysqli_prepare($conn, 'SELECT assignment_id FROM repair_assignments WHERE request_id = ? LIMIT 1'); mysqli_stmt_bind_param($statement, 'i', $requestId); mysqli_stmt_execute($statement); $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
        if (!$savedJob || !in_array($savedJob['status'], ['Open', 'Verified'], true) || $existing) { throw new RuntimeException('This job is no longer available.'); }
        $statement = mysqli_prepare($conn, "SELECT volunteer_id FROM volunteer_profiles WHERE user_id = ? AND approval_status = 'Approved' LIMIT 1"); mysqli_stmt_bind_param($statement, 'i', $userId); mysqli_stmt_execute($statement); $volunteer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
        $volunteerId = (int) ($volunteer['volunteer_id'] ?? 0); if (!$volunteerId) { throw new RuntimeException('Volunteer profile not found.'); }
        $statement = mysqli_prepare($conn, "INSERT INTO repair_assignments (request_id, volunteer_id, progress_status) VALUES (?, ?, 'Accepted')"); mysqli_stmt_bind_param($statement, 'ii', $requestId, $volunteerId); mysqli_stmt_execute($statement); $assignmentId = mysqli_insert_id($conn); mysqli_stmt_close($statement);
        $statement = mysqli_prepare($conn, "UPDATE repair_requests SET status = 'In Progress' WHERE request_id = ?"); mysqli_stmt_bind_param($statement, 'i', $requestId); mysqli_stmt_execute($statement); mysqli_stmt_close($statement);
        mysqli_commit($conn); header('Location: job-console.php?assignment_id=' . $assignmentId); exit;
    } catch (Throwable $exception) { mysqli_rollback($conn); $errors[] = $exception->getMessage(); }
}
require __DIR__ . '/../../includes/header.php';
?>
<section class="section volunteer-page"><div class="container"><div class="volunteer-heading"><div><p class="volunteer-kicker">REPAIR JOB</p><h1><?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($job['category_name'], ENT_QUOTES, 'UTF-8') ?> · Requested by <?= htmlspecialchars($job['requester_name'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn btn-outline-primary" href="browse-jobs.php">Back to jobs</a></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?><article class="volunteer-panel volunteer-detail"><p><strong>Status:</strong> <?= htmlspecialchars($job['status'], ENT_QUOTES, 'UTF-8') ?></p><h2>Problem description</h2><p class="volunteer-description"><?= nl2br(htmlspecialchars($job['problem_description'], ENT_QUOTES, 'UTF-8')) ?></p><?php if ($images): ?><div class="volunteer-images"><?php foreach ($images as $image): ?><img src="/fix-and-exchange/<?= htmlspecialchars($image['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Repair request image"><?php endforeach; ?></div><?php endif; ?><?php if (in_array($job['status'], ['Open', 'Verified'], true)): ?><form method="post"><input type="hidden" name="request_id" value="<?= $requestId ?>"><input type="hidden" name="token" value="<?= htmlspecialchars(form_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-primary" type="submit">Accept this job</button></form><?php else: ?><p class="alert alert-warning">This request is no longer available.</p><?php endif; ?></article></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
