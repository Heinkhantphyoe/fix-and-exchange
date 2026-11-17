<?php
// Shared functions and layout helpers for the Admin panel.

if (!function_exists('escape_html')) {
    function escape_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('form_token')) {
    function form_token(): string
    {
        if (empty($_SESSION['admin_form_token'])) {
            $_SESSION['admin_form_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['admin_form_token'];
    }
}

if (!function_exists('check_form_token')) {
    function check_form_token(): void
    {
        $submitted = $_POST['token'] ?? '';
        $validToken = $_SESSION['admin_form_token'] ?? $_SESSION['app_form_token'] ?? $_SESSION['buyer_seller_token'] ?? '';

        if (!is_string($submitted) || $submitted === '' || !hash_equals((string) $validToken, $submitted)) {
            http_response_code(403);
            exit('This action has expired. Please refresh the page and try again.');
        }
    }
}

if (!function_exists('redirect_message')) {
    function redirect_message(string $url, string $message, string $type = 'success'): void
    {
        set_flash($type, $message);
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Render the top navigation and header for Admin pages.
 */
function render_admin_header(string $pageTitle, string $activeTab = ''): void
{
    $admin = current_user();
    $adminName = $admin['full_name'] ?? 'Administrator';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape_html($pageTitle) ?> | Fix &amp; Exchange</title>
    <link rel="icon" type="image/jpeg" href="/fix-and-exchange/assets/images/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/fix-and-exchange/assets/css/adminStyles.css">
    <script src="/fix-and-exchange/assets/js/admin.js?v=3" defer></script>
</head>
<body>
    <header class="siteHeader">
        <div class="headerContainer">
            <a class="siteLogo" href="adminDashboard.php">
                <img src="/fix-and-exchange/assets/images/logo.jpg" alt="Fix And Exchange Logo" width="38" height="38">
                <span>Fix &amp; Exchange</span>
            </a>

            <nav class="adminNav" id="adminNav" aria-label="Admin Navigation">
                <a href="adminDashboard.php" <?= $activeTab === 'dashboard' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-house" aria-hidden="true"></i> Dashboard
                </a>
                <a href="adminUsers.php" <?= $activeTab === 'users' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-users" aria-hidden="true"></i> Users
                </a>
                <a href="adminListings.php" <?= $activeTab === 'listings' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Listings
                </a>
                <a href="adminRepairs.php" <?= $activeTab === 'repairs' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-wrench" aria-hidden="true"></i> Repairs
                </a>
                <a href="adminReports.php" <?= $activeTab === 'reports' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-flag" aria-hidden="true"></i> Reports
                </a>
                <a href="adminStatistics.php" <?= $activeTab === 'statistics' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Statistics
                </a>
                <a href="adminCategories.php" <?= $activeTab === 'categories' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-tags" aria-hidden="true"></i> Categories
                </a>
            </nav>

            <div class="adminHeaderActions">
                <div class="adminProfileMenu" id="adminProfileMenu">
                    <button class="adminProfileBtn" id="adminProfileBtn" type="button" aria-label="Open administrator menu" aria-expanded="false" title="<?= escape_html($adminName) ?> (Admin)">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    </button>
                    <div class="adminProfileDropdown" id="adminProfileDropdown" role="menu" aria-labelledby="adminProfileBtn">
                        <div class="adminProfileDropdownHeader">
                            <span class="adminProfileDropdownName"><?= escape_html($adminName) ?></span>
                            <span class="adminProfileDropdownRole">Administrator</span>
                        </div>
                        <hr class="adminProfileDropdownDivider">
                        <a class="adminProfileDropdownItem adminLogoutItem" href="/fix-and-exchange/src/auth/logout.php" role="menuitem">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <button class="adminMenuToggle" id="adminMenuToggle" type="button" aria-label="Toggle navigation" aria-controls="adminNav" aria-expanded="false">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </header>

    <?php $flash = get_flash(); ?>
    <?php if ($flash): ?>
        <div style="max-width: 1200px; margin: 16px auto 0; padding: 0 20px;">
            <div class="adminFlashMessage adminFlash-<?= escape_html($flash['type']) ?>" style="padding: 12px 18px; border-radius: 8px; font-size: 14px; font-weight: 500; background: <?= $flash['type'] === 'danger' ? '#fee2e2' : ($flash['type'] === 'warning' ? '#fef3c7' : '#dcfce7') ?>; color: <?= $flash['type'] === 'danger' ? '#991b1b' : ($flash['type'] === 'warning' ? '#92400e' : '#166534') ?>; border: 1px solid rgba(0,0,0,0.1);">
                <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-circle-xmark' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check') ?>"></i>
                <?= escape_html($flash['message']) ?>
            </div>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * Render the footer for Admin pages.
 */
function render_admin_footer(): void
{
    ?>
    <footer class="siteFooter">
        <div class="footerContainer">
            <strong>Fix &amp; Exchange</strong>
            <span>APU Campus Sustainability Platform &bull; Administration Console</span>
        </div>
    </footer>
</body>
</html>
    <?php
}
