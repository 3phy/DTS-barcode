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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['document_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit();
}

try {
    // Get user's department first
    $userDeptQuery = "SELECT department_id FROM users WHERE id = ?";
    $userDeptStmt = $db->prepare($userDeptQuery);
    $userDeptStmt->execute([$payload['user_id']]);
    $userDept = $userDeptStmt->fetch(PDO::FETCH_ASSOC);
    $userDepartmentId = $userDept ? $userDept['department_id'] : null;
    
    if (!$userDepartmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User must be assigned to a department to cancel documents']);
        exit();
    }
    
    // Check if document exists and is in the user's department, and user is not the sender
    $checkQuery = "SELECT d.* 
                   FROM documents d 
                   WHERE d.id = ? AND d.uploaded_by != ? AND (d.current_department_id = ? OR d.department_id = ?)";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$input['document_id'], $payload['user_id'], $userDepartmentId, $userDepartmentId]);
    $document = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found, not in your department, or you cannot cancel your own documents']);
        exit();
    }
    
    // Check if document is in a cancellable state
    if (!in_array($document['status'], ['pending', 'outgoing', 'received'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document cannot be cancelled in its current state']);
        exit();
    }
    
    // Update document status to rejected
    $updateQuery = "UPDATE documents SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([$input['document_id']]);
    
    if ($result) {
        // Log the activity
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'cancel_document',
                "Cancelled document '{$document['title']}' - marked as rejected"
            ]);
        } catch (Exception $logError) {
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Document cancelled successfully - marked as rejected',
            'document' => [
                'id' => $input['document_id'],
                'status' => 'rejected'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel document']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
