<?php
// Sign in to an existing account.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';

$error = null;
$email = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter your email address and password.';
    } else {
        $statement = mysqli_prepare($conn, 'SELECT user_id, full_name, email, password_hash, role, account_status, tp_number FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($statement, 's', $email);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($statement);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'The email or password is incorrect.';
        } elseif ($user['account_status'] !== 'Active') {
            $error = 'This account is not active. Please contact an administrator.';
        } else {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            header('Location: /fix-and-exchange/src/account/home.php');
            exit;
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-layout">
        <div class="auth-card">
            <div class="auth-brand">Fix &amp; Exchange</div>
            <p class="auth-subtitle">APU Sustainability Journey</p>
            <h1>Sign In</h1>
            <p class="text-muted">Access the campus circular network.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Student email</label>
                    <input class="form-control" type="email" id="email" name="email" placeholder="student@apu.edu.my" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <p class="forgot-link"><a href="#">Forgot password?</a></p>
                <button class="btn btn-primary btn-block" type="submit">Sign In</button>
            </form>

            <p class="auth-link">New student? <a href="register.php">Register here</a></p>
        </div>

        <div class="auth-image-panel">
            <img src="/fix-and-exchange/assets/images/login.jpg" alt="Fix and Exchange sustainability platform">
            <div class="auth-image-content">
                <span class="auth-image-kicker">Welcome back</span>
                <h2>Keep good things in motion.</h2>
                <p>Reconnect with your campus community and make your next sustainable choice count.</p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
