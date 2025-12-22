<?php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $room_code = $data['room_code'] ?? '';
    $user_name = $data['user_name'] ?? '';
    $action = $data['action'] ?? '';
    $song_id = $data['song_id'] ?? null;
    $current_time = $data['current_time'] ?? 0;
    $is_playing = $data['is_playing'] ?? 0;
    
    if (empty($room_code) || empty($user_name) || empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Get room
    $room_sql = "SELECT r.* FROM rooms r 
                 JOIN room_users ru ON r.id = ru.room_id 
                 WHERE r.room_code = ? AND ru.user_name = ? AND ru.is_online = 1";
    $room = db_query_one($room_sql, [$room_code, $user_name], 'ss');
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room or user not found']);
        exit;
    }
    
    global $conn;
    
    switch ($action) {
        case 'add_song':
            if (empty($song_id)) {
                echo json_encode(['success' => false, 'message' => 'Song ID required']);
                exit;
            }
            
            // Check if song exists
            $song_sql = "SELECT id, title, artist, video_source, song_number FROM songs WHERE id = ?";
            $song = db_query_one($song_sql, [$song_id], 'i');
            
            if (!$song) {
                echo json_encode(['success' => false, 'message' => 'Song not found']);
                exit;
            }
            
            // Check if song is already in the queue (pending)
            $check_queue_sql = "SELECT id FROM room_queue WHERE room_id = ? AND song_id = ? AND status = 'pending'";
            $existing = db_query_one($check_queue_sql, [$room['id'], $song_id], 'ii');
            
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Song is already in the queue']);
                exit;
            }
            
            // Add to queue
            $queue_sql = "INSERT INTO room_queue (room_id, song_id, user_name, status) VALUES (?, ?, ?, 'pending')";
            $stmt = $conn->prepare($queue_sql);
            $stmt->bind_param("iis", $room['id'], $song_id, $user_name);
            
            if ($stmt->execute()) {
                // If this is the first song in queue and no current song, set it as current
                $check_queue_count = "SELECT COUNT(*) as count FROM room_queue WHERE room_id = ? AND status = 'pending'";
                $queue_count_result = db_query_one($check_queue_count, [$room['id']], 'i');
                
                if ($queue_count_result && $queue_count_result['count'] == 1 && empty($room['current_song_id'])) {
                    $update_room_sql = "UPDATE rooms SET current_song_id = ?, last_active = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_room_sql);
                    $update_stmt->bind_param("ii", $song_id, $room['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Update room last active
                $update_last_active = "UPDATE rooms SET last_active = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_last_active);
                $update_stmt->bind_param("i", $room['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Song added to queue successfully',
                    'song' => [
                        'id' => $song['id'],
                        'title' => $song['title'],
                        'artist' => $song['artist'],
                        'song_number' => $song['song_number'],
                        'video_source' => $song['video_source']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add song to queue']);
            }
            $stmt->close();
            break;
            
        case 'set_current_song':
            if (empty($song_id)) {
                echo json_encode(['success' => false, 'message' => 'Song ID required']);
                exit;
            }
            
            // Get song details
            $song_sql = "SELECT id, title, artist, video_source FROM songs WHERE id = ?";
            $song = db_query_one($song_sql, [$song_id], 'i');
            
            if (!$song) {
                echo json_encode(['success' => false, 'message' => 'Song not found']);
                exit;
            }
            
            // Update room with current song
            $update_room_sql = "UPDATE rooms SET current_song_id = ?, current_song_time = 0, 
                                is_playing = 1, last_active = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_room_sql);
            $stmt->bind_param("ii", $song_id, $room['id']);
            
            if ($stmt->execute()) {
                // Update queue status for this song if it exists
                $update_queue_sql = "UPDATE room_queue SET status = 'playing', played_at = NOW() 
                                     WHERE room_id = ? AND song_id = ? AND status = 'pending'";
                $queue_stmt = $conn->prepare($update_queue_sql);
                $queue_stmt->bind_param("ii", $room['id'], $song_id);
                $queue_stmt->execute();
                $queue_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Current song set',
                    'song' => $song
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to set current song']);
            }
            $stmt->close();
            break;
            
        case 'play':
            // Update room playback state
            $play_sql = "UPDATE rooms SET is_playing = ?, current_song_time = ?, last_active = NOW() WHERE id = ?";
            $stmt = $conn->prepare($play_sql);
            $stmt->bind_param("iii", $is_playing, $current_time, $room['id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Playback state updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update playback state']);
            }
            $stmt->close();
            break;
            
        case 'next_song':
            // Mark current song as played
            $update_queue_sql = "UPDATE room_queue SET status = 'played', played_at = NOW() 
                                 WHERE room_id = ? AND status = 'playing'";
            $update_stmt = $conn->prepare($update_queue_sql);
            $update_stmt->bind_param("i", $room['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Get next song in queue
            $next_song_sql = "SELECT rq.*, s.* FROM room_queue rq 
                              JOIN songs s ON rq.song_id = s.id
                              WHERE rq.room_id = ? AND rq.status = 'pending'
                              ORDER BY rq.added_at LIMIT 1";
            $next_song = db_query_one($next_song_sql, [$room['id']], 'i');
            
            if ($next_song) {
                // Update room with next song
                $update_room_sql = "UPDATE rooms SET current_song_id = ?, current_song_time = 0, 
                                    is_playing = 1, last_active = NOW() WHERE id = ?";
                $room_stmt = $conn->prepare($update_room_sql);
                $room_stmt->bind_param("ii", $next_song['song_id'], $room['id']);
                $room_stmt->execute();
                $room_stmt->close();
                
                // Update queue status
                $update_next_sql = "UPDATE room_queue SET status = 'playing' WHERE id = ?";
                $next_stmt = $conn->prepare($update_next_sql);
                $next_stmt->bind_param("i", $next_song['id']);
                $next_stmt->execute();
                $next_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Next song set',
                    'next_song' => $next_song
                ]);
            } else {
                // No more songs
                $update_room_sql = "UPDATE rooms SET current_song_id = NULL, current_song_time = 0, 
                                    is_playing = 0, last_active = NOW() WHERE id = ?";
                $room_stmt = $conn->prepare($update_room_sql);
                $room_stmt->bind_param("i", $room['id']);
                $room_stmt->execute();
                $room_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'No more songs in queue',
                    'next_song' => null
                ]);
            }
            break;
            
        case 'sync':
            // Update user last seen
            $sync_sql = "UPDATE room_users SET last_seen = NOW() 
                         WHERE room_id = ? AND user_name = ?";
            $stmt = $conn->prepare($sync_sql);
            $stmt->bind_param("is", $room['id'], $user_name);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User synced']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to sync user']);
            }
            $stmt->close();
            break;
            
        case 'leave':
            // Mark user as offline
            $leave_sql = "UPDATE room_users SET is_online = 0 WHERE room_id = ? AND user_name = ?";
            $stmt = $conn->prepare($leave_sql);
            $stmt->bind_param("is", $room['id'], $user_name);

            if ($stmt->execute()) {
                // Check if the leaving user is the creator
                if ($user_name === $room['creator_name']) {
                    if (!empty($room['password'])) {
                        // Scenario 1: Creator leaves a password-protected room -> delete the room
                        $delete_sql = "DELETE FROM rooms WHERE id = ?";
                        $delete_stmt = $conn->prepare($delete_sql);
                        $delete_stmt->bind_param("i", $room['id']);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                        echo json_encode(['success' => true, 'message' => 'Room deleted']);
                        exit;
                    } else {
                        // Scenario 2: Creator leaves a password-less room -> transfer ownership
                        $find_next_creator_sql = "SELECT user_name FROM room_users WHERE room_id = ? AND user_name != ? AND is_online = 1 ORDER BY joined_at ASC LIMIT 1";
                        $next_creator = db_query_one($find_next_creator_sql, [$room['id'], $user_name], 'is');

                        if ($next_creator) {
                            // A new creator is found
                            $update_creator_sql = "UPDATE rooms SET creator_name = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_creator_sql);
                            $update_stmt->bind_param("si", $next_creator['user_name'], $room['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                            echo json_encode(['success' => true, 'message' => 'Creator left, new creator assigned']);
                        } else {
                            // No other users in the room, so deactivate it.
                            $update_room_sql = "UPDATE rooms SET is_playing = 0, last_active = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?";
                            $room_stmt = $conn->prepare($update_room_sql);
                            $room_stmt->bind_param("i", $room['id']);
                            $room_stmt->execute();
                            $room_stmt->close();
                            echo json_encode(['success' => true, 'message' => 'User left room, room is now empty and inactive']);
                        }
                    }
                } else {
                    // Non-creator leaves, same as original logic
                    $check_users_sql = "SELECT COUNT(*) as online_count FROM room_users WHERE room_id = ? AND is_online = 1";
                    $users_count = db_query_one($check_users_sql, [$room['id']], 'i');

                    if ($users_count && $users_count['online_count'] == 0) {
                        // Mark room as inactive
                        $update_room_sql = "UPDATE rooms SET is_playing = 0, last_active = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?";
                        $room_stmt = $conn->prepare($update_room_sql);
                        $room_stmt->bind_param("i", $room['id']);
                        $room_stmt->execute();
                        $room_stmt->close();
                    }

                    echo json_encode(['success' => true, 'message' => 'User left room']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to leave room']);
            }
            $stmt->close();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>