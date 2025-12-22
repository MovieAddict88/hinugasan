<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['room_id'])) {
    $room_id = trim($_POST['room_id']);

    if (!empty($room_id)) {
        $sql = "DELETE FROM rooms WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $room_id);
            if ($stmt->execute()) {
                // Deletion successful
            } else {
                // Error handling
            }
            $stmt->close();
        }
    }
}

header("location: dashboard.php");
exit;
?>
