<?php
// Listings Management: Review, filter, and moderate campus marketplace items.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

// Handle POST actions for listing moderation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $action = $_POST['action'] ?? '';
    $listingId = (int) ($_POST['listing_id'] ?? 0);

    if ($listingId > 0) {
        if ($action === 'remove_listing') {
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, "UPDATE item_listings SET status = 'Removed' WHERE listing_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $listingId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $offerStmt = mysqli_prepare($conn, "UPDATE exchange_offers SET status = 'Cancelled' WHERE listing_id = ? AND status IN ('Pending', 'Accepted')");
                mysqli_stmt_bind_param($offerStmt, 'i', $listingId);
                mysqli_stmt_execute($offerStmt);
                mysqli_stmt_close($offerStmt);

                mysqli_commit($conn);
                redirect_message("adminListings.php?listing=$listingId#listingDetails", "Listing #L-$listingId has been removed from the marketplace.", 'success');
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                redirect_message("adminListings.php?listing=$listingId#listingDetails", "Failed to remove listing: " . $e->getMessage(), 'danger');
            }
        } elseif ($action === 'restore_listing') {
            $stmt = mysqli_prepare($conn, "UPDATE item_listings SET status = 'Available' WHERE listing_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $listingId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_message("adminListings.php?listing=$listingId#listingDetails", "Listing #L-$listingId has been restored to Available status.", 'success');
        }
    }
}

// Load categories
$categories = [];
$catQuery = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = mysqli_fetch_assoc($catQuery)) {
    $categories[(int) $row['category_id']] = $row['category_name'];
}

// Load all item listings with owner details and count of unresolved reports
$listings = [];
$listSql = "SELECT l.*, c.category_name, u.full_name AS owner_name, u.tp_number, u.email,
    (SELECT COUNT(*) FROM reported_issues WHERE listing_id = l.listing_id AND status IN ('Pending', 'Investigating')) AS open_report_count
    FROM item_listings l
    JOIN categories c ON c.category_id = l.category_id
    JOIN users u ON u.user_id = l.seller_id
    ORDER BY l.listing_id DESC";
$listQuery = mysqli_query($conn, $listSql);
while ($row = mysqli_fetch_assoc($listQuery)) {
    $listings[] = $row;
}

$listingTypes = [
    'Sale' => 'Sale',
    'Exchange' => 'Exchange',
    'Giveaway' => 'Free Giveaway',
];
$listingStatuses = ['Available', 'Pending', 'Completed', 'Removed'];

// Read filters
$search = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
if (!is_string($type) || !isset($listingTypes[$type])) {
    $type = '';
}
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if (!$categoryId || !isset($categories[$categoryId])) {
    $categoryId = 0;
}
$status = $_GET['status'] ?? '';
if (!in_array($status, $listingStatuses, true)) {
    $status = '';
}

$listingId = filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedListing = null;
$selectedImages = [];
$filteredListings = [];
$availableCount = 0;

foreach ($listings as $listing) {
    if ($listing['status'] === 'Available') {
        $availableCount++;
    }

    if ((int) $listing['listing_id'] === $listingId) {
        $selectedListing = $listing;
    }

    $reference = '#L-' . $listing['listing_id'];
    $searchText = $listing['title'] . ' ' . $reference . ' ' . $listing['owner_name'] . ' ' . $listing['description'];
    $matchesSearch = $search === '' || stripos($searchText, $search) !== false;
    $matchesType = $type === '' || $listing['listing_type'] === $type;
    $matchesCategory = $categoryId === 0 || (int) $listing['category_id'] === $categoryId;
    $matchesStatus = $status === '' || $listing['status'] === $status;

    if ($matchesSearch && $matchesType && $matchesCategory && $matchesStatus) {
        $filteredListings[] = $listing;
    }
}

// Fetch photos for selected listing
if ($selectedListing) {
    $imgStmt = mysqli_prepare($conn, "SELECT image_path FROM item_images WHERE listing_id = ? ORDER BY image_id ASC");
    mysqli_stmt_bind_param($imgStmt, 'i', $selectedListing['listing_id']);
    mysqli_stmt_execute($imgStmt);
    $selectedImages = mysqli_fetch_all(mysqli_stmt_get_result($imgStmt), MYSQLI_ASSOC);
    mysqli_stmt_close($imgStmt);
}

$hasFilters = $search !== '' || $type !== '' || $categoryId !== 0 || $status !== '';
$resultsUrl = 'adminListings.php?' . http_build_query([
    'q' => $search,
    'type' => $type,
    'category' => $categoryId,
    'status' => $status,
]);

render_admin_header('Listing Management', 'listings');
?>

<main class="dashboardMain">
    <div class="listingsPageHeading">
        <div>
            <p class="listingsPageLabel">Administrator</p>
            <h1>Item Listing Management</h1>
            <p>Review and moderate second-hand marketplace listings across campus.</p>
        </div>

        <div class="listingsTotals">
            <span class="summaryIcon" aria-hidden="true">
                <i class="fa-solid fa-box-archive"></i>
            </span>
            <div>
                <strong><?= $availableCount ?> available</strong>
                <small>Marketplace listings</small>
            </div>
        </div>
    </div>

    <!-- Filters form -->
    <form class="listingsFilters" action="adminListings.php" method="get">
        <div class="listingsSearch">
            <label for="listingSearch">Search listings</label>
            <input id="listingSearch" type="search" name="q" value="<?= escape_html($search) ?>" placeholder="Item, #L-ID, owner or description">
        </div>

        <div class="listingsFilter">
            <label for="listingType">Listing type</label>
            <select id="listingType" name="type">
                <option value="">All types</option>
                <?php foreach ($listingTypes as $val => $lbl): ?>
                    <option value="<?= escape_html($val) ?>" <?= $type === $val ? 'selected' : '' ?>><?= escape_html($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listingsFilter">
            <label for="listingCategory">Category</label>
            <select id="listingCategory" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $id => $categoryName): ?>
                    <option value="<?= (int) $id ?>" <?= $categoryId === (int) $id ? 'selected' : '' ?>><?= escape_html($categoryName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listingsFilter">
            <label for="listingStatus">Status</label>
            <select id="listingStatus" name="status">
                <option value="">All statuses</option>
                <?php foreach ($listingStatuses as $opt): ?>
                    <option value="<?= escape_html($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= escape_html($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="listingsButton listingsApplyButton" type="submit">Apply filters</button>
        <a class="listingsButton" href="adminListings.php">Clear filters</a>
    </form>

    <!-- Detailed listing review card -->
    <?php if ($listingId): ?>
        <section class="dashboardPanel adminDetailPanel" id="listingDetails">
            <div class="adminDetailHeader">
                <div class="adminDetailTitle">
                    <span class="summaryIcon listingIcon" aria-hidden="true">
                        <i class="fa-solid fa-box-archive"></i>
                    </span>

                    <?php if ($selectedListing): ?>
                        <h2><?= escape_html($selectedListing['title']) ?></h2>
                        <span class="listingBadge <?= escape_html(strtolower($selectedListing['status'])) ?>">
                            <?= escape_html($selectedListing['status']) ?>
                        </span>
                    <?php else: ?>
                        <h2>Listing unavailable</h2>
                    <?php endif; ?>
                </div>

                <a class="listingsButton" href="<?= escape_html($resultsUrl) ?>#listingResults">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to results
                </a>
            </div>

            <?php if ($selectedListing): ?>
                <div class="adminDetailBody">
                    <div>
                        <!-- Photos preview -->
                        <?php if ($selectedImages): ?>
                            <div style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
                                <?php foreach ($selectedImages as $img): ?>
                                    <img src="/fix-and-exchange/<?= escape_html($img['image_path']) ?>" alt="Listing image" style="width: 110px; height: 90px; object-fit: cover; border-radius: 6px; border: 1px solid #d6e3da;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="adminDetailText">
                            <h3>Description</h3>
                            <p style="white-space: pre-line;"><?= escape_html($selectedListing['description']) ?></p>
                        </div>

                        <dl class="adminDetailFacts">
                            <div>
                                <dt>Listing ID</dt>
                                <dd>#L-<?= (int) $selectedListing['listing_id'] ?></dd>
                            </div>
                            <div>
                                <dt>Owner</dt>
                                <dd><?= escape_html($selectedListing['owner_name']) ?> (<?= escape_html($selectedListing['tp_number']) ?>)</dd>
                            </div>
                            <div>
                                <dt>Category</dt>
                                <dd><?= escape_html($categories[$selectedListing['category_id']] ?? 'Uncategorised') ?></dd>
                            </div>
                            <div>
                                <dt>Type</dt>
                                <dd><?= escape_html($listingTypes[$selectedListing['listing_type']] ?? $selectedListing['listing_type']) ?></dd>
                            </div>
                            <div>
                                <dt>Posted</dt>
                                <dd><?= date('d M Y, g:i a', strtotime($selectedListing['created_at'])) ?></dd>
                            </div>
                            <div>
                                <dt>Price / Cost</dt>
                                <dd>
                                    <?php if ($selectedListing['listing_type'] === 'Giveaway'): ?>
                                        <strong style="color: #16a34a;">Free Giveaway</strong>
                                    <?php elseif ($selectedListing['listing_type'] === 'Exchange'): ?>
                                        <em>Exchange / Trade</em>
                                    <?php else: ?>
                                        <strong>RM <?= number_format((float) $selectedListing['price'], 2) ?></strong>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="adminDetailSidebar">
                        <h3>Moderation Actions</h3>
                        <p style="font-size: 13px; color: #52645a; margin-bottom: 12px;">
                            Remove listings that violate campus guidelines, contain false info, or duplicate posts.
                        </p>

                        <form method="post" style="display: flex; flex-direction: column; gap: 8px;">
                            <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                            <input type="hidden" name="listing_id" value="<?= (int) $selectedListing['listing_id'] ?>">

                            <?php if ($selectedListing['status'] !== 'Removed'): ?>
                                <button class="listingsButton" type="submit" name="action" value="remove_listing" style="color: #b91c1c;" onclick="return confirm('Remove this listing from the marketplace? Active offers will be cancelled.');">
                                    <i class="fa-solid fa-trash-can"></i> Remove listing
                                </button>
                            <?php else: ?>
                                <button class="listingsButton" type="submit" name="action" value="restore_listing" style="background: #1c7049; color: #fff;" onclick="return confirm('Restore this listing to Available status?');">
                                    <i class="fa-solid fa-rotate-left"></i> Restore listing
                                </button>
                            <?php endif; ?>
                        </form>

                        <div class="adminDetailSidebarSection" style="margin-top: 20px;">
                            <h3>Marketplace Public View</h3>
                            <a class="listingsButton" href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $selectedListing['listing_id'] ?>" target="_blank">
                                <i class="fa-solid fa-store" aria-hidden="true"></i> View item details
                            </a>
                        </div>

                        <div class="adminDetailSidebarSection" style="margin-top: 16px;">
                            <h3>Open Reports</h3>
                            <?php if ((int) $selectedListing['open_report_count'] > 0): ?>
                                <p style="color: #b91c1c; font-weight: 600;"><?= (int) $selectedListing['open_report_count'] ?> unresolved report(s)</p>
                                <a class="listingsButton" href="adminReports.php?listing=<?= (int) $selectedListing['listing_id'] ?>&amp;status=unresolved">
                                    <i class="fa-solid fa-flag"></i> Review open reports
                                </a>
                            <?php else: ?>
                                <p style="font-size: 13px; color: #52645a;">No active reports for this listing.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="adminDetailEmpty">This listing could not be found.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Table of listings -->
    <section class="listingsResults" id="listingResults">
        <div class="listingsResultsHeading">
            <h2>Listings</h2>
            <span><?= count($filteredListings) ?> of <?= count($listings) ?> shown</span>
        </div>

        <?php if (empty($filteredListings)): ?>
            <p class="listingsEmpty"><?= $hasFilters ? 'No listings match your filters.' : 'No listings available yet.' ?></p>
        <?php else: ?>
            <div class="listingsTableContainer">
                <table class="listingsTable">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col">Owner</th>
                            <th scope="col">Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Posted</th>
                            <th scope="col">Open reports</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredListings as $listing): ?>
                            <tr>
                                <th scope="row">
                                    <div class="listingItem">
                                        <span class="summaryIcon listingIcon" aria-hidden="true">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </span>
                                        <div>
                                            <?= escape_html($listing['title']) ?>
                                            <small class="listingReference">
                                                #L-<?= (int) $listing['listing_id'] ?> &middot;
                                                <?= escape_html($listing['category_name']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </th>
                                <td>
                                    <span class="listingsMobileLabel">Owner</span>
                                    <?= escape_html($listing['owner_name']) ?>
                                </td>
                                <td>
                                    <span class="listingsMobileLabel">Type</span>
                                    <span class="listingBadge <?= escape_html(strtolower($listing['listing_type'])) ?>">
                                        <?= escape_html($listingTypes[$listing['listing_type']] ?? $listing['listing_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="listingsMobileLabel">Status</span>
                                    <span class="listingBadge <?= escape_html(strtolower($listing['status'])) ?>">
                                        <?= escape_html($listing['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="listingsMobileLabel">Posted</span>
                                    <?= date('d M', strtotime($listing['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="listingsMobileLabel">Open reports</span>
                                    <?php if ((int) $listing['open_report_count'] > 0): ?>
                                        <a class="openReportsLink" href="adminReports.php?listing=<?= (int) $listing['listing_id'] ?>&amp;status=unresolved">
                                            <?= (int) $listing['open_report_count'] ?>
                                        </a>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="adminDetailActions">
                                        <a class="listingsButton" href="<?= escape_html($resultsUrl . '&listing=' . (int) $listing['listing_id']) ?>#listingDetails">
                                            Review
                                        </a>
                                        <a class="listingsButton" href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $listing['listing_id'] ?>" target="_blank">
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php render_admin_footer(); ?>
