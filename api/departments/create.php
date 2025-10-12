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
$auth_header = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
} else {
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
}   

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

// Check if user is admin
if ($payload['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['name']) || empty(trim($input['name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department name is required']);
    exit();
}

try {
    // Debug: Log the input data
    error_log("Department create input: " . json_encode($input));
    
    // Check if department already exists
    $checkQuery = "SELECT id FROM departments WHERE name = ? AND is_active = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([trim($input['name'])]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department already exists']);
        exit();
    }
    
    // Insert new department
    $query = "INSERT INTO departments (name, description) VALUES (?, ?)";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        trim($input['name']),
        isset($input['description']) ? trim($input['description']) : null
    ]);
    
    if ($result) {
        $departmentId = $db->lastInsertId();
        
        // Log the activity (if table exists)
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'create_department',
                "Created department: " . trim($input['name'])
            ]);
        } catch (Exception $logError) {
            // Log table might not exist, continue without logging
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Department created successfully',
            'department' => [
                'id' => $departmentId,
                'name' => trim($input['name']),
                'description' => isset($input['description']) ? trim($input['description']) : null
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create department']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
