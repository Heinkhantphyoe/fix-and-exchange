<?php
// Simple conversation between a repair requester and the assigned volunteer.
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth-check.php';
require_student();
require __DIR__ . '/../../includes/buyer-seller.php';

$userId = (int) current_user()['user_id'];
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$error = '';

$statement = mysqli_prepare($conn, "SELECT r.request_id, r.title, r.requester_id, a.assignment_id, a.volunteer_id, vu.user_id AS volunteer_user_id, vu.full_name AS volunteer_name, ru.full_name AS requester_name
    FROM repair_requests r JOIN repair_assignments a ON a.request_id = r.request_id JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id
    JOIN users ru ON ru.user_id = r.requester_id JOIN users vu ON vu.user_id = v.user_id
    WHERE r.request_id = ? AND a.progress_status <> 'Cancelled' LIMIT 1");
mysqli_stmt_bind_param($statement, 'i', $requestId); mysqli_stmt_execute($statement); $repair = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
if (!$repair || !in_array($userId, [(int) $repair['requester_id'], (int) $repair['volunteer_user_id']], true)) { http_response_code(403); exit('Repair conversation not found.'); }

$otherId = (int) $repair['requester_id'] === $userId ? (int) $repair['volunteer_user_id'] : (int) $repair['requester_id'];
$statement = mysqli_prepare($conn, "SELECT conversation_id FROM conversation WHERE context_type = 'Repair' AND context_id = ? AND ((seller_id = ? AND buyer_id = ?) OR (seller_id = ? AND buyer_id = ?)) LIMIT 1");
mysqli_stmt_bind_param($statement, 'iiiii', $requestId, $repair['requester_id'], $repair['volunteer_user_id'], $repair['volunteer_user_id'], $repair['requester_id']); mysqli_stmt_execute($statement); $conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($statement)); mysqli_stmt_close($statement);
$conversationId = (int) ($conversation['conversation_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form_token(); $text = trim($_POST['message_text'] ?? '');
    if ($text === '' || mb_strlen($text) > 5000) { $error = 'Enter a message using 5,000 characters or fewer.'; }
    else {
        mysqli_begin_transaction($conn);
        try {
            if (!$conversationId) {
                $statement = mysqli_prepare($conn, "INSERT INTO conversation (seller_id, buyer_id, listing_id, context_type, context_id) VALUES (?, ?, NULL, 'Repair', ?)");
                mysqli_stmt_bind_param($statement, 'iii', $repair['requester_id'], $repair['volunteer_user_id'], $requestId); mysqli_stmt_execute($statement); $conversationId = mysqli_insert_id($conn); mysqli_stmt_close($statement);
            }
            $statement = mysqli_prepare($conn, 'INSERT INTO messages (sender_id, receiver_id, conversation_id, message_text) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($statement, 'iiis', $userId, $otherId, $conversationId, $text); mysqli_stmt_execute($statement); mysqli_stmt_close($statement);
            mysqli_commit($conn); header('Location: repair.php?request_id=' . $requestId); exit;
        } catch (Throwable $exception) { mysqli_rollback($conn); $error = 'Your message could not be saved.'; }
    }
} else { $text = ''; }

$messages = [];
if ($conversationId) { $statement = mysqli_prepare($conn, 'SELECT m.*, u.full_name AS sender_name FROM messages m JOIN users u ON u.user_id = m.sender_id WHERE conversation_id = ? ORDER BY message_id'); mysqli_stmt_bind_param($statement, 'i', $conversationId); mysqli_stmt_execute($statement); $messages = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC); mysqli_stmt_close($statement); }
require __DIR__ . '/../../includes/header.php';
?>
<section class="section volunteer-page"><div class="container"><div class="volunteer-heading"><div><p class="volunteer-kicker">REPAIR MESSAGES</p><h1><?= htmlspecialchars($repair['title'], ENT_QUOTES, 'UTF-8') ?></h1><p>Conversation with <?= htmlspecialchars($userId === (int) $repair['requester_id'] ? $repair['volunteer_name'] : $repair['requester_name'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn btn-outline-primary" href="<?= $userId === (int) $repair['volunteer_user_id'] ? '../repair-volunteer/job-console.php?assignment_id=' . (int) $repair['assignment_id'] : '../repair-requester/request-details.php?request_id=' . $requestId ?>">Back to repair</a></div><article class="volunteer-panel"><div class="repair-chat"><?php if (!$messages): ?><p class="empty-state">No messages yet. Start the conversation.</p><?php else: ?><?php foreach ($messages as $message): ?><p class="repair-message <?= (int) $message['sender_id'] === $userId ? 'is-mine' : '' ?>"><strong><?= htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8') ?></strong><br><?= nl2br(htmlspecialchars($message['message_text'], ENT_QUOTES, 'UTF-8')) ?></p><?php endforeach; ?><?php endif; ?></div><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><input type="hidden" name="request_id" value="<?= $requestId ?>"><input type="hidden" name="token" value="<?= htmlspecialchars(form_token(), ENT_QUOTES, 'UTF-8') ?>"><textarea class="form-control form-textarea" name="message_text" maxlength="5000" placeholder="Write a message" required><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></textarea><button class="btn btn-primary" type="submit">Send message</button></form></article></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
