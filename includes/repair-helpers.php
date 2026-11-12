<?php
// Shared functions and helpers for repair requester and repair views.

if (!function_exists('escape_html')) {
    function escape_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('form_token')) {
    function form_token(): string
    {
        if (empty($_SESSION['app_form_token'])) {
            $_SESSION['app_form_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['app_form_token'];
    }
}

if (!function_exists('check_form_token')) {
    function check_form_token(): void
    {
        $submitted = $_POST['token'] ?? '';
        $validToken = $_SESSION['app_form_token'] ?? $_SESSION['buyer_seller_token'] ?? '';

        if (!is_string($submitted) || $submitted === '' || !hash_equals((string) $validToken, $submitted)) {
            http_response_code(403);
            exit('This form has expired. Refresh the page and try again.');
        }
    }
}

if (!function_exists('redirect_message')) {
    function redirect_message(string $url, string $message, string $type = 'success'): void
    {
        set_flash($type, $message);
        header('Location: ' . $url);
        exit;
    }
}

function get_repair_categories(mysqli $conn): array
{
    $statement = mysqli_prepare($conn, 'SELECT category_id, category_name FROM categories ORDER BY category_name ASC');
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
    return $categories;
}

function get_repair_request(mysqli $conn, int $requestId): ?array
{
    $statement = mysqli_prepare(
        $conn,
        'SELECT r.*, c.category_name, u.full_name AS requester_name, u.tp_number AS requester_tp
         FROM repair_requests r
         JOIN categories c ON c.category_id = r.category_id
         JOIN users u ON u.user_id = r.requester_id
         WHERE r.request_id = ?
         LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $requestId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $request = mysqli_fetch_assoc($result);
    mysqli_stmt_close($statement);
    return $request ?: null;
}

function get_repair_images(mysqli $conn, int $requestId): array
{
    $statement = mysqli_prepare(
        $conn,
        'SELECT repair_image_id, request_id, image_path
         FROM repair_images
         WHERE request_id = ?
         ORDER BY repair_image_id ASC'
    );
    mysqli_stmt_bind_param($statement, 'i', $requestId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $images = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
    return $images;
}

function get_repair_assignment_details(mysqli $conn, int $requestId): ?array
{
    $statement = mysqli_prepare(
        $conn,
        'SELECT a.*, v.skills_description, u.user_id AS volunteer_user_id, u.full_name AS volunteer_name, u.tp_number AS volunteer_tp, u.email AS volunteer_email
         FROM repair_assignments a
         JOIN volunteer_profiles v ON v.volunteer_id = a.volunteer_id
         JOIN users u ON u.user_id = v.user_id
         WHERE a.request_id = ?
         ORDER BY a.assignment_id DESC
         LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $requestId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $assignment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($statement);
    return $assignment ?: null;
}

function delete_repair_photo(string $path): void
{
    if (preg_match('~^uploads/repairs/[a-f0-9]{32}\.(jpg|png|webp)$~', $path)) {
        $file = __DIR__ . '/../' . $path;
        if (is_file($file)) {
            unlink($file);
        }
    }
}

function show_repair_photo(?string $path, string $alt = 'Repair photo', string $class = ''): void
{
    if ($path) {
        $url = '/fix-and-exchange/' . escape_html($path);
        echo '<img class="' . escape_html($class) . '" src="' . $url . '" alt="' . escape_html($alt) . '">';
    } else {
        echo '<div class="repair-no-image ' . escape_html($class) . '"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>No photo</span></div>';
    }
}

function show_repair_requester_nav(string $active = ''): void
{
    global $conn;
    $userId = (int) (current_user()['user_id'] ?? 0);
    $isVolunteer = false;

    if ($userId && isset($conn)) {
        $statement = mysqli_prepare(
            $conn,
            "SELECT volunteer_id FROM volunteer_profiles WHERE user_id = ? AND approval_status = 'Approved' LIMIT 1"
        );
        if ($statement) {
            mysqli_stmt_bind_param($statement, 'i', $userId);
            mysqli_stmt_execute($statement);
            $isVolunteer = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
            mysqli_stmt_close($statement);
        }
    }

    $links = [
        'repair-hub' => ['<i class="fa-solid fa-compass" aria-hidden="true"></i> Repair Hub', '/fix-and-exchange/src/repair-requester/repair-hub.php'],
        'dashboard' => ['<i class="fa-solid fa-gauge" aria-hidden="true"></i> Dashboard', '/fix-and-exchange/src/repair-requester/dashboard.php'],
        'my-requests' => ['<i class="fa-solid fa-list-check" aria-hidden="true"></i> My Requests', '/fix-and-exchange/src/repair-requester/my-requests.php'],
        'create-request' => ['<i class="fa-solid fa-plus-circle" aria-hidden="true"></i> Request a Repair', '/fix-and-exchange/src/repair-requester/create-request.php'],
    ];

    if ($isVolunteer) {
        $links['volunteer'] = ['<i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Volunteer Console', '/fix-and-exchange/src/repair-volunteer/dashboard.php'];
    } else {
        $links['volunteer'] = ['<i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Become a Volunteer', '/fix-and-exchange/src/repair-volunteer/apply.php'];
    }
    ?>
    <nav class="repair-subnav" aria-label="Repair requester sub-navigation">
        <?php foreach ($links as $key => $link): ?>
            <a class="repair-subnav-link <?= $active === $key ? 'is-active' : '' ?>" href="<?= $link[1] ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
                <?= $link[0] ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
    $flash = get_flash();
    if ($flash):
    ?>
        <div class="alert alert-<?= escape_html($flash['type']) ?>" role="status">
            <?= escape_html($flash['message']) ?>
        </div>
    <?php
    endif;
}
