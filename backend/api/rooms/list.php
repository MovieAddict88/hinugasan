<?php
require_once '../../includes/db.php';
header('Content-Type: application/json');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get active rooms (last active within 24 hours)
$sql = "SELECT r.*, COUNT(DISTINCT ru.id) as user_count, 
               GROUP_CONCAT(DISTINCT ru.user_name) as users
        FROM rooms r 
        LEFT JOIN room_users ru ON r.id = ru.room_id AND ru.is_online = 1
        WHERE r.is_public = 1 
        AND r.last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
if (!empty($search)) {
    $sql .= " AND (r.room_name LIKE ? OR r.creator_name LIKE ?)";
}

$sql .= " GROUP BY r.id 
          ORDER BY r.last_active DESC 
          LIMIT ? OFFSET ?";

if (!empty($search)) {
    $search_param = "%$search%";
    $rooms = db_query($sql, [$search_param, $search_param, $limit, $offset], 'ssii');
    $count_sql = "SELECT COUNT(*) as total FROM rooms r 
                  WHERE r.is_public = 1 
                  AND r.last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND (r.room_name LIKE ? OR r.creator_name LIKE ?)";
    $total = db_query_one($count_sql, [$search_param, $search_param], 'ss');
} else {
    $rooms = db_query($sql, [$limit, $offset], 'ii');
    $count_sql = "SELECT COUNT(*) as total FROM rooms r 
                  WHERE r.is_public = 1 
                  AND r.last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $total = db_query_one($count_sql);
}

echo json_encode([
    'success' => true,
    'rooms' => $rooms,
    'total' => $total ? $total['total'] : 0,
    'page' => $page,
    'limit' => $limit
]);
?>