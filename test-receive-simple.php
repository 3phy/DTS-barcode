<?php
// Simple test to check receive functionality
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Simple Receive Test</h2>";

try {
    // Get current user (assuming user ID 1)
    $user_id = 1;
    $user_query = "SELECT id, name, email, department_id FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Current User:</h3>";
    echo "<p><strong>ID:</strong> " . $user['id'] . "</p>";
    echo "<p><strong>Name:</strong> " . $user['name'] . "</p>";
    echo "<p><strong>Email:</strong> " . $user['email'] . "</p>";
    echo "<p><strong>Department ID:</strong> " . ($user['department_id'] ?: 'None') . "</p>";
    
    // Get user's department name
    if ($user['department_id']) {
        $dept_query = "SELECT name FROM departments WHERE id = ?";
        $dept_stmt = $db->prepare($dept_query);
        $dept_stmt->execute([$user['department_id']]);
        $dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Department Name:</strong> " . ($dept['name'] ?: 'Unknown') . "</p>";
    }
    
    // Get all documents
    echo "<h3>All Documents:</h3>";
    $doc_query = "SELECT d.id, d.title, d.status, d.barcode, d.current_department_id, d.department_id,
                         curr_dept.name as current_dept_name, dest_dept.name as dest_dept_name
                  FROM documents d
                  LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                  LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
                  ORDER BY d.uploaded_at DESC";
    
    $doc_stmt = $db->prepare($doc_query);
    $doc_stmt->execute();
    $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($documents) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Title</th><th>Status</th><th>Barcode</th><th>Current Dept</th><th>Dest Dept</th><th>Can Receive?</th></tr>";
        
        foreach ($documents as $doc) {
            $can_receive = false;
            $reason = '';
            
            if ($doc['status'] === 'pending' && $doc['current_department_id'] == $user['department_id']) {
                $can_receive = true;
                $reason = 'Yes - pending in your department';
            } else {
                if ($doc['status'] !== 'pending') {
                    $reason = 'No - status is ' . $doc['status'];
                } else if ($doc['current_department_id'] != $user['department_id']) {
                    $reason = 'No - not in your department (current: ' . $doc['current_department_id'] . ', yours: ' . $user['department_id'] . ')';
                } else {
                    $reason = 'No - unknown reason';
                }
            }
            
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . $doc['title'] . "</td>";
            echo "<td>" . $doc['status'] . "</td>";
            echo "<td>" . $doc['barcode'] . "</td>";
            echo "<td>" . ($doc['current_dept_name'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['dest_dept_name'] ?: 'None') . "</td>";
            echo "<td>" . ($can_receive ? '✅ ' . $reason : '❌ ' . $reason) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No documents found. Upload a document first.</p>";
    }
    
    // Test receive for a specific document
    if (isset($_GET['test_barcode'])) {
        $barcode = $_GET['test_barcode'];
        echo "<h3>Testing Receive for Barcode: $barcode</h3>";
        
        // Simulate the receive API logic
        $check_query = "SELECT d.*, u.name as uploaded_by_name, dept.name as department_name 
                       FROM documents d 
                       LEFT JOIN users u ON d.uploaded_by = u.id 
                       LEFT JOIN departments dept ON d.department_id = dept.id 
                       WHERE d.barcode = ? AND d.status = 'pending' AND d.current_department_id = ?";
        
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$barcode, $user['department_id']]);
        $document = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            echo "<p style='color: green;'><strong>✅ Document found and can be received!</strong></p>";
            echo "<p><strong>Document:</strong> " . $document['title'] . "</p>";
            echo "<p><strong>Status:</strong> " . $document['status'] . "</p>";
            echo "<p><strong>Current Department:</strong> " . $document['current_department_id'] . "</p>";
            echo "<p><strong>User Department:</strong> " . $user['department_id'] . "</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Document not found or cannot be received</strong></p>";
            
            // Check if document exists at all
            $any_query = "SELECT * FROM documents WHERE barcode = ?";
            $any_stmt = $db->prepare($any_query);
            $any_stmt->execute([$barcode]);
            $any_doc = $any_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($any_doc) {
                echo "<p><strong>Document exists but cannot be received:</strong></p>";
                echo "<ul>";
                echo "<li>Status: " . $any_doc['status'] . " (needs to be 'pending')</li>";
                echo "<li>Current Department: " . $any_doc['current_department_id'] . " (needs to be " . $user['department_id'] . ")</li>";
                echo "<li>User Department: " . $user['department_id'] . "</li>";
                echo "</ul>";
            } else {
                echo "<p><strong>No document found with barcode: $barcode</strong></p>";
            }
        }
    }
    
    echo "<h3>Quick Actions:</h3>";
    echo "<p><a href='?test_barcode=DOC123456789'>Test with barcode: DOC123456789</a></p>";
    echo "<p><a href='fix-user-department.php'>Fix user department assignment</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
