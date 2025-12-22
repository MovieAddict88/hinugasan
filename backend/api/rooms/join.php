<?php
require_once '../../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $room_code = $data['room_code'] ?? '';
    $user_name = $data['user_name'] ?? '';
    $password = $data['password'] ?? '';
    $device = $data['device'] ?? 'mobile';
    
    if (empty($room_code) || empty($user_name)) {
        echo json_encode(['success' => false, 'message' => 'Room code and user name are required']);
        exit;
    }
    
    // Get room details
    $room_sql = "SELECT r.*, COUNT(ru.id) as user_count 
                 FROM rooms r 
                 LEFT JOIN room_users ru ON r.id = ru.room_id AND ru.is_online = 1
                 WHERE r.room_code = ? 
                 AND r.last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY r.id";
    
    $room = db_query_one($room_sql, [$room_code], 's');
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room not found or expired']);
        exit;
    }
    
    // Check password if room has one
    if (!empty($room['password'])) {
        if (empty($password) || !password_verify($password, $room['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
            exit;
        }
    }
    
    // Check if room is full
    if ($room['user_count'] >= $room['max_users']) {
        echo json_encode(['success' => false, 'message' => 'Room is full']);
        exit;
    }
    
    // Check if user already exists in room
    $user_check_sql = "SELECT id FROM room_users WHERE room_id = ? AND user_name = ?";
    $existing_user = db_query_one($user_check_sql, [$room['id'], $user_name], 'is');
    
    global $conn;
    
    if ($existing_user) {
        // Update existing user
        $update_sql = "UPDATE room_users SET is_online = 1, device_type = ?, last_seen = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $device, $existing_user['id']);
    } else {
        // Add new user
        $insert_sql = "INSERT INTO room_users (room_id, user_name, device_type, is_online) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iss", $room['id'], $user_name, $device);
    }
    
    if ($stmt->execute()) {
        // Update room last active
        $update_room_sql = "UPDATE rooms SET last_active = NOW() WHERE id = ?";
        $room_stmt = $conn->prepare($update_room_sql);
        $room_stmt->bind_param("i", $room['id']);
        $room_stmt->execute();
        $room_stmt->close();
        
        echo json_encode([
            'success' => true,
            'room' => $room,
            'message' => 'Joined room successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to join room']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>