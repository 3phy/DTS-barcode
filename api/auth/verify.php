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

try {
    $query = "SELECT u.id, u.name, u.email, u.role, u.is_active, d.name as department_name 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $payload['user_id']);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user['is_active']) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Account is deactivated']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
