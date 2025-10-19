<?php
// Debug script to check why receive is failing
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Debug Receive Functionality</h2>";

try {
    // Get all documents with their status
    echo "<h3>All Documents:</h3>";
    $query = "SELECT d.id, d.title, d.status, d.barcode, d.current_department_id, d.department_id,
                     curr_dept.name as current_dept_name, dest_dept.name as dest_dept_name,
                     u.name as uploaded_by_name
              FROM documents d
              LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
              LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
              LEFT JOIN users u ON d.uploaded_by = u.id
              ORDER BY d.uploaded_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($documents) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Title</th><th>Status</th><th>Barcode</th><th>Current Dept</th><th>Dest Dept</th><th>Uploaded By</th></tr>";
        
        foreach ($documents as $doc) {
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . $doc['title'] . "</td>";
            echo "<td>" . $doc['status'] . "</td>";
            echo "<td>" . $doc['barcode'] . "</td>";
            echo "<td>" . ($doc['current_dept_name'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['dest_dept_name'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['uploaded_by_name'] ?: 'Unknown') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No documents found<br>";
    }
    
    // Get all users and their departments
    echo "<h3>Users and Departments:</h3>";
    $user_query = "SELECT u.id, u.name, u.email, u.department_id, d.name as department_name
                   FROM users u
                   LEFT JOIN departments d ON u.department_id = d.id
                   ORDER BY u.id";
    
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Email</th><th>Department</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['name'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . ($user['department_name'] ?: 'No Department') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test receive functionality
    echo "<h3>Test Receive Functionality:</h3>";
    
    if (isset($_GET['test_receive']) && isset($_GET['barcode'])) {
        $barcode = $_GET['barcode'];
        $user_id = $_GET['user_id'] ?? 1; // Default to user ID 1
        
        echo "<p><strong>Testing receive for barcode:</strong> $barcode</p>";
        echo "<p><strong>User ID:</strong> $user_id</p>";
        
        // Get user's department
        $user_dept_query = "SELECT department_id FROM users WHERE id = ?";
        $user_dept_stmt = $db->prepare($user_dept_query);
        $user_dept_stmt->execute([$user_id]);
        $user_dept = $user_dept_stmt->fetch(PDO::FETCH_ASSOC);
        $user_department_id = $user_dept ? $user_dept['department_id'] : null;
        
        echo "<p><strong>User Department ID:</strong> " . ($user_department_id ?: 'None') . "</p>";
        
        // Check if document exists with this barcode
        $doc_query = "SELECT d.*, curr_dept.name as current_dept_name, dest_dept.name as dest_dept_name
                      FROM documents d
                      LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
                      LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
                      WHERE d.barcode = ?";
        $doc_stmt = $db->prepare($doc_query);
        $doc_stmt->execute([$barcode]);
        $document = $doc_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            echo "<p><strong>Document found:</strong></p>";
            echo "<ul>";
            echo "<li>ID: " . $document['id'] . "</li>";
            echo "<li>Title: " . $document['title'] . "</li>";
            echo "<li>Status: " . $document['status'] . "</li>";
            echo "<li>Current Department: " . ($document['current_dept_name'] ?: 'None') . " (ID: " . $document['current_department_id'] . ")</li>";
            echo "<li>Destination Department: " . ($document['dest_dept_name'] ?: 'None') . " (ID: " . $document['department_id'] . ")</li>";
            echo "</ul>";
            
            // Check if user can receive this document
            if ($document['status'] === 'pending' && $document['current_department_id'] == $user_department_id) {
                echo "<p style='color: green;'><strong>✅ User can receive this document!</strong></p>";
            } else {
                echo "<p style='color: red;'><strong>❌ User cannot receive this document:</strong></p>";
                echo "<ul>";
                if ($document['status'] !== 'pending') {
                    echo "<li>Document status is not 'pending' (current: " . $document['status'] . ")</li>";
                }
                if ($document['current_department_id'] != $user_department_id) {
                    echo "<li>Document is not in user's department (current: " . $document['current_department_id'] . ", user: " . $user_department_id . ")</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ No document found with barcode: $barcode</strong></p>";
        }
    } else {
        echo "<p>To test receive functionality, add these parameters to the URL:</p>";
        echo "<p><code>?test_receive=1&barcode=DOC123456789&user_id=1</code></p>";
        echo "<p>Replace 'DOC123456789' with an actual barcode from the documents above.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
