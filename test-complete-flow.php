<?php
// Complete test of the document flow: upload -> forward -> receive
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Complete Document Flow Test</h2>";

try {
    // Step 1: Check if we have any outgoing documents
    echo "<h3>Step 1: Check Outgoing Documents</h3>";
    $outgoing_query = "SELECT d.*, curr_dept.name as current_dept_name, dest_dept.name as dest_dept_name
                       FROM documents d
                       LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                       LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
                       WHERE d.status = 'outgoing'
                       ORDER BY d.uploaded_at DESC
                       LIMIT 1";
    
    $outgoing_stmt = $db->prepare($outgoing_query);
    $outgoing_stmt->execute();
    $outgoing_doc = $outgoing_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$outgoing_doc) {
        echo "<p style='color: red;'><strong>❌ No outgoing documents found. Upload a document first.</strong></p>";
        echo "<p><a href='test-upload.php'>Create a test document</a></p>";
        exit;
    }
    
    echo "<p><strong>Found outgoing document:</strong></p>";
    echo "<ul>";
    echo "<li>ID: " . $outgoing_doc['id'] . "</li>";
    echo "<li>Title: " . $outgoing_doc['title'] . "</li>";
    echo "<li>Status: " . $outgoing_doc['status'] . "</li>";
    echo "<li>Barcode: " . $outgoing_doc['barcode'] . "</li>";
    echo "<li>Current Department: " . ($outgoing_doc['current_dept_name'] ?: 'None') . " (ID: " . $outgoing_doc['current_department_id'] . ")</li>";
    echo "<li>Destination Department: " . ($outgoing_doc['dest_dept_name'] ?: 'None') . " (ID: " . $outgoing_doc['department_id'] . ")</li>";
    echo "</ul>";
    
    // Step 2: Forward the document
    echo "<h3>Step 2: Forward Document</h3>";
    
    if ($outgoing_doc['current_department_id'] == $outgoing_doc['department_id']) {
        echo "<p style='color: red;'><strong>❌ Document is already at its destination!</strong></p>";
        exit;
    }
    
    $next_department_id = $outgoing_doc['department_id'];
    
    // Get destination department name
    $dest_dept_query = "SELECT name FROM departments WHERE id = ?";
    $dest_dept_stmt = $db->prepare($dest_dept_query);
    $dest_dept_stmt->execute([$next_department_id]);
    $dest_dept = $dest_dept_stmt->fetch(PDO::FETCH_ASSOC);
    $next_department_name = $dest_dept['name'];
    
    echo "<p><strong>Forwarding from:</strong> " . ($outgoing_doc['current_dept_name'] ?: 'Unknown') . "</p>";
    echo "<p><strong>Forwarding to:</strong> " . $next_department_name . "</p>";
    
    // Update document to next department
    $update_query = "UPDATE documents SET current_department_id = ?, status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $result = $update_stmt->execute([$next_department_id, $outgoing_doc['id']]);
    
    if (!$result) {
        echo "<p style='color: red;'><strong>❌ Failed to forward document</strong></p>";
        exit;
    }
    
    echo "<p style='color: green;'><strong>✅ Document forwarded successfully!</strong></p>";
    echo "<p><strong>New Status:</strong> pending</p>";
    echo "<p><strong>New Current Department:</strong> " . $next_department_name . " (ID: " . $next_department_id . ")</p>";
    
    // Step 3: Find a user in the destination department
    echo "<h3>Step 3: Find User in Destination Department</h3>";
    
    $user_query = "SELECT id, name, email, department_id FROM users WHERE department_id = ? LIMIT 1";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$next_department_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color: red;'><strong>❌ No user found in destination department</strong></p>";
        echo "<p><strong>Destination Department ID:</strong> " . $next_department_id . "</p>";
        echo "<p><a href='fix-user-department.php'>Fix user department assignment</a></p>";
        exit;
    }
    
    echo "<p><strong>Found user in destination department:</strong></p>";
    echo "<ul>";
    echo "<li>ID: " . $user['id'] . "</li>";
    echo "<li>Name: " . $user['name'] . "</li>";
    echo "<li>Email: " . $user['email'] . "</li>";
    echo "<li>Department ID: " . $user['department_id'] . "</li>";
    echo "</ul>";
    
    // Step 4: Test receive functionality
    echo "<h3>Step 4: Test Receive Functionality</h3>";
    
    $receive_query = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                     FROM documents d 
                     LEFT JOIN users u ON d.uploaded_by = u.id 
                     LEFT JOIN departments dept ON d.department_id = dept.id 
                     WHERE d.barcode = ? AND d.status = 'pending' AND d.current_department_id = ?";
    
    $receive_stmt = $db->prepare($receive_query);
    $receive_stmt->execute([$outgoing_doc['barcode'], $user['department_id']]);
    $receive_doc = $receive_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($receive_doc) {
        echo "<p style='color: green;'><strong>✅ Document can be received!</strong></p>";
        echo "<p><strong>Document Details:</strong></p>";
        echo "<ul>";
        echo "<li>ID: " . $receive_doc['id'] . "</li>";
        echo "<li>Title: " . $receive_doc['title'] . "</li>";
        echo "<li>Status: " . $receive_doc['status'] . "</li>";
        echo "<li>Barcode: " . $receive_doc['barcode'] . "</li>";
        echo "<li>Current Department: " . $receive_doc['current_department_id'] . "</li>";
        echo "<li>User Department: " . $user['department_id'] . "</li>";
        echo "</ul>";
        
        echo "<p><strong>🎯 Test Results:</strong></p>";
        echo "<p><strong>Barcode to scan:</strong> " . $outgoing_doc['barcode'] . "</p>";
        echo "<p><strong>User ID to test with:</strong> " . $user['id'] . "</p>";
        echo "<p><strong>Expected result:</strong> Document should be received successfully</p>";
        
    } else {
        echo "<p style='color: red;'><strong>❌ Document cannot be received</strong></p>";
        
        // Debug why it can't be received
        $debug_query = "SELECT d.*, curr_dept.name as current_dept_name 
                        FROM documents d 
                        LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                        WHERE d.barcode = ?";
        $debug_stmt = $db->prepare($debug_query);
        $debug_stmt->execute([$outgoing_doc['barcode']]);
        $debug_doc = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($debug_doc) {
            echo "<p><strong>Debug Information:</strong></p>";
            echo "<ul>";
            echo "<li>Document Status: " . $debug_doc['status'] . " (needs 'pending')</li>";
            echo "<li>Current Department: " . ($debug_doc['current_dept_name'] ?: 'None') . " (ID: " . $debug_doc['current_department_id'] . ")</li>";
            echo "<li>User Department: " . $user['department_id'] . "</li>";
            echo "<li>Status Match: " . ($debug_doc['status'] === 'pending' ? 'Yes' : 'No') . "</li>";
            echo "<li>Department Match: " . ($debug_doc['current_department_id'] == $user['department_id'] ? 'Yes' : 'No') . "</li>";
            echo "</ul>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
