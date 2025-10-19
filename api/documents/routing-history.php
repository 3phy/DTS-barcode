<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../config/jwt.php';

$database = new Database();
$db = $database->getConnection();
$jwt = new JWT();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

// Get document ID from query parameter
$document_id = isset($_GET['document_id']) ? $_GET['document_id'] : null;

if (!$document_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit();
}

try {
    // Get document basic info
    $docQuery = "SELECT d.*, 
                        upload_dept.name as upload_department_name,
                        curr_dept.name as current_department_name,
                        dest_dept.name as destination_department_name,
                        u.name as uploaded_by_name
                 FROM documents d 
                 LEFT JOIN departments upload_dept ON d.current_department_id = upload_dept.id
                 LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                 LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
                 LEFT JOIN users u ON d.uploaded_by = u.id
                 WHERE d.id = ?";
    
    $docStmt = $db->prepare($docQuery);
    $docStmt->execute([$document_id]);
    $document = $docStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }
    
    // Get forwarding history
    $historyQuery = "SELECT dfh.*, 
                            from_dept.name as from_department_name,
                            to_dept.name as to_department_name,
                            u.name as forwarded_by_name
                     FROM document_forwarding_history dfh
                     LEFT JOIN departments from_dept ON dfh.from_department_id = from_dept.id
                     LEFT JOIN departments to_dept ON dfh.to_department_id = to_dept.id
                     LEFT JOIN users u ON dfh.forwarded_by = u.id
                     WHERE dfh.document_id = ?
                     ORDER BY dfh.forwarded_at ASC";
    
    $historyStmt = $db->prepare($historyQuery);
    $historyStmt->execute([$document_id]);
    $forwardingHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build dynamic routing path
    $routingPath = [];
    $routingDetails = [];
    
    // Start with upload department
    $routingPath[] = $document['upload_department_name'] ?: 'Unknown Department';
    $routingDetails[] = [
        'department_name' => $document['upload_department_name'] ?: 'Unknown Department',
        'action' => 'uploaded',
        'timestamp' => $document['uploaded_at'],
        'user_name' => $document['uploaded_by_name']
    ];
    
    // Add forwarding history
    foreach ($forwardingHistory as $forward) {
        $routingPath[] = $forward['to_department_name'];
        $routingDetails[] = [
            'department_name' => $forward['to_department_name'],
            'action' => 'forwarded',
            'timestamp' => $forward['forwarded_at'],
            'user_name' => $forward['forwarded_by_name'],
            'from_department' => $forward['from_department_name']
        ];
    }
    
    // Add current status
    if ($document['status'] === 'received') {
        $routingDetails[] = [
            'department_name' => $document['current_department_name'],
            'action' => 'received',
            'timestamp' => $document['received_at'],
            'user_name' => 'Current User'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'document' => [
            'id' => $document['id'],
            'title' => $document['title'],
            'status' => $document['status'],
            'uploaded_at' => $document['uploaded_at'],
            'received_at' => $document['received_at']
        ],
        'routing_path' => $routingPath,
        'routing_details' => $routingDetails,
        'forwarding_history' => $forwardingHistory
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
