<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../config/jwt.php';

$database = new Database();
$db = $database->getConnection();
$jwt = new JWT();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

// Check if user is admin
if ($payload['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name']) || !isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name and email are required']);
    exit();
}

$name = $data['name'];
$email = $data['email'];
$password = isset($data['password']) ? $data['password'] : null;
$role = isset($data['role']) ? $data['role'] : 'staff';
$department_id = isset($data['department_id']) ? $data['department_id'] : null;
$is_active = isset($data['is_active']) ? (bool)$data['is_active'] : true;

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate role
if (!in_array($role, ['admin', 'staff'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

try {
    // Check if user exists
    $check_query = "SELECT id FROM users WHERE id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Check if email already exists for another user
    $email_check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
    $email_check_stmt = $db->prepare($email_check_query);
    $email_check_stmt->bindParam(':email', $email);
    $email_check_stmt->bindParam(':user_id', $user_id);
    $email_check_stmt->execute();
    
    if ($email_check_stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }
    
    // Build update query
    if ($password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET name = :name, email = :email, password = :password, role = :role, department_id = :department_id, is_active = :is_active WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
    } else {
        $query = "UPDATE users SET name = :name, email = :email, role = :role, department_id = :department_id, is_active = :is_active WHERE id = :user_id";
        $stmt = $db->prepare($query);
    }
    
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':department_id', $department_id);
    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Staff member updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update staff member']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
