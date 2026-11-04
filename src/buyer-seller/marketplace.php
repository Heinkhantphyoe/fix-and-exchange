<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

// Read the search and filter values.
$search = trim($_GET['q'] ?? '');
$category = max(0, (int) ($_GET['category_id'] ?? 0));
$type = $_GET['listing_type'] ?? '';
$range = trim($_GET['price_range'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$errors = [];
$minimumPrice = 0;
$maximumPrice = 0;
$filterPrice = 0;
$searchText = '%' . $search . '%';

if (!in_array($type, ['Sale', 'Exchange', 'Giveaway'], true)) {
    $type = '';
}

if ($range !== '') {
    if (preg_match('/^(?:RM\s*)?(\d+(?:\.\d{1,2})?)\s*-\s*(?:RM\s*)?(\d+(?:\.\d{1,2})?)$/i', $range, $matches) && (float) $matches[1] <= (float) $matches[2]) {
        $minimumPrice = (float) $matches[1];
        $maximumPrice = (float) $matches[2];
        $filterPrice = 1;
    } else {
        $errors[] = 'Enter a price range such as 10 - 150.';
    }
}

// Empty filters are skipped by the OR conditions.
$sql = "SELECT l.*, c.category_name,
    (SELECT image_path FROM item_images WHERE listing_id = l.listing_id ORDER BY image_id LIMIT 1) AS image_path
    FROM item_listings l
    JOIN categories c ON c.category_id = l.category_id
    WHERE l.status = 'Available'
      AND (? = '' OR l.title LIKE ? OR l.description LIKE ?)
      AND (? = 0 OR l.category_id = ?)
      AND (? = '' OR l.listing_type = ?)
      AND (? = 0 OR l.price BETWEEN ? AND ?)";

// Only these fixed sort choices are allowed in the SQL.
$orders = [
    'newest' => 'l.created_at DESC, l.listing_id DESC',
    'price_low' => 'l.price IS NULL, l.price ASC, l.listing_id DESC',
    'price_high' => 'l.price IS NULL, l.price DESC, l.listing_id DESC'
];
$sql .= ' ORDER BY ' . ($orders[$sort] ?? $orders['newest']);

// Load the matching listings and categories.
$statement = mysqli_prepare(
    $conn,
    $sql
);
mysqli_stmt_bind_param($statement, 'sssiissidd', $search, $searchText, $searchText, $category, $category, $type, $type, $filterPrice, $minimumPrice, $maximumPrice);
mysqli_stmt_execute($statement);
$listings = mysqli_stmt_get_result($statement);
mysqli_stmt_close($statement);

$statement = mysqli_prepare(
    $conn,
    'SELECT * FROM categories ORDER BY category_name'
);
mysqli_stmt_execute($statement);
$categories = mysqli_stmt_get_result($statement);
mysqli_stmt_close($statement);

require __DIR__ . '/../../includes/header.php';
?>


<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <form method="get" class="bs-marketplace">
            <aside class="card bs-filters">
                <h2>Filters</h2>
                <div class="form-group">
                    <label class="form-label" for="category_id">Category</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $row): ?>
                            <option value="<?= (int) $row['category_id'] ?>" <?= $category === (int) $row['category_id'] ? 'selected' : '' ?>><?= escape_html($row['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="listing_type">Listing type</label>
                    <select class="form-control" id="listing_type" name="listing_type">
                        <option value="">All Types</option>
                        <?php foreach (['Sale', 'Exchange', 'Giveaway'] as $option): ?>
                            <option <?= $type === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price_range">Price range</label>
                    <input class="form-control" id="price_range" name="price_range" placeholder="e.g., RM 10 - RM 150" value="<?= escape_html($range) ?>">
                </div>
                <button class="btn btn-primary btn-block">Apply Filters</button>
                <a class="bs-reset" href="marketplace.php">Clear filters</a>
            </aside>
            <div class="bs-market-main">
                <h1>Campus Marketplace</h1>
                <div class="bs-search-row">
                    <label class="sr-only" for="q">Search for listings</label>
                    <input class="form-control" type="search" id="q" name="q" placeholder="Search for listings..." value="<?= escape_html($search) ?>">
                    <label for="sort">Sort by:</label>
                    <select class="form-control" id="sort" name="sort">
                        <?php foreach (['newest' => 'Date Posted', 'price_low' => 'Price: Low to High', 'price_high' => 'Price: High to Low'] as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary">Search</button>
                </div>
                <?php foreach ($errors as $error): ?>
                    <p class="alert alert-danger"><?= escape_html($error) ?></p>
                <?php endforeach; ?>
                <div class="bs-listing-grid">
                    <?php foreach ($listings as $listing): ?>
                        <article class="card bs-listing-card">
                            <?php show_listing_photo($listing['image_path'], $listing['title'], 'bs-card-photo'); ?>
                            <h2><?= escape_html($listing['title']) ?></h2>
                            <p>
                                <strong>Category:</strong> <?= escape_html($listing['category_name']) ?><br>
                                <strong>Listing Type:</strong> <?= escape_html($listing['listing_type']) ?><br>
                                <strong>Price:</strong> <?= display_price($listing) ?><br>
                                <strong>Status:</strong> <?= escape_html($listing['status']) ?>
                            </p>
                            <a class="btn btn-primary" href="item-details.php?listing_id=<?= (int) $listing['listing_id'] ?>">View Details</a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if (mysqli_num_rows($listings) === 0): ?>
                    <div class="empty-state">No listings found. Try different filters or <a href="create-listing.php">create a listing</a>.</div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
