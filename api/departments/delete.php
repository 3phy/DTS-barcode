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

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department ID is required']);
    exit();
}

try {
    // Debug: Log the input data
    error_log("Department delete input: " . json_encode($input));
    
    // Check if department exists and get its name
    $checkQuery = "SELECT id, name FROM departments WHERE id = ? AND is_active = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$input['id']]);
    $existingDept = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingDept) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        exit();
    }
    
    // Check if department has associated users
    $usersQuery = "SELECT COUNT(*) as user_count FROM users WHERE department_id = ? AND is_active = 1";
    $usersStmt = $db->prepare($usersQuery);
    $usersStmt->execute([$input['id']]);
    $userCount = $usersStmt->fetch(PDO::FETCH_ASSOC)['user_count'];
    
    if ($userCount > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete department with associated users. Please reassign users first.']);
        exit();
    }
    
    // Check if department has associated documents
    $docsQuery = "SELECT COUNT(*) as doc_count FROM documents WHERE department_id = ?";
    $docsStmt = $db->prepare($docsQuery);
    $docsStmt->execute([$input['id']]);
    $docCount = $docsStmt->fetch(PDO::FETCH_ASSOC)['doc_count'];
    
    if ($docCount > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete department with associated documents. Please reassign documents first.']);
        exit();
    }
    
    // Soft delete department (set is_active to false)
    $query = "UPDATE departments SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$input['id']]);
    
    if ($result) {
        // Log the activity (if table exists)
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'delete_department',
                "Deleted department: " . $existingDept['name']
            ]);
        } catch (Exception $logError) {
            // Log table might not exist, continue without logging
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Department deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete department']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
