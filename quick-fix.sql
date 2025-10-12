-- Quick fix to assign users to departments
-- Run this in your MySQL client or phpMyAdmin

USE document_tracking;

-- Assign all users without departments to IT Department (ID: 3)
UPDATE users 
SET department_id = 3 
WHERE department_id IS NULL;

-- Verify the assignment
SELECT id, name, email, department_id, 
       (SELECT name FROM departments WHERE id = users.department_id) as department_name
FROM users;
