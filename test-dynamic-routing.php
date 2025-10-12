<?php
// Test script to demonstrate dynamic routing creation
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Dynamic Routing System Test</h2>";

try {
    // Check if routing tables exist
    $table_check = $db->query("SHOW TABLES LIKE 'document_routing'");
    $routing_table_exists = $table_check->rowCount() > 0;
    
    $pref_table_check = $db->query("SHOW TABLES LIKE 'department_routing_preferences'");
    $pref_table_exists = $pref_table_check->rowCount() > 0;
    
    if (!$routing_table_exists || !$pref_table_exists) {
        echo "❌ Routing tables do not exist. Please run the migration script first.<br>";
        exit;
    }
    
    echo "✅ Both routing tables exist<br><br>";
    
    // Get departments for testing
    $dept_query = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Departments:</h3>";
    echo "<ul>";
    foreach ($departments as $dept) {
        echo "<li><strong>" . $dept['name'] . "</strong> (ID: " . $dept['id'] . ")</li>";
    }
    echo "</ul>";
    
    // Test dynamic routing creation
    echo "<h3>Testing Dynamic Routing Creation</h3>";
    
    // Example: IT (ID: 3) wants to send to HR (ID: 1)
    $from_dept_id = 3; // IT
    $to_dept_id = 1;   // HR
    
    echo "<p><strong>Scenario:</strong> IT Department wants to send a document to HR Department</p>";
    
    // Check if routing already exists
    $existing_check = "SELECT id FROM document_routing WHERE from_department_id = ? AND to_department_id = ?";
    $existing_stmt = $db->prepare($existing_check);
    $existing_stmt->execute([$from_dept_id, $to_dept_id]);
    $existing = $existing_stmt->fetch();
    
    if ($existing) {
        echo "ℹ️ Routing already exists for this path<br>";
    } else {
        echo "🔄 Creating new routing dynamically...<br>";
        
        // Find intermediate department using routing preferences
        $preference_query = "SELECT drp1.can_route_through as intermediate_dept_id, d.name as intermediate_dept_name
                            FROM department_routing_preferences drp1
                            JOIN department_routing_preferences drp2 ON drp1.can_route_through = drp2.can_route_through
                            JOIN departments d ON drp1.can_route_through = d.id
                            WHERE drp1.department_id = ? AND drp2.department_id = ? 
                            AND drp1.is_active = 1 AND drp2.is_active = 1
                            LIMIT 1";
        
        $preference_stmt = $db->prepare($preference_query);
        $preference_stmt->execute([$from_dept_id, $to_dept_id]);
        $preference = $preference_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($preference) {
            $intermediate_department_id = $preference['intermediate_dept_id'];
            $intermediate_dept_name = $preference['intermediate_dept_name'];
            
            echo "✅ Found intermediate department: <strong>" . $intermediate_dept_name . "</strong><br>";
            
            // Create the routing rule
            $insert_query = "INSERT INTO document_routing (from_department_id, to_department_id, intermediate_department_id) 
                           VALUES (?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            $result = $insert_stmt->execute([$from_dept_id, $to_dept_id, $intermediate_department_id]);
            
            if ($result) {
                echo "✅ Routing created successfully!<br>";
                
                // Get department names
                $from_dept_name = '';
                $to_dept_name = '';
                foreach ($departments as $dept) {
                    if ($dept['id'] == $from_dept_id) $from_dept_name = $dept['name'];
                    if ($dept['id'] == $to_dept_id) $to_dept_name = $dept['name'];
                }
                
                $routing_path = [$from_dept_name, $intermediate_dept_name, $to_dept_name];
                echo "<div style='background: #e8f5e8; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "<strong>Created Route:</strong> " . implode(' → ', $routing_path);
                echo "</div>";
            } else {
                echo "❌ Failed to create routing<br>";
            }
        } else {
            echo "❌ No intermediate department found for this routing path<br>";
        }
    }
    
    // Show all current routing rules
    echo "<h3>Current Routing Rules</h3>";
    $routing_query = "SELECT 
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
    
    $routing_stmt = $db->prepare($routing_query);
    $routing_stmt->execute();
    $routing_rules = $routing_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($routing_rules) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>From</th><th>To</th><th>Intermediate</th><th>Full Path</th></tr>";
        
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
            echo "<td><strong>" . $path . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "ℹ️ No routing rules found<br>";
    }
    
    echo "<h3>How It Works</h3>";
    echo "<ol>";
    echo "<li><strong>Department Preferences:</strong> Each department defines which other departments it can route through</li>";
    echo "<li><strong>Dynamic Creation:</strong> When a user uploads a document, the system checks if a routing rule exists</li>";
    echo "<li><strong>Automatic Routing:</strong> If no rule exists, it finds a common intermediate department and creates the route</li>";
    echo "<li><strong>Example:</strong> IT → Operations → HR (Operations is the hub department)</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
