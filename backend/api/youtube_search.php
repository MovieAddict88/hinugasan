<?php
header("Content-Type: application/json; charset=UTF-8");

require_once '../config.php';
$apiKey = YOUTUBE_API_KEY;
$query = isset($_GET['q']) ? urlencode($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['error' => 'Search query is required']);
    exit;
}

$searchUrl = "https://www.googleapis.com/youtube/v3/search?part=snippet&q={$query}&type=video&videoEmbeddable=true&key={$apiKey}&maxResults=10";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['items'])) {
    $results = array_map(function($item) {
        return [
            'videoId' => $item['id']['videoId'],
            'title' => $item['snippet']['title'],
            'description' => $item['snippet']['description'],
            'thumbnail' => $item['snippet']['thumbnails']['default']['url'],
            'channelTitle' => $item['snippet']['channelTitle']
        ];
    }, $data['items']);
    echo json_encode($results);
} else {
    echo json_encode(['error' => 'Could not fetch YouTube search results', 'details' => $data]);
}
?>
