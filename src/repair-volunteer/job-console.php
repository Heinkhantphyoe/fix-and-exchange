<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_approved_volunteer();
require __DIR__ . '/../../includes/buyer-seller.php';
$assignmentId = (int) ($_GET['assignment_id'] ?? $_POST['assignment_id'] ?? 0); $userId = (int) current_user()['user_id']; $errors = [];
$statement = mysqli_prepare($conn, "SELECT a.*, r.request_id, r.title, r.problem_description, u.full_name AS requester_name, v.volunteer_id FROM repair_assignments a JOIN repair_requests r ON r.request_id = a.request_id JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id JOIN users u ON u.user_id = r.requester_id WHERE a.assignment_id = ? AND v.user_id = ? LIMIT 1");
mysqli_stmt_bind_param($statement, 'ii', $assignmentId, $userId); mysqli_stmt_execute($statement); $assignment = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
if (!$assignment) { http_response_code(404); exit('Repair assignment not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment['progress_status'] !== 'Completed') {
    check_form_token();
    $newStatus = $_POST['progress_status'] ?? ''; $notes = trim($_POST['completion_notes'] ?? ''); $transitions = ['Accepted' => 'Diagnosing', 'Diagnosing' => 'Repairing', 'Repairing' => 'Completed'];
    if (($transitions[$assignment['progress_status']] ?? null) !== $newStatus) { $errors[] = 'Please follow the progress steps in order.'; }
    elseif ($newStatus === 'Completed' && $notes === '') { $errors[] = 'Please add completion notes before completing the job.'; }
    else {
        mysqli_begin_transaction($conn);
        try {
            $statement = mysqli_prepare($conn, 'SELECT a.progress_status, a.request_id FROM repair_assignments a JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id WHERE a.assignment_id = ? AND v.user_id = ? FOR UPDATE'); mysqli_stmt_bind_param($statement, 'ii', $assignmentId, $userId); mysqli_stmt_execute($statement); $saved = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
            if (!$saved || $saved['progress_status'] !== $assignment['progress_status']) { throw new RuntimeException('This assignment was updated. Refresh the page.'); }
            if ($newStatus === 'Completed') {
                $points = 25;
                $statement = mysqli_prepare($conn, "UPDATE repair_assignments SET progress_status = 'Completed', completion_notes = ?, completed_at = NOW(), points_value = ? WHERE assignment_id = ?"); mysqli_stmt_bind_param($statement, 'sii', $notes, $points, $assignmentId); mysqli_stmt_execute($statement); mysqli_stmt_close($statement);
                $statement = mysqli_prepare($conn, "UPDATE repair_requests SET status = 'Completed' WHERE request_id = ?"); mysqli_stmt_bind_param($statement, 'i', $saved['request_id']); mysqli_stmt_execute($statement); mysqli_stmt_close($statement);
                $statement = mysqli_prepare($conn, "INSERT INTO points_transactions (user_id, points_awarded, source_type, reference_id) SELECT ?, ?, 'Repair', ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM points_transactions WHERE user_id = ? AND source_type = 'Repair' AND reference_id = ?)"); mysqli_stmt_bind_param($statement, 'iiiii', $userId, $points, $assignmentId, $userId, $assignmentId); mysqli_stmt_execute($statement); $inserted = mysqli_stmt_affected_rows($statement); mysqli_stmt_close($statement);
                if ($inserted) { $statement = mysqli_prepare($conn, 'UPDATE users SET total_points = total_points + ? WHERE user_id = ?'); mysqli_stmt_bind_param($statement, 'ii', $points, $userId); mysqli_stmt_execute($statement); mysqli_stmt_close($statement); }
            } else { $statement = mysqli_prepare($conn, 'UPDATE repair_assignments SET progress_status = ? WHERE assignment_id = ?'); mysqli_stmt_bind_param($statement, 'si', $newStatus, $assignmentId); mysqli_stmt_execute($statement); mysqli_stmt_close($statement); }
            mysqli_commit($conn); header('Location: job-console.php?assignment_id=' . $assignmentId); exit;
        } catch (Throwable $exception) { mysqli_rollback($conn); $errors[] = $exception->getMessage(); }
    }
}
require __DIR__ . '/../../includes/header.php';
?>
<section class="section volunteer-page"><div class="container"><div class="volunteer-heading"><div><p class="volunteer-kicker">MY REPAIR JOB</p><h1><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></h1><p>Requester: <?= htmlspecialchars($assignment['requester_name'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn btn-outline-primary" href="dashboard.php">Dashboard</a></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?><article class="volunteer-panel volunteer-detail"><div class="volunteer-detail-top"><span class="volunteer-badge"><?= htmlspecialchars($assignment['progress_status'], ENT_QUOTES, 'UTF-8') ?></span><a class="btn btn-sm btn-outline-primary" href="../messages/repair.php?request_id=<?= (int) $assignment['request_id'] ?>">Message requester</a></div><h2>Problem</h2><p class="volunteer-description"><?= nl2br(htmlspecialchars($assignment['problem_description'], ENT_QUOTES, 'UTF-8')) ?></p><?php if ($assignment['progress_status'] === 'Completed'): ?><h2>Completion notes</h2><p><?= nl2br(htmlspecialchars($assignment['completion_notes'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p><p class="alert alert-success">Completed. <?= (int) $assignment['points_value'] ?> points awarded.</p><?php else: ?><form method="post" class="volunteer-progress-form"><input type="hidden" name="assignment_id" value="<?= $assignmentId ?>"><input type="hidden" name="token" value="<?= htmlspecialchars(form_token(), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="progress_status">Next progress step</label><select class="form-control" id="progress_status" name="progress_status"><option value="">Choose next step</option><?php if ($assignment['progress_status'] === 'Accepted'): ?><option value="Diagnosing">Diagnosing</option><?php elseif ($assignment['progress_status'] === 'Diagnosing'): ?><option value="Repairing">Repairing</option><?php elseif ($assignment['progress_status'] === 'Repairing'): ?><option value="Completed">Completed</option><?php endif; ?></select><label class="form-label" for="completion_notes">Completion notes (required at completion)</label><textarea class="form-control form-textarea" id="completion_notes" name="completion_notes" maxlength="5000" placeholder="Describe the repair and handover notes."><?= htmlspecialchars($assignment['completion_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea><button class="btn btn-primary" type="submit">Save progress</button></form><?php endif; ?></article></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
