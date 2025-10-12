<?php
// Test script to verify Document Tracking System setup
echo "<h1>Document Tracking System - Setup Test</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    require_once 'api/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✅ Database connection successful<br>";
        
        // Test 2: Check if tables exist
        echo "<h2>2. Database Tables Test</h2>";
        $tables = ['users', 'documents'];
        foreach ($tables as $table) {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists<br>";
            } else {
                echo "❌ Table '$table' missing<br>";
            }
        }
        
        // Test 3: Check default users
        echo "<h2>3. Default Users Test</h2>";
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count >= 2) {
            echo "✅ Default users found ($count users)<br>";
        } else {
            echo "❌ Default users missing (found $count users)<br>";
        }
        
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 4: File Permissions
echo "<h2>4. File Permissions Test</h2>";
$upload_dir = 'uploads/';
if (is_dir($upload_dir) && is_writable($upload_dir)) {
    echo "✅ Uploads directory is writable<br>";
} else {
    echo "❌ Uploads directory not writable<br>";
}

// Test 5: PHP Extensions
echo "<h2>5. PHP Extensions Test</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'fileinfo'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext extension loaded<br>";
    } else {
        echo "❌ $ext extension missing<br>";
    }
}

// Test 6: API Endpoints
echo "<h2>6. API Endpoints Test</h2>";
$endpoints = [
    'api/auth/login.php',
    'api/documents/list.php',
    'api/dashboard/stats.php'
];

foreach ($endpoints as $endpoint) {
    if (file_exists($endpoint)) {
        echo "✅ $endpoint exists<br>";
    } else {
        echo "❌ $endpoint missing<br>";
    }
}

echo "<h2>Setup Test Complete</h2>";
echo "<p>If all tests show ✅, your setup is ready!</p>";
echo "<p>You can now run: <code>npm run electron-dev</code></p>";
?>
