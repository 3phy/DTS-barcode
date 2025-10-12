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

// Check if user is admin
if ($payload['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

try {
    $stats = [];
    
    // Total documents
    $total_query = "SELECT COUNT(*) as total FROM documents";
    $stmt = $db->prepare($total_query);
    $stmt->execute();
    $stats['totalDocuments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Outgoing documents
    $outgoing_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'outgoing'";
    $stmt = $db->prepare($outgoing_query);
    $stmt->execute();
    $stats['outgoingCount'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending documents
    $pending_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'pending'";
    $stmt = $db->prepare($pending_query);
    $stmt->execute();
    $stats['pendingCount'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Received documents
    $received_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'received'";
    $stmt = $db->prepare($received_query);
    $stmt->execute();
    $stats['receivedCount'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Completion rate
    $completion_rate = 0;
    if ($stats['totalDocuments'] > 0) {
        $completion_rate = round(($stats['receivedCount'] / $stats['totalDocuments']) * 100, 2);
    }
    $stats['completionRate'] = $completion_rate;
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
