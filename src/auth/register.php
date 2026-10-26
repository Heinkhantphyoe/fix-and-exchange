<?php
// Create a student account.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';

$errors = [];
$form = [
    'full_name' => '',
    'tp_number' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['full_name'] = trim($_POST['full_name'] ?? '');
    $form['tp_number'] = trim($_POST['tp_number'] ?? '');
    $form['email'] = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 100) {
        $errors[] = 'Please enter your full name using 100 characters or fewer.';
    }

    if ($form['tp_number'] === '' || mb_strlen($form['tp_number']) > 20) {
        $errors[] = 'Please enter a valid TP Number using 20 characters or fewer.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 255) {
        $errors[] = 'Please enter a valid university email address.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE email = ? OR tp_number = ? LIMIT 1');
        mysqli_stmt_bind_param($check, 'ss', $form['email'], $form['tp_number']);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $errors[] = 'An account with that email or TP Number already exists.';
        }

        mysqli_stmt_close($check);
    }

    if (!$errors) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $statement = mysqli_prepare($conn, 'INSERT INTO users (full_name, email, password_hash, role, account_status, tp_number) VALUES (?, ?, ?, \'Student\', \'Active\', ?)');
        mysqli_stmt_bind_param($statement, 'ssss', $form['full_name'], $form['email'], $password_hash, $form['tp_number']);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        set_flash('success', 'Your account has been created. Please sign in.');
        header('Location: login.php');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-layout">
        <div class="auth-card">
            <h1>Create Account</h1>
            <p class="auth-subtitle">Start your sustainability journey</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label" for="full_name">Name</label>
                    <input class="form-control" type="text" id="full_name" name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($form['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tp_number">TP Number</label>
                    <input class="form-control" type="text" id="tp_number" name="tp_number" placeholder="Student ID (TP Number)" value="<?= htmlspecialchars($form['tp_number'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">University Email</label>
                    <input class="form-control" type="email" id="email" name="email" placeholder="student@apu.edu.my" value="<?= htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" placeholder="Password" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Create Account</button>
            </form>

            <p class="auth-link">Already have an account? <a href="login.php">Sign In</a></p>
        </div>

        <div class="auth-image-panel">
            <img src="/fix-and-exchange/assets/images/login.jpg" alt="Fix and Exchange sustainability platform">
            <div class="auth-image-content">
                <span class="auth-image-kicker">Join the movement</span>
                <h2>Your everyday choices can make an impact.</h2>
                <p>Repair, reuse, trade and donate with students who care about a more sustainable campus.</p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
