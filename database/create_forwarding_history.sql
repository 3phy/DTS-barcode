-- Create document forwarding history table
CREATE TABLE IF NOT EXISTS document_forwarding_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    from_department_id INT NOT NULL,
    to_department_id INT NOT NULL,
    forwarded_by INT NOT NULL,
    forwarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (from_department_id) REFERENCES departments(id),
    FOREIGN KEY (to_department_id) REFERENCES departments(id),
    FOREIGN KEY (forwarded_by) REFERENCES users(id),
    INDEX idx_document_id (document_id),
    INDEX idx_forwarded_at (forwarded_at)
);

-- Add some sample data for testing (optional)
-- INSERT INTO document_forwarding_history (document_id, from_department_id, to_department_id, forwarded_by) 
-- VALUES (1, 1, 2, 1), (1, 2, 3, 2);
