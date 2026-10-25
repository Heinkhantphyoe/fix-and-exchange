<?php
// Database settings
$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'fix_and_exchange';

// Let try and catch handle MySQL errors.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($host, $username, $password, $database);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Database connection failed. Please try again later.');
}
