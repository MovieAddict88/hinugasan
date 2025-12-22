<?php
// IMPORTANT: This file should be removed after installation.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials - Adjust if necessary
define('DB_SERVER', 'sql106.infinityfree.com');
define('DB_USERNAME', 'if0_40702186');
define('DB_PASSWORD', 'IQjknMN0aks');
define('DB_NAME', 'if0_40702186_videoke');


// Admin user credentials - Change these!
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'password123');

// Get the base directory (htdocs folder)
define('BASE_DIR', dirname(dirname(__DIR__)) . '/htdocs');
define('UPLOADS_DIR', BASE_DIR . '/uploads');

echo "<!DOCTYPE html><html><head><title>Karaoke System Installation</title>
      <meta name='viewport' content='width=device-width, initial-scale=1'>
      <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
      <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .container { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; color: #333; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { color: #4a00e0; text-align: center; margin-bottom: 30px; }
        .step { background: #f8f9fa; border-left: 4px solid #4a00e0; padding: 15px; margin: 15px 0; border-radius: 0 10px 10px 0; }
        .success { color: #10b981; border-left-color: #10b981; }
        .error { color: #ef4444; border-left-color: #ef4444; }
        .warning { color: #f59e0b; border-left-color: #f59e0b; }
        .info-box { background: #e3f2fd; border: 1px solid #bbdefb; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .btn { display: inline-block; background: #4a00e0; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 10px 5px; transition: all 0.3s; border: none; cursor: pointer; }
        .btn:hover { background: #3a00b0; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; }
        .btn-success { background: #10b981; }
        .credentials { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        code { background: #f1f3f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
      </style>
      </head><body>";

echo "<div class='container'>";
echo "<h1><i class='fas fa-microphone'></i> Karaoke System Installation</h1>";

// --- 1. Connect to MySQL Server ---
echo "<div class='step'>";
echo "<h3><i class='fas fa-server'></i> Step 1: Connect to MySQL Server</h3>";
echo "<p>Attempting to connect to MySQL server...</p>";

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);
if ($conn->connect_error) {
    echo "<p class='error'><strong><i class='fas fa-times-circle'></i> Connection failed:</strong> " . $conn->connect_error . "</p>";
    echo "<p class='warning'>Please check your database credentials in the install.php file.</p>";
    echo "</div></div></body></html>";
    die();
}
echo "<p class='success'><i class='fas fa-check-circle'></i> Connected successfully to MySQL server.</p>";
echo "</div>";

// --- 2. Create Database ---
echo "<div class='step'>";
echo "<h3><i class='fas fa-database'></i> Step 2: Create Database</h3>";
echo "<p>Attempting to create database '<code>" . DB_NAME . "</code>'...</p>";

$sql_create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql_create_db) === TRUE) {
    echo "<p class='success'><i class='fas fa-check-circle'></i> Database '<code>" . DB_NAME . "</code>' created or already exists.</p>";
} else {
    echo "<p class='error'><strong><i class='fas fa-times-circle'></i> Error creating database:</strong> " . $conn->error . "</p>";
    echo "<p class='warning'>Your InfinityFree username is 'if0_40117343' and the database name must match this pattern.</p>";
    echo "</div></div></body></html>";
    die();
}
$conn->select_db(DB_NAME);
echo "</div>";

// --- 3. Create Tables from schema.sql ---
echo "<div class='step'>";
echo "<h3><i class='fas fa-table'></i> Step 3: Create Tables</h3>";
echo "<p>Attempting to create tables from <code>schema.sql</code>...</p>";

$sql_schema = file_get_contents(__DIR__ . '/schema.sql');
if ($sql_schema === false) {
    echo "<p class='error'><strong><i class='fas fa-times-circle'></i> Error:</strong> Could not read <code>schema.sql</code>.</p>";
    echo "</div></div></body></html>";
    die();
}

// Split SQL by semicolon
$queries = explode(';', $sql_schema);
$success_count = 0;
$error_count = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        if ($conn->query($query . ';') === FALSE) {
            if (strpos($conn->error, "already exists") === false) {
                echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Query failed: " . $conn->error . "</p>";
                $error_count++;
            } else {
                $success_count++;
            }
        } else {
            $success_count++;
        }
    }
}

if ($error_count > 0) {
    echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Some tables may already exist or had errors.</p>";
} else {
    echo "<p class='success'><i class='fas fa-check-circle'></i> Tables created successfully.</p>";
}
echo "</div>";

// --- 4. Create Admin User ---
echo "<div class='step'>";
echo "<h3><i class='fas fa-user-shield'></i> Step 4: Create Admin User</h3>";
echo "<p>Attempting to create admin user '<code>" . ADMIN_USERNAME . "</code>'...</p>";

$username = ADMIN_USERNAME;
$password_hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);

$stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt_check->bind_param("s", $username);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Admin user '<code>" . $username . "</code>' already exists.</p>";
} else {
    $stmt_insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt_insert->bind_param("ss", $username, $password_hash);
    if ($stmt_insert->execute()) {
        echo "<p class='success'><i class='fas fa-check-circle'></i> Admin user created successfully.</p>";
    } else {
        echo "<p class='error'><strong><i class='fas fa-times-circle'></i> Error creating admin user:</strong> " . $stmt_insert->error . "</p>";
    }
    $stmt_insert->close();
}
$stmt_check->close();

// Display credentials
echo "<div class='credentials'>";
echo "<h4><i class='fas fa-key'></i> Admin Credentials</h4>";
echo "<p><strong>Username:</strong> " . ADMIN_USERNAME . "</p>";
echo "<p><strong>Password:</strong> " . ADMIN_PASSWORD . "</p>";
echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Please change this password after logging in!</p>";
echo "</div>";
echo "</div>";

// --- 5. Test Sample Data ---
echo "<div class='step'>";
echo "<h3><i class='fas fa-vial'></i> Step 5: Test Configuration</h3>";

// Test YouTube API
if (defined('YOUTUBE_API_KEY') && YOUTUBE_API_KEY != 'AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY') {
    echo "<p class='success'><i class='fas fa-check-circle'></i> YouTube API key configured.</p>";
} else {
    echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Using default YouTube API key. For production, get your own from <a href='https://console.cloud.google.com/' target='_blank'>Google Cloud Console</a>.</p>";
}

// Check uploads directory within htdocs
$uploads_dir = dirname(dirname(__DIR__)) . '/htdocs/uploads';
echo "<p>Checking uploads directory: <code>" . $uploads_dir . "</code></p>";

if (!is_dir($uploads_dir)) {
    if (mkdir($uploads_dir, 0755, true)) {
        echo "<p class='success'><i class='fas fa-check-circle'></i> Created uploads directory.</p>";
    } else {
        echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Could not create uploads directory. Please create it manually in your htdocs folder.</p>";
        echo "<p>Create folder: <code>htdocs/uploads</code> with permissions 755</p>";
    }
} else {
    echo "<p class='success'><i class='fas fa-check-circle'></i> Uploads directory exists.</p>";
}

echo "</div>";

// --- 6. Finalization ---
echo "<div class='info-box'>";
echo "<h3><i class='fas fa-flag-checkered'></i> Installation Complete!</h3>";
echo "<p><i class='fas fa-check-circle text-success'></i> Your karaoke system has been successfully installed.</p>";
echo "<p><i class='fas fa-info-circle text-primary'></i> <strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Delete this installation file for security</li>";
echo "<li>Configure your YouTube API key in config.php</li>";
echo "<li>Add songs through the admin dashboard</li>";
echo "<li>Test the room creation feature</li>";
echo "</ul>";

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='../backend/admin/' class='btn btn-success'><i class='fas fa-sign-in-alt'></i> Go to Admin Login</a>";
echo "<a href='../frontend/' class='btn'><i class='fas fa-play-circle'></i> Go to Karaoke Player</a>";
echo "<button onclick='deleteInstallFile()' class='btn btn-danger'><i class='fas fa-trash'></i> Delete This Installation File</button>";
echo "</div>";
echo "</div>";

echo "<script>
function deleteInstallFile() {
    if (confirm('Are you sure you want to delete the installation file? This action cannot be undone.')) {
        fetch(window.location.href + '?delete=true')
            .then(response => response.text())
            .then(data => {
                alert('Installation file deleted. Redirecting...');
                window.location.href = '../frontend/';
            })
            .catch(error => {
                alert('Error deleting file. Please delete it manually.');
            });
    }
}
</script>";

// Handle deletion
if (isset($_GET['delete']) && $_GET['delete'] == 'true') {
    if (unlink(__FILE__)) {
        echo "<p class='success'>Installation file deleted successfully.</p>";
    } else {
        echo "<p class='error'>Could not delete installation file. Please delete it manually.</p>";
    }
}

$conn->close();
echo "</div></body></html>";
?>