<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

// Load the item and check who can view it.
$listingId = (int) ($_GET['listing_id'] ?? 0);
$listing = get_listing($listingId);
$owner = (int) $listing['seller_id'] === $userId;
$statement = mysqli_prepare(
    $conn,
    "SELECT buyer_id FROM exchange_offers
    WHERE listing_id = ? AND status = 'Accepted' LIMIT 1"
);
mysqli_stmt_bind_param($statement, 'i', $listingId);
mysqli_stmt_execute($statement);
$accepted = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

if ($listing['status'] === 'Removed' && !$owner) {
    $statement = mysqli_prepare(
        $conn,
        'SELECT offer_id FROM exchange_offers
        WHERE listing_id = ? AND buyer_id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'ii', $listingId, $userId);
    mysqli_stmt_execute($statement);
    $hasOffer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $conn,
        "SELECT conversation_id FROM conversation
        WHERE listing_id = ? AND context_type = 'Listing' AND (seller_id = ? OR buyer_id = ?) LIMIT 1"
    );
    mysqli_stmt_bind_param($statement, 'iii', $listingId, $userId, $userId);
    mysqli_stmt_execute($statement);
    $hasConversation = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if (!$hasOffer && !$hasConversation) {
        http_response_code(404);
        exit('Listing not found.');
    }
}

$images = get_listing_images($listingId);

require __DIR__ . '/../../includes/header.php';
?>
<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <p><a href="marketplace.php">Marketplace</a> &gt; Item Details</p>
        <h1><?= escape_html($listing['title']) ?></h1>
        <div class="bs-details-grid">
            <div>
                <?php show_listing_photo($images[0]['image_path'] ?? null, $listing['title'], 'bs-main-photo'); ?>
                <div class="bs-photo-previews">
                    <?php foreach ($images as $image): ?>
                        <button type="button" class="bs-thumbnail-button" data-listing-photo="/fix-and-exchange/<?= escape_html($image['image_path']) ?>" aria-label="View item photo"><?php show_listing_photo($image['image_path'], 'Item thumbnail', 'bs-thumbnail'); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="bs-details-info">
                <h2><?= escape_html($listing['title']) ?></h2>
                <p><strong>Price:</strong> <?= display_price($listing) ?></p>
                <p><strong>Category:</strong> <?= escape_html($listing['category_name']) ?></p>
                <p><strong>Listing Type:</strong> <?= escape_html($listing['listing_type']) ?></p>
                <p><strong>Status:</strong> <?= escape_html($listing['status']) ?></p>
                <p><strong>Seller:</strong> <?= escape_html($listing['seller_name']) ?></p>
                <div class="btn-group">
                    <?php if ($owner): ?>
                        <a class="btn btn-primary" href="my-listings.php">Manage Listing</a>
                    <?php elseif ($listing['status'] !== 'Removed'): ?>
                        <?php if ($listing['status'] === 'Available' && !$accepted): ?>
                            <a class="btn btn-primary" href="create-offer.php?listing_id=<?= $listingId ?>">Create Offer</a>
                        <?php endif; ?>
                        <a class="btn btn-outline-primary" href="../messages/index.php?listing_id=<?= $listingId ?>">Message Seller</a>
                    <?php endif; ?>
                </div>
                <?php if (!$owner && $listing['status'] !== 'Removed'): ?>
                    <p class="bs-report-link"><a href="report-listing.php?listing_id=<?= $listingId ?>">Report Listing</a></p>
                <?php endif; ?>
            </div>
        </div>
        <hr>
        <h2>Description</h2>
        <p class="bs-text"><?= escape_html($listing['description']) ?></p>
        <p class="bs-back"><a href="marketplace.php">&lt; Back to Marketplace</a></p>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
