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

$user_id = $payload['user_id'];
$user_role = $payload['role'];

try {
    // Get user's department
    $user_dept_query = "SELECT department_id FROM users WHERE id = :user_id";
    $user_dept_stmt = $db->prepare($user_dept_query);
    $user_dept_stmt->bindParam(':user_id', $user_id);
    $user_dept_stmt->execute();
    $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
    $user_department_id = $user_dept ? $user_dept['department_id'] : null;

    // Build query based on user role
    if ($user_role === 'admin') {
        $query = "SELECT d.*, u.name as uploaded_by_name, r.name as received_by_name, dept.name as department_name, curr_dept.name as current_department_name
                  FROM documents d 
                  LEFT JOIN users u ON d.uploaded_by = u.id 
                  LEFT JOIN users r ON d.received_by = r.id 
                  LEFT JOIN departments dept ON d.department_id = dept.id
                  LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                  ORDER BY d.uploaded_at DESC";
    } else {
        // Staff sees documents from their department or documents they uploaded
        if ($user_department_id) {
            $query = "SELECT d.*, u.name as uploaded_by_name, r.name as received_by_name, dept.name as department_name, curr_dept.name as current_department_name
                      FROM documents d 
                      LEFT JOIN users u ON d.uploaded_by = u.id 
                      LEFT JOIN users r ON d.received_by = r.id 
                      LEFT JOIN departments dept ON d.department_id = dept.id 
                      LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                      WHERE d.current_department_id = :dept_id OR d.department_id = :dept_id OR d.uploaded_by = :user_id
                      ORDER BY d.uploaded_at DESC";
        } else {
            // Staff with no department sees only their uploaded documents
            $query = "SELECT d.*, u.name as uploaded_by_name, r.name as received_by_name, dept.name as epartment_name, curr_dept.name as current_department_name
                      FROM documents d 
                      LEFT JOIN users u ON d.uploaded_by = u.id 
                      LEFT JOIN users r ON d.received_by = r.id 
                      LEFT JOIN departments dept ON d.department_id = dept.id 
                      LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                      WHERE d.uploaded_by = :user_id 
                      ORDER BY d.uploaded_at DESC";
        }
    }
    
    $stmt = $db->prepare($query);
    
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate status based on user perspective
    foreach ($documents as &$doc) {
        if ($user_role === 'admin') {
            // Admin sees actual status
            $doc['display_status'] = $doc['status'];
        } else {
            // For regular users, determine status based on their relationship to the document
            if ($doc['uploaded_by'] == $user_id) {
                // User uploaded this document - always shows as "outgoing"
                $doc['display_status'] = 'outgoing';
            } else {
                // User didn't upload this document - shows as "pending" if not received, "received" if received
                $doc['display_status'] = $doc['status'] === 'received' ? 'received' : 'pending';
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
