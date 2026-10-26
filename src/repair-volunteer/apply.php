<?php
// Submit a volunteer application with your repair skills.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();

$user = current_user();
$userId = (int) $user['user_id'];
$errors = [];
$form = [
    'intake_code' => $user['intake_code'] ?? '',
    'academic_program' => $user['academic_program'] ?? '',
    'skills_description' => '',
];
$application = null;

$statement = mysqli_prepare(
    $conn,
    'SELECT volunteer_id, skills_description, approval_status, approved_at
     FROM volunteer_profiles
     WHERE user_id = ?
     LIMIT 1'
);
mysqli_stmt_bind_param($statement, 'i', $userId);
mysqli_stmt_execute($statement);
$applicationResult = mysqli_stmt_get_result($statement);
$application = mysqli_fetch_assoc($applicationResult) ?: null;
mysqli_stmt_close($statement);

if ($application) {
    $form['skills_description'] = $application['skills_description'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$application || $application['approval_status'] === 'Rejected')) {
    $form['intake_code'] = trim($_POST['intake_code'] ?? '');
    $form['academic_program'] = trim($_POST['academic_program'] ?? '');
    $form['skills_description'] = trim($_POST['skills_description'] ?? '');
    $confirmed = isset($_POST['safety_confirmation']) && $_POST['safety_confirmation'] === '1';

    if ($form['intake_code'] === '' || mb_strlen($form['intake_code']) > 50) {
        $errors[] = 'Please enter your intake code using 50 characters or fewer.';
    }

    if ($form['academic_program'] === '' || mb_strlen($form['academic_program']) > 100) {
        $errors[] = 'Please enter your academic program using 100 characters or fewer.';
    }

    if ($form['skills_description'] === '' || mb_strlen($form['skills_description']) > 5000) {
        $errors[] = 'Please describe your repair skills using 5,000 characters or fewer.';
    }

    if (!$confirmed) {
        $errors[] = 'Please confirm that you agree to the APU Makerspace safety protocols and Volunteer Code of Conduct.';
    }

    if (!$errors) {
        $statement = mysqli_prepare(
            $conn,
            'UPDATE users SET intake_code = ?, academic_program = ? WHERE user_id = ?'
        );
        mysqli_stmt_bind_param($statement, 'ssi', $form['intake_code'], $form['academic_program'], $userId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        $_SESSION['user']['intake_code'] = $form['intake_code'];
        $_SESSION['user']['academic_program'] = $form['academic_program'];

        if ($application && $application['approval_status'] === 'Rejected') {
            $statement = mysqli_prepare(
                $conn,
                "UPDATE volunteer_profiles
                 SET skills_description = ?, approval_status = 'Pending', approved_by = NULL, approved_at = NULL
                 WHERE volunteer_id = ? AND user_id = ?"
            );
            mysqli_stmt_bind_param($statement, 'sii', $form['skills_description'], $application['volunteer_id'], $userId);
        } else {
            $statement = mysqli_prepare(
                $conn,
                "INSERT INTO volunteer_profiles (user_id, skills_description, approval_status)
                 VALUES (?, ?, 'Pending')"
            );
            mysqli_stmt_bind_param($statement, 'is', $userId, $form['skills_description']);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        set_flash('success', 'Your repair volunteer application has been submitted for review.');
        header('Location: apply.php');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="volunteer-application-page">
    <div class="container volunteer-application-container">
        <div class="volunteer-application-back-link">
            <a class="btn btn-outline-primary" href="../account/home.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back to Dashboard
            </a>
        </div>

        <section class="volunteer-application-hero" aria-labelledby="application-page-title">
            <div class="volunteer-application-kicker">
                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                Accredited Co-Curricular Role · Earn up to 25 Green Points / Repair
            </div>
            <h1 id="application-page-title">Repair Volunteer Application</h1>
            <p>Join the APU Makerspace Student Repair Squad. Mentor peers, troubleshoot malfunctioning hardware, curb campus e-waste, and turn your technical passion into verified circular impact.</p>

            <div class="volunteer-application-stats" aria-label="Repair squad information">
                <div>
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <span><strong>Active Squad</strong><small>38 Students</small></span>
                </div>
                <div>
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <span><strong>Makerspace Lab</strong><small>Level 4, Block E</small></span>
                </div>
            </div>
        </section>

        <?php if ($flash = get_flash()): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($application && $application['approval_status'] !== 'Rejected'): ?>
            <section class="volunteer-application-status" aria-labelledby="application-status-title">
                <h2 id="application-status-title">Application status</h2>
                <p>Your application is currently <span class="badge <?= $application['approval_status'] === 'Approved' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($application['approval_status'], ENT_QUOTES, 'UTF-8') ?></span>.</p>
                <?php if ($application['approval_status'] === 'Approved'): ?>
                    <a class="btn btn-primary" href="dashboard.php">Open volunteer dashboard</a>
                <?php else: ?>
                    <p class="form-hint">An administrator will review your skills description before you can browse and accept repair jobs.</p>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="volunteer-application-card" aria-labelledby="application-form-title">
                <h2 id="application-form-title">Application Form</h2>

                <form method="post" action="" novalidate>
                    <div class="volunteer-application-fields">
                        <div>
                            <div class="form-group">
                                <label class="form-label" for="full_name">Full Name</label>
                                <input class="form-control" type="text" id="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">University Email</label>
                                <input class="form-control" type="email" id="email" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="tp_number">TP Number</label>
                                <input class="form-control" type="text" id="tp_number" value="<?= htmlspecialchars($user['tp_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                        </div>

                        <div>
                            <div class="form-group">
                                <label class="form-label" for="intake_program">Intake Code / Program</label>
                                <div class="volunteer-application-inline-fields">
                                    <input class="form-control" type="text" id="intake_code" name="intake_code" placeholder="Intake code" maxlength="50" value="<?= htmlspecialchars($form['intake_code'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    <input class="form-control" type="text" id="academic_program" name="academic_program" placeholder="Academic program" maxlength="100" value="<?= htmlspecialchars($form['academic_program'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="skills_description">Skill Description</label>
                                <textarea class="form-control form-textarea volunteer-skills-input" id="skills_description" name="skills_description" maxlength="5000" placeholder="Tell us about your repair, technical, or troubleshooting skills." required><?= htmlspecialchars($form['skills_description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                <p class="form-hint">Include relevant devices, tools, certifications, or experience.</p>
                            </div>
                        </div>
                    </div>

                    <label class="volunteer-safety-check" for="safety_confirmation">
                        <input class="form-check-input" type="checkbox" id="safety_confirmation" name="safety_confirmation" value="1" required>
                        <span>I solemnly confirm that I will adhere to APU Makerspace Safety Protocols, always wear certified ESD protection when handling sensitive logic boards, respect peer device privacy, and strictly follow the Fix &amp; Exchange Volunteer Code of Conduct.</span>
                    </label>

                    <div class="volunteer-application-actions">
                        <button class="btn btn-primary" type="submit">Submit Application</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
