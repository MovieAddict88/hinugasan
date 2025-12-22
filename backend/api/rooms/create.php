<?php
require_once '../../includes/db.php';
header('Content-Type: application/json');

function generateRoomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $room_name = $data['room_name'] ?? '';
    $creator_name = $data['creator_name'] ?? '';
    $password = $data['password'] ?? '';
    $device = $data['device'] ?? 'mobile';
    $max_users = $data['max_users'] ?? 10;
    $is_public = $data['is_public'] ?? 1;
    
    if (empty($room_name) || empty($creator_name)) {
        echo json_encode(['success' => false, 'message' => 'Room name and creator name are required']);
        exit;
    }
    
    // Generate unique room code
    do {
        $room_code = generateRoomCode();
        $check_sql = "SELECT id FROM rooms WHERE room_code = ?";
        $check_result = db_query_one($check_sql, [$room_code], 's');
    } while ($check_result);
    
    // Hash password if provided
    $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    // Create room
    $sql = "INSERT INTO rooms (room_code, room_name, password, creator_name, creator_device, max_users, is_public) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssii", $room_code, $room_name, $hashed_password, $creator_name, $device, $max_users, $is_public);
    
    if ($stmt->execute()) {
        $room_id = $conn->insert_id;
        
        // Add creator to room users
        $user_sql = "INSERT INTO room_users (room_id, user_name, device_type, is_online) VALUES (?, ?, ?, 1)";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("iss", $room_id, $creator_name, $device);
        $user_stmt->execute();
        $user_stmt->close();
        
        echo json_encode([
            'success' => true,
            'room_code' => $room_code,
            'room_id' => $room_id,
            'message' => 'Room created successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create room']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>