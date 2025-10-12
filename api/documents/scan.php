<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../config/jwt.php';

$database = new Database();
$db = $database->getConnection();
$jwt = new JWT();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['barcode'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit();
}

$barcode = $data['barcode'];

try {
    // Find document by barcode
    $query = "SELECT * FROM documents WHERE barcode = :barcode";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':barcode', $barcode);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if document is already received
    if ($document['status'] === 'received') {
        echo json_encode(['success' => false, 'message' => 'Document already received']);
        exit();
    }
    
    // Update document status to received
    $update_query = "UPDATE documents SET status = 'received', received_by = :received_by, received_at = NOW() WHERE barcode = :barcode";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':received_by', $user_id);
    $update_stmt->bindParam(':barcode', $barcode);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Document marked as received',
            'document' => [
                'title' => $document['title'],
                'status' => 'received'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update document status']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
