<?php
// Shared page header.
require_once __DIR__ . '/auth-check.php';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isLoggedIn = current_user() !== null;
$navActive = static function (string $path) use ($currentPath): bool {
    if (rtrim($path, '/') === '/fix-and-exchange') {
        return $currentPath === '/fix-and-exchange' || $currentPath === '/fix-and-exchange/';
    }

    $sectionPath = rtrim($path, '/') . '/';
    return $currentPath === $path || strpos($currentPath, $sectionPath) === 0;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix &amp; Exchange</title>
    <link rel="icon" type="image/jpeg" href="/fix-and-exchange/assets/images/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/fix-and-exchange/assets/css/style.css">
    <?php if (strpos($currentPath, '/fix-and-exchange/src/repair-volunteer/') === 0): ?>
        <link rel="stylesheet" href="/fix-and-exchange/assets/css/repair-volunteer.css">
    <?php elseif (strpos($currentPath, '/fix-and-exchange/src/repair-requester/') === 0): ?>
        <link rel="stylesheet" href="/fix-and-exchange/assets/css/repair-requester.css">
    <?php elseif (strpos($currentPath, '/fix-and-exchange/src/sustainability/') === 0): ?>
        <link rel="stylesheet" href="/fix-and-exchange/assets/css/sustainability.css">
    <?php elseif (strpos($currentPath, '/fix-and-exchange/src/account/') === 0): ?>
        <link rel="stylesheet" href="/fix-and-exchange/assets/css/account.css">
    <?php endif; ?>
    <script src="/fix-and-exchange/assets/js/main.js?v=2" defer></script>
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <a class="site-logo" href="/fix-and-exchange/">
                <img src="/fix-and-exchange/assets/images/logo.jpg" alt="Fix &amp; Exchange logo">
                <span>Fix &amp; Exchange</span>
            </a>

            <nav class="main-navigation" id="main-navigation" aria-label="Main navigation">
                <a class="<?= ($navActive('/fix-and-exchange/') && strpos($currentPath, '/fix-and-exchange/src/') !== 0) ? 'is-active' : '' ?>" href="/fix-and-exchange/"><i class="fa-solid fa-house" aria-hidden="true"></i> Home</a>
                <?php if ($isLoggedIn): ?>
                    <a class="<?= $navActive('/fix-and-exchange/src/account/home.php') ? 'is-active' : '' ?>" href="/fix-and-exchange/src/account/home.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
                <?php endif; ?>
                <a class="<?= $navActive('/fix-and-exchange/src/buyer-seller/') ? 'is-active' : '' ?>" href="/fix-and-exchange/src/buyer-seller/marketplace.php"><i class="fa-solid fa-store" aria-hidden="true"></i> Marketplace</a>
                <a class="<?= $navActive('/fix-and-exchange/src/repair-requester/') ? 'is-active' : '' ?>" href="/fix-and-exchange/src/repair-requester/repair-hub.php"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Repair Hub</a>
                <a class="<?= $navActive('/fix-and-exchange/src/sustainability/') ? 'is-active' : '' ?>" href="/fix-and-exchange/src/sustainability/dashboard.php"><i class="fa-solid fa-leaf" aria-hidden="true"></i> Sustainability</a>
                <?php if ((current_user()['role'] ?? null) === 'Admin'): ?>
                    <a class="<?= $navActive('/fix-and-exchange/src/admin/') ? 'is-active' : '' ?>" href="/fix-and-exchange/src/admin/dashboard.php"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Admin</a>
                <?php endif; ?>
            </nav>

            <div class="header-actions">
                <?php if (current_user() !== null): ?>
                    <?php $headerUser = current_user(); ?>
                    <div class="profile-menu">
                        <button class="profile-button <?= $navActive('/fix-and-exchange/src/account/profile.php') ? 'is-active' : '' ?>" type="button" aria-label="Open profile menu" aria-expanded="false" title="<?= escape_html($headerUser['full_name'] ?? 'User') ?>">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                        </button>
                        <div class="profile-dropdown">
                            <div class="profile-dropdown-user">
                                <strong><?= escape_html($headerUser['full_name'] ?? '') ?></strong>
                                <span><?= escape_html($headerUser['tp_number'] ?? '') ?> &bull; <?= escape_html($headerUser['role'] ?? '') ?></span>
                            </div>
                            <hr class="profile-dropdown-divider">
                            <a href="/fix-and-exchange/src/account/home.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
                            <a href="/fix-and-exchange/src/account/profile.php"><i class="fa-solid fa-id-badge" aria-hidden="true"></i> My Profile</a>
                            <a href="/fix-and-exchange/src/sustainability/points.php"><i class="fa-solid fa-leaf" aria-hidden="true"></i> My Eco Points</a>
                            <hr class="profile-dropdown-divider">
                            <a class="dropdown-logout" href="/fix-and-exchange/src/auth/logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="nav-login-button <?= $navActive('/fix-and-exchange/src/auth/login.php') ? 'is-active' : '' ?>" href="/fix-and-exchange/src/auth/login.php">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Login
                    </a>
                <?php endif; ?>

                <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="main-navigation" aria-expanded="false">
                    <i class="fa-solid fa-bars menu-toggle-icon" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="page-content">
