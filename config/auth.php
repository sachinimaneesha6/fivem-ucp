<?php
session_start();

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($username, $password) {
        $query = "SELECT * FROM user_accounts WHERE username = :username AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['license'] = $user['license'];
                
                // Update last login
                $update_query = "UPDATE user_accounts SET last_login = NOW() WHERE id = :id";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bindParam(':id', $user['id']);
                $update_stmt->execute();
                
                // Log admin login
                if ($user['is_admin']) {
                    $this->logActivity('login', $user['id'], $user['username'], 'Admin login', $_SERVER['REMOTE_ADDR'] ?? '');
                }
                
                return true;
            }
        }
        return false;
    }
    
    public function logout() {
        session_destroy();
        header('Location: index.php');
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php');
            exit();
        }
    }
    
    public function getUserCharacters($license) {
        $query = "SELECT * FROM players WHERE license = :license ORDER BY last_updated DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':license', $license);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function isAdmin() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $query = "SELECT is_admin FROM user_accounts WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return (bool)$user['is_admin'];
        }
        return false;
    }
    
    public function requireAdmin() {
        if (!$this->isAdmin()) {
            header('Location: dashboard.php');
            exit();
        }
    }
    
    private function logActivity($type, $user_id, $username, $message, $ip = '') {
        try {
            // Create server_logs table if it doesn't exist
            $create_table_query = "CREATE TABLE IF NOT EXISTS server_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                log_type ENUM('login', 'logout', 'admin_action', 'error', 'warning', 'info') NOT NULL,
                user_id INT NULL,
                username VARCHAR(50) NULL,
                message TEXT NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_log_type (log_type),
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (user_id)
            )";
            $this->db->exec($create_table_query);
            
            $query = "INSERT INTO server_logs (log_type, user_id, username, message, ip_address, user_agent) 
                      VALUES (:type, :user_id, :username, :message, :ip, :user_agent)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':message', $message);
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
            $stmt->execute();
        } catch (Exception $e) {
            // Log error silently - don't break the login process
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
?>