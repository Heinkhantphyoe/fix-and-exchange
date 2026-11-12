<?php
// User Management: Manage student accounts and volunteer applications.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

$adminUser = current_user();
$adminId = (int) $adminUser['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_account_status') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = $_POST['account_status'] ?? '';
        $allowedStatuses = ['Active', 'Suspended', 'Disabled'];

        if ($targetUserId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            $updateStmt = mysqli_prepare($conn, "UPDATE users SET account_status = ? WHERE user_id = ? AND role = 'Student'");
            mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $targetUserId);
            mysqli_stmt_execute($updateStmt);
            $affected = mysqli_stmt_affected_rows($updateStmt);
            mysqli_stmt_close($updateStmt);

            if ($affected > 0) {
                redirect_message("adminUsers.php?record=$targetUserId#recordDetails", "Student account status updated to $newStatus.", 'success');
            } else {
                redirect_message("adminUsers.php?record=$targetUserId#recordDetails", "Could not update account status.", 'warning');
            }
        }
    } elseif ($action === 'update_volunteer_status') {
        $volunteerId = (int) ($_POST['volunteer_id'] ?? 0);
        $approvalStatus = $_POST['approval_status'] ?? '';

        if ($volunteerId > 0 && in_array($approvalStatus, ['Approved', 'Rejected'], true)) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE volunteer_profiles
                 SET approval_status = ?, approved_by = ?, approved_at = CASE WHEN ? = 'Approved' THEN NOW() ELSE NULL END
                 WHERE volunteer_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'sisi', $approvalStatus, $adminId, $approvalStatus, $volunteerId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message("adminUsers.php?view=applications&record=$volunteerId#recordDetails", "Volunteer application marked as $approvalStatus.", 'success');
        }
    }
}

// Load real data from MySQL
$studentAccounts = [];
$volunteerApplications = [];

// Student accounts
$studentsQuery = mysqli_query(
    $conn,
    "SELECT user_id, full_name, tp_number, email, phone_number, intake_code, academic_program, account_status, total_points, created_at
     FROM users
     WHERE role = 'Student'
     ORDER BY user_id DESC"
);
while ($row = mysqli_fetch_assoc($studentsQuery)) {
    $studentAccounts[] = $row;
}

// Volunteer applications
$volunteersQuery = mysqli_query(
    $conn,
    "SELECT v.volunteer_id, v.user_id, v.skills_description, v.approval_status, v.approved_at,
            u.full_name, u.tp_number, u.email, u.phone_number, u.intake_code, u.academic_program
     FROM volunteer_profiles v
     JOIN users u ON u.user_id = v.user_id
     ORDER BY FIELD(v.approval_status, 'Pending', 'Approved', 'Rejected'), v.volunteer_id DESC"
);
while ($row = mysqli_fetch_assoc($volunteersQuery)) {
    $volunteerApplications[] = $row;
}

// Current tab view
$showApplications = ($_GET['view'] ?? '') === 'applications';
$view = $showApplications ? 'applications' : 'accounts';
$records = $showApplications ? $volunteerApplications : $studentAccounts;
$listTitle = $showApplications ? 'Volunteer applications' : 'Student accounts';
$nameLabel = $showApplications ? 'Applicant' : 'Student';
$informationLabel = $showApplications ? 'Skills' : 'Email';
$statusLabel = $showApplications ? 'Approval status' : 'Account status';
$idField = $showApplications ? 'volunteer_id' : 'user_id';
$informationField = $showApplications ? 'skills_description' : 'email';
$statusField = $showApplications ? 'approval_status' : 'account_status';
$statuses = $showApplications ? ['Pending', 'Approved', 'Rejected'] : ['Active', 'Suspended', 'Disabled'];

// Count pending applications
$pendingCount = 0;
foreach ($volunteerApplications as $application) {
    if ($application['approval_status'] === 'Pending') {
        $pendingCount++;
    }
}

// Filters from URL
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, $statuses, true)) {
    $status = '';
}

$recordId = filter_input(INPUT_GET, 'record', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedRecord = null;
$filteredRecords = [];

foreach ($records as $record) {
    if ((int) $record[$idField] === $recordId) {
        $selectedRecord = $record;
    }

    $searchText = $record['full_name'] . ' ' . $record['tp_number'] . ' ' . ($record[$informationField] ?? '');
    $matchesSearch = $search === '' || stripos($searchText, $search) !== false;
    $matchesStatus = $status === '' || $record[$statusField] === $status;

    if ($matchesSearch && $matchesStatus) {
        $filteredRecords[] = $record;
    }
}

$tabUrl = $showApplications ? 'adminUsers.php?view=applications' : 'adminUsers.php';
$resultsUrl = 'adminUsers.php?' . http_build_query(['view' => $view, 'q' => $search, 'status' => $status]);

render_admin_header('User Management', 'users');
?>

<main class="dashboardMain">
    <!-- page title and account totals -->
    <div class="usersPageHeading">
        <div>
            <p class="usersPageLabel">Administrator</p>
            <h1>User Management</h1>
            <p>Manage student accounts, permissions and review repair volunteer applications.</p>
        </div>

        <div class="usersTotals">
            <span class="summaryIcon<?= $showApplications ? ' applicationIcon' : '' ?>" aria-hidden="true">
                <i class="fa-solid fa-users"></i>
            </span>
            <div>
                <strong><?= $showApplications ? $pendingCount . ' pending' : count($studentAccounts) . ' accounts' ?></strong>
                <small><?= $showApplications ? 'Applications awaiting review' : $pendingCount . ' applications pending' ?></small>
            </div>
        </div>
    </div>

    <!-- links switch between student accounts and volunteer applications -->
    <div class="usersTabs">
        <a href="adminUsers.php" <?= $showApplications ? '' : 'aria-current="page"' ?>>Student accounts</a>
        <a href="adminUsers.php?view=applications" <?= $showApplications ? 'aria-current="page"' : '' ?>>
            Volunteer applications
            <span class="usersPendingCount"><?= $pendingCount ?> pending</span>
        </a>
    </div>

    <!-- search and status filters stay on current tab -->
    <form class="usersFilters" action="adminUsers.php" method="get">
        <input type="hidden" name="view" value="<?= escape_html($view) ?>">

        <div class="usersSearch">
            <label for="userSearch"><?= $showApplications ? 'Search applicants' : 'Search students' ?></label>
            <input id="userSearch" type="search" name="q"
                   value="<?= escape_html($search) ?>"
                   placeholder="<?= $showApplications ? 'Name, TP number or skills' : 'Name, TP number or email' ?>">
        </div>

        <div class="usersStatusFilter">
            <label for="userStatus"><?= escape_html($statusLabel) ?></label>
            <select id="userStatus" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= escape_html($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= escape_html($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="usersButton usersApplyButton" type="submit">Apply filters</button>
        <a class="usersButton" href="<?= escape_html($tabUrl) ?>">Clear filters</a>
    </form>

    <!-- Detailed record inspection panel -->
    <?php if ($recordId): ?>
        <section class="dashboardPanel adminDetailPanel" id="recordDetails">
            <div class="adminDetailHeader">
                <div class="adminDetailTitle">
                    <span class="summaryIcon" aria-hidden="true">
                        <i class="fa-solid fa-users"></i>
                    </span>

                    <?php if ($selectedRecord): ?>
                        <h2><?= escape_html($selectedRecord['full_name']) ?></h2>
                        <span class="usersStatus <?= escape_html(strtolower($selectedRecord[$statusField])) ?>">
                            <?= escape_html($selectedRecord[$statusField]) ?>
                        </span>
                    <?php else: ?>
                        <h2>Record unavailable</h2>
                    <?php endif; ?>
                </div>

                <a class="usersButton" href="<?= escape_html($resultsUrl) ?>#userResults">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to results
                </a>
            </div>

            <?php if ($selectedRecord): ?>
                <div class="adminDetailBody">
                    <div>
                        <?php if ($showApplications): ?>
                            <div class="adminDetailText">
                                <h3>Skills and Experience</h3>
                                <p style="white-space: pre-line;"><?= escape_html($selectedRecord['skills_description']) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="adminDetailText">
                            <h3><?= $showApplications ? 'Applicant details' : 'Account details' ?></h3>
                            <dl class="adminDetailFacts">
                                <div>
                                    <dt>TP Number</dt>
                                    <dd><?= escape_html($selectedRecord['tp_number']) ?></dd>
                                </div>
                                <div>
                                    <dt>Email</dt>
                                    <dd><?= escape_html($selectedRecord['email']) ?></dd>
                                </div>
                                <div>
                                    <dt>Phone</dt>
                                    <dd><?= escape_html($selectedRecord['phone_number'] ?: '—') ?></dd>
                                </div>
                                <div>
                                    <dt>Intake / Programme</dt>
                                    <dd><?= escape_html(($selectedRecord['intake_code'] ?: '—') . ' / ' . ($selectedRecord['academic_program'] ?: '—')) ?></dd>
                                </div>
                                <?php if (!$showApplications): ?>
                                    <div>
                                        <dt>Sustainability Points</dt>
                                        <dd><strong style="color: #1c7049;"><?= (int) ($selectedRecord['total_points'] ?? 0) ?> pts</strong></dd>
                                    </div>
                                    <div>
                                        <dt>Registered Date</dt>
                                        <dd><?= date('M j, Y', strtotime($selectedRecord['created_at'])) ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <div class="adminDetailSidebar">
                        <?php if ($showApplications): ?>
                            <h3>Volunteer Approval Action</h3>
                            <p style="font-size: 13px; color: #52645a; margin-bottom: 14px;">
                                Approving allows this student to accept repair jobs on campus and earn co-curricular points.
                            </p>
                            <form method="post" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                                <input type="hidden" name="action" value="update_volunteer_status">
                                <input type="hidden" name="volunteer_id" value="<?= (int) $selectedRecord['volunteer_id'] ?>">

                                <?php if ($selectedRecord['approval_status'] !== 'Approved'): ?>
                                    <button class="usersButton" type="submit" name="approval_status" value="Approved" style="background: #1c7049; color: #fff;">
                                        <i class="fa-solid fa-check"></i> Approve Volunteer
                                    </button>
                                <?php endif; ?>

                                <?php if ($selectedRecord['approval_status'] !== 'Rejected'): ?>
                                    <button class="usersButton" type="submit" name="approval_status" value="Rejected" style="color: #b91c1c;">
                                        <i class="fa-solid fa-xmark"></i> Reject Application
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <h3>Account Moderation</h3>
                            <p style="font-size: 13px; color: #52645a; margin-bottom: 14px;">
                                Change student account state to suspend access or reactivate standing.
                            </p>
                            <form method="post" style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                                <input type="hidden" name="action" value="update_account_status">
                                <input type="hidden" name="user_id" value="<?= (int) $selectedRecord['user_id'] ?>">

                                <?php if ($selectedRecord['account_status'] === 'Active'): ?>
                                    <button class="usersButton" type="submit" name="account_status" value="Suspended" style="color: #b45309;" onclick="return confirm('Suspend this student account?');">
                                        <i class="fa-solid fa-pause"></i> Suspend Account
                                    </button>
                                <?php else: ?>
                                    <button class="usersButton" type="submit" name="account_status" value="Active" style="background: #1c7049; color: #fff;">
                                        <i class="fa-solid fa-play"></i> Reactivate Account
                                    </button>
                                <?php endif; ?>

                                <?php if ($selectedRecord['account_status'] !== 'Disabled'): ?>
                                    <button class="usersButton" type="submit" name="account_status" value="Disabled" style="color: #b91c1c;" onclick="return confirm('Disable this student account? They will not be able to sign in.');">
                                        <i class="fa-solid fa-ban"></i> Disable Account
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="adminDetailEmpty">This record could not be found.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Table of results -->
    <section class="usersResults" id="userResults">
        <div class="usersResultsHeading">
            <h2><?= escape_html($listTitle) ?></h2>
            <span><?= count($filteredRecords) ?> of <?= count($records) ?> shown</span>
        </div>

        <?php if (empty($filteredRecords)): ?>
            <p class="usersEmpty">
                <?php if ($search !== '' || $status !== ''): ?>
                    No <?= strtolower($listTitle) ?> match your filters.
                <?php else: ?>
                    No <?= strtolower($listTitle) ?> available yet.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="usersTableContainer">
                <table class="usersTable">
                    <thead>
                        <tr>
                            <th scope="col"><?= escape_html($nameLabel) ?></th>
                            <th scope="col">TP number</th>
                            <th scope="col"><?= escape_html($informationLabel) ?></th>
                            <th scope="col"><?= escape_html($statusLabel) ?></th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredRecords as $record): ?>
                            <tr>
                                <th scope="row"><?= escape_html($record['full_name']) ?></th>
                                <td>
                                    <span class="usersMobileLabel">TP number</span>
                                    <?= escape_html($record['tp_number']) ?>
                                </td>
                                <td>
                                    <span class="usersMobileLabel"><?= escape_html($informationLabel) ?></span>
                                    <?= escape_html(mb_strimwidth($record[$informationField] ?? '', 0, 45, '...')) ?>
                                </td>
                                <td>
                                    <span class="usersMobileLabel"><?= escape_html($statusLabel) ?></span>
                                    <span class="usersStatus <?= escape_html(strtolower($record[$statusField])) ?>">
                                        <?= escape_html($record[$statusField]) ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="usersButton" href="<?= escape_html($resultsUrl . '&record=' . (int) $record[$idField]) ?>#recordDetails">
                                        <?php if (!$showApplications): ?>
                                            View account
                                        <?php elseif ($record['approval_status'] === 'Pending'): ?>
                                            Review application
                                        <?php else: ?>
                                            View application
                                        <?php endif; ?>
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
