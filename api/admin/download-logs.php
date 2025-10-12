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
    // Get filter parameters
    $level = isset($_GET['level']) ? $_GET['level'] : 'all';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;
    
    // Build query
    $query = "SELECT level, message, context, timestamp FROM system_logs WHERE 1=1";
    $params = [];
    
    if ($level !== 'all') {
        $query .= " AND level = ?";
        $params[] = $level;
    }
    
    if ($dateFrom) {
        $query .= " AND DATE(timestamp) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $query .= " AND DATE(timestamp) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search) {
        $query .= " AND (message LIKE ? OR context LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $query .= " ORDER BY timestamp DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV content
    $csvContent = "Level,Message,Context,Timestamp\n";
    foreach ($logs as $log) {
        $context = $log['context'] ? json_encode($log['context']) : '';
        $csvContent .= sprintf(
            '"%s","%s","%s","%s"' . "\n",
            $log['level'],
            str_replace('"', '""', $log['message']),
            str_replace('"', '""', $context),
            $log['timestamp']
        );
    }
    
    // Set headers for file download
    $filename = 'system_logs_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csvContent));
    
    echo $csvContent;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
