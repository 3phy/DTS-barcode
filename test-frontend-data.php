<?php
// Test to see what data the frontend is receiving
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Frontend Data Test</h2>";

try {
    // Simulate the documents list API
    $query = "SELECT d.id, d.title, d.description, d.filename, d.file_path, d.file_size, d.file_type, 
                     d.barcode, d.department_id, d.current_department_id, d.status, d.uploaded_by, 
                     d.received_by, d.received_at, d.uploaded_at, d.updated_at,
                     u.name as uploaded_by_name, u.email as uploaded_by_email,
                     curr_dept.name as current_department_name, dest_dept.name as destination_department_name
              FROM documents d
              LEFT JOIN users u ON d.uploaded_by = u.id
              LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
              LEFT JOIN departments dest_dept ON d.department_id = dest_dept.id
              ORDER BY d.uploaded_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($documents) > 0) {
        echo "<h3>Documents Data (as seen by frontend):</h3>";
        
        foreach ($documents as $doc) {
            echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>";
            echo "<h4>Document " . $doc['id'] . ": " . $doc['title'] . "</h4>";
            
            echo "<p><strong>Status:</strong> " . $doc['status'] . "</p>";
            echo "<p><strong>Received By:</strong> " . ($doc['received_by'] ?: 'null') . "</p>";
            echo "<p><strong>Current Department ID:</strong> " . $doc['current_department_id'] . "</p>";
            echo "<p><strong>Destination Department ID:</strong> " . $doc['department_id'] . "</p>";
            
            // Test the forward logic
            $hasBeenReceived = $doc['status'] === 'received' || $doc['received_by'];
            $notAtDestination = $doc['current_department_id'] != $doc['department_id'];
            $canForward = $hasBeenReceived && $notAtDestination;
            
            echo "<p><strong>Can Forward (JavaScript logic):</strong> " . ($canForward ? 'true' : 'false') . "</p>";
            
            echo "<p><strong>Debug Info:</strong></p>";
            echo "<ul>";
            echo "<li>status === 'received': " . ($doc['status'] === 'received' ? 'true' : 'false') . "</li>";
            echo "<li>received_by exists: " . ($doc['received_by'] ? 'true' : 'false') . "</li>";
            echo "<li>hasBeenReceived: " . ($hasBeenReceived ? 'true' : 'false') . "</li>";
            echo "<li>current_department_id != department_id: " . ($notAtDestination ? 'true' : 'false') . "</li>";
            echo "<li>current_department_id: " . $doc['current_department_id'] . "</li>";
            echo "<li>department_id: " . $doc['department_id'] . "</li>";
            echo "</ul>";
            
            echo "</div>";
        }
        
        // Show JSON format (what the frontend receives)
        echo "<h3>JSON Format (what frontend receives):</h3>";
        echo "<pre>" . json_encode($documents, JSON_PRETTY_PRINT) . "</pre>";
        
    } else {
        echo "<p>No documents found. Upload a document first.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
