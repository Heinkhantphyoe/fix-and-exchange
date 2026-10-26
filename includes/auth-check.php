<?php
// Shared session and access helpers.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        header('Location: /fix-and-exchange/src/auth/login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();

    if ((current_user()['role'] ?? null) !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function require_admin(): void
{
    require_role('Admin');
}

function require_student(): void
{
    require_role('Student');
}

function require_approved_volunteer(): void
{
    require_student();

    require_once __DIR__ . '/../config/database.php';

    $userId = (int) current_user()['user_id'];
    $statement = mysqli_prepare(
        $conn,
        "SELECT volunteer_id
         FROM volunteer_profiles
         WHERE user_id = ? AND approval_status = 'Approved'
         LIMIT 1"
    );
    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_store_result($statement);
    $approved = mysqli_stmt_num_rows($statement) === 1;
    mysqli_stmt_close($statement);

    if (!$approved) {
        http_response_code(403);
        exit('Access denied. Your volunteer application must be approved first.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}
