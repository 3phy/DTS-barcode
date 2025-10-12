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

$headers = getallheaders();
$jwt = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: No token provided.']);
    exit();
}

try {
    $payload = decodeJWT($jwt);
    
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
    // Get total logs
    $total_logs_query = $db->query("SELECT COUNT(*) as count FROM system_logs");
    $total_logs = $total_logs_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get error count
    $error_count_query = $db->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'error'");
    $error_count = $error_count_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get warning count
    $warning_count_query = $db->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'warning'");
    $warning_count = $warning_count_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get info count
    $info_count_query = $db->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'info'");
    $info_count = $info_count_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get debug count
    $debug_count_query = $db->query("SELECT COUNT(*) as count FROM system_logs WHERE level = 'debug'");
    $debug_count = $debug_count_query->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stats = [
        'totalLogs' => (int)$total_logs,
        'errorCount' => (int)$error_count,
        'warningCount' => (int)$warning_count,
        'infoCount' => (int)$info_count,
        'debugCount' => (int)$debug_count
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
