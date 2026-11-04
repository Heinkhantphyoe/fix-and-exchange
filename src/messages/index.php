<?php
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$user = current_user();
$userId = (int) $user['user_id'];

$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$listingId = (int) ($_GET['listing_id'] ?? 0);
$buyerId = $userId;
$conversation = null;
$listing = null;
$otherUser = null;
$error = '';
$text = '';

// Open an existing conversation, or start from a listing.
if ($conversationId > 0) {
    $statement = mysqli_prepare(
        $conn,
        "SELECT * FROM conversation
        WHERE conversation_id = ? AND context_type = 'Listing' AND (seller_id = ? OR buyer_id = ?)"
    );
    mysqli_stmt_bind_param($statement, 'iii', $conversationId, $userId, $userId);
    mysqli_stmt_execute($statement);
    $conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if (!$conversation) {
        http_response_code(404);
        exit('Conversation not found.');
    }
    $listingId = (int) $conversation['listing_id'];
    $buyerId = (int) $conversation['buyer_id'];
    $listing = get_listing($listingId);
} elseif ($listingId > 0) {
    $listing = get_listing($listingId);
    if ((int) $listing['seller_id'] === $userId) {
        $buyerId = (int) ($_GET['buyer_id'] ?? 0);
        $statement = mysqli_prepare(
            $conn,
            'SELECT offer_id FROM exchange_offers
            WHERE listing_id = ? AND buyer_id = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($statement, 'ii', $listingId, $buyerId);
        mysqli_stmt_execute($statement);
        $offer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
        mysqli_stmt_close($statement);

        if ($buyerId === $userId || !$offer) {
            http_response_code(403);
            exit('Choose a buyer from your incoming offers.');
        }
    }
    $statement = mysqli_prepare(
        $conn,
        "SELECT conversation_id FROM conversation
        WHERE listing_id = ? AND buyer_id = ? AND context_type = 'Listing' ORDER BY conversation_id LIMIT 1"
    );
    mysqli_stmt_bind_param($statement, 'ii', $listingId, $buyerId);
    mysqli_stmt_execute($statement);
    $conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if ($conversation) {
        $conversationId = (int) $conversation['conversation_id'];

        // Keep a submitted message if another tab has just started the chat.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?conversation_id=' . $conversationId);
            exit;
        }
    } elseif ($listing['status'] === 'Removed') {
        http_response_code(404);
        exit('This listing is no longer available for a new conversation.');
    }
}

if ($listing) {
    $otherId = (int) $listing['seller_id'];
    if ($otherId === $userId) {
        $otherId = $buyerId;
    }
    $statement = mysqli_prepare(
        $conn,
        'SELECT full_name FROM users WHERE user_id = ?'
    );
    mysqli_stmt_bind_param($statement, 'i', $otherId);
    mysqli_stmt_execute($statement);
    $otherUser = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

// Save a message. The sender always comes from the login session.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token();
    $text = trim($_POST['message_text'] ?? '');
    if (!$listing || !$otherUser) {
        $error = 'Choose a listing conversation first.';
    } elseif ($text === '' || mb_strlen($text) > 5000) {
        $error = 'Enter a message using 5,000 characters or fewer.';
    } else {
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

            $statement = mysqli_prepare(
                $conn,
                "SELECT conversation_id FROM conversation
                WHERE listing_id = ? AND buyer_id = ? AND context_type = 'Listing' ORDER BY conversation_id LIMIT 1"
            );
            mysqli_stmt_bind_param($statement, 'ii', $listingId, $buyerId);
            mysqli_stmt_execute($statement);
            $savedChat = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);

            if ($savedChat) {
                $conversationId = (int) $savedChat['conversation_id'];
            } elseif ($savedListing['status'] === 'Removed') {
                $error = 'This listing has been removed.';
            } else {
                $statement = mysqli_prepare(
                    $conn,
                    "INSERT INTO conversation (seller_id, buyer_id, listing_id, context_type, context_id)
                    VALUES (?, ?, ?, 'Listing', ?)"
                );
                mysqli_stmt_bind_param($statement, 'iiii', $listing['seller_id'], $buyerId, $listingId, $listingId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);

                $conversationId = mysqli_insert_id($conn);
            }
            if (!$error) {
                $statement = mysqli_prepare(
                    $conn,
                    'INSERT INTO messages (sender_id, receiver_id, conversation_id, message_text)
                    VALUES (?, ?, ?, ?)'
                );
                mysqli_stmt_bind_param($statement, 'iiis', $userId, $otherId, $conversationId, $text);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);

                mysqli_commit($conn);
            }
        } catch (mysqli_sql_exception $e) {
            $error = 'Your message could not be saved. Please try again.';
        }
        if ($error) {
            mysqli_rollback($conn);
        } else {
            header('Location: ?conversation_id=' . $conversationId);
            exit;
        }
    }
}

// Load the conversation list and selected messages.
$statement = mysqli_prepare(
    $conn,
    "SELECT c.*, seller.full_name AS seller_name, buyer.full_name AS buyer_name,
    l.title, last_message.message_text AS last_message
    FROM conversation c
    JOIN item_listings l ON l.listing_id = c.listing_id
    JOIN users seller ON seller.user_id = c.seller_id
    JOIN users buyer ON buyer.user_id = c.buyer_id
    LEFT JOIN messages last_message ON last_message.message_id =
        (SELECT MAX(message_id) FROM messages WHERE conversation_id = c.conversation_id)
    WHERE c.context_type = 'Listing' AND (c.seller_id = ? OR c.buyer_id = ?)
    ORDER BY last_message.message_id DESC, c.conversation_id DESC"
);
mysqli_stmt_bind_param($statement, 'ii', $userId, $userId);
mysqli_stmt_execute($statement);
$conversations = mysqli_stmt_get_result($statement);
mysqli_stmt_close($statement);

$messages = [];
if ($conversationId > 0) {
    $statement = mysqli_prepare(
        $conn,
        'SELECT * FROM messages WHERE conversation_id = ? ORDER BY message_id'
    );
    mysqli_stmt_bind_param($statement, 'i', $conversationId);
    mysqli_stmt_execute($statement);
    $messages = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

$image = $listing ? (get_listing_images($listingId)[0]['image_path'] ?? null) : null;

require __DIR__ . '/../../includes/header.php';
?>

<section class="section bs-page">
    <div class="container">
        <?php show_buyer_seller_nav(); ?>
        <div class="bs-heading-row">
            <h1>Messages</h1>
            <?php if ($conversationId > 0): ?>
                <form method="get" action="index.php">
                    <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                    <button class="btn btn-outline-primary">Refresh Messages</button>
                </form>
            <?php endif; ?>
        </div>
        <p class="form-hint mb-3">Refresh this page to see new replies.</p>
        <div class="bs-messages-layout">
            <aside class="bs-conversations">
                <h2>Conversations</h2>
                <?php foreach ($conversations as $row): ?>
                    <a class="bs-conversation <?= $conversationId === (int) $row['conversation_id'] ? 'bs-selected-conversation' : '' ?>" href="?conversation_id=<?= (int) $row['conversation_id'] ?>">
                        <strong><?= escape_html((int) $row['seller_id'] === $userId ? $row['buyer_name'] : $row['seller_name']) ?></strong>
                        <small><?= escape_html($row['title']) ?></small>
                        <span><?= escape_html(mb_strimwidth($row['last_message'] ?? 'Start a conversation', 0, 65, '...')) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!mysqli_num_rows($conversations)): ?>
                    <p class="text-muted">No conversations yet. Open an item and choose Message Seller.</p>
                <?php endif; ?>
            </aside>
            <div class="bs-chat">
                <?php if ($listing && $otherUser): ?>
                    <div class="bs-chat-header">
                        <h2><?= escape_html($otherUser['full_name']) ?></h2>
                        <a class="bs-chat-item" href="../buyer-seller/item-details.php?listing_id=<?= $listingId ?>">
                            <?php show_listing_photo($image, $listing['title'], 'bs-table-photo'); ?>
                            <span>
                                <?= escape_html($listing['title']) ?><br>
                                <?= display_price($listing) ?><br>
                                <small>View post</small>
                            </span>
                        </a>
                    </div>
                    <div class="bs-chat-messages">
                        <?php foreach ($messages as $message): ?>
                            <div class="bs-message <?= (int) $message['sender_id'] === $userId ? 'bs-message-sent' : 'bs-message-received' ?>">
                                <p class="bs-text"><?= escape_html($message['message_text']) ?></p>
                                <time datetime="<?= escape_html($message['sent_at']) ?>">
                                    <?= escape_html(date('d M Y, H:i', strtotime($message['sent_at']))) ?>
                                </time>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$messages): ?>
                            <p class="text-muted">Send a message to discuss this item.</p>
                        <?php endif; ?>
                    </div>
                    <div class="bs-chat-composer">
                        <?php if ($error): ?>
                            <p class="alert alert-danger" role="alert"><?= escape_html($error) ?></p>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= form_token() ?>">
                            <label class="sr-only" for="message_text">Type a message</label>
                            <textarea class="form-control" id="message_text" name="message_text" rows="2" maxlength="5000" placeholder="Type a message..." required><?= escape_html($text) ?></textarea>
                            <button class="btn btn-primary">Send</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-state">Choose a conversation to read and send messages.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
