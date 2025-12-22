<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Clear cache (simplified version - in production you'd use a proper caching system)
$cache_dir = __DIR__ . '/../cache/';
if (is_dir($cache_dir)) {
    array_map('unlink', glob($cache_dir . "*.cache"));
}

echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
$conn->close();
?>