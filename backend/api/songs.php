<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "../includes/db.php";

// Set pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

// Get search term
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get total number of songs
$total_songs_sql = "SELECT COUNT(*) as total FROM songs";
if (!empty($search)) {
    $total_songs_sql .= " WHERE title LIKE ? OR artist LIKE ? OR song_number LIKE ?";
}
$stmt_total = $conn->prepare($total_songs_sql);
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_total->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total_songs = $total_result->fetch_assoc()['total'];

$songs = [];
// Fetch a paginated list of songs
$sql = "SELECT id, song_number, title, artist, video_source FROM songs";
if (!empty($search)) {
    $sql .= " WHERE title LIKE ? OR artist LIKE ? OR song_number LIKE ?";
}
$sql .= " ORDER BY CAST(song_number AS UNSIGNED) ASC, song_number ASC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $songs[] = $row;
    }
}

// Prepare the response
$response = [
    'total' => (int)$total_songs,
    'page' => $page,
    'limit' => $limit,
    'songs' => $songs
];

echo json_encode($response);

$stmt->close();
$stmt_total->close();
$conn->close();
?>