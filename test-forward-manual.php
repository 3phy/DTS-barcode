<?php
// Manual test to forward a document and then test receive
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Manual Forward Test</h2>";

try {
    // Get the first document that's in "outgoing" status
    $doc_query = "SELECT d.*, curr_dept.name as current_dept_name, dest_dept.name as dest_dept_name
                  FROM documents d
                  LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                  LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
                  WHERE d.status = 'outgoing'
                  ORDER BY d.uploaded_at DESC
                  LIMIT 1";
    
    $doc_stmt = $db->prepare($doc_query);
    $doc_stmt->execute();
    $document = $doc_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo "<p style='color: red;'><strong>❌ No outgoing documents found. Upload a document first.</strong></p>";
        exit;
    }
    
    echo "<h3>Found Document:</h3>";
    echo "<p><strong>ID:</strong> " . $document['id'] . "</p>";
    echo "<p><strong>Title:</strong> " . $document['title'] . "</p>";
    echo "<p><strong>Status:</strong> " . $document['status'] . "</p>";
    echo "<p><strong>Barcode:</strong> " . $document['barcode'] . "</p>";
    echo "<p><strong>Current Department:</strong> " . ($document['current_dept_name'] ?: 'None') . " (ID: " . $document['current_department_id'] . ")</p>";
    echo "<p><strong>Destination Department:</strong> " . ($document['dest_dept_name'] ?: 'None') . " (ID: " . $document['department_id'] . ")</p>";
    
    // Check if this is a direct route or needs routing
    if ($document['current_department_id'] == $document['department_id']) {
        echo "<p style='color: red;'><strong>❌ Document is already at its destination!</strong></p>";
        exit;
    }
    
    // For direct routes, send directly to destination
    $next_department_id = $document['department_id'];
    
    // Get destination department name
    $dest_dept_query = "SELECT name FROM departments WHERE id = ?";
    $dest_dept_stmt = $db->prepare($dest_dept_query);
    $dest_dept_stmt->execute([$next_department_id]);
    $dest_dept = $dest_dept_stmt->fetch(PDO::FETCH_ASSOC);
    $next_department_name = $dest_dept['name'];
    
    echo "<h3>Forwarding Document:</h3>";
    echo "<p><strong>From:</strong> " . ($document['current_dept_name'] ?: 'Unknown') . "</p>";
    echo "<p><strong>To:</strong> " . $next_department_name . "</p>";
    
    // Update document to next department
    $update_query = "UPDATE documents SET current_department_id = ?, status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $result = $update_stmt->execute([$next_department_id, $document['id']]);
    
    if ($result) {
        echo "<p style='color: green;'><strong>✅ Document forwarded successfully!</strong></p>";
        echo "<p><strong>New Status:</strong> pending</p>";
        echo "<p><strong>New Current Department:</strong> " . $next_department_name . " (ID: " . $next_department_id . ")</p>";
        
        // Now test if it can be received
        echo "<h3>Testing Receive:</h3>";
        $receive_query = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                         FROM documents d 
                         LEFT JOIN users u ON d.uploaded_by = u.id 
                         LEFT JOIN departments dept ON d.department_id = dept.id 
                         WHERE d.barcode = ? AND d.status = 'pending' AND d.current_department_id = ?";
        
        // We need to find a user in the destination department
        $user_query = "SELECT id, name, department_id FROM users WHERE department_id = ? LIMIT 1";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([$next_department_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<p><strong>Testing with user:</strong> " . $user['name'] . " (Department ID: " . $user['department_id'] . ")</p>";
            
            $receive_stmt = $db->prepare($receive_query);
            $receive_stmt->execute([$document['barcode'], $user['department_id']]);
            $receive_doc = $receive_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($receive_doc) {
                echo "<p style='color: green;'><strong>✅ Document can be received by this user!</strong></p>";
                echo "<p><strong>Barcode to test:</strong> " . $document['barcode'] . "</p>";
                echo "<p><strong>User ID to test with:</strong> " . $user['id'] . "</p>";
            } else {
                echo "<p style='color: red;'><strong>❌ Document cannot be received by this user</strong></p>";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ No user found in destination department</strong></p>";
        }
        
    } else {
        echo "<p style='color: red;'><strong>❌ Failed to forward document</strong></p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
