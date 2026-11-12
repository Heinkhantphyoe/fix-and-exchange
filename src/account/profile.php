<?php
// Student Profile: View campus credentials, eco-points, volunteer status, and active items.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_login();
require_once __DIR__ . '/../../includes/sustainability-helpers.php';

$currentUser = current_user();
$currentUserId = (int) $currentUser['user_id'];

// Determine target profile
$profileUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: $currentUserId;
$isSelf = ($profileUserId === $currentUserId);

// Fetch user data
$userStmt = mysqli_prepare(
    $conn,
    "SELECT user_id, full_name, email, role, account_status, total_points, tp_number, phone_number, intake_code, academic_program, created_at
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($userStmt, 'i', $profileUserId);
mysqli_stmt_execute($userStmt);
$profileUser = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);

if (!$profileUser) {
    http_response_code(404);
    exit('Student profile not found.');
}

// Fetch volunteer profile
$volStmt = mysqli_prepare(
    $conn,
    "SELECT volunteer_id, skills_description, approval_status, approved_at
     FROM volunteer_profiles
     WHERE user_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($volStmt, 'i', $profileUserId);
mysqli_stmt_execute($volStmt);
$volunteerProfile = mysqli_fetch_assoc(mysqli_stmt_get_result($volStmt));
mysqli_stmt_close($volStmt);

// Calculate eco tier
$totalPoints = (int) $profileUser['total_points'];
$ecoTier = calculate_eco_tier($totalPoints);

// Fetch metrics
$metricsStmt = mysqli_prepare(
    $conn,
    "SELECT
        (SELECT COUNT(*) FROM item_listings WHERE seller_id = ?) AS total_listings,
        (SELECT COUNT(*) FROM item_listings WHERE seller_id = ? AND status = 'Available') AS active_listings,
        (SELECT COUNT(*) FROM repair_requests WHERE requester_id = ?) AS total_repairs,
        (SELECT COUNT(*) FROM repair_assignments a JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id WHERE v.user_id = ? AND a.progress_status = 'Completed') AS completed_repairs"
);
mysqli_stmt_bind_param($metricsStmt, 'iiii', $profileUserId, $profileUserId, $profileUserId, $profileUserId);
mysqli_stmt_execute($metricsStmt);
$metrics = mysqli_fetch_assoc(mysqli_stmt_get_result($metricsStmt)) ?: [
    'total_listings' => 0,
    'active_listings' => 0,
    'total_repairs' => 0,
    'completed_repairs' => 0,
];
mysqli_stmt_close($metricsStmt);

// Fetch available listings posted by this user
$itemsStmt = mysqli_prepare(
    $conn,
    "SELECT l.*, c.category_name,
            (SELECT image_path FROM item_images WHERE listing_id = l.listing_id ORDER BY image_id ASC LIMIT 1) AS image_path
     FROM item_listings l
     JOIN categories c ON c.category_id = l.category_id
     WHERE l.seller_id = ? AND l.status = 'Available'
     ORDER BY l.listing_id DESC
     LIMIT 4"
);
mysqli_stmt_bind_param($itemsStmt, 'i', $profileUserId);
mysqli_stmt_execute($itemsStmt);
$availableItems = mysqli_fetch_all(mysqli_stmt_get_result($itemsStmt), MYSQLI_ASSOC);
mysqli_stmt_close($itemsStmt);

require __DIR__ . '/../../includes/header.php';
?>

<section class="section account-dashboard">
    <div class="container">
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= escape_html($flash['type']) ?>" role="status">
                <?= escape_html($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header Banner -->
        <div class="dashboard-welcome profile-banner">
            <div class="profile-avatar-wrap">
                <div class="profile-avatar">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="dashboard-eyebrow">
                        <?= strtoupper(escape_html($profileUser['role'])) ?> PROFILE
                        <?php if ($isSelf): ?> &bull; (YOU)<?php endif; ?>
                    </p>
                    <h1 class="profile-title"><?= escape_html($profileUser['full_name']) ?></h1>
                    <p class="profile-subtitle">
                        TP Number: <strong><?= escape_html($profileUser['tp_number']) ?></strong> &bull;
                        Member since <?= date('F Y', strtotime($profileUser['created_at'])) ?>
                    </p>
                </div>
            </div>

            <?php if ($isSelf): ?>
                <div class="profile-actions">
                    <a class="btn btn-outline-primary profile-btn-ghost" href="edit-profile.php">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit Profile
                    </a>
                    <a class="btn btn-primary profile-btn-solid" href="home.php">
                        <i class="fa-solid fa-house" aria-hidden="true"></i> My Dashboard
                    </a>
                </div>
            <?php else: ?>
                <a class="btn btn-outline-primary profile-btn-ghost" href="/fix-and-exchange/src/buyer-seller/marketplace.php">
                    <i class="fa-solid fa-store" aria-hidden="true"></i> Marketplace
                </a>
            <?php endif; ?>
        </div>

        <!-- Stat Grid -->
        <div class="dashboard-stat-grid account-stat-grid">
            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon points-icon">
                    <i class="fa-solid <?= escape_html($ecoTier['badge']) ?>" aria-hidden="true"></i>
                </span>
                <div>
                    <strong><?= $totalPoints ?> pts</strong>
                    <span><?= escape_html($ecoTier['name']) ?> (L<?= (int) $ecoTier['level'] ?>)</span>
                </div>
            </div>

            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon listing-icon">
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                </span>
                <div>
                    <strong><?= (int) $metrics['active_listings'] ?></strong>
                    <span>Available Items Posted</span>
                </div>
            </div>

            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon repair-icon">
                    <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                </span>
                <div>
                    <strong><?= (int) $metrics['total_repairs'] ?></strong>
                    <span>Repairs Requested</span>
                </div>
            </div>

            <div class="dashboard-stat-card">
                <span class="dashboard-stat-icon offer-icon">
                    <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
                </span>
                <div>
                    <strong><?= (int) $metrics['completed_repairs'] ?></strong>
                    <span>Volunteer Fixes Done</span>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="profile-main-grid">
            <!-- Academic & Account Info -->
            <div class="card">
                <div class="card-header panel-header-flex">
                    <h2 class="panel-title">
                        <i class="fa-solid fa-id-card" aria-hidden="true"></i> Campus Details
                    </h2>
                    <?php if ($isSelf): ?>
                        <a href="edit-profile.php" class="tier-link">Edit &rarr;</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="profile-data-list">
                        <div class="profile-data-row">
                            <span class="profile-data-label">TP Number</span>
                            <span class="profile-data-value"><?= escape_html($profileUser['tp_number']) ?></span>
                        </div>
                        <div class="profile-data-row">
                            <span class="profile-data-label">Intake Code</span>
                            <span class="profile-data-value"><?= escape_html($profileUser['intake_code'] ?: 'Not specified') ?></span>
                        </div>
                        <div class="profile-data-row">
                            <span class="profile-data-label">Academic Programme</span>
                            <span class="profile-data-value"><?= escape_html($profileUser['academic_program'] ?: 'Not specified') ?></span>
                        </div>
                        <?php if ($isSelf): ?>
                            <div class="profile-data-row">
                                <span class="profile-data-label">Email Address</span>
                                <span class="profile-data-value"><?= escape_html($profileUser['email']) ?></span>
                            </div>
                            <div class="profile-data-row">
                                <span class="profile-data-label">Phone Number</span>
                                <span class="profile-data-value"><?= escape_html($profileUser['phone_number'] ?: 'Not specified') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Volunteer & Sustainability Standing -->
            <div class="card">
                <div class="card-header">
                    <h2 class="panel-title">
                        <i class="fa-solid fa-seedling" aria-hidden="true"></i> Eco &amp; Volunteer Status
                    </h2>
                </div>
                <div class="card-body">
                    <div>
                        <span class="volunteer-skills-title">Volunteer Status:</span>
                        <?php if ($volunteerProfile && $volunteerProfile['approval_status'] === 'Approved'): ?>
                            <span class="badge badge-success">
                                <i class="fa-solid fa-check-circle"></i> Certified Repair Volunteer
                            </span>
                        <?php elseif ($volunteerProfile && $volunteerProfile['approval_status'] === 'Pending'): ?>
                            <span class="badge badge-warning">
                                <i class="fa-solid fa-hourglass-half"></i> Volunteer Application Pending
                            </span>
                        <?php else: ?>
                            <span class="badge">
                                Not an Active Volunteer
                            </span>
                            <?php if ($isSelf): ?>
                                <a href="../repair-volunteer/apply.php" class="volunteer-cta-link">Apply now &rarr;</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($volunteerProfile && !empty($volunteerProfile['skills_description'])): ?>
                        <div class="volunteer-status-box">
                            <h3 class="volunteer-skills-title">Specialized Skills:</h3>
                            <p class="volunteer-skills-text"><?= nl2br(escape_html($volunteerProfile['skills_description'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions-bordered panel-header-flex">
                        <span class="text-muted text-small">Eco Recognition Tier:</span>
                        <a href="../sustainability/points.php" class="tier-link">
                            <?= escape_html($ecoTier['name']) ?> &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Items from this Student -->
        <section class="dashboard-panel profile-items-section">
            <div class="dashboard-panel-heading panel-header-flex">
                <h2 class="panel-title">
                    <?= $isSelf ? 'My Available Listings' : 'Items Available from ' . escape_html($profileUser['full_name']) ?>
                </h2>
                <a href="/fix-and-exchange/src/buyer-seller/marketplace.php" class="tier-link">
                    Browse All Marketplace Items &rarr;
                </a>
            </div>

            <?php if (!$availableItems): ?>
                <p class="dashboard-empty">
                    <?= $isSelf ? 'You do not have any active marketplace items right now.' : 'This student does not have any active listings right now.' ?>
                </p>
            <?php else: ?>
                <div class="profile-items-grid">
                    <?php foreach ($availableItems as $item): ?>
                        <article class="item-mini-card">
                            <?php if ($item['image_path']): ?>
                                <img class="item-mini-img" src="/fix-and-exchange/<?= escape_html($item['image_path']) ?>" alt="<?= escape_html($item['title']) ?>">
                            <?php else: ?>
                                <div class="item-mini-placeholder">
                                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                                </div>
                            <?php endif; ?>
                            <div class="item-mini-body">
                                <div>
                                    <span class="badge <?= $item['listing_type'] === 'Giveaway' ? 'badge-success' : 'badge-primary' ?>">
                                        <?= escape_html($item['listing_type']) ?>
                                    </span>
                                </div>
                                <h3 class="item-mini-title">
                                    <a href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $item['listing_id'] ?>">
                                        <?= escape_html($item['title']) ?>
                                    </a>
                                </h3>
                                <p class="item-mini-cat">
                                    <?= escape_html($item['category_name']) ?>
                                </p>
                                <div class="item-mini-footer">
                                    <strong class="item-mini-price">
                                        <?php if ($item['listing_type'] === 'Giveaway'): ?>
                                            Free
                                        <?php elseif ($item['listing_type'] === 'Exchange'): ?>
                                            Exchange
                                        <?php else: ?>
                                            RM <?= number_format((float) $item['price'], 2) ?>
                                        <?php endif; ?>
                                    </strong>
                                    <a class="btn btn-sm btn-outline-primary" href="/fix-and-exchange/src/buyer-seller/item-details.php?listing_id=<?= (int) $item['listing_id'] ?>">
                                        View
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
