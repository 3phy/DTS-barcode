<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['http://localhost:3000'];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: 'null'");
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
    // Get total users
    $total_users_query = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $total_users = $total_users_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get active users (logged in within last 24 hours)
    $active_users_query = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM user_activities 
                                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $active_users = $active_users_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total activities
    $total_activities_query = $db->query("SELECT COUNT(*) as count FROM user_activities");
    $total_activities = $total_activities_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get today's activities
    $today_activities_query = $db->query("SELECT COUNT(*) as count FROM user_activities 
                                          WHERE DATE(created_at) = CURDATE()");
    $today_activities = $today_activities_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stats = [
        'totalUsers' => (int)$total_users,
        'activeUsers' => (int)$active_users,
        'totalActivities' => (int)$total_activities,
        'todayActivities' => (int)$today_activities
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
