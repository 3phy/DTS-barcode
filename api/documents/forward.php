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
    // Check if document exists and is in the user's department
    $checkQuery = "SELECT d.*, u.department_id as user_dept_id 
                   FROM documents d 
                   LEFT JOIN users u ON d.current_department_id = u.department_id
                   WHERE d.id = ? AND u.id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$input['document_id'], $payload['user_id']]);
    $document = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found or not in your department']);
        exit();
    }
    
    // Check if routing table exists
    $table_check = $db->query("SHOW TABLES LIKE 'document_routing'");
    $routing_table_exists = $table_check->rowCount() > 0;
    
    if (!$routing_table_exists) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Routing system not available']);
        exit();
    }
    
    // Find the routing rule for this document
    $routing_query = "SELECT dr.*, 
                             from_dept.name as from_department_name,
                             to_dept.name as to_department_name,
                             inter_dept.name as intermediate_department_name
                      FROM document_routing dr
                      LEFT JOIN departments from_dept ON dr.from_department_id = from_dept.id
                      LEFT JOIN departments to_dept ON dr.to_department_id = to_dept.id
                      LEFT JOIN departments inter_dept ON dr.intermediate_department_id = inter_dept.id
                      WHERE dr.from_department_id = ? AND dr.to_department_id = ? AND dr.is_active = 1";
    
    $routing_stmt = $db->prepare($routing_query);
    $routing_stmt->execute([$document['current_department_id'], $document['department_id']]);
    $routing_rule = $routing_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$routing_rule) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No routing rule found for this document']);
        exit();
    }
    
    // Determine the next department in the routing path
    $next_department_id = null;
    $next_department_name = '';
    $is_final_destination = false;
    
    if ($document['current_department_id'] == $routing_rule['from_department_id']) {
        // We're at the starting department, go to intermediate or final
        if ($routing_rule['intermediate_department_id']) {
            $next_department_id = $routing_rule['intermediate_department_id'];
            $next_department_name = $routing_rule['intermediate_department_name'];
        } else {
            $next_department_id = $routing_rule['to_department_id'];
            $next_department_name = $routing_rule['to_department_name'];
            $is_final_destination = true;
        }
    } else if ($document['current_department_id'] == $routing_rule['intermediate_department_id']) {
        // We're at the intermediate department, go to final destination
        $next_department_id = $routing_rule['to_department_id'];
        $next_department_name = $routing_rule['to_department_name'];
        $is_final_destination = true;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document is not at a valid position in the routing path']);
        exit();
    }
    
    // Update document to next department
    $updateQuery = "UPDATE documents SET current_department_id = ?, status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([$next_department_id, $input['document_id']]);
    
    if ($result) {
        // Log the activity
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'forward_document',
                "Forwarded document '{$document['title']}' to {$next_department_name} department"
            ]);
        } catch (Exception $logError) {
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => $is_final_destination ? 'Document forwarded to final destination' : 'Document forwarded to next department',
            'document' => [
                'id' => $input['document_id'],
                'status' => 'pending',
                'current_department_name' => $next_department_name,
                'is_final_destination' => $is_final_destination
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to forward document']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
