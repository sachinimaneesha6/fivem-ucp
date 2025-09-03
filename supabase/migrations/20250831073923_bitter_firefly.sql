-- Add admin column to user_accounts table
ALTER TABLE user_accounts 
ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) DEFAULT 0 AFTER is_active;

-- Create support_tickets table if it doesn't exist
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    admin_response TEXT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at)
);

-- Create UCP chat table
CREATE TABLE IF NOT EXISTS ucp_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- Create server logs table
CREATE TABLE IF NOT EXISTS server_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('login', 'logout', 'admin_action', 'error', 'warning', 'info') NOT NULL,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_type (log_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- Make the first user an admin (update this with your username)
UPDATE user_accounts SET is_admin = 1 WHERE username = 'issa' LIMIT 1;