<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once '../config.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

function search_youtube($query, $apiKey, $type = 'video,playlist', $maxResults = 50) {
    if (empty($query)) {
        return ['error' => 'Search query is required'];
    }

    $searchUrl = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
        'part' => 'snippet',
        'q' => $query,
        'type' => $type,
        'key' => $apiKey,
        'maxResults' => $maxResults
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $searchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function get_playlist_items($playlistId, $apiKey, $maxResults = 50) {
    $url = "https://www.googleapis.com/youtube/v3/playlistItems?" . http_build_query([
        'part' => 'snippet',
        'playlistId' => $playlistId,
        'key' => $apiKey,
        'maxResults' => $maxResults
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Main search execution
$searchResults = search_youtube($query, YOUTUBE_API_KEY, 'video,playlist', 50);
$allResults = [];

// Get existing songs from DB
require_once '../includes/db.php';
$existing_video_ids = [];
$sql = "SELECT video_source FROM songs";
if ($result = $conn->query($sql)) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (preg_match("/(?<=v=)[a-zA-Z0-9_-]+/", $row["video_source"], $matches)) {
                $existing_video_ids[] = $matches[0];
            }
        }
        $result->free();
    }
}


if (isset($searchResults['items'])) {
    foreach ($searchResults['items'] as $item) {
        $kind = $item['id']['kind'];
        
        if ($kind == 'youtube#video') {
            $is_added = in_array($item['id']['videoId'], $existing_video_ids);
            // Individual video
            $allResults[] = [
                'type' => 'video',
                'videoId' => $item['id']['videoId'],
                'title' => $item['snippet']['title'],
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channelTitle' => $item['snippet']['channelTitle'],
                'itemCount' => 1,
                'is_added' => $is_added
            ];
            
        } elseif ($kind == 'youtube#playlist') {
            // Playlist (collection)
            $playlistId = $item['id']['playlistId'];
            
            // Optional: Get playlist item count (requires another API call)
            // For now, we'll just show it as a playlist
            $allResults[] = [
                'type' => 'playlist',
                'playlistId' => $playlistId,
                'title' => $item['snippet']['title'],
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channelTitle' => $item['snippet']['channelTitle'],
                'itemCount' => 'Multiple songs',
                'description' => substr($item['snippet']['description'], 0, 100) . '...'
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>YouTube Search: "<?php echo htmlspecialchars($query); ?>"</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* Custom styles for YouTube search results page */
        body {
            padding: 20px;
            background-color: #f4f7f6;
            color: #333;
        }
        .result-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            transition: box-shadow 0.3s;
        }
        .result-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .result-video {
            background-color: #f9f9f9;
        }
        .result-playlist {
            background-color: #f0f7ff;
            border-left: 4px solid #007bff;
        }
        .thumbnail {
            width: 160px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
        }
        .badge-type {
            font-size: 0.7rem;
            padding: 3px 8px;
            margin-right: 8px;
        }
        /* --- Enhanced Action Button Styles --- */
        .result-card .d-flex > div:last-child {
            display: flex;
            align-items: center;
        }

        .result-card .btn {
            border-radius: 50px; /* Pill-shaped buttons */
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            border: none;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        .result-card .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .result-card .btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(38, 166, 154, 0.4);
        }

        .result-card .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 3px 5px rgba(0,0,0,0.1);
        }

        .btn-success.add-song-btn {
            background: linear-gradient(45deg, #1DB954, #1ED760);
            color: white;
        }

        .btn.add-song-btn[disabled] {
            background: #a5a5a5;
            color: #f0f0f0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-playlist {
            background: linear-gradient(45deg, #5f27cd, #8e44ad);
            color: white;
        }

        .result-card .btn-outline-secondary {
            background-color: #fff;
            border: 2px solid #ccc;
            color: #555;
            box-shadow: none;
        }

        .result-card .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #aaa;
            color: #333;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        /* --- Modal Specific Styles (Dark Theme) --- */
        #playlistModal .modal-content {
            background-color: #2d2d2d;
            color: #f5f5f5;
            border-radius: 10px;
            border: 1px solid #444;
        }

        #playlistModal .modal-header {
            border-bottom: 1px solid #444;
        }

        #playlistModal .modal-header .close {
            color: #f5f5f5;
            text-shadow: none;
            opacity: 0.7;
        }

        #playlistModal .modal-header .close:hover {
            opacity: 1;
        }

        #playlistModal .modal-body {
            padding: 1rem 0.5rem;
        }

        #playlistModal .list-group-item {
            background-color: transparent;
            border: none;
        }

        #playlistModal .list-group-item strong {
            color: #f5f5f5;
            font-weight: 500;
        }

        #playlistModal .list-group-item .text-muted {
            color: #aaa !important;
        }

        #playlistModal .list-group-item > div:last-child {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        #playlistModal .btn {
            border-radius: 15px;
            font-weight: 500;
            padding: 0.2rem 0.8rem;
            border: none;
            transition: all 0.2s ease-in-out;
            letter-spacing: 0.5px;
            font-size: 0.7rem;
            margin-bottom: 5px;
            min-width: 80px;
            text-align: center;
        }

        #playlistModal .list-group-item .btn:last-child {
            margin-bottom: 0;
        }

        #playlistModal .btn-success {
             background: linear-gradient(45deg, #1DB954, #1ED760);
             color: white;
        }

        #playlistModal .btn-success:hover {
            opacity: 0.9;
        }

        #playlistModal .btn[disabled] {
            background: #a5a5a5;
            color: #f0f0f0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        #playlistModal .btn-outline-secondary {
            border: 1px solid #777;
            color: #f5f5f5;
            background: transparent;
        }

        #playlistModal .btn-outline-secondary:hover {
            background-color: #444;
            border-color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>YouTube Search Results</h2>
                <p class="text-muted">Search: <strong><?php echo htmlspecialchars($query); ?></strong></p>
            </div>
            <a href="?q=<?php echo urlencode($query); ?>&type=video" class="btn btn-outline-primary btn-sm">Videos Only</a>
            <a href="?q=<?php echo urlencode($query); ?>&type=playlist" class="btn btn-outline-primary btn-sm">Playlists Only</a>
            <a href="?q=<?php echo urlencode($query); ?>&type=video,playlist" class="btn btn-primary btn-sm">Both</a>
        </div>
        
        <?php if (isset($searchResults['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($searchResults['error']['message'] ?? 'Search failed'); ?>
            </div>
        <?php elseif (empty($allResults)): ?>
            <div class="alert alert-info">
                No results found for your query.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($allResults as $item): ?>
                <div class="col-md-12">
                    <div class="result-card <?php echo $item['type'] == 'playlist' ? 'result-playlist' : 'result-video'; ?>">
                        <div class="d-flex">
                            <div class="mr-3">
                                <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                                     alt="Thumbnail" 
                                     class="thumbnail">
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5>
                                            <span class="badge badge-type badge-<?php echo $item['type'] == 'playlist' ? 'primary' : 'secondary'; ?>">
                                                <?php echo strtoupper($item['type']); ?>
                                            </span>
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </h5>
                                        <p class="text-muted mb-1">
                                            <small>Channel: <?php echo htmlspecialchars($item['channelTitle']); ?></small>
                                        </p>
                                        <p class="mb-1">
                                            <small class="text-info">
                                                <strong>
                                                    <?php echo $item['type'] == 'playlist' ? 'Collection: ' . $item['itemCount'] . ' songs' : 'Single video'; ?>
                                                </strong>
                                            </small>
                                        </p>
                                        <?php if ($item['type'] == 'playlist' && isset($item['description'])): ?>
                                            <p class="text-muted mb-2">
                                                <small><?php echo htmlspecialchars($item['description']); ?></small>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($item['type'] == 'video'): ?>
                                            <button class="btn <?php echo $item['is_added'] ? 'btn-secondary' : 'btn-success'; ?> add-song-btn"
                                                    data-video-id="<?php echo htmlspecialchars($item['videoId']); ?>"
                                                    data-title="<?php echo htmlspecialchars($item['title']); ?>"
                                                    data-artist="<?php echo htmlspecialchars($item['channelTitle']); ?>"
                                                    <?php if ($item['is_added']) echo 'disabled'; ?>>
                                                <?php echo $item['is_added'] ? 'Added' : 'Add Song'; ?>
                                            </button>
                                            <a href="https://www.youtube.com/watch?v=<?php echo $item['videoId']; ?>" 
                                               target="_blank" 
                                               class="btn btn-outline-secondary btn-sm">
                                                Preview
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-playlist view-playlist-btn"
                                                    data-playlist-id="<?php echo htmlspecialchars($item['playlistId']); ?>"
                                                    data-title="<?php echo htmlspecialchars($item['title']); ?>">
                                                View Collection (<?php echo $item['itemCount']; ?>)
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for Playlist View -->
    <div class="modal fade" id="playlistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="playlistModalTitle">Playlist Songs</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="playlistModalBody">
                    <!-- Playlist songs will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
    const existingVideoIds = new Set(<?php echo json_encode($existing_video_ids); ?>);

    function updateVideoButtonState(videoId) {
        const button = document.querySelector(`.add-song-btn[data-video-id="${videoId}"]`);
        if (button) {
            button.disabled = true;
            button.textContent = 'Added';
            button.classList.remove('btn-success');
            button.classList.add('btn-secondary');
        }
        existingVideoIds.add(videoId);
    }

    document.querySelectorAll('.add-song-btn').forEach(button => {
        button.addEventListener('click', function() {
            const videoId = this.dataset.videoId;
            const title = this.dataset.title;
            const artist = this.dataset.artist;
            
            this.disabled = true;
            this.textContent = 'Adding...';

            $.post('add_song_api.php', {
                title: title,
                artist: artist,
                video_link: `https://www.youtube.com/watch?v=${videoId}`
            }, (data) => {
                if (data.success) {
                    updateVideoButtonState(videoId);
                    if (window.opener && !window.opener.closed) {
                        window.opener.location.reload();
                    }
                } else {
                    this.disabled = false;
                    this.textContent = 'Add Song';
                    alert('Error: ' + (data.message || 'Could not add song.'));
                }
            }).fail(() => {
                this.disabled = false;
                this.textContent = 'Add Song';
                alert('An error occurred.');
            });
        });
    });

    function getSongItemHtml(song) {
        const isAdded = existingVideoIds.has(song.videoId);
        return `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${song.title}</strong><br>
                    <small class="text-muted">${song.channel}</small>
                </div>
                <div>
                    <button class="btn btn-sm ${isAdded ? 'btn-secondary' : 'btn-success'} add-from-playlist"
                            data-video-id="${song.videoId}"
                            data-title="${song.title}"
                            data-artist="${song.channel}"
                            ${isAdded ? 'disabled' : ''}>
                        ${isAdded ? 'Added' : 'Add'}
                    </button>
                    <a href="https://www.youtube.com/watch?v=${song.videoId}"
                       target="_blank"
                       class="btn btn-sm btn-outline-secondary">
                        Preview
                    </a>
                </div>
            </div>`;
    }

    document.querySelectorAll('.view-playlist-btn').forEach(button => {
        button.addEventListener('click', function() {
            const playlistId = this.dataset.playlistId;
            const title = this.dataset.title;
            
            $('#playlistModalTitle').text(title + ' - Songs');
            $('#playlistModalBody').html('<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading songs...</p></div>');
            $('#playlistModal').modal('show');
            
            $.get('get_playlist_items.php', { playlist_id: playlistId }, function(response) {
                if (response.success) {
                    let html = '';
                    if (response.songs.length > 0) {
                        html += '<div class="list-group">';
                        response.songs.forEach(song => {
                            html += getSongItemHtml(song);
                        });
                        html += '</div>';
                        if (response.hasMore) {
                            html += `<div class="mt-3 text-center">
                                <button class="btn btn-primary" id="loadMorePlaylist" data-playlist-id="${playlistId}" data-page-token="${response.nextPageToken}">
                                    Load More Songs
                                </button>
                            </div>`;
                        }
                    } else {
                        html = '<p class="text-center">No songs found in this playlist.</p>';
                    }
                    $('#playlistModalBody').html(html);
                } else {
                    $('#playlistModalBody').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            });
        });
    });

    $(document).on('click', '.add-from-playlist', function() {
        const button = $(this);
        const videoId = button.data('video-id');
        const title = button.data('title');
        const artist = button.data('artist');
        
        button.prop('disabled', true).text('Adding...');

        $.post('add_song_api.php', {
            title: title,
            artist: artist,
            video_link: `https://www.youtube.com/watch?v=${videoId}`
        }, function(response) {
            if (response.success) {
                button.text('Added!').removeClass('btn-success').addClass('btn-secondary');
                updateVideoButtonState(videoId);
                if (window.opener && !window.opener.closed) {
                    window.opener.location.reload();
                }
            } else {
                button.prop('disabled', false).text('Add');
                alert('Error: ' + (response.message || 'Could not add song.'));
            }
        });
    });

    $(document).on('click', '#loadMorePlaylist', function() {
        const button = $(this);
        const playlistId = button.data('playlist-id');
        const pageToken = button.data('page-token');

        button.prop('disabled', true).text('Loading...');

        $.get('get_playlist_items.php', {
            playlist_id: playlistId,
            page_token: pageToken
        }, function(response) {
            if (response.success && response.songs.length > 0) {
                let newSongsHtml = '';
                response.songs.forEach(function(song) {
                    // Use the is_added flag from the API response
                    const isAdded = song.is_added || existingVideoIds.has(song.videoId);
                    newSongsHtml += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${song.title}</strong><br>
                                <small class="text-muted">${song.channel}</small>
                            </div>
                            <div>
                                <button class="btn btn-sm ${isAdded ? 'btn-secondary' : 'btn-success'} add-from-playlist"
                                        data-video-id="${song.videoId}"
                                        data-title="${song.title}"
                                        data-artist="${song.channel}"
                                        ${isAdded ? 'disabled' : ''}>
                                    ${isAdded ? 'Added' : 'Add'}
                                </button>
                                <a href="https://www.youtube.com/watch?v=${song.videoId}"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-secondary">
                                    Preview
                                </a>
                            </div>
                        </div>`;
                });

                $('#playlistModalBody .list-group').append(newSongsHtml);
                button.parent().remove(); 

                if (response.hasMore) {
                    const newButtonHtml = `<div class="mt-3 text-center">
                        <button class="btn btn-primary" id="loadMorePlaylist" data-playlist-id="${playlistId}" data-page-token="${response.nextPageToken}">
                            Load More Songs
                        </button>
                    </div>`;
                    $('#playlistModalBody').append(newButtonHtml);
                }
            } else {
                button.parent().html('<p class="text-muted text-center">No more songs found.</p>');
            }
        }).fail(function() {
            alert('Failed to load more songs. Please try again.');
            button.prop('disabled', false).text('Load More Songs');
        });
    });
    </script>
</body>
</html>
