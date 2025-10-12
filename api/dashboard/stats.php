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
    $stats = [];
    $recent_documents = [];
    
    // Get user's department
    $user_dept_query = "SELECT department_id FROM users WHERE id = :user_id";
    $user_dept_stmt = $db->prepare($user_dept_query);
    $user_dept_stmt->bindParam(':user_id', $user_id);
    $user_dept_stmt->execute();
    $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
    $user_department_id = $user_dept ? $user_dept['department_id'] : null;

    if ($user_role === 'admin') {
        // Admin sees all documents
        $total_query = "SELECT COUNT(*) as total FROM documents";
        $outgoing_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'outgoing'";
        $pending_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'pending'";
        $received_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'received'";
        
        $recent_query = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                        FROM documents d 
                        LEFT JOIN users u ON d.uploaded_by = u.id 
                        LEFT JOIN departments dept ON d.department_id = dept.id 
                        ORDER BY d.uploaded_at DESC 
                        LIMIT 5";
    } else {
        // Staff sees documents from their department or documents they uploaded
        if ($user_department_id) {
            $total_query = "SELECT COUNT(*) as total FROM documents WHERE department_id = :dept_id OR uploaded_by = :user_id";
            $outgoing_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'outgoing' AND (department_id = :dept_id OR uploaded_by = :user_id)";
            $pending_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'pending' AND (department_id = :dept_id OR uploaded_by = :user_id)";
            $received_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'received' AND (department_id = :dept_id OR uploaded_by = :user_id)";
            
            $recent_query = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                            FROM documents d 
                            LEFT JOIN users u ON d.uploaded_by = u.id 
                            LEFT JOIN departments dept ON d.department_id = dept.id 
                            WHERE d.department_id = :dept_id OR d.uploaded_by = :user_id 
                            ORDER BY d.uploaded_at DESC 
                            LIMIT 5";
        } else {
            // Staff with no department sees only their uploaded documents
            $total_query = "SELECT COUNT(*) as total FROM documents WHERE uploaded_by = :user_id";
            $outgoing_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'outgoing' AND uploaded_by = :user_id";
            $pending_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'pending' AND uploaded_by = :user_id";
            $received_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'received' AND uploaded_by = :user_id";
            
            $recent_query = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                            FROM documents d 
                            LEFT JOIN users u ON d.uploaded_by = u.id 
                            LEFT JOIN departments dept ON d.department_id = dept.id 
                            WHERE d.uploaded_by = :user_id 
                            ORDER BY d.uploaded_at DESC 
                            LIMIT 5";
        }
    }
    
    // Get total documents
    $stmt = $db->prepare($total_query);
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    $stmt->execute();
    $stats['totalDocuments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get outgoing documents
    $stmt = $db->prepare($outgoing_query);
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    $stmt->execute();
    $stats['outgoingDocuments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get pending documents
    $stmt = $db->prepare($pending_query);
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    $stmt->execute();
    $stats['pendingDocuments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get received documents
    $stmt = $db->prepare($received_query);
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    $stmt->execute();
    $stats['receivedDocuments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent documents
    $stmt = $db->prepare($recent_query);
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    $stmt->execute();
    $recent_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recentDocuments' => $recent_documents
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
