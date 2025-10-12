<?php
// Script to fix user department assignment
require_once 'api/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Fix User Department Assignment</h2>";

try {
    // Get all users
    echo "<h3>Current Users:</h3>";
    $users_query = "SELECT id, name, email, role, department_id FROM users ORDER BY id";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department ID</th><th>Action</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['name'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td>" . ($user['department_id'] ?: 'None') . "</td>";
            echo "<td>";
            if (!$user['department_id']) {
                echo "<a href='?assign_dept=1&user_id=" . $user['id'] . "'>Assign Department</a>";
            } else {
                echo "✅ Assigned";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No users found<br>";
    }
    
    // Get all departments
    echo "<h3>Available Departments:</h3>";
    $dept_query = "SELECT id, name, description FROM departments WHERE is_active = 1 ORDER BY name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($departments) > 0) {
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
        echo "No departments found<br>";
    }
    
    // Handle department assignment
    if (isset($_GET['assign_dept']) && isset($_GET['user_id'])) {
        $user_id = $_GET['user_id'];
        
        // Assign to first available department (or IT department if available)
        $assign_dept_query = "SELECT id FROM departments WHERE is_active = 1 ORDER BY 
                             CASE WHEN name LIKE '%IT%' THEN 1 ELSE 2 END, name LIMIT 1";
        $assign_dept_stmt = $db->prepare($assign_dept_query);
        $assign_dept_stmt->execute();
        $dept_to_assign = $assign_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dept_to_assign) {
            $update_query = "UPDATE users SET department_id = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $result = $update_stmt->execute([$dept_to_assign['id'], $user_id]);
            
            if ($result) {
                echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "✅ User assigned to department ID " . $dept_to_assign['id'] . " successfully!";
                echo "</div>";
                echo "<script>setTimeout(function(){ window.location.href = 'fix-user-department.php'; }, 2000);</script>";
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "❌ Failed to assign department";
                echo "</div>";
            }
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "❌ No departments available to assign";
            echo "</div>";
        }
    }
    
    // Auto-assign all users without departments
    if (isset($_GET['auto_assign'])) {
        echo "<h3>Auto-assigning all users without departments...</h3>";
        
        $users_without_dept = "SELECT id, name FROM users WHERE department_id IS NULL";
        $users_stmt = $db->prepare($users_without_dept);
        $users_stmt->execute();
        $users_to_assign = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assign_dept_query = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY 
                             CASE WHEN name LIKE '%IT%' THEN 1 ELSE 2 END, name LIMIT 1";
        $assign_dept_stmt = $db->prepare($assign_dept_query);
        $assign_dept_stmt->execute();
        $dept_to_assign = $assign_dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dept_to_assign && count($users_to_assign) > 0) {
            $update_query = "UPDATE users SET department_id = ? WHERE department_id IS NULL";
            $update_stmt = $db->prepare($update_query);
            $result = $update_stmt->execute([$dept_to_assign['id']]);
            
            if ($result) {
                echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "✅ Assigned " . count($users_to_assign) . " users to " . $dept_to_assign['name'] . " department!";
                echo "</div>";
                echo "<script>setTimeout(function(){ window.location.href = 'fix-user-department.php'; }, 2000);</script>";
            }
        }
    }
    
    // Show action buttons
    echo "<h3>Quick Actions:</h3>";
    echo "<p><a href='?auto_assign=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Auto-assign all users to IT department</a></p>";
    echo "<p><a href='fix-user-department.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Refresh page</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
