<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

$listingId = (int) ($_GET['listing_id'] ?? 0);
$listing = get_listing($listingId);
if ((int) $listing['seller_id'] === $userId) {
    http_response_code(403);
    exit('You cannot make an offer on your own listing.');
}

// Match the offer type to the listing.
$offerTypes = ['Sale' => 'Buy', 'Exchange' => 'Trade', 'Giveaway' => 'Claim Giveaway'];
$offerType = $offerTypes[$listing['listing_type']];
$details = '';
$errors = [];

// Check the form and save the offer.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $details = trim($_POST['offered_item_details'] ?? '');
    if (($offerType === 'Trade' && $details === '') || mb_strlen($details) > 5000) {
        $errors[] = 'For a trade, describe your offered item. Use 5,000 characters or fewer.';
    }
    if (!$errors) {
        // Check and save together so a second offer cannot slip through.
        mysqli_begin_transaction($conn);
        try {
            $statement = mysqli_prepare(
                $conn,
                'SELECT status, listing_type FROM item_listings
                WHERE listing_id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($statement, 'i', $listingId);
            mysqli_stmt_execute($statement);
            $saved = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            $statement = mysqli_prepare(
                $conn,
                "SELECT offer_id FROM exchange_offers
                WHERE listing_id = ? AND status = 'Accepted' LIMIT 1"
            );
            mysqli_stmt_bind_param($statement, 'i', $listingId);
            mysqli_stmt_execute($statement);
            $accepted = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            $statement = mysqli_prepare(
                $conn,
                "SELECT offer_id FROM exchange_offers
                WHERE listing_id = ? AND buyer_id = ? AND status IN ('Pending', 'Accepted') LIMIT 1"
            );
            mysqli_stmt_bind_param($statement, 'ii', $listingId, $userId);
            mysqli_stmt_execute($statement);
            $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            if (!$saved || $saved['status'] !== 'Available' || $saved['listing_type'] !== $listing['listing_type']) {
                $errors[] = 'This listing is no longer available for this offer.';
            } elseif ($accepted) {
                $errors[] = 'The seller has already accepted an offer for this listing.';
            } elseif ($duplicate) {
                $errors[] = 'You already have an unfinished offer for this listing.';
            } else {
                $statement = mysqli_prepare(
                    $conn,
                    "INSERT INTO exchange_offers (listing_id, buyer_id, offer_type, offered_item_details, status)
                    VALUES (?, ?, ?, ?, 'Pending')"
                );
                mysqli_stmt_bind_param($statement, 'iiss', $listingId, $userId, $offerType, $details);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);

                mysqli_commit($conn);
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Your offer could not be saved. Please try again.';
        }
        if ($errors) {
            mysqli_rollback($conn);
        } else {
            redirect_message('my-offers.php?tab=sent', 'Your offer has been sent.');
        }
    }
}

// Check whether this item still accepts offers.
$image = get_listing_images($listingId)[0]['image_path'] ?? null;
$statement = mysqli_prepare(
    $conn,
    "SELECT offer_id FROM exchange_offers
    WHERE listing_id = ? AND status = 'Accepted' LIMIT 1"
);
mysqli_stmt_bind_param($statement, 'i', $listingId);
mysqli_stmt_execute($statement);
$accepted = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

$available = $listing['status'] === 'Available' && !$accepted;

require __DIR__ . '/../../includes/header.php';
?>

<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <h1>Create Offer</h1>
        <div class="card bs-requested-item">
            <h2>Requested Item</h2>
            <div class="bs-offer-row">
                <?php show_listing_photo($image, $listing['title'], 'bs-offer-photo'); ?>
                <div>
                    <h3><?= escape_html($listing['title']) ?></h3>
                    <p>
                        <strong>Listing Type:</strong> <?= escape_html($listing['listing_type']) ?><br>
                        <strong>Seller:</strong> <?= escape_html($listing['seller_name']) ?><br>
                        <strong>Status:</strong> <?= escape_html($listing['status']) ?>
                    </p>
                </div>
            </div>
        </div>
        <?php foreach ($errors as $error): ?>
            <p class="alert alert-danger" role="alert"><?= escape_html($error) ?></p>
        <?php endforeach; ?>
        <?php if (!$available): ?>
            <p class="alert alert-warning">This item is no longer available for new offers.</p>
        <?php endif; ?>
        <form method="post" class="bs-offer-form">
            <input type="hidden" name="token" value="<?= form_token() ?>">
            <div class="form-group">
                <label class="form-label" for="offer_type">Offer Type</label>
                <input class="form-control bs-short-input" id="offer_type" value="<?= escape_html($offerType) ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label" for="offered_item_details"><?= $offerType === 'Trade' ? 'Offered Item Details' : 'Message to Seller (optional)' ?></label>
                <textarea class="form-control form-textarea" id="offered_item_details" name="offered_item_details" maxlength="5000" <?= $offerType === 'Trade' ? 'required' : '' ?>><?= escape_html($details) ?></textarea>
                <p class="form-hint">Contact the seller through Messages to discuss details.</p>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" <?= !$available ? 'disabled' : '' ?>>Submit Offer</button>
                <a class="btn" href="item-details.php?listing_id=<?= $listingId ?>">Cancel</a>
            </div>
        </form>
        <hr>
        <p>Sale listings use Buy; exchange listings use Trade; giveaways use Claim Giveaway.</p>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
