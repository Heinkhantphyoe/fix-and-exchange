<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

// Update only the signed-in student's listings.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $listingId = (int) ($_POST['listing_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $error = '';
    mysqli_begin_transaction($conn);
    try {
        $statement = mysqli_prepare(
            $conn,
            'SELECT * FROM item_listings
            WHERE listing_id = ? AND seller_id = ? FOR UPDATE'
        );
        mysqli_stmt_bind_param($statement, 'ii', $listingId, $userId);
        mysqli_stmt_execute($statement);
        $listing = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
        mysqli_stmt_close($statement);

        if (!$listing) {
            $error = 'Listing not found, or it does not belong to you.';
        } elseif ($action === 'pending' && $listing['status'] === 'Available') {
            $newStatus = 'Pending';
        } elseif ($action === 'complete' && $listing['status'] === 'Pending') {
            $newStatus = 'Completed';
        } elseif ($action === 'remove' && in_array($listing['status'], ['Available', 'Pending'], true)) {
            $newStatus = 'Removed';
        } else {
            $error = 'This action is not available for the current listing status.';
        }
        if (!$error) {
            $statement = mysqli_prepare(
                $conn,
                'UPDATE item_listings SET status = ? WHERE listing_id = ? AND seller_id = ?'
            );
            mysqli_stmt_bind_param($statement, 'sii', $newStatus, $listingId, $userId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            if ($newStatus === 'Removed') {
                $statement = mysqli_prepare(
                    $conn,
                    "UPDATE exchange_offers SET status = 'Cancelled'
                    WHERE listing_id = ? AND status IN ('Pending', 'Accepted')"
                );
                mysqli_stmt_bind_param($statement, 'i', $listingId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            } elseif ($newStatus === 'Completed') {
                $statement = mysqli_prepare(
                    $conn,
                    "UPDATE exchange_offers SET status = 'Rejected'
                    WHERE listing_id = ? AND status = 'Pending'"
                );
                mysqli_stmt_bind_param($statement, 'i', $listingId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
            mysqli_commit($conn);
        }
    } catch (mysqli_sql_exception $e) {
        $error = 'The listing could not be updated. Please try again.';
    }
    if ($error) {
        mysqli_rollback($conn);
        redirect_message('my-listings.php', $error, 'danger');
    }
    redirect_message('my-listings.php', 'Listing marked ' . $newStatus . '.');
}

// Load listings for the selected status tab.
$status = $_GET['status'] ?? 'All';
$statuses = ['All', 'Available', 'Pending', 'Completed', 'Removed'];
if (!in_array($status, $statuses, true)) {
    $status = 'All';
}

$sql = "SELECT l.*,
    (SELECT image_path FROM item_images WHERE listing_id = l.listing_id ORDER BY image_id LIMIT 1) AS image_path,
    (SELECT COUNT(*) FROM exchange_offers WHERE listing_id = l.listing_id AND status = 'Accepted') AS accepted_count
    FROM item_listings l
    WHERE l.seller_id = ? AND (? = 'All' OR l.status = ?)
    ORDER BY l.listing_id DESC";
$statement = mysqli_prepare(
    $conn,
    $sql
);
mysqli_stmt_bind_param($statement, 'iss', $userId, $status, $status);
mysqli_stmt_execute($statement);
$listings = mysqli_stmt_get_result($statement);
mysqli_stmt_close($statement);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <div class="bs-heading-row">
            <h1>My Listings</h1>
            <a class="btn btn-primary" href="create-listing.php">Create New Listing</a>
        </div>
        <nav class="bs-tabs" aria-label="Listing status">
            <?php foreach ($statuses as $tab): ?>
                <a class="<?= $status === $tab ? 'bs-active' : '' ?>" href="?status=<?= $tab ?>"><?= $tab ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="table-wrap">
            <table class="table bs-listings-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Item Title</th>
                        <th>Listing Type</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $listing): ?>
                        <tr>
                            <td><?php show_listing_photo($listing['image_path'], $listing['title'], 'bs-table-photo'); ?></td>
                            <td><a href="item-details.php?listing_id=<?= (int) $listing['listing_id'] ?>"><?= escape_html($listing['title']) ?></a></td>
                            <td><?= escape_html($listing['listing_type']) ?></td>
                            <td><?= display_price($listing) ?></td>
                            <td><?= escape_html($listing['status']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($listing['status'] === 'Available' && !$listing['accepted_count']): ?>
                                        <a class="btn btn-sm" href="create-listing.php?listing_id=<?= (int) $listing['listing_id'] ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if (in_array($listing['status'], ['Available', 'Pending'], true)): ?>
                                        <form method="post" class="bs-inline-form" data-confirm="Remove this listing and cancel unfinished offers?">
                                            <input type="hidden" name="token" value="<?= form_token() ?>">
                                            <input type="hidden" name="listing_id" value="<?= (int) $listing['listing_id'] ?>">
                                            <button class="btn btn-sm btn-danger" name="action" value="remove">Remove</button>
                                        </form>
                                        <form method="post" class="bs-inline-form" <?= $listing['status'] === 'Pending' ? 'data-confirm="Mark this listing completed after the handover?"' : '' ?>>
                                            <input type="hidden" name="token" value="<?= form_token() ?>">
                                            <input type="hidden" name="listing_id" value="<?= (int) $listing['listing_id'] ?>">
                                            <button class="btn btn-sm btn-primary" name="action" value="<?= $listing['status'] === 'Available' ? 'pending' : 'complete' ?>"><?= $listing['status'] === 'Available' ? 'Mark Pending' : 'Mark Completed' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!mysqli_num_rows($listings)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No listings in this tab.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
