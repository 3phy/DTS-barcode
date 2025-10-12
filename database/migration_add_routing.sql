-- Migration script to add document routing functionality
-- Run this script to add the routing table and default routing rules

USE document_tracking;

-- Create document routing table if it doesn't exist
CREATE TABLE IF NOT EXISTS document_routing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_department_id INT NOT NULL,
    to_department_id INT NOT NULL,
    intermediate_department_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (to_department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (intermediate_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    UNIQUE KEY unique_routing (from_department_id, to_department_id)
);

-- Create department routing preferences table if it doesn't exist
CREATE TABLE IF NOT EXISTS department_routing_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    can_route_through INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (can_route_through) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_preference (department_id, can_route_through)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_document_routing_from_dept ON document_routing(from_department_id);
CREATE INDEX IF NOT EXISTS idx_document_routing_to_dept ON document_routing(to_department_id);
CREATE INDEX IF NOT EXISTS idx_department_routing_preferences_dept ON department_routing_preferences(department_id);
CREATE INDEX IF NOT EXISTS idx_department_routing_preferences_through ON department_routing_preferences(can_route_through);

-- Insert default department routing preferences
-- This defines which departments can route through others
INSERT IGNORE INTO department_routing_preferences (department_id, can_route_through) VALUES
-- IT Department can route through Operations (MIS)
(3, 4), -- IT -> Operations
-- HR Department can route through Operations
(1, 4), -- HR -> Operations  
-- Finance Department can route through Operations
(2, 4), -- Finance -> Operations
-- Marketing Department can route through Operations
(5, 4), -- Marketing -> Operations
-- Legal Department can route through Operations
(6, 4), -- Legal -> Operations
-- Operations can route through any department (hub department)
(4, 1), -- Operations -> HR
(4, 2), -- Operations -> Finance
(4, 3), -- Operations -> IT
(4, 5), -- Operations -> Marketing
(4, 6); -- Operations -> Legal

-- Update existing documents to use the new routing system
-- This will set all existing documents to 'pending' status and update their current_department_id
-- based on routing rules if they exist
UPDATE documents d
JOIN document_routing dr ON d.current_department_id = dr.from_department_id AND d.department_id = dr.to_department_id
SET d.status = 'pending', 
    d.current_department_id = COALESCE(dr.intermediate_department_id, d.department_id)
WHERE d.status = 'outgoing' AND dr.is_active = 1;

-- For documents without routing rules, set them to pending at destination
UPDATE documents d
LEFT JOIN document_routing dr ON d.current_department_id = dr.from_department_id AND d.department_id = dr.to_department_id
SET d.status = 'pending', 
    d.current_department_id = d.department_id
WHERE d.status = 'outgoing' AND dr.id IS NULL;

SELECT 'Migration completed successfully!' as message;
