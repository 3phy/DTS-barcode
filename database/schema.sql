-- Document Tracking System Database Schema
CREATE DATABASE IF NOT EXISTS document_tracking;
USE document_tracking;

-- Departments table (must be created first)
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    current_department_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (current_department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Documents table 
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    barcode VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('outgoing', 'pending', 'received') DEFAULT 'outgoing',
    current_department_id INT NOT NULL,
    department_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    received_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (current_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default departments
INSERT INTO departments (name, description) VALUES 
('Human Resources', 'HR department for employee management and policies'),
('Finance', 'Financial department for accounting and budgeting'),
('IT Department', 'Information Technology department for technical support'),
('Operations', 'Operations department for daily business operations'),
('Marketing', 'Marketing department for promotional activities'),
('Legal', 'Legal department for compliance and contracts');

-- Insert default admin user
-- Password for both users is 'password123'
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@doctrack.com', '$2y$10$iRZcGKxg.d6gR75QrlhxD.DrKPLLQowtV.17mZHnzi1lkfcsn4kRC', 'admin'),
('Staff User', 'staff@doctrack.com', '$2y$10$iRZcGKxg.d6gR75QrlhxD.DrKPLLQowtV.17mZHnzi1lkfcsn4kRC', 'staff');

-- User activities table for tracking user actions
CREATE TABLE user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System logs table for application logging
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('error', 'warning', 'info', 'debug') NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Document routing table for defining routing paths between departments
CREATE TABLE document_routing (
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

-- Department routing preferences table for defining which departments can route through others
CREATE TABLE department_routing_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    can_route_through INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (can_route_through) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_preference (department_id, can_route_through)
);

-- Insert default department routing preferences
-- This defines which departments can route through others
INSERT INTO department_routing_preferences (department_id, can_route_through) VALUES
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

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'Document Tracking System', 'Name of the system'),
('max_file_size', '10', 'Maximum file size in MB'),
('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'Allowed file extensions'),
('auto_backup', '1', 'Enable automatic backup'),
('backup_frequency', 'daily', 'Backup frequency'),
('email_notifications', '1', 'Enable email notifications'),
('session_timeout', '30', 'Session timeout in minutes');

-- Create indexes for better performance
CREATE INDEX idx_documents_status ON documents(status);
CREATE INDEX idx_documents_uploaded_by ON documents(uploaded_by);
CREATE INDEX idx_documents_barcode ON documents(barcode);
CREATE INDEX idx_documents_uploaded_at ON documents(uploaded_at);
CREATE INDEX idx_documents_department_id ON documents(department_id);
CREATE INDEX idx_users_department_id ON users(department_id);
CREATE INDEX idx_user_activities_user_id ON user_activities(user_id);
CREATE INDEX idx_user_activities_created_at ON user_activities(created_at);
CREATE INDEX idx_system_logs_level ON system_logs(level);
CREATE INDEX idx_system_logs_timestamp ON system_logs(timestamp);
CREATE INDEX idx_document_routing_from_dept ON document_routing(from_department_id);
CREATE INDEX idx_document_routing_to_dept ON document_routing(to_department_id);
CREATE INDEX idx_department_routing_preferences_dept ON department_routing_preferences(department_id);
CREATE INDEX idx_department_routing_preferences_through ON department_routing_preferences(can_route_through);
