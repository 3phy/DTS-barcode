<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../config/jwt.php';

$database = new Database();
$db = $database->getConnection();
$jwt = new JWT();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Verify token
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($auth_header) || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit();
}

$token = $matches[1];
$payload = $jwt->decode($token);

if (!$payload || $payload['exp'] < time()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit();
}

$user_id = $payload['user_id'];

if (!isset($_POST['title']) || !isset($_FILES['file']) || !isset($_POST['department_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title, file, and department are required']);
    exit();
}

$title = $_POST['title'];
$description = isset($_POST['description']) ? $_POST['description'] : '';
$department_id = $_POST['department_id'];
$file = $_FILES['file'];

// Validate file
$allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit();
}

if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit();
}

// Get current department automatically
$dept_query = "SELECT department_id FROM users WHERE id = :user_id LIMIT 1";
$dept_stmt = $db->prepare($dept_query);
$dept_stmt->bindParam(':user_id', $user_id);
$dept_stmt->execute();
$current_department_id = $dept_stmt->fetchColumn();

if (!$current_department_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'You need to be assigned to a department before uploading documents. Please contact your administrator.'
    ]);
    exit();
}

// Generate unique barcode
$barcode = 'DOC' . time() . rand(1000, 9999);

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$filename = $barcode . '_' . $file['name'];
$file_path = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $file_path)) {
    try {
        // Check if routing table exists
        $table_check = $db->query("SHOW TABLES LIKE 'document_routing'");
        $routing_table_exists = $table_check->rowCount() > 0;
        
        $routing_rule = null;
        
        if ($routing_table_exists) {
            // Check if there's a routing rule for this path
            $routing_query = "SELECT intermediate_department_id FROM document_routing 
                             WHERE from_department_id = :from_dept AND to_department_id = :to_dept AND is_active = 1";
            $routing_stmt = $db->prepare($routing_query);
            $routing_stmt->bindParam(':from_dept', $current_department_id);
            $routing_stmt->bindParam(':to_dept', $department_id);
            $routing_stmt->execute();
            $routing_rule = $routing_stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no routing rule exists, create one dynamically
            if (!$routing_rule) {
                // Find intermediate department using routing preferences
                $preference_query = "SELECT drp1.can_route_through as intermediate_dept_id
                                    FROM department_routing_preferences drp1
                                    JOIN department_routing_preferences drp2 ON drp1.can_route_through = drp2.can_route_through
                                    WHERE drp1.department_id = :from_dept AND drp2.department_id = :to_dept 
                                    AND drp1.is_active = 1 AND drp2.is_active = 1
                                    LIMIT 1";
                
                $preference_stmt = $db->prepare($preference_query);
                $preference_stmt->bindParam(':from_dept', $current_department_id);
                $preference_stmt->bindParam(':to_dept', $department_id);
                $preference_stmt->execute();
                $preference = $preference_stmt->fetch(PDO::FETCH_ASSOC);
                
                $intermediate_department_id = $preference ? $preference['intermediate_dept_id'] : null;
                
                // Only create routing rule if there's an intermediate department (3+ departments in path)
                if ($intermediate_department_id) {
                    $create_routing_query = "INSERT INTO document_routing (from_department_id, to_department_id, intermediate_department_id) 
                                           VALUES (:from_dept, :to_dept, :intermediate_dept)";
                    $create_routing_stmt = $db->prepare($create_routing_query);
                    $create_routing_stmt->bindParam(':from_dept', $current_department_id);
                    $create_routing_stmt->bindParam(':to_dept', $department_id);
                    $create_routing_stmt->bindParam(':intermediate_dept', $intermediate_department_id);
                    $create_routing_stmt->execute();
                    
                    // Set the routing rule for use below
                    $routing_rule = ['intermediate_department_id' => $intermediate_department_id];
                }
                // For direct routes (2 departments), no routing rule is created
            }
        }
        
        // Documents start as "outgoing" in sender's department - ready to be received
        $initial_status = 'outgoing';
        $initial_current_department = $current_department_id; // Keep in sender's department
        
        $query = "INSERT INTO documents (title, description, filename, file_path, file_size, file_type, barcode, department_id, current_department_id, uploaded_by, status) 
                  VALUES (:title, :description, :filename, :file_path, :file_size, :file_type, :barcode, :department_id, :current_department_id, :uploaded_by, :status)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_size', $file['size']);
        $stmt->bindParam(':file_type', $file_extension);
        $stmt->bindParam(':barcode', $barcode);
        $stmt->bindParam(':department_id', $department_id);
        $stmt->bindParam(':current_department_id', $initial_current_department);
        $stmt->bindParam(':uploaded_by', $user_id);
        $stmt->bindParam(':status', $initial_status);
        
        if ($stmt->execute()) {
            // Log the activity
            try {
                $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
                $logStmt = $db->prepare($logQuery);
                $logStmt->execute([
                    $user_id,
                    'upload_document',
                    "Uploaded document '{$title}' and sent to department ID {$department_id}"
                ]);
            } catch (Exception $logError) {
                error_log("Could not log activity: " . $logError->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'barcode' => $barcode,
                'routing_info' => $routing_rule ? [
                    'has_intermediate' => !empty($routing_rule['intermediate_department_id']),
                    'intermediate_department_id' => $routing_rule['intermediate_department_id']
                ] : null
            ]);
        } else {
            unlink($file_path); // Delete uploaded file if database insert fails
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save document to database']);
        }
    } catch (Exception $e) {
        unlink($file_path); // Delete uploaded file if database error
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>
