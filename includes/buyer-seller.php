<?php
// Shared functions used only by Buyer/Seller and listing messages.

// Only the photo-delete checkboxes should send more than one value.
foreach ([$_GET, $_POST] as $inputs) {
    foreach ($inputs as $field => $value) {
        if (is_array($value) && $field !== 'remove_images') {
            http_response_code(400);
            exit('Invalid form input. Please go back and try again.');
        }
    }
}

// Display user text safely in HTML.
function escape_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Add a session token to each form.
function form_token()
{
    if (empty($_SESSION['buyer_seller_token'])) {
        $_SESSION['buyer_seller_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['buyer_seller_token'];
}

// Reject a form with a missing or incorrect token.
function check_form_token()
{
    if (!is_string($_POST['token'] ?? null) || !hash_equals(form_token(), $_POST['token'])) {
        http_response_code(403);
        exit('This form has expired. Refresh the page and try again.');
    }
}

// Show a message after redirecting to another page.
function redirect_message($url, $message, $type = 'success')
{
    set_flash($type, $message);
    header('Location: ' . $url);
    exit;
}

// Get one listing with its category and seller.
function get_listing($id)
{
    global $conn;

    $statement = mysqli_prepare(
        $conn,
        'SELECT l.*, c.category_name, u.full_name AS seller_name
        FROM item_listings l
        JOIN categories c ON c.category_id = l.category_id
        JOIN users u ON u.user_id = l.seller_id
        WHERE l.listing_id = ?'
    );
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    mysqli_stmt_close($statement);

    $listing = mysqli_fetch_assoc($result);

    if (!$listing) {
        http_response_code(404);
        exit('Listing not found.');
    }

    return $listing;
}

// Get the photos saved for a listing.
function get_listing_images($id)
{
    global $conn;

    $statement = mysqli_prepare(
        $conn,
        'SELECT * FROM item_images WHERE listing_id = ? ORDER BY image_id'
    );
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    mysqli_stmt_close($statement);

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Show a sale price, Free, or a dash for exchanges.
function display_price($listing)
{
    if ($listing['listing_type'] === 'Giveaway') {
        return 'Free';
    }
    if ($listing['listing_type'] === 'Exchange') {
        return '—';
    }
    return 'RM ' . number_format((float) $listing['price'], 2);
}

// Show the photo, or a placeholder when no photo was uploaded.
function show_listing_photo($path, $alt, $class = '')
{
    if ($path) {
        $url = '/fix-and-exchange/' . $path;
        echo '<img class="' . escape_html($class) . '" src="' . escape_html($url) . '" alt="' . escape_html($alt) . '">';
    } else {
        echo '<div class="bs-no-image ' . escape_html($class) . '">No photo</div>';
    }
}

function delete_listing_photo($path)
{
    // Uploaded photos use a generated filename inside uploads/listings.
    if (preg_match('~^uploads/listings/[a-f0-9]{32}\.(jpg|png|webp)$~', $path)) {
        $file = __DIR__ . '/../' . $path;
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// Show the Buyer/Seller links and the latest form message.
function show_buyer_seller_nav()
{
    // Highlight the current Buyer/Seller page.
    $section = strpos($_SERVER['SCRIPT_NAME'], '/messages/') !== false ? 'messages' : basename($_SERVER['SCRIPT_NAME'], '.php');
    if (in_array($section, ['item-details', 'report-listing'], true)) {
        $section = 'marketplace';
    } elseif ($section === 'create-listing') {
        $section = 'my-listings';
    } elseif ($section === 'create-offer') {
        $section = 'my-offers';
    }

    $links = [
        'marketplace' => ['Marketplace', '/fix-and-exchange/src/buyer-seller/marketplace.php'],
        'my-listings' => ['My Listings', '/fix-and-exchange/src/buyer-seller/my-listings.php'],
        'my-offers' => ['Offers', '/fix-and-exchange/src/buyer-seller/my-offers.php'],
        'messages' => ['Messages', '/fix-and-exchange/src/messages/index.php'],
    ];
    ?>
    <nav class="bs-nav" aria-label="Buyer and seller navigation">
        <?php foreach ($links as $key => $link): ?>
            <a class="<?= $section === $key ? 'bs-active' : '' ?>" href="<?= $link[1] ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><?= $link[0] ?></a>
        <?php endforeach; ?>
        <a href="/fix-and-exchange/src/account/profile.php">Profile</a>
    </nav>
    <?php $flash = get_flash(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= escape_html($flash['type']) ?>" role="status"><?= escape_html($flash['message']) ?></div>
    <?php endif; ?>
    <?php
}
