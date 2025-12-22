<?php
// backend/config.php

// YouTube Data API v3 key - Replace with your own
define('YOUTUBE_API_KEY', 'AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY');

// Room settings
define('ROOM_CODE_LENGTH', 8);
define('ROOM_EXPIRE_HOURS', 24);
define('MAX_USERS_PER_ROOM', 20);
define('MAX_SONGS_PER_ROOM', 100);

// InfinityFree specific settings
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]");
define('SITE_URL', BASE_URL);
define('UPLOADS_URL', BASE_URL . '/uploads');

// Enable CORS for cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Set timezone
date_default_timezone_set('Asia/Manila');

// Error reporting for development (disable for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check for maintenance mode
define('MAINTENANCE_MODE', false);
if (MAINTENANCE_MODE && !isset($_SESSION['admin'])) {
    http_response_code(503);
    die("System under maintenance. Please try again later.");
}
?>