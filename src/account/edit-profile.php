<?php
// Edit Profile: Update contact details, intake code, academic programme, and password.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_login();
require_once __DIR__ . '/../../includes/repair-helpers.php';

$user = current_user();
$userId = (int) $user['user_id'];
$errors = [];

// Fetch latest user data from DB
$stmt = mysqli_prepare(
    $conn,
    "SELECT user_id, full_name, email, password_hash, role, tp_number, phone_number, intake_code, academic_program
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$dbUser = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$dbUser) {
    http_response_code(404);
    exit('User record not found.');
}

$form = [
    'phone_number' => $dbUser['phone_number'] ?? '',
    'intake_code' => $dbUser['intake_code'] ?? '',
    'academic_program' => $dbUser['academic_program'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();

    $form['phone_number'] = trim($_POST['phone_number'] ?? '');
    $form['intake_code'] = trim($_POST['intake_code'] ?? '');
    $form['academic_program'] = trim($_POST['academic_program'] ?? '');

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Field length validations
    if (mb_strlen($form['phone_number']) > 30) {
        $errors[] = 'Phone number cannot exceed 30 characters.';
    }

    if (mb_strlen($form['intake_code']) > 50) {
        $errors[] = 'Intake code cannot exceed 50 characters.';
    }

    if (mb_strlen($form['academic_program']) > 100) {
        $errors[] = 'Academic programme cannot exceed 100 characters.';
    }

    // Password change validation
    $updatePassword = false;
    $newPasswordHash = null;

    if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
        if ($currentPassword === '') {
            $errors[] = 'Please enter your current password to set a new password.';
        } elseif (!password_verify($currentPassword, $dbUser['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $updatePassword = true;
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    if (!$errors) {
        if ($updatePassword && $newPasswordHash) {
            $updateStmt = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET phone_number = ?, intake_code = ?, academic_program = ?, password_hash = ?
                 WHERE user_id = ?"
            );
            mysqli_stmt_bind_param($updateStmt, 'ssssi', $form['phone_number'], $form['intake_code'], $form['academic_program'], $newPasswordHash, $userId);
        } else {
            $updateStmt = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET phone_number = ?, intake_code = ?, academic_program = ?
                 WHERE user_id = ?"
            );
            mysqli_stmt_bind_param($updateStmt, 'sssi', $form['phone_number'], $form['intake_code'], $form['academic_program'], $userId);
        }

        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        // Refresh session data
        $_SESSION['user']['phone_number'] = $form['phone_number'];
        $_SESSION['user']['intake_code'] = $form['intake_code'];
        $_SESSION['user']['academic_program'] = $form['academic_program'];

        set_flash('success', 'Your profile details have been updated successfully.');
        header('Location: profile.php');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="section account-dashboard">
    <div class="container container-sm">
        <div class="dashboard-welcome profile-banner">
            <div>
                <p class="dashboard-eyebrow">ACCOUNT SETTINGS</p>
                <h1 class="profile-title">Edit Profile</h1>
                <p class="profile-subtitle">Update your campus contact info and manage account credentials.</p>
            </div>
            <a class="btn btn-outline-primary profile-btn-ghost" href="profile.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Profile
            </a>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= escape_html($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card edit-profile-card">
            <form method="post" novalidate class="edit-profile-form">
                <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">

                <h3 class="form-section-title">
                    <i class="fa-solid fa-user" aria-hidden="true"></i> Personal Information
                </h3>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" type="text" value="<?= escape_html($dbUser['full_name']) ?>" disabled>
                    <span class="form-hint">Institutional name as registered with the campus.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">TP Number</label>
                    <input class="form-control" type="text" value="<?= escape_html($dbUser['tp_number']) ?>" disabled>
                    <span class="form-hint">Student TP number cannot be changed.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Campus Email</label>
                    <input class="form-control" type="email" value="<?= escape_html($dbUser['email']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone_number">Contact / Phone Number</label>
                    <input class="form-control" type="tel" id="phone_number" name="phone_number" maxlength="30"
                           placeholder="e.g. +60 12-345 6789" value="<?= escape_html($form['phone_number']) ?>">
                    <span class="form-hint">Used by volunteers and buyers/sellers to coordinate handovers.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="intake_code">Intake Code</label>
                    <input class="form-control" type="text" id="intake_code" name="intake_code" maxlength="50"
                           placeholder="e.g. UC3F2311CS" value="<?= escape_html($form['intake_code']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="academic_program">Academic Programme</label>
                    <input class="form-control" type="text" id="academic_program" name="academic_program" maxlength="100"
                           placeholder="e.g. BSc (Hons) in Software Engineering" value="<?= escape_html($form['academic_program']) ?>">
                </div>

                <h3 class="form-section-title spaced">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Change Password
                </h3>
                <p class="form-subtext">Leave password fields blank if you do not wish to change your password.</p>

                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" placeholder="Enter existing password">
                </div>

                <div class="form-two-col">
                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" placeholder="At least 8 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Re-type new password">
                    </div>
                </div>

                <div class="form-actions form-actions-bordered">
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-check" aria-hidden="true"></i> Save Changes
                    </button>
                    <a class="btn btn-secondary" href="profile.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
