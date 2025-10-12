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

if (!$input || !isset($input['id']) || !isset($input['name']) || empty(trim($input['name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department ID and name are required']);
    exit();
}

try {
    // Check if department exists
    $checkQuery = "SELECT id, name FROM departments WHERE id = ? AND is_active = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$input['id']]);
    $existingDept = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingDept) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        exit();
    }
    
    // Check if another department with the same name exists
    $duplicateQuery = "SELECT id FROM departments WHERE name = ? AND id != ? AND is_active = 1";
    $duplicateStmt = $db->prepare($duplicateQuery);
    $duplicateStmt->execute([trim($input['name']), $input['id']]);
    
    if ($duplicateStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department name already exists']);
        exit();
    }
    
    // Update department
    $query = "UPDATE departments SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        trim($input['name']),
        isset($input['description']) ? trim($input['description']) : null,
        $input['id']
    ]);
    
    if ($result) {
        // Log the activity (if table exists)
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'update_department',
                "Updated department from '{$existingDept['name']}' to '" . trim($input['name']) . "'"
            ]);
        } catch (Exception $logError) {
            // Log table might not exist, continue without logging
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Department updated successfully',
            'department' => [
                'id' => $input['id'],
                'name' => trim($input['name']),
                'description' => isset($input['description']) ? trim($input['description']) : null
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update department']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
