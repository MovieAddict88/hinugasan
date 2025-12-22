<?php
// Database configuration for InfinityFree
define('DB_SERVER', 'sql106.infinityfree.com');
define('DB_USERNAME', 'if0_40702186');
define('DB_PASSWORD', 'IQjknMN0aks');
define('DB_NAME', 'if0_40702186_videoke');


// Base paths for InfinityFree
define('BASE_DIR', dirname(dirname(dirname(__DIR__))) . '/htdocs');
define('UPLOADS_DIR', BASE_DIR . '/uploads');

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Try to create database if it doesn't exist (InfinityFree usually creates it)
    try {
        $temp_conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);
        if (!$temp_conn->connect_error) {
            $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if ($temp_conn->query($sql) === TRUE) {
                $temp_conn->select_db(DB_NAME);
                $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                
                // Run schema if needed
                $schema_file = dirname(dirname(__FILE__)) . '/database/schema.sql';
                if (file_exists($schema_file)) {
                    $schema_sql = file_get_contents($schema_file);
                    $conn->multi_query($schema_sql);
                }
            }
            $temp_conn->close();
        }
    } catch (Exception $e) {
        // Silent fail for InfinityFree
    }
    
    // If still error, show message
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed. Please check your database credentials.']));
    }
}

// Set charset to utf8mb4 for emoji support
$conn->set_charset("utf8mb4");

// Helper function for secure queries
function db_query($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['error' => 'Prepare failed: ' . $conn->error];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        return ['error' => 'Execute failed: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
    }
    
    $stmt->close();
    
    return $data;
}

// Helper function for single row queries
function db_query_one($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    
    if ($result) {
        $result->free();
    }
    
    $stmt->close();
    
    return $row;
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}
?>