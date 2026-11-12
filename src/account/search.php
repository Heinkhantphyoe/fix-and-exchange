<?php
// Global Campus Search: Search second-hand marketplace listings and campus repair requests.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require __DIR__ . '/../../includes/header.php';

$query = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? 'all';
$validFilters = ['all', 'marketplace', 'repairs'];
if (!in_array($filterType, $validFilters, true)) {
    $filterType = 'all';
}

$listingsResults = [];
$repairsResults = [];

if ($query !== '') {
    $searchTerm = '%' . $query . '%';

    // Search marketplace listings (only Available status)
    if ($filterType === 'all' || $filterType === 'marketplace') {
        $listStmt = mysqli_prepare(
            $conn,
            "SELECT l.*, c.category_name, u.full_name AS seller_name,
                    (SELECT image_path FROM item_images WHERE listing_id = l.listing_id ORDER BY image_id ASC LIMIT 1) AS image_path
             FROM item_listings l
             JOIN categories c ON c.category_id = l.category_id
             JOIN users u ON u.user_id = l.seller_id
             WHERE l.status = 'Available'
               AND (l.title LIKE ? OR l.description LIKE ? OR c.category_name LIKE ?)
             ORDER BY l.listing_id DESC
             LIMIT 30"
        );
        mysqli_stmt_bind_param($listStmt, 'sss', $searchTerm, $searchTerm, $searchTerm);
        mysqli_stmt_execute($listStmt);
        $listingsResults = mysqli_fetch_all(mysqli_stmt_get_result($listStmt), MYSQLI_ASSOC);
        mysqli_stmt_close($listStmt);
    }

    // Search repair requests (only Open or Verified status)
    if ($filterType === 'all' || $filterType === 'repairs') {
        $repStmt = mysqli_prepare(
            $conn,
            "SELECT r.*, c.category_name, u.full_name AS requester_name,
                    (SELECT image_path FROM repair_images WHERE request_id = r.request_id ORDER BY repair_image_id ASC LIMIT 1) AS image_path
             FROM repair_requests r
             JOIN categories c ON c.category_id = r.category_id
             JOIN users u ON u.user_id = r.requester_id
             WHERE r.status IN ('Open', 'Verified')
               AND (r.title LIKE ? OR r.problem_description LIKE ? OR c.category_name LIKE ?)
             ORDER BY r.request_id DESC
             LIMIT 30"
        );
        mysqli_stmt_bind_param($repStmt, 'sss', $searchTerm, $searchTerm, $searchTerm);
        mysqli_stmt_execute($repStmt);
        $repairsResults = mysqli_fetch_all(mysqli_stmt_get_result($repStmt), MYSQLI_ASSOC);
        mysqli_stmt_close($repStmt);
    }
}

$totalFound = count($listingsResults) + count($repairsResults);
?>

<section class="section account-dashboard">
    <div class="container">
        <!-- Search Header -->
        <div class="dashboard-welcome search-header-box">
            <p class="dashboard-eyebrow"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> CAMPUS GLOBAL SEARCH</p>
            <h1 class="profile-title">Search Marketplace &amp; Repair Hub</h1>

            <form method="get" action="search.php" class="search-input-group">
                <input class="form-control search-input-field" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Search second-hand items, study gear, electronics or repair jobs..." required>
                <?php if ($filterType !== 'all'): ?>
                    <input type="hidden" name="type" value="<?= htmlspecialchars($filterType, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <button class="btn btn-primary" type="submit">
                    Search
                </button>
            </form>
        </div>

        <?php if ($query !== ''): ?>
            <!-- Filter Tabs -->
            <div class="search-filters-bar">
                <div class="filter-pills">
                    <a href="search.php?q=<?= urlencode($query) ?>&amp;type=all"
                       class="filter-pill <?= $filterType === 'all' ? 'is-active' : '' ?>">
                        All Results (<?= $totalFound ?>)
                    </a>
                    <a href="search.php?q=<?= urlencode($query) ?>&amp;type=marketplace"
                       class="filter-pill <?= $filterType === 'marketplace' ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-store" aria-hidden="true"></i> Marketplace (<?= count($listingsResults) ?>)
                    </a>
                    <a href="search.php?q=<?= urlencode($query) ?>&amp;type=repairs"
                       class="filter-pill <?= $filterType === 'repairs' ? 'is-active' : '' ?>">
                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Repair Jobs (<?= count($repairsResults) ?>)
                    </a>
                </div>
                <span class="search-summary-text mb-0">
                    Results for "<strong><?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></strong>"
                </span>
            </div>

            <?php if ($totalFound === 0): ?>
                <div class="card search-empty-prompt">
                    <i class="fa-solid fa-box-open search-empty-icon" aria-hidden="true"></i>
                    <h3>No matching items or repair jobs found</h3>
                    <p>
                        We couldn't find anything matching "<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>". Try searching for different keywords, category names, or browse our sections directly.
                    </p>
                    <div class="form-actions" style="justify-content: center;">
                        <a class="btn btn-primary" href="/fix-and-exchange/src/buyer-seller/marketplace.php">
                            <i class="fa-solid fa-store" aria-hidden="true"></i> Browse Marketplace
                        </a>
                        <a class="btn btn-outline-primary" href="/fix-and-exchange/src/repair-requester/repair-hub.php">
                            <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Explore Repair Hub
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Marketplace Results -->
            <?php if ($listingsResults): ?>
                <section class="search-results-section">
                    <div class="search-section-header">
                        <h2 class="search-section-heading">
                            <i class="fa-solid fa-store" aria-hidden="true"></i> Marketplace Items (<?= count($listingsResults) ?>)
                        </h2>
                        <a href="/fix-and-exchange/src/buyer-seller/marketplace.php" class="search-view-all-link">View Marketplace &rarr;</a>
                    </div>

                    <div class="search-items-grid">
                        <?php foreach ($listingsResults as $item): ?>
                            <article class="search-item-card">
                                <?php if ($item['image_path']): ?>
                                    <img class="search-item-img" src="/fix-and-exchange/<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                <?php else: ?>
                                    <div class="search-item-placeholder">
                                        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="search-item-body">
                                    <div>
                                        <span class="badge <?= $item['listing_type'] === 'Giveaway' ? 'badge-success' : 'badge-primary' ?>">
                                            <?= htmlspecialchars($item['listing_type'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                    <h3 class="item-mini-title">
                                        <a href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $item['listing_id'] ?>">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </h3>
                                    <p class="search-item-desc">
                                        <?= htmlspecialchars(mb_strimwidth($item['description'], 0, 90, '...'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <div class="search-item-seller">
                                        <i class="fa-solid fa-tag" aria-hidden="true"></i> <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?> &bull; By <?= htmlspecialchars($item['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="search-item-footer">
                                        <strong class="item-mini-price">
                                            <?php if ($item['listing_type'] === 'Giveaway'): ?>
                                                Free Giveaway
                                            <?php elseif ($item['listing_type'] === 'Exchange'): ?>
                                                Exchange
                                            <?php else: ?>
                                                RM <?= number_format((float) $item['price'], 2) ?>
                                            <?php endif; ?>
                                        </strong>
                                        <a class="btn btn-sm btn-primary" href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $item['listing_id'] ?>">
                                            View Item
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Repair Results -->
            <?php if ($repairsResults): ?>
                <section class="search-results-section">
                    <div class="search-section-header">
                        <h2 class="search-section-heading">
                            <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Open Repair Requests (<?= count($repairsResults) ?>)
                        </h2>
                        <a href="/fix-and-exchange/src/repair-requester/repair-hub.php" class="search-view-all-link">Repair Hub &rarr;</a>
                    </div>

                    <div class="search-repairs-list">
                        <?php foreach ($repairsResults as $repair): ?>
                            <article class="search-repair-row">
                                <div class="search-repair-left">
                                    <?php if ($repair['image_path']): ?>
                                        <img class="search-repair-thumb" src="/fix-and-exchange/<?= htmlspecialchars($repair['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                    <?php else: ?>
                                        <div class="search-repair-thumb-placeholder">
                                            <i class="fa-solid fa-wrench" aria-hidden="true"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="search-repair-title">
                                            <a href="/fix-and-exchange/src/repair-volunteer/job-details.php?request_id=<?= (int) $repair['request_id'] ?>">
                                                <?= htmlspecialchars($repair['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </h3>
                                        <div class="search-repair-meta">
                                            Category: <?= htmlspecialchars($repair['category_name'], ENT_QUOTES, 'UTF-8') ?> &bull;
                                            Submitted by <?= htmlspecialchars($repair['requester_name'], ENT_QUOTES, 'UTF-8') ?> &bull;
                                            <?= date('M j, Y', strtotime($repair['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="search-repair-right">
                                    <span class="badge badge-info">
                                        <?= htmlspecialchars($repair['status'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <a class="btn btn-sm btn-outline-primary" href="/fix-and-exchange/src/repair-volunteer/job-details.php?request_id=<?= (int) $repair['request_id'] ?>">
                                        View Job
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php else: ?>
            <!-- Initial Search State -->
            <div class="card search-empty-prompt">
                <i class="fa-solid fa-magnifying-glass search-empty-icon" aria-hidden="true"></i>
                <h3>Looking for something on campus?</h3>
                <p>
                    Type in an item name, brand, model, textbook title, or repair service in the search box above to discover matching campus resources.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
