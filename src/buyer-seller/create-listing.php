<?php
// Use this page to create a listing or edit one of your own listings.
$editing = isset($_GET['listing_id']);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

$listingId = $editing ? (int) ($_GET['listing_id'] ?? 0) : 0;
$listing = $editing ? get_listing($listingId) : null;
if ($editing && (int) $listing['seller_id'] !== $userId) {
    http_response_code(403);
    exit('You can only edit your own listings.');
}

$images = $editing ? get_listing_images($listingId) : [];
$form = $listing ?? [
    'title' => '',
    'category_id' => '',
    'description' => '',
    'listing_type' => 'Sale',
    'price' => '',
];
$statement = mysqli_prepare(
    $conn,
    'SELECT * FROM categories ORDER BY category_name'
);
mysqli_stmt_execute($statement);
$categories = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
mysqli_stmt_close($statement);

$errors = [];
$removedIds = [];

// Stop the same form from publishing a listing twice.
if (empty($_SESSION['listing_form_key'])) {
    $_SESSION['listing_form_key'] = bin2hex(random_bytes(16));
}

// Read and check the form before saving anything.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    if (!is_string($_POST['form_key'] ?? null) || !hash_equals($_SESSION['listing_form_key'], $_POST['form_key'])) {
        redirect_message('my-listings.php', 'This listing form was already submitted. Please open it again.', 'warning');
    }
    $form['title'] = trim($_POST['title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['listing_type'] = $_POST['listing_type'] ?? '';
    $form['price'] = trim($_POST['price'] ?? '');
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    if ($form['title'] === '' || mb_strlen($form['title']) > 150) {
        $errors[] = 'Enter an item title using 150 characters or fewer.';
    }
    if ($form['description'] === '' || mb_strlen($form['description']) > 10000) {
        $errors[] = 'Enter a description using 10,000 characters or fewer.';
    }
    if (!in_array($form['category_id'], array_column($categories, 'category_id'))) {
        $errors[] = 'Choose a valid category.';
    }
    if (!in_array($form['listing_type'], ['Sale', 'Exchange', 'Giveaway'], true)) {
        $errors[] = 'Choose a valid listing type.';
    }
    // Sale needs a price; Exchange has none; Giveaway is free.
    $price = null;
    if ($form['listing_type'] === 'Sale') {
        $price = (float) $form['price'];
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $form['price']) || $price <= 0) {
            $errors[] = 'Enter a sale price above zero, with at most two decimal places.';
        }
    } elseif ($form['listing_type'] === 'Giveaway') {
        $price = 0;
    }
    $removedIds = array_map('intval', (array) ($_POST['remove_images'] ?? []));
    $uploads = [];
    // Check each photo before saving it.
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    foreach ((array) ($_FILES['photos']['error'] ?? []) as $index => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'A photo could not be uploaded. Check the file size and try again.';
            continue;
        }
        $temp = $_FILES['photos']['tmp_name'][$index];
        $info = @getimagesize($temp);
        $mime = $info['mime'] ?? '';
        if (!isset($extensions[$mime]) || filesize($temp) > 5 * 1024 * 1024) {
            $errors[] = 'Photos must be JPEG, PNG, or WebP images, no larger than 5 MB each.';
        } else {
            $uploads[] = ['temp' => $temp, 'path' => 'uploads/listings/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime]];
        }
    }

    // Save the listing and its photos together. Undo both if saving fails.
    if (!$errors) {
        $moved = [];
        $removedImages = [];
        mysqli_begin_transaction($conn);
        try {
            if ($editing) {
                $statement = mysqli_prepare(
                    $conn,
                    'SELECT * FROM item_listings
                    WHERE listing_id = ? AND seller_id = ? FOR UPDATE'
                );
                mysqli_stmt_bind_param($statement, 'ii', $listingId, $userId);
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
                    WHERE listing_id = ? AND status = 'Pending' LIMIT 1"
                );
                mysqli_stmt_bind_param($statement, 'i', $listingId);
                mysqli_stmt_execute($statement);
                $pending = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
                mysqli_stmt_close($statement);

                if (!$saved || $saved['status'] !== 'Available' || $accepted) {
                    $errors[] = 'Only available listings without an accepted offer can be edited.';
                } elseif ($pending && ($saved['listing_type'] !== $form['listing_type'] || $saved['price'] != $price)) {
                    $errors[] = 'Reject pending offers before changing the listing type or price.';
                }
                $images = get_listing_images($listingId);
            }
            foreach ($images as $image) {
                if (in_array((int) $image['image_id'], $removedIds, true)) {
                    $removedImages[] = $image;
                }
            }
            if (count($images) - count($removedImages) + count($uploads) > 3) {
                $errors[] = 'You can save up to three photos per listing.';
            }
            if (!$errors) {
                if ($editing) {
                    $statement = mysqli_prepare(
                        $conn,
                        'UPDATE item_listings SET title = ?, category_id = ?, description = ?, listing_type = ?, price = ?
                        WHERE listing_id = ? AND seller_id = ?'
                    );
                    mysqli_stmt_bind_param($statement, 'sissdii', $form['title'], $form['category_id'], $form['description'], $form['listing_type'], $price, $listingId, $userId);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);
                } else {
                    $statement = mysqli_prepare(
                        $conn,
                        "INSERT INTO item_listings (seller_id, category_id, title, description, listing_type, price, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'Available')"
                    );
                    mysqli_stmt_bind_param($statement, 'iisssd', $userId, $form['category_id'], $form['title'], $form['description'], $form['listing_type'], $price);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);

                    $listingId = mysqli_insert_id($conn);
                }
                foreach ($removedImages as $image) {
                    $statement = mysqli_prepare(
                        $conn,
                        'DELETE FROM item_images WHERE image_id = ? AND listing_id = ?'
                    );
                    mysqli_stmt_bind_param($statement, 'ii', $image['image_id'], $listingId);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);
                }
                foreach ($uploads as $upload) {
                    if (!move_uploaded_file($upload['temp'], __DIR__ . '/../../' . $upload['path'])) {
                        $errors[] = 'The photo could not be saved. Please try again.';
                        break;
                    }
                    $moved[] = $upload['path'];
                    $statement = mysqli_prepare(
                        $conn,
                        'INSERT INTO item_images (listing_id, image_path) VALUES (?, ?)'
                    );
                    mysqli_stmt_bind_param($statement, 'is', $listingId, $upload['path']);
                    mysqli_stmt_execute($statement);
                    mysqli_stmt_close($statement);
                }
            }
            if (!$errors) {
                mysqli_commit($conn);
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'The listing could not be saved. Please try again.';
        }
        if ($errors) {
            mysqli_rollback($conn);
            foreach ($moved as $path) {
                delete_listing_photo($path);
            }
        } else {
            foreach ($removedImages as $image) {
                delete_listing_photo($image['image_path']);
            }
            unset($_SESSION['listing_form_key']);
            redirect_message('my-listings.php', $editing ? 'Your listing has been updated.' : 'Your listing has been published.');
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <h1><?= $editing ? 'Edit' : 'Create' ?> Listing</h1>
        <div class="card bs-listing-form">
            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape_html($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!$categories): ?>
                <p class="alert alert-warning">No categories are available. Ask your team to run database/seed-categories.sql first.</p>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="bs-photo-form">
                <input type="hidden" name="token" value="<?= form_token() ?>">
                <input type="hidden" name="form_key" value="<?= escape_html($_SESSION['listing_form_key']) ?>">
                <div class="form-group">
                    <label class="form-label" for="title">Item Title</label>
                    <input class="form-control" id="title" name="title" maxlength="150" value="<?= escape_html($form['title']) ?>" placeholder="e.g., Used Engineering Textbook" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="category_id">Category</label>
                    <select class="form-control" id="category_id" name="category_id" required>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $form['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= escape_html($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control form-textarea" id="description" name="description" maxlength="10000" placeholder="Describe your item, including its condition." required><?= escape_html($form['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="photos">Photo Upload</label>
                    <input class="form-control" type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <p class="form-hint">Up to 3 photos, maximum 5 MB each. Select all new photos together.</p>
                    <div class="bs-photo-previews">
                        <?php foreach ($images as $image): ?>
                            <label class="bs-existing-photo">
                                <?php show_listing_photo($image['image_path'], 'Current item photo', 'bs-thumbnail'); ?>
                                <span>
                                    <input type="checkbox" name="remove_images[]" value="<?= (int) $image['image_id'] ?>" <?= in_array((int) $image['image_id'], $removedIds, true) ? 'checked' : '' ?>> Delete photo</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="bs-photo-previews" id="bs-new-photos"></div>
                </div>

                <fieldset class="bs-type-fieldset">
                    <legend>Listing Type</legend>
                    <?php foreach (['Sale', 'Exchange', 'Giveaway'] as $type): ?>
                        <label><input type="radio" name="listing_type" value="<?= $type ?>" <?= $form['listing_type'] === $type ? 'checked' : '' ?>> <?= $type ?></label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="form-group">
                    <label class="form-label" for="price">Price (RM)</label>
                    <input class="form-control" type="number" id="price" name="price" min="0.01" max="99999999.99" step="0.01" value="<?= escape_html($form['price']) ?>">
                    <p class="form-hint">Price is required for Sale. Leave blank for Exchange; Giveaway is free.</p>
                </div>

                <div class="form-actions bs-form-actions">
                    <button class="btn btn-primary" <?= !$categories ? 'disabled' : '' ?>><?= $editing ? 'Save Changes' : 'Publish Listing' ?></button>
                    <a class="btn" href="my-listings.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
