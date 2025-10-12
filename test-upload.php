<?php
// Test script to diagnose upload issues
echo "<h2>Upload System Diagnostics</h2>";

// Check PHP configuration
echo "<h3>PHP Configuration</h3>";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post max size: " . ini_get('post_max_size') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . "<br>";
echo "File uploads enabled: " . (ini_get('file_uploads') ? 'Yes' : 'No') . "<br>";

// Check uploads directory
echo "<h3>Uploads Directory</h3>";
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    echo "✅ Uploads directory exists<br>";
    echo "Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";
    echo "Directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";
} else {
    echo "❌ Uploads directory does not exist<br>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Created uploads directory<br>";
    } else {
        echo "❌ Failed to create uploads directory<br>";
    }
}

// Check database connection
echo "<h3>Database Connection</h3>";
try {
    require_once 'api/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Check if routing table exists
    $query = "SHOW TABLES LIKE 'document_routing'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $routing_table = $stmt->fetch();
    
    if ($routing_table) {
        echo "✅ Routing table exists<br>";
        
        // Check routing rules
        $query = "SELECT COUNT(*) as count FROM document_routing WHERE is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Found " . $count['count'] . " active routing rules<br>";
    } else {
        echo "❌ Routing table does not exist - you need to run the migration script<br>";
    }
    
    // Check departments
    $query = "SELECT COUNT(*) as count FROM departments WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $dept_count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Found " . $dept_count['count'] . " active departments<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Check if user is logged in (simulate)
echo "<h3>Authentication Test</h3>";
if (isset($_GET['test_auth'])) {
    try {
        require_once 'api/config/jwt.php';
        $jwt = new JWT();
        
        // Test with a dummy token (this will fail but shows if JWT class works)
        echo "✅ JWT class loaded successfully<br>";
    } catch (Exception $e) {
        echo "❌ JWT error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "ℹ️ Add ?test_auth=1 to URL to test JWT functionality<br>";
}

// Test file upload simulation
echo "<h3>File Upload Test</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    echo "File name: " . $file['name'] . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "Upload error: " . $file['error'] . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "✅ File upload successful<br>";
        
        // Test file extension
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            echo "✅ File type allowed<br>";
        } else {
            echo "❌ File type not allowed: " . $file_extension . "<br>";
        }
        
        // Test file size
        if ($file['size'] <= 10 * 1024 * 1024) {
            echo "✅ File size within limit<br>";
        } else {
            echo "❌ File too large: " . round($file['size'] / 1024 / 1024, 2) . " MB<br>";
        }
    } else {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        echo "❌ Upload error: " . ($upload_errors[$file['error']] ?? 'Unknown error') . "<br>";
    }
} else {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="test_file" required>';
    echo '<button type="submit">Test Upload</button>';
    echo '</form>';
}

echo "<h3>Common Issues & Solutions</h3>";
echo "<ul>";
echo "<li><strong>Routing table missing:</strong> Run database/migration_add_routing.sql</li>";
echo "<li><strong>File too large:</strong> Check PHP upload_max_filesize and post_max_size</li>";
echo "<li><strong>Permission denied:</strong> Check uploads directory permissions</li>";
echo "<li><strong>Authentication failed:</strong> Make sure you're logged in</li>";
echo "<li><strong>Department not selected:</strong> Select a destination department</li>";
echo "</ul>";
?>
