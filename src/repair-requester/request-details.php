<?php
// Repair Request Details: Inspect status, photos, volunteer assignment, and chat.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/repair-helpers.php';

$userId = (int) current_user()['user_id'];
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

if ($requestId <= 0) {
    http_response_code(404);
    exit('Invalid repair request ID.');
}

$request = get_repair_request($conn, $requestId);

if (!$request) {
    http_response_code(404);
    exit('Repair request not found.');
}

$isOwner = ((int) $request['requester_id'] === $userId);
$assignment = get_repair_assignment_details($conn, $requestId);
$isAssignedVolunteer = ($assignment && (int) ($assignment['volunteer_user_id'] ?? 0) === $userId);

// Access check: only owner, assigned volunteer, or admin can inspect this request
if (!$isOwner && !$isAssignedVolunteer && (current_user()['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Access denied. You do not have permission to view this repair request.');
}

// Handle Cancel Request Action (Owner only, when Open or Verified)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    check_form_token();

    if (!$isOwner) {
        http_response_code(403);
        exit('Only the requester can cancel this request.');
    }

    if (!in_array($request['status'], ['Open', 'Verified'], true)) {
        redirect_message('request-details.php?request_id=' . $requestId, 'This request cannot be cancelled because it is already in progress or completed.', 'warning');
    }

    $cancelStmt = mysqli_prepare($conn, "UPDATE repair_requests SET status = 'Cancelled' WHERE request_id = ? AND requester_id = ?");
    mysqli_stmt_bind_param($cancelStmt, 'ii', $requestId, $userId);
    mysqli_stmt_execute($cancelStmt);
    mysqli_stmt_close($cancelStmt);

    redirect_message('request-details.php?request_id=' . $requestId, 'Your repair request has been cancelled.', 'info');
}

$images = get_repair_images($conn, $requestId);

// Compute stepper progress stages
$progressStages = [
    'Accepted'   => ['icon' => 'fa-handshake', 'title' => 'Accepted', 'desc' => 'Volunteer accepted the request'],
    'Diagnosing' => ['icon' => 'fa-magnifying-glass', 'title' => 'Diagnosing', 'desc' => 'Inspecting symptoms and faults'],
    'Repairing'  => ['icon' => 'fa-screwdriver-wrench', 'title' => 'Repairing', 'desc' => 'Repair and testing in progress'],
    'Completed'  => ['icon' => 'fa-circle-check', 'title' => 'Completed', 'desc' => 'Fixed and ready for collection'],
];

$currentProgress = $assignment['progress_status'] ?? '';
$stageKeys = array_keys($progressStages);
$currentStageIndex = array_search($currentProgress, $stageKeys, true);
if ($currentStageIndex === false) {
    $currentStageIndex = -1;
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="section repair-page">
    <div class="container">
        <?php show_repair_requester_nav(); ?>

        <div class="repair-header-row">
            <div>
                <p class="repair-kicker"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> REQUEST #<?= (int) $request['request_id'] ?></p>
                <h1><?= escape_html($request['title']) ?></h1>
                <p>
                    Category: <strong><?= escape_html($request['category_name']) ?></strong> &bull;
                    Submitted on <?= date('F j, Y, g:i a', strtotime($request['created_at'])) ?>
                </p>
            </div>
            <a class="btn btn-outline-primary" href="my-requests.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to My Requests
            </a>
        </div>

        <div class="repair-details-layout">
            <!-- Left Main Column -->
            <div class="repair-details-main">
                <!-- Gallery Card -->
                <div class="repair-card">
                    <div class="repair-card-header">
                        <h2>Item Photos</h2>
                        <span class="repair-badge <?= 'repair-badge-' . strtolower(str_replace(' ', '-', $request['status'])) ?>">
                            <?= escape_html($request['status']) ?>
                        </span>
                    </div>

                    <?php if ($images): ?>
                        <img id="main-repair-photo" class="repair-gallery-main" src="/fix-and-exchange/<?= escape_html($images[0]['image_path']) ?>" alt="<?= escape_html($request['title']) ?>">
                        <?php if (count($images) > 1): ?>
                            <div class="repair-gallery-thumbs">
                                <?php foreach ($images as $index => $img): ?>
                                    <img class="repair-gallery-thumb <?= $index === 0 ? 'is-active' : '' ?>"
                                         src="/fix-and-exchange/<?= escape_html($img['image_path']) ?>"
                                         alt="Thumbnail"
                                         data-full-src="/fix-and-exchange/<?= escape_html($img['image_path']) ?>">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="repair-no-image" style="width: 100%; height: 220px; font-size: 14px;">
                            <i class="fa-solid fa-camera" aria-hidden="true" style="font-size: 32px; margin-bottom: 8px;"></i>
                            <span>No photos uploaded for this repair request.</span>
                        </div>
                    <?php endif; ?>

                    <h3 style="margin-top: 24px; color: #123c2a; font-size: 18px;">Problem Description</h3>
                    <div style="line-height: 1.65; color: #34483d; white-space: pre-line; background: #fbfdfb; padding: 18px; border-radius: 8px; border: 1px solid #e1ece5;">
                        <?= escape_html($request['problem_description']) ?>
                    </div>
                </div>

                <!-- Live Progress Stepper (If In Progress or Completed) -->
                <?php if ($assignment): ?>
                    <div class="repair-card">
                        <div class="repair-card-header">
                            <h2>Repair Progress Timeline</h2>
                            <span class="repair-badge repair-badge-verified">
                                Step <?= max(1, (int) $currentStageIndex + 1) ?> of 4: <?= escape_html($currentProgress) ?>
                            </span>
                        </div>

                        <div class="repair-stepper">
                            <?php foreach ($stageKeys as $idx => $stageKey): ?>
                                <?php
                                $stageData = $progressStages[$stageKey];
                                $isCompleted = ($currentStageIndex > $idx || ($currentStageIndex === $idx && $stageKey === 'Completed'));
                                $isActive = ($currentStageIndex === $idx && $stageKey !== 'Completed');
                                $nodeClass = $isCompleted ? 'is-completed' : ($isActive ? 'is-active' : '');
                                ?>
                                <div class="repair-step-node <?= $nodeClass ?>">
                                    <div class="repair-step-bullet">
                                        <?php if ($isCompleted): ?>
                                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        <?php else: ?>
                                            <?= $idx + 1 ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="repair-step-info">
                                        <h4><?= escape_html($stageData['title']) ?></h4>
                                        <p><?= escape_html($stageData['desc']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Completion Notes Card -->
                <?php if ($request['status'] === 'Completed' && $assignment): ?>
                    <div class="repair-card" style="border-left: 5px solid #1c7049; background: #f7fdf9;">
                        <div class="repair-card-header">
                            <h2 style="color: #125433;"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Repair Finished</h2>
                            <span class="text-muted" style="font-size: 13px;">
                                Completed: <?= date('M j, Y', strtotime($assignment['completed_at'])) ?>
                            </span>
                        </div>
                        <p style="margin: 0 0 10px; font-weight: 600; color: #164e32;">Volunteer Handover Notes:</p>
                        <div style="line-height: 1.6; color: #2d4537; white-space: pre-line; background: #ffffff; padding: 14px; border-radius: 6px; border: 1px solid #cce5d4;">
                            <?= escape_html($assignment['completion_notes'] ?: 'No additional notes provided by volunteer.') ?>
                        </div>
                        <p style="margin: 14px 0 0; font-size: 13px; color: #1c7049;">
                            <i class="fa-solid fa-award" aria-hidden="true"></i>
                            <?= (int) $assignment['points_value'] ?> campus sustainability points were awarded to the volunteer for this repair!
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar Column -->
            <div class="repair-details-sidebar">
                <!-- Status & Action Card -->
                <div class="repair-card">
                    <div class="repair-card-header">
                        <h2>Status Overview</h2>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <span class="repair-badge <?= 'repair-badge-' . strtolower(str_replace(' ', '-', $request['status'])) ?>" style="font-size: 14px; padding: 6px 14px;">
                            <?= escape_html($request['status']) ?>
                        </span>
                    </div>

                    <?php if ($assignment): ?>
                        <div class="repair-volunteer-box">
                            <div class="repair-volunteer-profile">
                                <div class="repair-volunteer-avatar">
                                    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <p class="repair-volunteer-name"><?= escape_html($assignment['volunteer_name']) ?></p>
                                    <p class="repair-volunteer-title">Assigned Volunteer &bull; <?= escape_html($assignment['volunteer_tp']) ?></p>
                                </div>
                            </div>
                            <p style="margin: 0; font-size: 12px; color: #55695d; line-height: 1.4;">
                                Assigned on <?= date('M j, Y, g:i a', strtotime($assignment['assigned_at'])) ?>
                            </p>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <a class="btn btn-primary btn-block" href="../messages/repair.php?request_id=<?= (int) $request['request_id'] ?>">
                                <i class="fa-solid fa-comments" aria-hidden="true"></i> Message Volunteer
                            </a>

                            <?php if ($isAssignedVolunteer): ?>
                                <a class="btn btn-outline-primary btn-block" href="../repair-volunteer/job-console.php?assignment_id=<?= (int) $assignment['assignment_id'] ?>">
                                    <i class="fa-solid fa-toolbox" aria-hidden="true"></i> Open Volunteer Console
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php elseif (in_array($request['status'], ['Open', 'Verified'], true)): ?>
                        <div class="alert alert-info" style="font-size: 13px; line-height: 1.5; margin-bottom: 18px;">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            Your request is published in the campus volunteer pool. An approved repair volunteer will accept this request soon.
                        </div>

                        <?php if ($isOwner): ?>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <a class="btn btn-outline-primary btn-block" href="create-request.php?request_id=<?= (int) $request['request_id'] ?>">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit Request Details
                                </a>

                                <form method="post" onsubmit="return confirm('Are you sure you want to cancel this repair request?');">
                                    <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button class="btn btn-danger btn-block" type="submit">
                                        <i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel Request
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($request['status'] === 'Cancelled'): ?>
                        <div class="alert alert-warning" style="font-size: 13px; margin: 0;">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            This request was cancelled. If you still need help, feel free to submit a new repair request.
                        </div>
                        <?php if ($isOwner): ?>
                            <div style="margin-top: 14px;">
                                <a class="btn btn-primary btn-block" href="create-request.php">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Submit New Request
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Helpful Tips Card -->
                <div class="card" style="background: #fbfdfb; border: 1px solid #d6e3da;">
                    <div class="card-body">
                        <h4 style="margin-top: 0; color: #123c2a; font-size: 15px;">
                            <i class="fa-solid fa-lightbulb" aria-hidden="true" style="color: #d18a1a;"></i> Preparation Tips
                        </h4>
                        <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #52665a; line-height: 1.6;">
                            <li>Clean your item before bringing it to campus meetup.</li>
                            <li>Bring all relevant cables, adapters, or remotes.</li>
                            <li>Back up personal files on computers or storage devices.</li>
                            <li>Agree on meetup points such as APU Library or Makerspace.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mainPhoto = document.getElementById('main-repair-photo');
    const thumbnails = document.querySelectorAll('.repair-gallery-thumb');

    if (mainPhoto && thumbnails.length > 0) {
        thumbnails.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                thumbnails.forEach(t => t.classList.remove('is-active'));
                thumb.classList.add('is-active');
                mainPhoto.src = thumb.dataset.fullSrc;
            });
        });
    }
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
