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

if (!$input || (!isset($input['document_id']) && !isset($input['barcode']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document ID or barcode is required']);
    exit();
}

try {
    // Get user's department
    $userDeptQuery = "SELECT department_id FROM users WHERE id = ?";
    $userDeptStmt = $db->prepare($userDeptQuery);
    $userDeptStmt->execute([$payload['user_id']]);
    $userDept = $userDeptStmt->fetch(PDO::FETCH_ASSOC);
    $userDepartmentId = $userDept ? $userDept['department_id'] : null;
    
    // Build query based on whether we have document_id or barcode
    if (isset($input['barcode'])) {
        // Find document by barcode - check if it's pending or outgoing, and user is not the sender
        $checkQuery = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                       FROM documents d 
                       LEFT JOIN users u ON d.uploaded_by = u.id 
                       LEFT JOIN departments dept ON d.department_id = dept.id 
                       WHERE d.barcode = ? AND d.status IN ('pending', 'outgoing') AND d.uploaded_by != ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$input['barcode'], $payload['user_id']]);
    } else {
        // Find document by ID - check if it's pending or outgoing, and user is not the sender
        $checkQuery = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                       FROM documents d 
                       LEFT JOIN users u ON d.uploaded_by = u.id 
                       LEFT JOIN departments dept ON d.department_id = dept.id 
                       WHERE d.id = ? AND d.status IN ('pending', 'outgoing') AND d.uploaded_by != ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$input['document_id'], $payload['user_id']]);
    }
    
    $document = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        // Check if document exists but is not in the right state
        $debug_query = "SELECT d.*, curr_dept.name as current_dept_name 
                        FROM documents d 
                        LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                        WHERE " . (isset($input['barcode']) ? "d.barcode = ?" : "d.id = ?");
        $debug_stmt = $db->prepare($debug_query);
        $debug_stmt->execute([isset($input['barcode']) ? $input['barcode'] : $input['document_id']]);
        $debug_doc = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($debug_doc) {
            $message = "Document found but cannot be received. ";
            $message .= "Status: " . $debug_doc['status'] . " (needs 'pending' or 'outgoing'). ";
            $message .= "Uploaded by: " . $debug_doc['uploaded_by'] . " (you cannot receive your own documents)";
        } else {
            $message = "Document not found with " . (isset($input['barcode']) ? "barcode" : "ID") . ": " . (isset($input['barcode']) ? $input['barcode'] : $input['document_id']);
        }
        
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
    
    // Update document status to received but keep original current_department_id
    $updateQuery = "UPDATE documents SET status = 'received', received_by = ?, received_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([$payload['user_id'], $document['id']]);
    
    if ($result) {
        // Log the activity
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'receive_document',
                "Received document '{$document['title']}' from {$document['uploaded_by_name']}"
            ]);
        } catch (Exception $logError) {
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Document received successfully',
            'document' => [
                'id' => $document['id'],
                'status' => 'received',
                'received_by' => $payload['user_id'],
                'received_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to receive document']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
