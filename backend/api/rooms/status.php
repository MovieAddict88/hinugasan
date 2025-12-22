<?php
require_once '../../includes/db.php';
header('Content-Type: application/json');

$room_code = $_GET['room_code'] ?? '';

if (empty($room_code)) {
    echo json_encode(['success' => false, 'message' => 'Room code is required']);
    exit;
}

// Get room status with users and current queue
$room_sql = "SELECT r.*, 
                    s.title as current_song_title,
                    s.artist as current_song_artist,
                    s.video_source as current_video_source,
                    s.song_number as current_song_number
             FROM rooms r 
             LEFT JOIN songs s ON r.current_song_id = s.id
             WHERE r.room_code = ?";
             
$room = db_query_one($room_sql, [$room_code], 's');

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room not found']);
    exit;
}

// Get online users
$users_sql = "SELECT user_name, device_type, joined_at 
              FROM room_users 
              WHERE room_id = ? AND is_online = 1 
              ORDER BY joined_at";
$users = db_query($users_sql, [$room['id']], 'i');

// Get queue (include song details)
$queue_sql = "SELECT rq.*, s.id as song_id, s.title, s.artist, s.video_source, s.song_number
              FROM room_queue rq 
              JOIN songs s ON rq.song_id = s.id
              WHERE rq.room_id = ? AND rq.status = 'pending'
              ORDER BY rq.added_at";
$queue = db_query($queue_sql, [$room['id']], 'i');

// Get recently played
$history_sql = "SELECT rq.*, s.title, s.artist, s.song_number
                FROM room_queue rq 
                JOIN songs s ON rq.song_id = s.id
                WHERE rq.room_id = ? AND rq.status = 'played'
                ORDER BY rq.played_at DESC 
                LIMIT 10";
$history = db_query($history_sql, [$room['id']], 'i');

echo json_encode([
    'success' => true,
    'room' => $room,
    'users' => $users,
    'queue' => $queue,
    'history' => $history,
    'timestamp' => time()
]);
?>