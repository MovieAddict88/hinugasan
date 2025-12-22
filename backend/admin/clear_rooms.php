<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Clear rooms inactive for more than 24 hours
$sql = "UPDATE rooms SET is_playing = 0, last_active = DATE_SUB(NOW(), INTERVAL 1 DAY) 
        WHERE last_active < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
if ($conn->query($sql) === TRUE) {
    $affected_rows = $conn->affected_rows;
    echo json_encode(['success' => true, 'message' => "Cleared {$affected_rows} inactive rooms"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
}

$conn->close();
?>