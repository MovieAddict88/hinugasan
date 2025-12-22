<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get system information
$info = [
    'php_version' => phpversion(),
    'mysql_version' => $conn->server_info,
    'server_name' => $_SERVER['SERVER_NAME'],
    'uptime' => shell_exec('uptime -p'),
    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
];

echo json_encode($info);
$conn->close();
?>