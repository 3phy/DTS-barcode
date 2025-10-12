<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['http://localhost:3000'];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once '../config/database.php';
require_once '../config/jwt.php';

$database = new Database();
$db = $database->getConnection();
$jwtHandler = new JWT();

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: No token provided.']);
    exit();
}

try {
    $payload = $jwtHandler->decode($authHeader);
    
    if (!$payload || $payload['exp'] < time()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid or expired token.']);
        exit();
    }

    if ($payload['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: ' . $e->getMessage()]);
    exit();
}

try {
    // Get filter parameters
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;

    // Build query
    $query = "SELECT a.*, u.name as user_name, u.email as user_email 
              FROM user_activities a 
              LEFT JOIN users u ON a.user_id = u.id 
              WHERE 1=1";
    
    $params = [];
    
    if ($user_id && $user_id !== 'all') {
        $query .= " AND a.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }
    
    if ($action && $action !== 'all') {
        $query .= " AND a.action = :action";
        $params[':action'] = $action;
    }
    
    if ($date_from) {
        $query .= " AND DATE(a.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND DATE(a.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    if ($search) {
        $query .= " AND (a.description LIKE :search OR u.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY a.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
