<?php
session_start();
require_once '../config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['playlist_id'])) {
    echo json_encode(['success' => false, 'message' => 'Playlist ID required']);
    exit;
}

$playlistId = $_GET['playlist_id'];
$pageToken = $_GET['page_token'] ?? '';

$url = "https://www.googleapis.com/youtube/v3/playlistItems?" . http_build_query([
    'part' => 'snippet',
    'playlistId' => $playlistId,
    'key' => YOUTUBE_API_KEY,
    'maxResults' => 20,
    'pageToken' => $pageToken
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['items'])) {
    $videoIds = [];
    foreach ($data['items'] as $item) {
        if (isset($item['snippet']['resourceId']['videoId'])) {
            $videoIds[] = $item['snippet']['resourceId']['videoId'];
        }
    }

    $existing_video_ids = [];
    if (!empty($videoIds)) {
        $like_clauses = [];
        foreach ($videoIds as $id) {
            $escaped_id = $conn->real_escape_string($id);
            $like_clauses[] = "video_source LIKE '%v={$escaped_id}%'";
        }
        $sql = "SELECT video_source FROM songs WHERE " . implode(' OR ', $like_clauses);
        
        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                if (preg_match("/(?<=v=)([a-zA-Z0-9_-]+)/", $row["video_source"], $matches)) {
                    $existing_video_ids[] = $matches[0];
                }
            }
            $result->free();
        }
    }
    
    $existing_video_ids_set = array_flip(array_unique($existing_video_ids));

    $songs = [];
    foreach ($data['items'] as $item) {
        if (!isset($item['snippet']['resourceId']['videoId'])) continue;
        
        $videoId = $item['snippet']['resourceId']['videoId'];
        $is_added = isset($existing_video_ids_set[$videoId]);

        $songs[] = [
            'videoId' => $videoId,
            'title' => $item['snippet']['title'],
            'channel' => $item['snippet']['videoOwnerChannelTitle'] ?? $item['snippet']['channelTitle'],
            'thumbnail' => $item['snippet']['thumbnails']['default']['url'],
            'is_added' => $is_added
        ];
    }
    
    echo json_encode([
        'success' => true,
        'songs' => $songs,
        'hasMore' => !empty($data['nextPageToken']),
        'nextPageToken' => $data['nextPageToken'] ?? '',
        'totalResults' => $data['pageInfo']['totalResults'] ?? 0
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $data['error']['message'] ?? 'Could not fetch playlist'
    ]);
}
