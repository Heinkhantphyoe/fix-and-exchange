<?php
// Show the campus homepage.
require __DIR__ . '/../../includes/auth-check.php';
require_login();
$user = current_user();
require __DIR__ . '/../../includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Welcome, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="page-subtitle">Your Fix &amp; Exchange sustainability journey starts here.</p>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="card-title">Account ready</h2>
                <p class="card-text">You are signed in as <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>.</p>
                <a class="btn btn-primary" href="/fix-and-exchange/src/auth/logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
