<?php
// Shared page footer and closing HTML.
$isLoggedIn = function_exists('current_user') && current_user() !== null;
?>

</main>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <a class="footer-logo" href="/fix-and-exchange/">Fix &amp; Exchange</a>
                <p>Repair, reuse, trade, and share within the APU campus community.</p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="/fix-and-exchange/">Home</a>
                <a href="/fix-and-exchange/src/buyer-seller/marketplace.php">Marketplace</a>
                <a href="/fix-and-exchange/src/repair-requester/repair-hub.php">Repair Hub</a>
                <a href="/fix-and-exchange/src/sustainability/leaderboard.php">Leaderboard</a>
            </div>

            <div class="footer-column">
                <h2>Account</h2>
                <?php if ($isLoggedIn): ?>
                    <a href="/fix-and-exchange/src/account/profile.php">My profile</a>
                    <a href="/fix-and-exchange/src/auth/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/fix-and-exchange/src/auth/login.php">Login</a>
                    <a href="/fix-and-exchange/src/auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-container">
                <span>&copy; <?= date('Y') ?> Fix &amp; Exchange</span>
                <span>APU Campus Sustainability Platform</span>
            </div>
        </div>
    </footer>

<script src="/fix-and-exchange/assets/js/main.js"></script>

</body>
</html>
