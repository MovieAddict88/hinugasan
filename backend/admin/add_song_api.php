<?php
session_start();
header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

require_once "../includes/db.php";

// Main logic to handle song addition
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $artist = isset($_POST['artist']) ? trim($_POST['artist']) : '';
    $video_link = isset($_POST['video_link']) ? trim($_POST['video_link']) : '';

    if (empty($title) || empty($artist) || empty($video_link)) {
        echo json_encode(['success' => false, 'message' => 'Missing required song data.']);
        exit;
    }

    // Check for duplicate song based on video link
    $sql_check_duplicate = "SELECT id FROM songs WHERE video_source = ?";
    if ($stmt_check = $conn->prepare($sql_check_duplicate)) {
        $stmt_check->bind_param("s", $video_link);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This song already exists in the database.']);
            exit;
        }
        $stmt_check->close();
    }

    // Generate a unique song number
    do {
        $song_number = rand(100000, 999999);
        $sql_check = "SELECT id FROM songs WHERE song_number = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("s", $song_number);
            $stmt_check->execute();
            $stmt_check->store_result();
            $is_duplicate = $stmt_check->num_rows > 0;
            $stmt_check->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error (check song number).']);
            exit;
        }
    } while ($is_duplicate);

    // Insert the new song
    $source_type = 'link'; // Since we're adding a YouTube link
    $sql_insert = "INSERT INTO songs (song_number, title, artist, source_type, video_source) VALUES (?, ?, ?, ?, ?)";
    if ($stmt_insert = $conn->prepare($sql_insert)) {
        $stmt_insert->bind_param("sssss", $song_number, $title, $artist, $source_type, $video_link);
        if ($stmt_insert->execute()) {
            echo json_encode(['success' => true, 'message' => 'Song added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add the song.']);
        }
        $stmt_insert->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error (prepare insert).']);
    }
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>