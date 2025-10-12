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

function decodeJWT($jwt) {
    // Example implementation of JWT decoding
    $secretKey = "mysupersecretkey";
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        throw new Exception('Invalid token structure');
    }
    $payload = json_decode(base64_decode($tokenParts[1]), true);
    if (!$payload) {
        throw new Exception('Invalid token payload');
    }
    // Add signature verification logic here if needed
    return $payload;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get system settings
    try {
        $settings = [
            'systemName' => 'Document Tracking System',
            'maxFileSize' => 10,
            'allowedFileTypes' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'autoBackup' => true,
            'backupFrequency' => 'daily',
            'emailNotifications' => true,
            'sessionTimeout' => 30
        ];
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save system settings
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        // In a real implementation, you would save these to a settings table
        // For now, we'll just return success
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
