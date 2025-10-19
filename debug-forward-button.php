<?php
// Debug script to check why forward button is not showing
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Debug Forward Button</h2>";

try {
    // Get all documents with their current state
    $query = "SELECT d.id, d.title, d.status, d.barcode, d.current_department_id, d.department_id,
                     d.received_by, d.received_at,
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
        echo "<h3>Forward Button Analysis:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Title</th><th>Status</th><th>Current Dept</th><th>Dest Dept</th>";
        echo "<th>Received By</th><th>Received At</th><th>Can Forward?</th><th>Reason</th>";
        echo "</tr>";
        
        foreach ($documents as $doc) {
            // Simulate the canForwardDocument logic
            $hasBeenReceived = $doc['status'] === 'received' || $doc['received_by'];
            $notAtDestination = $doc['current_department_id'] != $doc['department_id'];
            
            $can_forward = $hasBeenReceived && $notAtDestination;
            
            $reason = '';
            if (!$hasBeenReceived) {
                $reason = 'Not received (status: ' . $doc['status'] . ', received_by: ' . ($doc['received_by'] ?: 'null') . ')';
            } else if (!$notAtDestination) {
                $reason = 'Already at final destination (current: ' . $doc['current_department_id'] . ', dest: ' . $doc['department_id'] . ')';
            } else {
                $reason = 'Can forward - received and not at destination';
            }
            
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . $doc['title'] . "</td>";
            echo "<td>" . $doc['status'] . "</td>";
            echo "<td>" . ($doc['current_dept_name'] ?: 'None') . " (ID: " . $doc['current_department_id'] . ")</td>";
            echo "<td>" . ($doc['dest_dept_name'] ?: 'None') . " (ID: " . $doc['department_id'] . ")</td>";
            echo "<td>" . ($doc['received_by'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['received_at'] ?: 'None') . "</td>";
            echo "<td>" . ($can_forward ? '✅ Yes' : '❌ No') . "</td>";
            echo "<td>" . $reason . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show detailed analysis for each document
        echo "<h3>Detailed Analysis:</h3>";
        foreach ($documents as $doc) {
            echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>";
            echo "<h4>Document " . $doc['id'] . ": " . $doc['title'] . "</h4>";
            
            echo "<p><strong>Status:</strong> " . $doc['status'] . "</p>";
            echo "<p><strong>Received By:</strong> " . ($doc['received_by'] ?: 'None') . "</p>";
            echo "<p><strong>Current Department:</strong> " . ($doc['current_dept_name'] ?: 'None') . " (ID: " . $doc['current_department_id'] . ")</p>";
            echo "<p><strong>Destination Department:</strong> " . ($doc['dest_dept_name'] ?: 'None') . " (ID: " . $doc['department_id'] . ")</p>";
            
            // Check each condition
            $hasBeenReceived = $doc['status'] === 'received' || $doc['received_by'];
            $notAtDestination = $doc['current_department_id'] != $doc['department_id'];
            
            echo "<p><strong>Has Been Received:</strong> " . ($hasBeenReceived ? '✅ Yes' : '❌ No') . "</p>";
            echo "<p><strong>Not At Destination:</strong> " . ($notAtDestination ? '✅ Yes' : '❌ No') . "</p>";
            
            $can_forward = $hasBeenReceived && $notAtDestination;
            echo "<p><strong>Can Forward:</strong> " . ($can_forward ? '✅ Yes' : '❌ No') . "</p>";
            
            if (!$can_forward) {
                echo "<p style='color: red;'><strong>Why Forward Button Won't Show:</strong></p>";
                echo "<ul>";
                if (!$hasBeenReceived) {
                    echo "<li>Document has not been received (status: " . $doc['status'] . ")</li>";
                }
                if (!$notAtDestination) {
                    echo "<li>Document is already at final destination</li>";
                }
                echo "</ul>";
            }
            
            echo "</div>";
        }
        
    } else {
        echo "<p>No documents found. Upload a document first.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
