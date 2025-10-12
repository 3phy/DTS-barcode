<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../config/jwt.php';

$database = new Database();
$db = $database->getConnection();

// Get JWT token from header
$headers = getallheaders();
$jwt = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: No token provided.']);
    exit();
}

try {
    $payload = decodeJWT($jwt);
    $user_id = $payload['user_id'];
    $user_role = $payload['role'];
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: ' . $e->getMessage()]);
    exit();
}

// Get document ID from query parameter
$document_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$document_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit();
}

try {
    // Get user's department for filtering
    $user_dept_query = "SELECT department_id FROM users WHERE id = :user_id";
    $user_dept_stmt = $db->prepare($user_dept_query);
    $user_dept_stmt->bindParam(':user_id', $user_id);
    $user_dept_stmt->execute();
    $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
    $user_department_id = $user_dept ? $user_dept['department_id'] : null;

    // Build query based on user role
    if ($user_role === 'admin') {
        $query = "SELECT * FROM documents WHERE id = :document_id";
    } else {
        // Staff can only download documents from their department or documents they uploaded
        if ($user_department_id) {
            $query = "SELECT * FROM documents WHERE id = :document_id AND (department_id = :dept_id OR uploaded_by = :user_id)";
        } else {
            $query = "SELECT * FROM documents WHERE id = :document_id AND uploaded_by = :user_id";
        }
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':document_id', $document_id);
    
    if ($user_role !== 'admin') {
        $stmt->bindParam(':user_id', $user_id);
        if ($user_department_id) {
            $stmt->bindParam(':dept_id', $user_department_id);
        }
    }
    
    $stmt->execute();
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
        exit();
    }

    $file_path = $document['file_path'];
    $filename = $document['filename'];

    // Check if file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found on server']);
        exit();
    }

    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Clear any previous output
    ob_clean();
    flush();

    // Read and output the file
    readfile($file_path);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
