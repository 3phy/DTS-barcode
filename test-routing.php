<?php
// Simple test script to verify routing functionality
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Document Routing System Test</h2>";

try {
    // Test 1: Check if routing table exists
    echo "<h3>Test 1: Routing Table Check</h3>";
    $query = "SHOW TABLES LIKE 'document_routing'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✅ Routing table exists<br>";
    } else {
        echo "❌ Routing table does not exist<br>";
        echo "Please run the migration script: database/migration_add_routing.sql<br>";
        exit;
    }
    
    // Test 2: Check department routing preferences
    echo "<h3>Test 2: Department Routing Preferences</h3>";
    $query = "SELECT 
                drp.department_id,
                drp.can_route_through,
                dept.name as department_name,
                through_dept.name as can_route_through_name
              FROM department_routing_preferences drp
              LEFT JOIN departments dept ON drp.department_id = dept.id
              LEFT JOIN departments through_dept ON drp.can_route_through = through_dept.id
              WHERE drp.is_active = 1
              ORDER BY dept.name, through_dept.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $routing_preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($routing_preferences) > 0) {
        echo "✅ Found " . count($routing_preferences) . " routing preferences:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Department</th><th>Can Route Through</th></tr>";
        
        foreach ($routing_preferences as $pref) {
            echo "<tr>";
            echo "<td>" . $pref['department_name'] . "</td>";
            echo "<td>" . $pref['can_route_through_name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No routing preferences found<br>";
    }
    
    // Test 3: Check existing routing rules
    echo "<h3>Test 3: Existing Routing Rules</h3>";
    $query = "SELECT 
                dr.from_department_id,
                dr.to_department_id,
                dr.intermediate_department_id,
                from_dept.name as from_department_name,
                to_dept.name as to_department_name,
                inter_dept.name as intermediate_department_name
              FROM document_routing dr
              LEFT JOIN departments from_dept ON dr.from_department_id = from_dept.id
              LEFT JOIN departments to_dept ON dr.to_department_id = to_dept.id
              LEFT JOIN departments inter_dept ON dr.intermediate_department_id = inter_dept.id
              WHERE dr.is_active = 1
              ORDER BY from_dept.name, to_dept.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $routing_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($routing_rules) > 0) {
        echo "✅ Found " . count($routing_rules) . " existing routing rules:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>From</th><th>To</th><th>Intermediate</th><th>Path</th></tr>";
        
        foreach ($routing_rules as $rule) {
            $path = $rule['from_department_name'];
            if ($rule['intermediate_department_name']) {
                $path .= ' → ' . $rule['intermediate_department_name'];
            }
            $path .= ' → ' . $rule['to_department_name'];
            
            echo "<tr>";
            echo "<td>" . $rule['from_department_name'] . "</td>";
            echo "<td>" . $rule['to_department_name'] . "</td>";
            echo "<td>" . ($rule['intermediate_department_name'] ?: 'Direct') . "</td>";
            echo "<td>" . $path . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "ℹ️ No existing routing rules found (they will be created dynamically)<br>";
    }
    
    // Test 4: Check departments
    echo "<h3>Test 4: Available Departments</h3>";
    $query = "SELECT id, name, description FROM departments WHERE is_active = 1 ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($departments) > 0) {
        echo "✅ Found " . count($departments) . " departments:<br>";
        echo "<ul>";
        foreach ($departments as $dept) {
            echo "<li><strong>" . $dept['name'] . "</strong> (ID: " . $dept['id'] . ")";
            if ($dept['description']) {
                echo " - " . $dept['description'];
            }
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "❌ No departments found<br>";
    }
    
    // Test 5: Check recent documents
    echo "<h3>Test 5: Recent Documents</h3>";
    $query = "SELECT 
                d.id,
                d.title,
                d.status,
                from_dept.name as from_department_name,
                to_dept.name as to_department_name,
                curr_dept.name as current_department_name,
                u.name as uploaded_by_name
              FROM documents d
              LEFT JOIN departments from_dept ON d.current_department_id = from_dept.id
              LEFT JOIN departments to_dept ON d.department_id = to_dept.id
              LEFT JOIN departments curr_dept ON d.current_department_id = curr_dept.id
              LEFT JOIN users u ON d.uploaded_by = u.id
              ORDER BY d.uploaded_at DESC
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($documents) > 0) {
        echo "✅ Found " . count($documents) . " recent documents:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Title</th><th>Status</th><th>Current Department</th><th>Destination</th><th>Uploaded By</th></tr>";
        
        foreach ($documents as $doc) {
            echo "<tr>";
            echo "<td>" . $doc['title'] . "</td>";
            echo "<td>" . $doc['status'] . "</td>";
            echo "<td>" . ($doc['current_department_name'] ?: 'Unknown') . "</td>";
            echo "<td>" . ($doc['to_department_name'] ?: 'Unknown') . "</td>";
            echo "<td>" . ($doc['uploaded_by_name'] ?: 'Unknown') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "ℹ️ No documents found<br>";
    }
    
    echo "<h3>✅ All tests completed!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure to run the migration script if you haven't already</li>";
    echo "<li>Test the upload functionality in the web interface</li>";
    echo "<li>Verify that documents are routed correctly</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
