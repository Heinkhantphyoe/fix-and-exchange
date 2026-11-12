<?php
// Repair Requests Management: Monitor, verify, and inspect campus repairs.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

// Handle POST actions for repair moderation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);

    if ($requestId > 0) {
        if ($action === 'verify_request') {
            $stmt = mysqli_prepare($conn, "UPDATE repair_requests SET status = 'Verified' WHERE request_id = ? AND status = 'Open'");
            mysqli_stmt_bind_param($stmt, 'i', $requestId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message("adminRepairs.php?request=$requestId#repairDetails", "Repair request #RP-" . sprintf('%03d', $requestId) . " has been verified.", 'success');
        } elseif ($action === 'cancel_request') {
            $stmt = mysqli_prepare($conn, "UPDATE repair_requests SET status = 'Cancelled' WHERE request_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $requestId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message("adminRepairs.php?request=$requestId#repairDetails", "Repair request #RP-" . sprintf('%03d', $requestId) . " has been cancelled.", 'warning');
        }
    }
}

// Load repair requests with categories, requesters, and assigned volunteers
$repairRequests = [];
$repairSql = "SELECT r.*, c.category_name, u.full_name AS requester_name, u.tp_number AS requester_tp, u.email AS requester_email,
    a.assignment_id, a.progress_status, a.completion_notes, a.assigned_at, a.completed_at,
    vu.full_name AS volunteer_name, vu.tp_number AS volunteer_tp
    FROM repair_requests r
    JOIN categories c ON c.category_id = r.category_id
    JOIN users u ON u.user_id = r.requester_id
    LEFT JOIN repair_assignments a ON a.request_id = r.request_id
    LEFT JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id
    LEFT JOIN users vu ON vu.user_id = v.user_id
    ORDER BY r.request_id DESC";

$repairResult = mysqli_query($conn, $repairSql);
while ($row = mysqli_fetch_assoc($repairResult)) {
    $repairRequests[] = $row;
}

$requestStatuses = ['Open', 'Verified', 'In Progress', 'Completed', 'Cancelled'];
$assignmentOptions = ['Assigned', 'Unassigned'];

// Read filters
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, $requestStatuses, true)) {
    $status = '';
}
$assignment = $_GET['assignment'] ?? '';
if (!in_array($assignment, $assignmentOptions, true)) {
    $assignment = '';
}

$requestId = filter_input(INPUT_GET, 'request', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedRequest = null;
$selectedImages = [];
$filteredRequests = [];
$awaitingVolunteerCount = 0;

foreach ($repairRequests as $req) {
    $reqAssignment = $req['assignment_id'] === null ? 'Unassigned' : 'Assigned';

    if ($reqAssignment === 'Unassigned' && in_array($req['status'], ['Open', 'Verified'], true)) {
        $awaitingVolunteerCount++;
    }

    if ((int) $req['request_id'] === $requestId) {
        $selectedRequest = $req;
    }

    $reference = sprintf('#RP-%03d', (int) $req['request_id']);
    $searchText = $req['title'] . ' ' . $reference . ' ' . $req['requester_name'] . ' ' . $req['problem_description'];
    $matchesSearch = $search === '' || stripos($searchText, $search) !== false;
    $matchesStatus = $status === '' || $req['status'] === $status;
    $matchesAssignment = $assignment === '' || $reqAssignment === $assignment;

    if ($matchesSearch && $matchesStatus && $matchesAssignment) {
        $filteredRequests[] = $req;
    }
}

// Fetch photos for selected request
if ($selectedRequest) {
    $imgStmt = mysqli_prepare($conn, "SELECT image_path FROM repair_images WHERE request_id = ? ORDER BY repair_image_id ASC");
    mysqli_stmt_bind_param($imgStmt, 'i', $selectedRequest['request_id']);
    mysqli_stmt_execute($imgStmt);
    $selectedImages = mysqli_fetch_all(mysqli_stmt_get_result($imgStmt), MYSQLI_ASSOC);
    mysqli_stmt_close($imgStmt);
}

$hasFilters = $search !== '' || $status !== '' || $assignment !== '';
$resultsUrl = 'adminRepairs.php?' . http_build_query([
    'q' => $search,
    'status' => $status,
    'assignment' => $assignment,
]);

render_admin_header('Repair Request Management', 'repairs');
?>

<main class="dashboardMain">
    <div class="repairsPageHeading">
        <div>
            <p class="repairsPageLabel">Administrator</p>
            <h1>Repair Request Monitoring</h1>
            <p>Track student repair requests, volunteer assignments, and completion progress across campus.</p>
        </div>

        <div class="repairsTotals">
            <span class="summaryIcon" aria-hidden="true">
                <i class="fa-solid fa-wrench"></i>
            </span>
            <div>
                <strong><?= $awaitingVolunteerCount ?> unassigned</strong>
                <small>Open or verified requests awaiting a volunteer</small>
            </div>
        </div>
    </div>

    <!-- Filters form -->
    <form class="repairsFilters" action="adminRepairs.php" method="get">
        <div class="repairsSearch">
            <label for="repairSearch">Search requests</label>
            <input id="repairSearch" type="search" name="q" value="<?= escape_html($search) ?>" placeholder="Item, #RP-ID or student">
        </div>

        <div class="repairsFilter">
            <label for="repairStatus">Request status</label>
            <select id="repairStatus" name="status">
                <option value="">All statuses</option>
                <?php foreach ($requestStatuses as $option): ?>
                    <option value="<?= escape_html($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= escape_html($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="repairsFilter">
            <label for="repairAssignment">Assignment</label>
            <select id="repairAssignment" name="assignment">
                <option value="">All assignments</option>
                <?php foreach ($assignmentOptions as $option): ?>
                    <option value="<?= escape_html($option) ?>" <?= $assignment === $option ? 'selected' : '' ?>><?= escape_html($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="repairsButton repairsApplyButton" type="submit">Apply filters</button>
        <a class="repairsButton" href="adminRepairs.php">Clear filters</a>
    </form>

    <!-- Detailed request card -->
    <?php if ($requestId): ?>
        <section class="dashboardPanel adminDetailPanel" id="repairDetails">
            <div class="adminDetailHeader">
                <div class="adminDetailTitle">
                    <span class="summaryIcon repairIcon" aria-hidden="true">
                        <i class="fa-solid fa-wrench"></i>
                    </span>

                    <?php if ($selectedRequest): ?>
                        <h2><?= escape_html($selectedRequest['title']) ?></h2>
                        <span class="repairStatusBadge <?= escape_html(strtolower(str_replace(' ', '-', $selectedRequest['status']))) ?>">
                            <?= escape_html($selectedRequest['status']) ?>
                        </span>
                    <?php else: ?>
                        <h2>Request unavailable</h2>
                    <?php endif; ?>
                </div>

                <a class="repairsButton" href="<?= escape_html($resultsUrl) ?>#repairResults">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to results
                </a>
            </div>

            <?php if ($selectedRequest): ?>
                <div class="adminDetailBody">
                    <div>
                        <!-- Photos preview -->
                        <?php if ($selectedImages): ?>
                            <div style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
                                <?php foreach ($selectedImages as $img): ?>
                                    <img src="/fix-and-exchange/<?= escape_html($img['image_path']) ?>" alt="Repair image" style="width: 110px; height: 90px; object-fit: cover; border-radius: 6px; border: 1px solid #d6e3da;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="adminDetailText">
                            <h3>Problem Description</h3>
                            <p style="white-space: pre-line;"><?= escape_html($selectedRequest['problem_description']) ?></p>
                        </div>

                        <?php if (!empty($selectedRequest['completion_notes'])): ?>
                            <div class="adminDetailText" style="border-left: 3px solid #16a34a; padding-left: 12px; margin-top: 14px;">
                                <h3 style="color: #15803d;">Volunteer Completion Notes</h3>
                                <p style="white-space: pre-line;"><?= escape_html($selectedRequest['completion_notes']) ?></p>
                            </div>
                        <?php endif; ?>

                        <dl class="adminDetailFacts">
                            <div>
                                <dt>Request ID</dt>
                                <dd><?= sprintf('#RP-%03d', (int) $selectedRequest['request_id']) ?></dd>
                            </div>
                            <div>
                                <dt>Requester</dt>
                                <dd><?= escape_html($selectedRequest['requester_name']) ?> (<?= escape_html($selectedRequest['requester_tp']) ?>)</dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd><?= escape_html($selectedRequest['requester_email']) ?></dd>
                            </div>
                            <div>
                                <dt>Category</dt>
                                <dd><?= escape_html($selectedRequest['category_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Submitted</dt>
                                <dd><?= date('d M Y, g:i a', strtotime($selectedRequest['created_at'])) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="adminDetailSidebar">
                        <h3>Volunteer Assignment</h3>
                        <dl class="adminDetailFacts">
                            <div>
                                <dt>Volunteer</dt>
                                <dd>
                                    <?php if ($selectedRequest['assignment_id'] === null): ?>
                                        <span class="repairStatusBadge unassigned">Unassigned</span>
                                    <?php else: ?>
                                        <strong><?= escape_html($selectedRequest['volunteer_name']) ?></strong> (<?= escape_html($selectedRequest['volunteer_tp']) ?>)
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Volunteer Progress</dt>
                                <dd><?= escape_html($selectedRequest['progress_status'] ?? 'No volunteer assigned yet') ?></dd>
                            </div>
                            <?php if ($selectedRequest['assigned_at']): ?>
                                <div>
                                    <dt>Assigned At</dt>
                                    <dd><?= date('d M Y', strtotime($selectedRequest['assigned_at'])) ?></dd>
                                </div>
                            <?php endif; ?>
                            <?php if ($selectedRequest['completed_at']): ?>
                                <div>
                                    <dt>Completed At</dt>
                                    <dd><?= date('d M Y', strtotime($selectedRequest['completed_at'])) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <div class="adminDetailSidebarSection" style="margin-top: 18px;">
                            <h3>Admin Verification</h3>
                            <form method="post" style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['request_id'] ?>">

                                <?php if ($selectedRequest['status'] === 'Open'): ?>
                                    <button class="repairsButton" type="submit" name="action" value="verify_request" style="background: #1c7049; color: #fff;">
                                        <i class="fa-solid fa-check"></i> Verify request
                                    </button>
                                <?php endif; ?>

                                <?php if ($selectedRequest['status'] !== 'Cancelled' && $selectedRequest['status'] !== 'Completed'): ?>
                                    <button class="repairsButton" type="submit" name="action" value="cancel_request" style="color: #b91c1c;" onclick="return confirm('Cancel this repair request?');">
                                        <i class="fa-solid fa-ban"></i> Cancel request
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="adminDetailEmpty">This repair request could not be found.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Table of repair requests -->
    <section class="repairsResults" id="repairResults">
        <div class="repairsResultsHeading">
            <h2>Repair requests</h2>
            <span><?= count($filteredRequests) ?> of <?= count($repairRequests) ?> shown</span>
        </div>

        <?php if (empty($filteredRequests)): ?>
            <p class="repairsEmpty"><?= $hasFilters ? 'No repair requests match your filters.' : 'No repair requests available yet.' ?></p>
        <?php else: ?>
            <div class="repairsTableContainer">
                <table class="repairsTable">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col">Requester</th>
                            <th scope="col">Volunteer</th>
                            <th scope="col">Request status</th>
                            <th scope="col">Volunteer progress</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredRequests as $req): ?>
                            <tr>
                                <th scope="row">
                                    <?= escape_html($req['title']) ?>
                                    <small class="repairReference"><?= sprintf('#RP-%03d', (int) $req['request_id']) ?> &middot; <?= escape_html($req['category_name']) ?></small>
                                </th>
                                <td>
                                    <span class="repairsMobileLabel">Requester</span>
                                    <?= escape_html($req['requester_name']) ?>
                                </td>
                                <td>
                                    <span class="repairsMobileLabel">Volunteer</span>
                                    <?php if ($req['assignment_id'] === null): ?>
                                        <span class="repairStatusBadge unassigned">Unassigned</span>
                                    <?php else: ?>
                                        <?= escape_html($req['volunteer_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="repairsMobileLabel">Request status</span>
                                    <span class="repairStatusBadge <?= escape_html(strtolower(str_replace(' ', '-', $req['status']))) ?>">
                                        <?= escape_html($req['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="repairsMobileLabel">Volunteer progress</span>
                                    <?= escape_html($req['progress_status'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="repairsMobileLabel">Submitted</span>
                                    <?= date('d M', strtotime($req['created_at'])) ?>
                                </td>
                                <td>
                                    <a class="repairsButton" href="<?= escape_html($resultsUrl . '&request=' . (int) $req['request_id']) ?>#repairDetails">
                                        View request
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php render_admin_footer(); ?>
