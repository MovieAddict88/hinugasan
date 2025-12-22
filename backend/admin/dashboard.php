<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

require_once "../includes/db.php";

// Song submission logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_song'])) {
    $title = trim($_POST['title']);
    $artist = trim($_POST['artist']);
    $source_type = trim($_POST['source_type']);
    $video_source = '';

    // Generate a unique song number
    do {
        $song_number = rand(100000, 999999);
        $sql_check = "SELECT id FROM songs WHERE song_number = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $song_number);
        $stmt_check->execute();
        $stmt_check->store_result();
        $is_duplicate = $stmt_check->num_rows > 0;
        $stmt_check->close();
    } while ($is_duplicate);

    if ($source_type === 'upload') {
        if (isset($_FILES["video_file"]) && $_FILES["video_file"]["error"] == 0) {
            // For InfinityFree, use relative path in htdocs
            $upload_dir = dirname(dirname(dirname(__DIR__))) . '/htdocs/uploads/';
            $filename = uniqid() . '_' . basename($_FILES["video_file"]["name"]);
            $target_file = $upload_dir . $filename;
            
            // Check file size (max 50MB for InfinityFree)
            if ($_FILES["video_file"]["size"] > 50000000) {
                $error = "File too large. Maximum size is 50MB.";
            } else {
                // Allow certain file formats
                $allowed_types = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
                $file_extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_types)) {
                    if (move_uploaded_file($_FILES["video_file"]["tmp_name"], $target_file)) {
                        $video_source = '/uploads/' . $filename;
                    } else {
                        $error = "Sorry, there was an error uploading your file.";
                    }
                } else {
                    $error = "Only video files are allowed (MP4, AVI, MOV, WMV, FLV, WEBM).";
                }
            }
            
            if (isset($error)) {
                echo "<div class='alert alert-danger'>$error</div>";
            }
        }
    } else {
        $video_source = trim($_POST['video_link']);
    }

    if (!empty($title) && !empty($artist) && !empty($video_source)) {
        $sql = "INSERT INTO songs (song_number, title, artist, source_type, video_source) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssss", $song_number, $title, $artist, $source_type, $video_source);
            if ($stmt->execute()) {
                $success = "Song added successfully! Song Number: " . $song_number;
            } else {
                $error = "Error adding song: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch songs
$songs = [];
$sql = "SELECT id, song_number, title, artist, video_source FROM songs ORDER BY id DESC LIMIT 50";
if ($result = $conn->query($sql)) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $songs[] = $row;
        }
    }
}

// Fetch active rooms
$rooms = [];
$room_sql = "SELECT r.*, COUNT(ru.id) as user_count 
             FROM rooms r 
             LEFT JOIN room_users ru ON r.id = ru.room_id AND ru.is_online = 1
             WHERE r.last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY r.id 
             ORDER BY r.last_active DESC 
             LIMIT 20";
if ($room_result = $conn->query($room_sql)) {
    if ($room_result->num_rows > 0) {
        while ($row = $room_result->fetch_assoc()) {
            $rooms[] = $row;
        }
    }
}

// Fetch statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM songs) as total_songs,
                (SELECT COUNT(*) FROM rooms WHERE last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_rooms,
                (SELECT COUNT(*) FROM room_users WHERE is_online = 1) as online_users";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Room Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        .wrapper{ width: 95%; max-width: 1400px; margin: auto; margin-top: 30px; padding: 0 15px; }
        .welcome-banner { margin-bottom: 30px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card i { font-size: 2.5rem; margin-bottom: 15px; }
        .stat-card h3 { font-size: 2rem; margin-bottom: 10px; }
        .stat-card p { color: #6c757d; margin: 0; }
        .stat-card.songs { border-top: 4px solid #4a00e0; }
        .stat-card.rooms { border-top: 4px solid #10b981; }
        .stat-card.users { border-top: 4px solid #3b82f6; }
        .section-title { margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #dee2e6; color: #495057; }
        .room-card { margin-bottom: 15px; transition: transform 0.2s; }
        .room-card:hover { transform: translateX(5px); }
        .room-header { display: flex; justify-content: space-between; align-items: center; }
        .room-code { background: #4a00e0; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; }
        .room-users { color: #6c757d; font-size: 0.9rem; }
        .room-actions { margin-top: 10px; }
        .youtube-result { display: flex; align-items: center; margin-bottom: 10px; cursor: pointer; border: 1px solid #ddd; padding: 10px; border-radius: 5px; transition: background-color 0.2s; }
        .youtube-result:hover { background-color: #f0f0f0; }
        .youtube-result img { width: 120px; height: 90px; margin-right: 15px; object-fit: cover; border-radius: 4px; }
        .youtube-result .info { flex-grow: 1; }
        .youtube-result .info h5 { margin: 0 0 5px 0; font-size: 1rem; font-weight: 600; }
        .youtube-result .info p { margin: 0; font-size: 0.85rem; color: #555; }
        .youtube-result .add-btn { margin-left: 15px; }
        @media (max-width: 768px) {
            .wrapper { width: 100%; padding: 15px; }
            .stats-cards { grid-template-columns: 1fr; }
            .room-header { flex-direction: column; align-items: flex-start; }
            .room-code { margin-top: 10px; }
        }
        .alert { margin: 15px 0; }
        .card-title { font-size: 1.1rem; }
        .table th { background: #f8f9fa; }
        .form-control-file { padding: 10px; border: 1px dashed #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="welcome-banner d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-sliders-h"></i> Admin Dashboard</h2>
                <p class="text-muted mb-0">Manage songs, rooms, and users</p>
            </div>
            <div>
                <span class="text-muted mr-3">Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</span>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-cards">
            <div class="stat-card songs">
                <i class="fas fa-music text-primary"></i>
                <h3><?php echo $stats['total_songs']; ?></h3>
                <p>Total Songs</p>
            </div>
            <div class="stat-card rooms">
                <i class="fas fa-users text-success"></i>
                <h3><?php echo $stats['active_rooms']; ?></h3>
                <p>Active Rooms</p>
            </div>
            <div class="stat-card users">
                <i class="fas fa-user-friends text-info"></i>
                <h3><?php echo $stats['online_users']; ?></h3>
                <p>Online Users</p>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Song Management -->
            <div class="col-lg-7">
                <h3 class="section-title"><i class="fas fa-music"></i> Add New Song</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="mb-4">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="Enter song title">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Artist *</label>
                            <input type="text" name="artist" class="form-control" required placeholder="Enter artist name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Source Type *</label>
                        <select name="source_type" class="form-control" id="source_type_selector">
                            <option value="link">YouTube Link</option>
                            <option value="upload">Upload Video</option>
                        </select>
                    </div>
                    <div class="form-group" id="video_link_group">
                        <label>YouTube Video Link *</label>
                        <input type="text" name="video_link" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                        <small class="form-text text-muted">Paste a YouTube video link</small>
                    </div>
                    <div class="form-group" id="video_upload_group" style="display: none;">
                         <label>Upload Video (Max 50MB) *</label>
                        <input type="file" name="video_file" class="form-control-file" accept="video/*">
                        <small class="form-text text-muted">Supported formats: MP4, AVI, MOV, WMV, FLV, WEBM</small>
                    </div>
                    <div class="form-group">
                        <input type="submit" name="submit_song" class="btn btn-primary" value="Add Song">
                        <small class="form-text text-muted">A unique 6-digit song number will be generated automatically</small>
                    </div>
                </form>

                <h3 class="section-title"><i class="fas fa-search"></i> Search YouTube</h3>
                <form id="youtube_search_form" action="youtube_results.php" method="get" target="_blank" class="mb-4">
                    <div class="input-group mb-3">
                        <input type="text" name="q" class="form-control" placeholder="Search for a karaoke song on YouTube..." required>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                </form>

                <h3 class="section-title"><i class="fas fa-list"></i> Recent Songs</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th width="100">Song #</th>
                                <th>Title</th>
                                <th>Artist</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($songs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No songs added yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($songs as $song): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($song['song_number']); ?></span></td>
                                    <td><?php echo htmlspecialchars($song['title']); ?></td>
                                    <td><?php echo htmlspecialchars($song['artist']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit_song.php?id=<?php echo $song['id']; ?>" class="btn btn-info" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="delete_song.php" method="post" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $song['id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this song?');" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <a href="<?php echo htmlspecialchars($song['video_source']); ?>" target="_blank" class="btn btn-secondary" title="View Source">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Room Management -->
            <div class="col-lg-5">
                <h3 class="section-title"><i class="fas fa-users"></i> Active Rooms</h3>
                
                <?php if (empty($rooms)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No active rooms at the moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                    <div class="card room-card">
                        <div class="card-body">
                            <div class="room-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($room['room_name']); ?>
                                </h5>
                                <span class="room-code"><?php echo $room['room_code']; ?></span>
                            </div>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($room['creator_name']); ?>
                                    <br>
                                    <i class="fas fa-clock"></i> Last active: <?php echo date('M j, g:i a', strtotime($room['last_active'])); ?>
                                </small>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="room-users">
                                    <i class="fas fa-user-friends"></i> <?php echo $room['user_count']; ?> user(s) online
                                </span>
                                <div class="room-actions">
                                    <?php if ($room['is_public']): ?>
                                        <span class="badge badge-success">Public</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Private</span>
                                    <?php endif; ?>
                                    <?php if ($room['password']): ?>
                                        <span class="badge badge-secondary"><i class="fas fa-lock"></i></span>
                                    <?php endif; ?>
                                    <form action="delete_room.php" method="post" style="display: inline;">
                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this room?');" title="Delete Room">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary" onclick="refreshRooms()">
                            <i class="fas fa-sync-alt"></i> Refresh Rooms
                        </button>
                    </div>
                <?php endif; ?>

                <h3 class="section-title mt-4"><i class="fas fa-cogs"></i> Quick Actions</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <button class="btn btn-block btn-outline-success" onclick="clearOldRooms()">
                            <i class="fas fa-trash"></i> Clear Old Rooms
                        </button>
                    </div>
                    <div class="col-md-6 mb-3">
                        <button class="btn btn-block btn-outline-warning" onclick="refreshCache()">
                            <i class="fas fa-sync"></i> Refresh Cache
                        </button>
                    </div>
                    <div class="col-md-6 mb-3">
                        <button class="btn btn-block btn-outline-info" onclick="showSystemInfo()">
                            <i class="fas fa-info-circle"></i> System Info
                        </button>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="../" target="_blank" class="btn btn-block btn-outline-secondary">
                            <i class="fas fa-external-link-alt"></i> View Site
                        </a>
                    </div>
                </div>
                
                <h3 class="section-title mt-4"><i class="fas fa-database"></i> Database Info</h3>
                <div class="card">
                    <div class="card-body">
                        <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                        <p><strong>Server:</strong> <?php echo DB_SERVER; ?></p>
                        <p><strong>Songs:</strong> <?php echo $stats['total_songs']; ?></p>
                        <p><strong>Active Rooms:</strong> <?php echo $stats['active_rooms']; ?></p>
                        <p><strong>Online Users:</strong> <?php echo $stats['online_users']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('source_type_selector').addEventListener('change', function() {
            var linkGroup = document.getElementById('video_link_group');
            var uploadGroup = document.getElementById('video_upload_group');
            if (this.value === 'upload') {
                linkGroup.style.display = 'none';
                uploadGroup.style.display = 'block';
                linkGroup.querySelector('input').removeAttribute('required');
                uploadGroup.querySelector('input').setAttribute('required', 'required');
            } else {
                linkGroup.style.display = 'block';
                uploadGroup.style.display = 'none';
                uploadGroup.querySelector('input').removeAttribute('required');
                linkGroup.querySelector('input').setAttribute('required', 'required');
            }
        });

        document.getElementById('youtube_search_form').addEventListener('submit', function(e) {
            e.preventDefault();
            const query = this.querySelector('input[name="q"]').value;
            if (query) {
                window.open(this.action + '?q=' + encodeURIComponent(query), 'youtube_results', 'width=800,height=600');
            }
        });

        function refreshRooms() {
            location.reload();
        }

        function clearOldRooms() {
            if (confirm('Clear rooms inactive for more than 24 hours?')) {
                fetch('clear_rooms.php')
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        location.reload();
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }

        function refreshCache() {
            fetch('refresh_cache.php')
                .then(response => response.json())
                .then(data => {
                    alert('Cache refreshed: ' + data.message);
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
        }

        function showSystemInfo() {
            fetch('system_info.php')
                .then(response => response.json())
                .then(data => {
                    let info = 'System Information:\n\n';
                    info += 'PHP Version: ' + data.php_version + '\n';
                    info += 'MySQL Version: ' + data.mysql_version + '\n';
                    info += 'Server: ' + data.server_name + '\n';
                    info += 'Uptime: ' + (data.uptime || 'N/A') + '\n';
                    info += 'Memory Usage: ' + data.memory_usage + '\n';
                    alert(info);
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
        }

        // Initialize tooltips
        $(function () {
            $('[title]').tooltip();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>