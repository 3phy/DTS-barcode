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

try {
    // Get user's department
    $user_dept_query = "SELECT department_id FROM users WHERE id = :user_id";
    $user_dept_stmt = $db->prepare($user_dept_query);
    $user_dept_stmt->bindParam(':user_id', $user_id);
    $user_dept_stmt->execute();
    $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
    $user_department_id = $user_dept ? $user_dept['department_id'] : null;

    if (!$user_department_id) {
        // Return empty routing info if user has no department
        echo json_encode([
            'success' => true,
            'user_department_id' => null,
            'routing_info' => [],
            'message' => 'User has no department assigned - no routing available'
        ]);
        exit();
    }

    // Check if routing table exists
    $table_check = $db->query("SHOW TABLES LIKE 'document_routing'");
    $routing_table_exists = $table_check->rowCount() > 0;
    
    $routing_rules = [];
    
    if ($routing_table_exists) {
        // Get routing information for the user's department
        $routing_query = "SELECT 
                            dr.from_department_id,
                            dr.to_department_id,
                            dr.intermediate_department_id,
                            from_dept.name as from_department_name,
                            to_dept.name as to_department_name,
                            inter_dept.name as intermediate_department_name
                          FROM document_routing dr
                          LEFT JOIN departments from_dept ON dr.from_department_id = from_dept.id
                          LEFT JOIN departments to_dept ON dr.to_department_id = to_dept.id
                          LEFT JOIN departments inter_dept ON dr.intermediate_department_id = inter_dept.id
                          WHERE dr.from_department_id = :user_dept_id AND dr.is_active = 1
                          ORDER BY to_dept.name";
        
        $routing_stmt = $db->prepare($routing_query);
        $routing_stmt->bindParam(':user_dept_id', $user_department_id);
        $routing_stmt->execute();
        $routing_rules = $routing_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Format routing information
    $routing_info = [];
    foreach ($routing_rules as $rule) {
        $path = [$rule['from_department_name']];
        if ($rule['intermediate_department_name']) {
            $path[] = $rule['intermediate_department_name'];
        }
        $path[] = $rule['to_department_name'];
        
        $routing_info[] = [
            'to_department_id' => $rule['to_department_id'],
            'to_department_name' => $rule['to_department_name'],
            'routing_path' => $path,
            'has_intermediate' => !empty($rule['intermediate_department_name']),
            'intermediate_department_name' => $rule['intermediate_department_name']
        ];
    }

    echo json_encode([
        'success' => true,
        'user_department_id' => $user_department_id,
        'routing_info' => $routing_info
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

