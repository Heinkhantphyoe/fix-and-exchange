<?php
// Public landing page for the Fix & Exchange platform.
require __DIR__ . '/includes/header.php';
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
            <article class="home-listing-card">
                <div class="home-listing-image"><img src="/fix-and-exchange/assets/images/login.jpg" alt="Desk lamp listing"></div>
                <div class="home-listing-body"><span class="home-listing-tag">Exchange</span><h3>Desk lamp</h3><p>APU Residence <span aria-hidden="true">•</span> Today</p></div>
            </article>
            <article class="home-listing-card">
                <div class="home-listing-image"><img src="/fix-and-exchange/assets/images/login.jpg" alt="Study notes bundle listing"></div>
                <div class="home-listing-body"><span class="home-listing-tag home-listing-tag-green">Free Giveaway</span><h3>Study notes bundle</h3><p>Campus Library <span aria-hidden="true">•</span> Yesterday</p></div>
            </article>
            <article class="home-listing-card">
                <div class="home-listing-image"><img src="/fix-and-exchange/assets/images/login.jpg" alt="Scientific calculator listing"></div>
                <div class="home-listing-body"><span class="home-listing-tag home-listing-tag-blue">Sale</span><h3>Scientific calculator</h3><p>New Campus <span aria-hidden="true">•</span> 2 days ago</p></div>
            </article>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
