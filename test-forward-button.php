<?php
// Test to check when the Forward button should appear
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Forward Button Test</h2>";

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
        echo "<h3>Document Status Analysis:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Title</th><th>Status</th><th>Barcode</th><th>Current Dept</th><th>Dest Dept</th>";
        echo "<th>Received By</th><th>Received At</th><th>Can Forward?</th><th>Reason</th>";
        echo "</tr>";
        
        foreach ($documents as $doc) {
            $can_forward = false;
            $reason = '';
            
            // Check if document can be forwarded
            if (!$doc['department_id']) {
                $reason = 'No destination department';
            } else if ($doc['status'] !== 'received' && !$doc['received_by']) {
                $reason = 'Not received yet (status: ' . $doc['status'] . ')';
            } else if ($doc['current_department_id'] == $doc['department_id']) {
                $reason = 'Already at final destination';
            } else {
                $can_forward = true;
                $reason = 'Can forward - received and not at destination';
            }
            
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . $doc['title'] . "</td>";
            echo "<td>" . $doc['status'] . "</td>";
            echo "<td>" . $doc['barcode'] . "</td>";
            echo "<td>" . ($doc['current_dept_name'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['dest_dept_name'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['received_by'] ?: 'None') . "</td>";
            echo "<td>" . ($doc['received_at'] ?: 'None') . "</td>";
            echo "<td>" . ($can_forward ? '✅ Yes' : '❌ No') . "</td>";
            echo "<td>" . $reason . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show workflow steps
        echo "<h3>Workflow Steps:</h3>";
        echo "<ol>";
        echo "<li><strong>Upload:</strong> Document status = 'outgoing' (ready to receive in sender's department)</li>";
        echo "<li><strong>Receive:</strong> Sender scans QR code → Status = 'received'</li>";
        echo "<li><strong>Forward:</strong> Forward button appears → Can send to any department</li>";
        echo "<li><strong>Receive:</strong> Next department receives and scans QR code → Process continues</li>";
        echo "</ol>";
        
        // Show what needs to happen for each document
        echo "<h3>Next Steps for Each Document:</h3>";
        foreach ($documents as $doc) {
            echo "<p><strong>Document " . $doc['id'] . " (" . $doc['title'] . "):</strong></p>";
            
            if ($doc['status'] === 'outgoing') {
                echo "<p style='color: blue;'>→ <strong>Action needed:</strong> Receive this document (scan QR code)</p>";
            } else if ($doc['status'] === 'pending') {
                echo "<p style='color: blue;'>→ <strong>Action needed:</strong> Receive this document (scan QR code)</p>";
            } else if ($doc['status'] === 'received') {
                if ($doc['current_department_id'] != $doc['department_id']) {
                    echo "<p style='color: green;'>→ <strong>Ready:</strong> Can forward to any department</p>";
                } else {
                    echo "<p style='color: gray;'>→ <strong>Complete:</strong> Document reached final destination</p>";
                }
            }
        }
        
    } else {
        echo "<p>No documents found. Upload a document first.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
