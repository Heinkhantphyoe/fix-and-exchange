<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

$listingId = (int) ($_GET['listing_id'] ?? 0);
$listing = get_listing($listingId);
if ((int) $listing['seller_id'] === $userId || $listing['status'] === 'Removed') {
    http_response_code(403);
    exit('This listing cannot be reported.');
}

$reason = '';
$error = '';
// Check the reason and save the report for admin review.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '' || mb_strlen($reason) > 5000) {
        $error = 'Enter a reason using 5,000 characters or fewer.';
    } else {
        // Check and save together so two tabs cannot create duplicate reports.
        mysqli_begin_transaction($conn);
        try {
            $statement = mysqli_prepare(
                $conn,
                'SELECT status FROM item_listings WHERE listing_id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($statement, 'i', $listingId);
            mysqli_stmt_execute($statement);
            $savedListing = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            if (!$savedListing || $savedListing['status'] === 'Removed') {
                $error = 'This listing is no longer available for reporting.';
            } else {
                $statement = mysqli_prepare(
                    $conn,
                    "SELECT report_id FROM reported_issues
                    WHERE listing_id = ? AND reporter_id = ? AND status IN ('Pending', 'Investigating') LIMIT 1"
                );
                mysqli_stmt_bind_param($statement, 'ii', $listingId, $userId);
                mysqli_stmt_execute($statement);
                $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
                mysqli_stmt_close($statement);

                if ($existing) {
                    $error = 'You already have an open report for this listing.';
                } else {
                    $statement = mysqli_prepare(
                        $conn,
                        "INSERT INTO reported_issues (reporter_id, listing_id, reason, status)
                        VALUES (?, ?, ?, 'Pending')"
                    );
                    mysqli_stmt_bind_param($statement, 'iis', $userId, $listingId, $reason);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);
                }
            }

            if (!$error) {
                mysqli_commit($conn);
            }
        } catch (mysqli_sql_exception $e) {
            $error = 'Your report could not be saved. Please try again.';
        }

        if ($error) {
            mysqli_rollback($conn);
        } else {
            redirect_message('item-details.php?listing_id=' . $listingId, 'Your report has been submitted for admin review.');
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <h1>Report Listing</h1>
        <div class="card bs-listing-form">
            <h2><?= escape_html($listing['title']) ?></h2>
            <?php if ($error): ?>
                <p class="alert alert-danger" role="alert"><?= escape_html($error) ?></p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= form_token() ?>">
                <div class="form-group">
                    <label class="form-label" for="reason">Reason for reporting</label>
                    <textarea class="form-control form-textarea" id="reason" name="reason" maxlength="5000" required><?= escape_html($reason) ?></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary">Submit Report</button>
                    <a class="btn" href="item-details.php?listing_id=<?= $listingId ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
