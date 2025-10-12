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

if (!$input || !isset($input['document_id']) || !isset($input['to_department_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document ID and destination department are required']);
    exit();
}

try {
    // Check if document exists and belongs to the user
    $checkQuery = "SELECT d.*, u.department_id as sender_dept_id 
                   FROM documents d 
                   LEFT JOIN users u ON d.uploaded_by = u.id 
                   WHERE d.id = ? AND d.uploaded_by = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$input['document_id'], $payload['user_id']]);
    $document = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
        exit();
    }
    
    // Check if destination department exists
    $deptQuery = "SELECT id, name FROM departments WHERE id = ? AND is_active = 1";
    $deptStmt = $db->prepare($deptQuery);
    $deptStmt->execute([$input['to_department_id']]);
    $destinationDept = $deptStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$destinationDept) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Destination department not found']);
        exit();
    }
    
    // Check if routing table exists and find routing path
    $table_check = $db->query("SHOW TABLES LIKE 'document_routing'");
    $routing_table_exists = $table_check->rowCount() > 0;
    
    $target_department_id = $input['to_department_id'];
    $routing_info = null;
    
    if ($routing_table_exists) {
        // Check if there's a routing rule for this path
        $routing_query = "SELECT intermediate_department_id FROM document_routing 
                         WHERE from_department_id = ? AND to_department_id = ? AND is_active = 1";
        $routing_stmt = $db->prepare($routing_query);
        $routing_stmt->execute([$document['current_department_id'], $input['to_department_id']]);
        $routing_rule = $routing_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no routing rule exists, create one dynamically
        if (!$routing_rule) {
            // Find intermediate department using routing preferences
            $preference_query = "SELECT drp1.can_route_through as intermediate_dept_id
                                FROM department_routing_preferences drp1
                                JOIN department_routing_preferences drp2 ON drp1.can_route_through = drp2.can_route_through
                                WHERE drp1.department_id = ? AND drp2.department_id = ? 
                                AND drp1.is_active = 1 AND drp2.is_active = 1
                                LIMIT 1";
            
            $preference_stmt = $db->prepare($preference_query);
            $preference_stmt->execute([$document['current_department_id'], $input['to_department_id']]);
            $preference = $preference_stmt->fetch(PDO::FETCH_ASSOC);
            
            $intermediate_department_id = $preference ? $preference['intermediate_dept_id'] : null;
            
            // Create the routing rule
            $create_routing_query = "INSERT INTO document_routing (from_department_id, to_department_id, intermediate_department_id) 
                                   VALUES (?, ?, ?)";
            $create_routing_stmt = $db->prepare($create_routing_query);
            $create_routing_stmt->execute([$document['current_department_id'], $input['to_department_id'], $intermediate_department_id]);
            
            $routing_rule = ['intermediate_department_id' => $intermediate_department_id];
        }
        
        // If there's an intermediate department, send there first
        if ($routing_rule && $routing_rule['intermediate_department_id']) {
            $target_department_id = $routing_rule['intermediate_department_id'];
            $routing_info = [
                'has_intermediate' => true,
                'intermediate_department_id' => $routing_rule['intermediate_department_id'],
                'final_department_id' => $input['to_department_id']
            ];
        }
    }
    
    // Update document status to pending and change department
    $updateQuery = "UPDATE documents SET status = 'pending', current_department_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([$target_department_id, $input['document_id']]);
    
    if ($result) {
        // Log the activity
        try {
            $logQuery = "INSERT INTO user_activities (user_id, action, description) VALUES (?, ?, ?)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                $payload['user_id'],
                'send_document',
                "Sent document '{$document['title']}' to {$destinationDept['name']} department"
            ]);
        } catch (Exception $logError) {
            error_log("Could not log activity: " . $logError->getMessage());
        }
        
        // Get the target department name for response
        $target_dept_query = "SELECT name FROM departments WHERE id = ?";
        $target_dept_stmt = $db->prepare($target_dept_query);
        $target_dept_stmt->execute([$target_department_id]);
        $target_dept = $target_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Document sent successfully',
            'document' => [
                'id' => $input['document_id'],
                'status' => 'pending',
                'current_department_name' => $target_dept['name']
            ],
            'routing_info' => $routing_info
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send document']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
