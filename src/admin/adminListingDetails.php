<?php
// Dedicated Admin Item Details Page
// Allows administrators to inspect, review, and moderate marketplace items within the Admin Console.

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_admin();
require __DIR__ . '/../../includes/admin-helpers.php';

$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    ?: filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    ?: (int) ($_POST['listing_id'] ?? 0);

if ($listingId <= 0) {
    redirect_message('adminListings.php', 'Invalid listing ID provided.', 'danger');
}

// Handle moderation actions (Remove / Restore)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $action = $_POST['action'] ?? '';

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
            redirect_message("adminListingDetails.php?listing_id=$listingId", "Listing #L-$listingId has been removed from the campus marketplace.", 'success');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            redirect_message("adminListingDetails.php?listing_id=$listingId", "Failed to remove listing: " . $e->getMessage(), 'danger');
        }
    } elseif ($action === 'restore_listing') {
        $stmt = mysqli_prepare($conn, "UPDATE item_listings SET status = 'Available' WHERE listing_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $listingId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        redirect_message("adminListingDetails.php?listing_id=$listingId", "Listing #L-$listingId has been restored to Available status.", 'success');
    }
}

// Fetch listing details joined with category and seller info
$stmt = mysqli_prepare(
    $conn,
    "SELECT l.*, c.category_name, u.user_id AS seller_id, u.full_name AS owner_name, u.tp_number, u.email AS owner_email,
            u.account_status AS owner_status, u.phone_number, u.intake_code, u.academic_program,
            (SELECT COUNT(*) FROM exchange_offers WHERE listing_id = l.listing_id) AS total_offers,
            (SELECT COUNT(*) FROM exchange_offers WHERE listing_id = l.listing_id AND status = 'Accepted') AS accepted_offers,
            (SELECT COUNT(*) FROM reported_issues WHERE listing_id = l.listing_id) AS total_reports,
            (SELECT COUNT(*) FROM reported_issues WHERE listing_id = l.listing_id AND status IN ('Pending', 'Investigating')) AS open_reports
     FROM item_listings l
     JOIN categories c ON c.category_id = l.category_id
     JOIN users u ON u.user_id = l.seller_id
     WHERE l.listing_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $listingId);
mysqli_stmt_execute($stmt);
$listing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$listing) {
    redirect_message('adminListings.php', "Listing #L-$listingId was not found.", 'danger');
}

// Fetch item photos
$imgStmt = mysqli_prepare($conn, "SELECT image_id, image_path FROM item_images WHERE listing_id = ? ORDER BY image_id ASC");
mysqli_stmt_bind_param($imgStmt, 'i', $listingId);
mysqli_stmt_execute($imgStmt);
$images = mysqli_fetch_all(mysqli_stmt_get_result($imgStmt), MYSQLI_ASSOC);
mysqli_stmt_close($imgStmt);

$listingTypes = [
    'Sale' => 'Direct Sale',
    'Exchange' => 'Item Exchange / Swap',
    'Giveaway' => 'Free Campus Giveaway',
];

render_admin_header('Listing Details: ' . $listing['title'], 'listings');
?>

<main class="dashboardMain">
    <!-- Top Action & Breadcrumb Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <p class="listingsPageLabel" style="margin: 0 0 4px; font-size: 13px; color: #115e39; font-weight: 600;">
                <i class="fa-solid fa-shield-halved"></i> Administrator &bull; Item Details
            </p>
            <h1 style="margin: 0; font-size: 26px; color: #123c2a;">
                <?= escape_html($listing['title']) ?>
            </h1>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="listingsButton" href="adminListings.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Listings
            </a>
            <a class="listingsButton" href="adminListings.php?listing=<?= (int) $listing['listing_id'] ?>#listingDetails">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i> Open in Queue
            </a>
        </div>
    </div>

    <!-- Main Detail Panel -->
    <section class="dashboardPanel adminDetailPanel" style="margin-top: 0;">
        <div class="adminDetailHeader">
            <div class="adminDetailTitle">
                <span class="summaryIcon listingIcon" aria-hidden="true">
                    <i class="fa-solid fa-box-archive"></i>
                </span>
                <h2 style="font-size: 22px;"><?= escape_html($listing['title']) ?></h2>
                <span class="listingBadge <?= escape_html(strtolower($listing['status'])) ?>">
                    <?= escape_html($listing['status']) ?>
                </span>
                <span class="listingBadge <?= escape_html(strtolower($listing['listing_type'])) ?>">
                    <?= escape_html($listingTypes[$listing['listing_type']] ?? $listing['listing_type']) ?>
                </span>
            </div>

            <div style="font-size: 13px; color: #496359;">
                Reference: <strong>#L-<?= (int) $listing['listing_id'] ?></strong>
            </div>
        </div>

        <div class="adminDetailBody" style="margin-top: 20px;">
            <!-- Left Column: Photos, Description & Facts -->
            <div>
                <!-- Photos Gallery -->
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 15px; color: #123c2a; margin: 0 0 10px;">Item Photos (<?= count($images) ?>)</h3>
                    <?php if (!empty($images)): ?>
                        <!-- Main Preview Image -->
                        <div style="margin-bottom: 12px; background: #f8fafc; border: 1px solid #d6e3da; border-radius: 8px; overflow: hidden; max-height: 400px; display: flex; align-items: center; justify-content: center;">
                            <img
                                id="adminMainPhoto"
                                src="/fix-and-exchange/<?= escape_html($images[0]['image_path']) ?>"
                                alt="<?= escape_html($listing['title']) ?>"
                                style="max-width: 100%; max-height: 400px; object-fit: contain;"
                            >
                        </div>

                        <!-- Thumbnails Grid -->
                        <?php if (count($images) > 1): ?>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <?php foreach ($images as $img): ?>
                                    <button
                                        type="button"
                                        onclick="document.getElementById('adminMainPhoto').src = '/fix-and-exchange/<?= escape_html($img['image_path']) ?>';"
                                        style="border: 2px solid #d6e3da; background: #fff; border-radius: 6px; padding: 2px; cursor: pointer; transition: border-color 0.15s ease;"
                                        onmouseover="this.style.borderColor = '#115e39';"
                                        onmouseout="this.style.borderColor = '#d6e3da';"
                                        title="Click to preview image"
                                    >
                                        <img
                                            src="/fix-and-exchange/<?= escape_html($img['image_path']) ?>"
                                            alt="Thumbnail"
                                            style="width: 72px; height: 56px; object-fit: cover; border-radius: 4px; display: block;"
                                        >
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="padding: 30px; background: #f8fafc; border: 1px dashed #d6e3da; border-radius: 8px; text-align: center; color: #64748b; font-size: 14px;">
                            <i class="fa-solid fa-image" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                            No photos were uploaded for this listing.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="adminDetailText" style="margin-bottom: 24px;">
                    <h3 style="font-size: 16px; margin: 0 0 8px; color: #123c2a;">Description</h3>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.6; color: #1e293b; white-space: pre-wrap; word-break: break-word;">
                        <?= escape_html($listing['description']) ?>
                    </div>
                </div>

                <!-- Core Metadata Facts -->
                <div>
                    <h3 style="font-size: 16px; margin: 0 0 10px; color: #123c2a;">Listing Specifications</h3>
                    <dl class="adminDetailFacts" style="margin: 0; background: #ffffff; border: 1px solid #d6e3da; border-radius: 8px; padding: 16px;">
                        <div>
                            <dt>Listing ID</dt>
                            <dd>#L-<?= (int) $listing['listing_id'] ?></dd>
                        </div>
                        <div>
                            <dt>Listing Type</dt>
                            <dd><?= escape_html($listingTypes[$listing['listing_type']] ?? $listing['listing_type']) ?></dd>
                        </div>
                        <div>
                            <dt>Price / Exchange</dt>
                            <dd>
                                <?php if ($listing['listing_type'] === 'Giveaway'): ?>
                                    <strong style="color: #16a34a;">Free Giveaway</strong>
                                <?php elseif ($listing['listing_type'] === 'Exchange'): ?>
                                    <span style="color: #d97706; font-weight: 600;">Exchange / Trade</span>
                                <?php else: ?>
                                    <strong style="color: #0c4a2c; font-size: 16px;">RM <?= number_format((float) $listing['price'], 2) ?></strong>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Category</dt>
                            <dd><?= escape_html($listing['category_name']) ?></dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>
                                <span class="listingBadge <?= escape_html(strtolower($listing['status'])) ?>">
                                    <?= escape_html($listing['status']) ?>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Date Posted</dt>
                            <dd><?= date('d M Y, g:i A', strtotime($listing['created_at'])) ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Right Column: Seller Profile, Moderation Controls & Reports -->
            <div class="adminDetailSidebar">
                <!-- Moderation Controls -->
                <div style="background: #ffffff; border: 1px solid #d6e3da; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 8px; font-size: 16px; color: #123c2a;">
                        <i class="fa-solid fa-gavel"></i> Moderation Controls
                    </h3>
                    <p style="font-size: 13px; color: #52645a; margin: 0 0 16px; line-height: 1.5;">
                        Take administrative action against inappropriate, prohibited, misleading, or resolved listings.
                    </p>

                    <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                        <input type="hidden" name="token" value="<?= escape_html(form_token()) ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['listing_id'] ?>">

                        <?php if ($listing['status'] !== 'Removed'): ?>
                            <button
                                class="listingsButton"
                                type="submit"
                                name="action"
                                value="remove_listing"
                                style="color: #b91c1c; border-color: #fca5a5; background: #fff5f5; padding: 10px 14px; font-weight: 600; justify-content: center;"
                                onclick="return confirm('Remove listing #L-<?= (int) $listing['listing_id'] ?> from the campus marketplace? All pending and accepted offers will be cancelled.');"
                            >
                                <i class="fa-solid fa-ban" aria-hidden="true"></i> Remove Listing (Takedown)
                            </button>
                        <?php else: ?>
                            <div style="padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 13px; color: #991b1b; margin-bottom: 6px;">
                                <i class="fa-solid fa-circle-exclamation"></i> This listing is currently <strong>Removed</strong>.
                            </div>
                            <button
                                class="listingsButton"
                                type="submit"
                                name="action"
                                value="restore_listing"
                                style="color: #ffffff; background: #115e39; border-color: #115e39; padding: 10px 14px; font-weight: 600; justify-content: center;"
                                onclick="return confirm('Restore listing #L-<?= (int) $listing['listing_id'] ?> to Available status in the marketplace?');"
                            >
                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore to Available
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Seller Profile Card -->
                <div style="background: #ffffff; border: 1px solid #d6e3da; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 12px; font-size: 16px; color: #123c2a;">
                        <i class="fa-solid fa-user-tag"></i> Seller Information
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13.5px;">
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Full Name</span>
                            <strong><?= escape_html($listing['owner_name']) ?></strong>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Student TP Number</span>
                            <span class="team-tp-badge" style="display: inline-flex; margin: 2px 0 0; padding: 2px 8px; font-size: 12px;">
                                <i class="fa-solid fa-id-card"></i> <?= escape_html($listing['tp_number']) ?>
                            </span>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Email</span>
                            <a href="mailto:<?= escape_html($listing['owner_email']) ?>" style="color: #115e39;">
                                <?= escape_html($listing['owner_email']) ?>
                            </a>
                        </div>
                        <?php if (!empty($listing['phone_number'])): ?>
                            <div>
                                <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Phone</span>
                                <span><?= escape_html($listing['phone_number']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($listing['academic_program'])): ?>
                            <div>
                                <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Programme &amp; Intake</span>
                                <span><?= escape_html($listing['academic_program']) ?> (<?= escape_html($listing['intake_code'] ?? '') ?>)</span>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Account Status</span>
                            <span class="listingBadge <?= escape_html(strtolower($listing['owner_status'])) ?>" style="font-size: 12px; padding: 2px 8px;">
                                <?= escape_html($listing['owner_status']) ?>
                            </span>
                        </div>
                    </div>

                    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px dashed #e2e8f0;">
                        <a class="listingsButton" href="adminUsers.php?search=<?= urlencode($listing['tp_number']) ?>" style="width: 100%; justify-content: center; box-sizing: border-box;">
                            <i class="fa-solid fa-user-shield"></i> Inspect Seller Account
                        </a>
                    </div>
                </div>

                <!-- Reports & Offers Summary -->
                <div style="background: #ffffff; border: 1px solid #d6e3da; border-radius: 12px; padding: 20px;">
                    <h3 style="margin: 0 0 12px; font-size: 16px; color: #123c2a;">
                        <i class="fa-solid fa-shield-virus"></i> Safety &amp; Activity
                    </h3>

                    <!-- Reports Count -->
                    <div style="margin-bottom: 14px;">
                        <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Incident Reports</span>
                        <?php if ((int) $listing['open_reports'] > 0): ?>
                            <p style="color: #b91c1c; font-weight: 700; margin: 4px 0 8px;">
                                <i class="fa-solid fa-triangle-exclamation"></i> <?= (int) $listing['open_reports'] ?> unresolved report(s)
                            </p>
                            <a class="listingsButton" href="adminReports.php?listing=<?= (int) $listing['listing_id'] ?>&amp;status=unresolved" style="color: #b91c1c; border-color: #fca5a5;">
                                <i class="fa-solid fa-flag"></i> Review Open Reports
                            </a>
                        <?php else: ?>
                            <p style="font-size: 13.5px; color: #166534; margin: 4px 0 0;">
                                <i class="fa-solid fa-circle-check"></i> No unresolved reports.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Marketplace Offers -->
                    <div style="padding-top: 12px; border-top: 1px solid #f1f5f9;">
                        <span style="color: #64748b; display: block; font-size: 12px; text-transform: uppercase;">Student Offers</span>
                        <p style="font-size: 13.5px; margin: 4px 0 0; color: #334155;">
                            <strong><?= (int) $listing['total_offers'] ?></strong> offer(s) submitted &bull;
                            <strong><?= (int) $listing['accepted_offers'] ?></strong> accepted
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php render_admin_footer(); ?>
