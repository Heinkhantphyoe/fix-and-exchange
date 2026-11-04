<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

$tab = ($_GET['tab'] ?? '') === 'sent' ? 'sent' : 'incoming';
// Accept, reject, or cancel an offer.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $offerId = (int) ($_POST['offer_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $error = '';
    mysqli_begin_transaction($conn);
    try {
        $statement = mysqli_prepare(
            $conn,
            'SELECT * FROM exchange_offers WHERE offer_id = ?'
        );
        mysqli_stmt_bind_param($statement, 'i', $offerId);
        mysqli_stmt_execute($statement);
        $offer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
        mysqli_stmt_close($statement);

        if (!$offer) {
            $error = 'Offer not found.';
        } else {
            // Lock the listing first, then read the latest offer status.
            $listingId = (int) $offer['listing_id'];
            $statement = mysqli_prepare(
                $conn,
                'SELECT * FROM item_listings WHERE listing_id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($statement, 'i', $listingId);
            mysqli_stmt_execute($statement);
            $listing = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            $statement = mysqli_prepare(
                $conn,
                'SELECT * FROM exchange_offers WHERE offer_id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($statement, 'i', $offerId);
            mysqli_stmt_execute($statement);
            $offer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            if ($offer['status'] !== 'Pending') {
                $error = 'This offer has already been handled.';
            } elseif ($action === 'cancel' && (int) $offer['buyer_id'] === $userId) {
                $newStatus = 'Cancelled';
            } elseif (($action === 'accept' || $action === 'reject') && (int) $listing['seller_id'] === $userId) {
                $newStatus = $action === 'accept' ? 'Accepted' : 'Rejected';
                if ($action === 'accept') {
                    $statement = mysqli_prepare(
                        $conn,
                        "SELECT offer_id FROM exchange_offers
                        WHERE listing_id = ? AND status = 'Accepted' LIMIT 1"
                    );
                    mysqli_stmt_bind_param($statement, 'i', $listingId);
                    mysqli_stmt_execute($statement);
                    $accepted = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
                    mysqli_stmt_close($statement);

                    if (!in_array($listing['status'], ['Available', 'Pending'], true)) {
                        $error = 'This listing is no longer available.';
                    } elseif ($accepted) {
                        $error = 'You have already accepted another offer.';
                    } else {
                        $statement = mysqli_prepare(
                            $conn,
                            "UPDATE exchange_offers SET status = 'Rejected'
                            WHERE listing_id = ? AND offer_id <> ? AND status = 'Pending'"
                        );
                        mysqli_stmt_bind_param($statement, 'ii', $listingId, $offerId);
                        mysqli_stmt_execute($statement);
                        mysqli_stmt_close($statement);
                    }
                }
            } else {
                $error = 'You cannot change this offer.';
            }
            if (!$error) {
                $statement = mysqli_prepare(
                    $conn,
                    'UPDATE exchange_offers SET status = ? WHERE offer_id = ?'
                );
                mysqli_stmt_bind_param($statement, 'si', $newStatus, $offerId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
        }
        if (!$error) {
            mysqli_commit($conn);
        }
    } catch (mysqli_sql_exception $e) {
        $error = 'The offer could not be updated. Please try again.';
    }
    if ($error) {
        mysqli_rollback($conn);
        redirect_message('my-offers.php?tab=' . $tab, $error, 'danger');
    }
    redirect_message('my-offers.php?tab=' . $tab, 'Offer ' . strtolower($newStatus) . '.');
}

// Load this student's incoming or sent offers.
$offerId = (int) ($_GET['offer_id'] ?? 0);
$sql = 'SELECT o.*, l.title, seller.full_name AS seller_name, buyer.full_name AS buyer_name,
    (SELECT image_path FROM item_images WHERE listing_id = l.listing_id ORDER BY image_id LIMIT 1) AS image_path
    FROM exchange_offers o
    JOIN item_listings l ON l.listing_id = o.listing_id
    JOIN users seller ON seller.user_id = l.seller_id
    JOIN users buyer ON buyer.user_id = o.buyer_id';
if ($tab === 'sent') {
    $sql .= ' WHERE o.buyer_id = ?';
} else {
    $sql .= ' WHERE l.seller_id = ?';
}

$statement = mysqli_prepare(
    $conn,
    $sql . ' ORDER BY o.offer_id DESC'
);
mysqli_stmt_bind_param($statement, 'i', $userId);
mysqli_stmt_execute($statement);
$offers = mysqli_stmt_get_result($statement);
mysqli_stmt_close($statement);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <h1>My Offers</h1>
        <nav class="bs-tabs" aria-label="Offers">
            <a class="<?= $tab === 'incoming' ? 'bs-active' : '' ?>" href="?tab=incoming">Incoming Offers</a>
            <a class="<?= $tab === 'sent' ? 'bs-active' : '' ?>" href="?tab=sent">Sent Offers</a>
        </nav>
        <?php foreach ($offers as $offer): ?>
            <article class="card bs-offer-row" id="offer-<?= (int) $offer['offer_id'] ?>">
                <?php show_listing_photo($offer['image_path'], $offer['title'], 'bs-offer-photo'); ?>
                <div class="bs-offer-content">
                    <h2><?= escape_html($offer['offer_type']) ?> request for <?= escape_html($offer['title']) ?></h2>
                    <p>
                        <strong>Offer Type:</strong> <?= escape_html($offer['offer_type']) ?><br>
                        <strong><?= $tab === 'sent' ? 'To' : 'From' ?>:</strong> <?= escape_html($tab === 'sent' ? $offer['seller_name'] : $offer['buyer_name']) ?><br>
                        <strong>Status:</strong> <?= escape_html($offer['status']) ?>
                    </p>
                    <?php if ($offerId === (int) $offer['offer_id']): ?>
                        <div class="bs-offer-detail">
                            <p><strong>Offered Item Details / Message:</strong></p>
                            <p class="bs-text"><?= escape_html($offer['offered_item_details'] ?: 'No additional details.') ?></p>
                            <p><a href="item-details.php?listing_id=<?= (int) $offer['listing_id'] ?>">View listing</a></p>
                        </div>
                    <?php endif; ?>
                    <div class="btn-group">
                        <a class="btn btn-sm" href="?tab=<?= $tab ?>&amp;offer_id=<?= (int) $offer['offer_id'] ?>#offer-<?= (int) $offer['offer_id'] ?>">View</a>
                        <?php if ($offer['status'] === 'Pending'): ?>
                            <form method="post" class="bs-inline-form">
                                <input type="hidden" name="token" value="<?= form_token() ?>">
                                <input type="hidden" name="offer_id" value="<?= (int) $offer['offer_id'] ?>">
                                <?php if ($tab === 'sent'): ?>
                                    <button class="btn btn-sm" name="action" value="cancel">Cancel Offer</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-primary" name="action" value="accept">Accept</button>
                                    <button class="btn btn-sm" name="action" value="reject">Reject</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                        <?php if ($offer['status'] === 'Accepted'): ?>
                            <a class="btn btn-sm btn-primary" href="../messages/index.php?listing_id=<?= (int) $offer['listing_id'] ?><?= $tab === 'incoming' ? '&amp;buyer_id=' . (int) $offer['buyer_id'] : '' ?>">Message Student</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!mysqli_num_rows($offers)): ?>
            <div class="empty-state">No <?= $tab ?> offers yet.</div>
        <?php endif; ?>
        <p class="form-hint"><?= $tab === 'sent' ? 'View your requests and cancel pending offers.' : 'Accept one offer, arrange the handover through Messages, then mark the listing Pending and Completed in My Listings.' ?></p>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
