<?php
// Public landing page for the Fix & Exchange platform.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

// Fetch latest active marketplace items
$recentListings = [];
if (isset($conn)) {
    $res = mysqli_query(
        $conn,
        "SELECT l.*, c.category_name,
                (SELECT image_path FROM item_images WHERE listing_id = l.listing_id ORDER BY image_id ASC LIMIT 1) AS image_path
         FROM item_listings l
         JOIN categories c ON c.category_id = l.category_id
         WHERE l.status = 'Available'
         ORDER BY l.listing_id DESC
         LIMIT 3"
    );
    if ($res) {
        $recentListings = mysqli_fetch_all($res, MYSQLI_ASSOC);
    }
}
?>

<div class="home-page">
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="home-hero-content">
            <p class="home-eyebrow">APU CAMPUS COMMUNITY</p>
            <h1 id="hero-title">Give campus items a second life</h1>
            <p class="home-hero-text">
                Find affordable items, exchange what you no longer need,
                or repair belongings before replacing them.
            </p>

            <form class="home-search" method="get" action="/fix-and-exchange/src/account/search.php">
                <label class="sr-only" for="home-search-input">Search items or repair help</label>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input id="home-search-input" name="q" type="search" placeholder="Search items or repair help">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="home-hero-image">
            <img src="/fix-and-exchange/assets/images/apu.jpg" alt="Students repairing, sharing, and exchanging items on campus">
        </div>
    </section>

    <?php $heroUser = current_user(); ?>
    <?php if ($heroUser): ?>
        <section class="home-user-banner" style="background: linear-gradient(135deg, #e8f5ed 0%, #d4ede0 100%); border: 1px solid #b7dfc8; border-radius: 12px; padding: 18px 24px; margin: 30px auto 10px; max-width: 1200px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: #115e39; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                </div>
                <div>
                    <strong style="display: block; font-size: 17px; color: #0c4a2c;">Welcome back, <?= htmlspecialchars($heroUser['full_name'], ENT_QUOTES, 'UTF-8') ?>!</strong>
                    <span style="font-size: 13px; color: #435b4f;"><?= htmlspecialchars($heroUser['tp_number'] ?? '') ?> &bull; <?= htmlspecialchars($heroUser['role'] ?? 'Student') ?></span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a class="btn btn-primary" href="/fix-and-exchange/src/account/home.php" style="padding: 9px 18px; font-size: 14px; text-decoration: none; border-radius: 6px;">
                    <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Student Dashboard
                </a>
                <a class="btn btn-outline-primary" href="/fix-and-exchange/src/buyer-seller/create-listing.php" style="padding: 9px 16px; font-size: 14px; text-decoration: none; border-radius: 6px; background: #fff;">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Post Item
                </a>
                <a class="btn btn-outline-primary" href="/fix-and-exchange/src/repair-requester/create-request.php" style="padding: 9px 16px; font-size: 14px; text-decoration: none; border-radius: 6px; background: #fff;">
                    <i class="fa-solid fa-wrench" aria-hidden="true"></i> Request Repair
                </a>
            </div>
        </section>
    <?php endif; ?>

    <section class="home-section" aria-labelledby="actions-title">
        <div class="home-section-heading">
            <h2 id="actions-title">What would you like to do?</h2>
        </div>

        <div class="home-action-grid">
            <a class="home-action-card" href="/fix-and-exchange/src/buyer-seller/marketplace.php">
                <span class="home-action-icon"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                <span><strong>Browse items</strong><small>Sale, exchange, giveaway</small></span>
                <i class="fa-solid fa-arrow-right home-action-arrow" aria-hidden="true"></i>
            </a>
            <a class="home-action-card" href="/fix-and-exchange/src/repair-requester/create-request.php">
                <span class="home-action-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                <span><strong>Request repair</strong><small>Repair before replace</small></span>
                <i class="fa-solid fa-arrow-right home-action-arrow" aria-hidden="true"></i>
            </a>
            <a class="home-action-card" href="/fix-and-exchange/src/sustainability/leaderboard.php">
                <span class="home-action-icon"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i></span>
                <span><strong>Leaderboard</strong><small>Campus participation</small></span>
                <i class="fa-solid fa-arrow-right home-action-arrow" aria-hidden="true"></i>
            </a>
            <a class="home-action-card" href="/fix-and-exchange/src/account/profile.php">
                <span class="home-action-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                <span><strong>My profile</strong><small>Points and activity</small></span>
                <i class="fa-solid fa-arrow-right home-action-arrow" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <section class="home-section home-listings" aria-labelledby="listings-title">
        <div class="home-section-heading home-listings-heading">
            <h2 id="listings-title">Recently added on campus</h2>
            <a href="/fix-and-exchange/src/buyer-seller/marketplace.php">View all <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        <div class="home-listing-grid">
            <?php if ($recentListings): ?>
                <?php foreach ($recentListings as $listing): ?>
                    <?php
                    $tagClass = '';
                    if ($listing['listing_type'] === 'Giveaway') $tagClass = 'home-listing-tag-green';
                    elseif ($listing['listing_type'] === 'Sale') $tagClass = 'home-listing-tag-blue';
                    ?>
                    <article class="home-listing-card">
                        <div class="home-listing-image">
                            <a href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $listing['listing_id'] ?>">
                                <?php if ($listing['image_path']): ?>
                                    <img src="/fix-and-exchange/<?= htmlspecialchars($listing['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php else: ?>
                                    <img src="/fix-and-exchange/assets/images/logo.jpg" alt="Item photo">
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="home-listing-body">
                            <span class="home-listing-tag <?= $tagClass ?>"><?= htmlspecialchars($listing['listing_type'], ENT_QUOTES, 'UTF-8') ?></span>
                            <h3>
                                <a href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $listing['listing_id'] ?>" style="color: inherit; text-decoration: none;">
                                    <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h3>
                            <p>
                                <?= htmlspecialchars($listing['category_name'], ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&bull;</span>
                                <?= date('M j', strtotime($listing['created_at'])) ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <article class="home-listing-card">
                    <div class="home-listing-image"><img src="/fix-and-exchange/assets/images/login.jpg" alt="Desk lamp listing"></div>
                    <div class="home-listing-body"><span class="home-listing-tag">Exchange</span><h3>Desk lamp</h3><p>APU Residence <span aria-hidden="true">&bull;</span> Today</p></div>
                </article>
                <article class="home-listing-card">
                    <div class="home-listing-image"><img src="/fix-and-exchange/assets/images/login.jpg" alt="Study notes bundle listing"></div>
                    <div class="home-listing-body"><span class="home-listing-tag home-listing-tag-green">Free Giveaway</span><h3>Study notes bundle</h3><p>Campus Library <span aria-hidden="true">&bull;</span> Yesterday</p></div>
                </article>
                <article class="home-listing-card">
                    <div class="home-listing-image"><img src="/fix-and-exchange/assets/images/login.jpg" alt="Scientific calculator listing"></div>
                    <div class="home-listing-body"><span class="home-listing-tag home-listing-tag-blue">Sale</span><h3>Scientific calculator</h3><p>New Campus <span aria-hidden="true">&bull;</span> 2 days ago</p></div>
                </article>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
