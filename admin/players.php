<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

// Create player_logs table if it doesn't exist
$create_logs_table = "CREATE TABLE IF NOT EXISTS player_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    admin_id INT NOT NULL,
    admin_username VARCHAR(50) NOT NULL,
    action_type ENUM('role_change', 'status_change', 'ban', 'unban', 'password_reset', 'profile_edit', 'delete', 'create') NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    reason TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_id (player_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
)";
$db->exec($create_logs_table);

// Create login_attempts table if it doesn't exist
$create_login_table = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at)
)";
$db->exec($create_login_table);

// Add missing columns to user_accounts if they don't exist
$add_columns_query = "ALTER TABLE user_accounts 
    ADD COLUMN IF NOT EXISTS role ENUM('user', 'moderator', 'admin') DEFAULT 'user' AFTER is_admin,
    ADD COLUMN IF NOT EXISTS status ENUM('active', 'banned', 'pending') DEFAULT 'active' AFTER role,
    ADD COLUMN IF NOT EXISTS ban_reason TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS banned_until DATETIME NULL AFTER ban_reason,
    ADD COLUMN IF NOT EXISTS banned_by VARCHAR(50) NULL AFTER banned_until,
    ADD COLUMN IF NOT EXISTS total_playtime INT DEFAULT 0 AFTER banned_by,
    ADD COLUMN IF NOT EXISTS discord_id VARCHAR(50) NULL AFTER total_playtime,
    ADD COLUMN IF NOT EXISTS steam_id VARCHAR(50) NULL AFTER discord_id,
    ADD COLUMN IF NOT EXISTS player_id VARCHAR(20) NULL AFTER steam_id";
$db->exec($add_columns_query);

$action_message = '';
$error_message = '';

// Handle player actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $player_id = $_POST['player_id'] ?? '';
    
    switch ($action) {
        case 'add_player':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $license = trim($_POST['license'] ?? '');
            $steam_id = trim($_POST['steam_id'] ?? '');
            $discord_id = trim($_POST['discord_id'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($email) || empty($license)) {
                $error_message = 'Username, email, and license are required';
            } else {
                // Check if username/email/license already exists
                $check_query = "SELECT id FROM user_accounts WHERE username = :username OR email = :email OR license = :license";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->bindParam(':email', $email);
                $check_stmt->bindParam(':license', $license);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    $error_message = 'Username, email, or license already exists';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $player_id_gen = 'PLR' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    
                    $insert_query = "INSERT INTO user_accounts (username, email, license, password_hash, role, steam_id, discord_id, player_id) 
                                     VALUES (:username, :email, :license, :password_hash, :role, :steam_id, :discord_id, :player_id)";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':username', $username);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':license', $license);
                    $insert_stmt->bindParam(':password_hash', $password_hash);
                    $insert_stmt->bindParam(':role', $role);
                    $insert_stmt->bindParam(':steam_id', $steam_id);
                    $insert_stmt->bindParam(':discord_id', $discord_id);
                    $insert_stmt->bindParam(':player_id', $player_id_gen);
                    
                    if ($insert_stmt->execute()) {
                        $new_player_id = $db->lastInsertId();
                        
                        // Log the action
                        $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, new_value, ip_address) 
                                      VALUES (:player_id, :admin_id, :admin_username, 'create', :username, :ip)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->bindParam(':player_id', $new_player_id);
                        $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                        $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                        $log_stmt->bindParam(':username', $username);
                        $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                        $log_stmt->execute();
                        
                        $action_message = "Player '{$username}' created successfully with ID: {$player_id_gen}";
                    } else {
                        $error_message = 'Failed to create player';
                    }
                }
            }
            break;
            
        case 'update_role':
            $new_role = $_POST['role'] ?? '';
            $old_role_query = "SELECT role FROM user_accounts WHERE id = :id";
            $old_role_stmt = $db->prepare($old_role_query);
            $old_role_stmt->bindParam(':id', $player_id);
            $old_role_stmt->execute();
            $old_role = $old_role_stmt->fetchColumn();
            
            $query = "UPDATE user_accounts SET role = :role WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':role', $new_role);
            $stmt->bindParam(':id', $player_id);
            if ($stmt->execute()) {
                // Log the action
                $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, old_value, new_value, ip_address) 
                              VALUES (:player_id, :admin_id, :admin_username, 'role_change', :old_value, :new_value, :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':player_id', $player_id);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                $log_stmt->bindParam(':old_value', $old_role);
                $log_stmt->bindParam(':new_value', $new_role);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                $log_stmt->execute();
                
                $action_message = "Player role updated from {$old_role} to {$new_role}";
            }
            break;
            
        case 'ban_player':
            $ban_reason = $_POST['ban_reason'] ?? '';
            $ban_duration = $_POST['ban_duration'] ?? '';
            $banned_until = null;
            
            if ($ban_duration && $ban_duration !== 'permanent') {
                $banned_until = date('Y-m-d H:i:s', strtotime("+{$ban_duration} days"));
            }
            
            $query = "UPDATE user_accounts SET status = 'banned', ban_reason = :reason, banned_until = :until, banned_by = :admin WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':reason', $ban_reason);
            $stmt->bindParam(':until', $banned_until);
            $stmt->bindParam(':admin', $_SESSION['username']);
            $stmt->bindParam(':id', $player_id);
            
            if ($stmt->execute()) {
                // Log the ban
                $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, new_value, reason, ip_address) 
                              VALUES (:player_id, :admin_id, :admin_username, 'ban', :duration, :reason, :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':player_id', $player_id);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                $log_stmt->bindParam(':duration', $ban_duration ?: 'permanent');
                $log_stmt->bindParam(':reason', $ban_reason);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                $log_stmt->execute();
                
                $action_message = 'Player banned successfully';
            }
            break;
            
        case 'unban_player':
            $query = "UPDATE user_accounts SET status = 'active', ban_reason = NULL, banned_until = NULL, banned_by = NULL WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $player_id);
            
            if ($stmt->execute()) {
                // Log the unban
                $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, ip_address) 
                              VALUES (:player_id, :admin_id, :admin_username, 'unban', :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':player_id', $player_id);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                $log_stmt->execute();
                
                $action_message = 'Player unbanned successfully';
            }
            break;
            
        case 'reset_password':
            $new_password = bin2hex(random_bytes(8));
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE user_accounts SET password_hash = :password WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id', $player_id);
            
            if ($stmt->execute()) {
                // Log the action
                $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, ip_address) 
                              VALUES (:player_id, :admin_id, :admin_username, 'password_reset', :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':player_id', $player_id);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                $log_stmt->execute();
                
                $action_message = "Password reset successfully. New password: {$new_password}";
            }
            break;
            
        case 'delete_player':
            $confirm = $_POST['confirm_delete'] ?? '';
            if ($confirm === 'DELETE') {
                // Get player info before deletion
                $player_query = "SELECT username FROM user_accounts WHERE id = :id";
                $player_stmt = $db->prepare($player_query);
                $player_stmt->bindParam(':id', $player_id);
                $player_stmt->execute();
                $player_info = $player_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log before deletion
                $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, old_value, ip_address) 
                              VALUES (:player_id, :admin_id, :admin_username, 'delete', :username, :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':player_id', $player_id);
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                $log_stmt->bindParam(':username', $player_info['username']);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                $log_stmt->execute();
                
                // Delete player
                $query = "DELETE FROM user_accounts WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $player_id);
                
                if ($stmt->execute()) {
                    $action_message = "Player '{$player_info['username']}' deleted successfully";
                }
            } else {
                $error_message = 'Please type DELETE to confirm player deletion';
            }
            break;
            
        case 'edit_player':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $steam_id = trim($_POST['steam_id'] ?? '');
            $discord_id = trim($_POST['discord_id'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            
            if (!empty($username) && !empty($email)) {
                $query = "UPDATE user_accounts SET username = :username, email = :email, steam_id = :steam_id, discord_id = :discord_id, role = :role, status = :status WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':steam_id', $steam_id);
                $stmt->bindParam(':discord_id', $discord_id);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $player_id);
                
                if ($stmt->execute()) {
                    // Log the action
                    $log_query = "INSERT INTO player_logs (player_id, admin_id, admin_username, action_type, new_value, ip_address) 
                                  VALUES (:player_id, :admin_id, :admin_username, 'profile_edit', :changes, :ip)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->bindParam(':player_id', $player_id);
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':admin_username', $_SESSION['username']);
                    $log_stmt->bindParam(':changes', "Updated profile for {$username}");
                    $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
                    $log_stmt->execute();
                    
                    $action_message = "Player '{$username}' updated successfully";
                }
            } else {
                $error_message = 'Username and email are required';
            }
            break;
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = (int)($_GET['per_page'] ?? 25);
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE :search OR email LIKE :search OR license LIKE :search OR steam_id LIKE :search OR discord_id LIKE :search OR player_id LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($role_filter !== 'all') {
    $where_conditions[] = "role = :role";
    $params[':role'] = $role_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM user_accounts $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_players = $count_stmt->fetchColumn();
$total_pages = ceil($total_players / $per_page);

// Get players with pagination
$players_query = "SELECT u.*, 
    (SELECT COUNT(*) FROM players p WHERE p.license = u.license) as character_count,
    (SELECT MAX(last_updated) FROM players p WHERE p.license = u.license) as last_activity,
    (SELECT COUNT(*) FROM login_attempts la WHERE la.username = u.username AND la.success = 0 AND la.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_attempts_today,
    (SELECT COUNT(*) FROM login_attempts la WHERE la.username = u.username AND la.success = 1) as total_logins
    FROM user_accounts u 
    $where_clause 
    ORDER BY u.created_at DESC 
    LIMIT :limit OFFSET :offset";
$players_stmt = $db->prepare($players_query);
foreach ($params as $key => $value) {
    $players_stmt->bindValue($key, $value);
}
$players_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$players_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$players_stmt->execute();
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quick stats
$stats_query = "SELECT 
    COUNT(*) as total_players,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_players,
    SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_players,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'moderator' THEN 1 ELSE 0 END) as moderator_count,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as new_today,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as online_now
    FROM user_accounts";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get detailed player stats for each player
foreach ($players as &$player) {
    // Get character count and total money
    $char_stats_query = "SELECT 
        COUNT(*) as character_count,
        SUM(JSON_EXTRACT(money, '$.bank') + JSON_EXTRACT(money, '$.cash')) as total_money
        FROM players WHERE license = :license";
    $char_stats_stmt = $db->prepare($char_stats_query);
    $char_stats_stmt->bindParam(':license', $player['license']);
    $char_stats_stmt->execute();
    $char_stats = $char_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $player['character_count'] = $char_stats['character_count'] ?? 0;
    $player['total_money'] = $char_stats['total_money'] ?? 0;
    
    // Get vehicle count
    $vehicle_count_query = "SELECT COUNT(*) FROM player_vehicles WHERE license = :license";
    $vehicle_count_stmt = $db->prepare($vehicle_count_query);
    $vehicle_count_stmt->bindParam(':license', $player['license']);
    $vehicle_count_stmt->execute();
    $player['vehicle_count'] = $vehicle_count_stmt->fetchColumn() ?? 0;
}

$page_title = 'Player Management';
include '../includes/header.php';
?>

<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <h1 class="text-xl font-bold text-red-400">Admin Panel</h1>
                </div>
                <div class="hidden md:ml-6 md:flex md:space-x-8">
                    <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="players.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-gamepad mr-2"></i>Players
                    </a>
                    <a href="tickets.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-ticket-alt mr-2"></i>Tickets
                    </a>
                    <a href="analytics.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                    <a href="server.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-server mr-2"></i>Server
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="../dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to UCP
                </a>
                <a href="../logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">
            <i class="fas fa-gamepad text-fivem-primary mr-3"></i>Player Management
        </h1>
        <p class="text-gray-400">Manage player accounts, roles, and permissions</p>
    </div>
    
    <?php if ($action_message): ?>
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-6 animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-6 animate-fade-in">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-500 bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-users text-blue-400 text-lg"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['total_players']; ?></p>
                    <p class="text-xs text-gray-400">Total Players</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-500 bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-user-check text-green-400 text-lg"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['active_players']; ?></p>
                    <p class="text-xs text-gray-400">Active</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-red-500 bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-user-slash text-red-400 text-lg"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['banned_players']; ?></p>
                    <p class="text-xs text-gray-400">Banned</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-500 bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-crown text-purple-400 text-lg"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['admin_count']; ?></p>
                    <p class="text-xs text-gray-400">Admins</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-yellow-500 bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-shield text-yellow-400 text-lg"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['moderator_count']; ?></p>
                    <p class="text-xs text-gray-400">Moderators</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-fivem-primary bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-user-plus text-fivem-primary text-lg"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['new_today']; ?></p>
                    <p class="text-xs text-gray-400">New Today</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 card-hover">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-emerald-500 bg-opacity-20 rounded-xl flex items-center justify-center mr-3">
                    <div class="w-3 h-3 bg-emerald-400 rounded-full animate-pulse"></div>
                </div>
                <div>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['online_now']; ?></p>
                    <p class="text-xs text-gray-400">Online Now</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters and Search -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-search mr-2"></i>Search Players
                </label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Username, email, license, steam ID, discord ID..."
                       class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-fivem-primary focus:border-transparent transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-user-tag mr-2"></i>Role
                </label>
                <select name="role" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-fivem-primary">
                    <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="moderator" <?php echo $role_filter == 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-toggle-on mr-2"></i>Status
                </label>
                <select name="status" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-fivem-primary">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="banned" <?php echo $status_filter == 'banned' ? 'selected' : ''; ?>>Banned</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            
            <div class="flex space-x-2">
                <button type="submit" class="bg-fivem-primary hover:bg-yellow-500 text-white px-6 py-3 rounded-lg font-medium transition-all transform hover:scale-105">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <a href="players.php" class="bg-gray-600 hover:bg-gray-500 text-white px-4 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Players Table -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-gamepad text-blue-400 mr-2"></i>Players (<?php echo number_format($total_players); ?>)
                </h2>
                <div class="flex items-center space-x-4">
                    <select onchange="changePerPage(this.value)" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-fivem-primary">
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                    <button onclick="openAddPlayerModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-all transform hover:scale-105 shadow-lg">
                        <i class="fas fa-user-plus mr-2"></i>Add Player
                    </button>
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Player</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">IDs</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Activity</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Stats</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach ($players as $player): 
                        $is_online = $player['last_login'] && strtotime($player['last_login']) > strtotime('-1 hour');
                        $is_new = strtotime($player['created_at']) > strtotime('-24 hours');
                    ?>
                        <tr class="hover:bg-gray-700 transition-colors <?php echo $is_new ? 'bg-green-500 bg-opacity-5' : ''; ?>" data-player-id="<?php echo $player['id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="relative">
                                        <div class="w-12 h-12 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-full flex items-center justify-center mr-4 shadow-lg">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <?php if ($is_online): ?>
                                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-gray-800 animate-pulse"></div>
                                        <?php endif; ?>
                                        <?php if ($is_new): ?>
                                            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-blue-400 rounded-full border-2 border-gray-800">
                                                <i class="fas fa-star text-white text-xs"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center">
                                            <span class="text-white font-medium"><?php echo htmlspecialchars($player['username']); ?></span>
                                            <?php if ($is_new): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-star mr-1"></i>New
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-gray-400 text-sm"><?php echo htmlspecialchars($player['email']); ?></div>
                                        <div class="text-gray-500 text-xs">
                                            <?php if ($player['player_id']): ?>
                                                ID: <?php echo htmlspecialchars($player['player_id']); ?> • 
                                            <?php endif; ?>
                                            <?php echo $player['character_count']; ?> characters • 
                                            Joined <?php echo date('M j, Y', strtotime($player['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm space-y-1">
                                    <div class="text-gray-300 font-mono text-xs flex items-center">
                                        <i class="fas fa-id-card mr-2 text-gray-500"></i>
                                        <span title="<?php echo htmlspecialchars($player['license']); ?>">
                                            <?php echo htmlspecialchars(substr($player['license'], -12)); ?>
                                        </span>
                                    </div>
                                    <?php if ($player['steam_id']): ?>
                                        <div class="text-gray-400 font-mono text-xs flex items-center">
                                            <i class="fab fa-steam mr-2 text-gray-500"></i>
                                            <span title="<?php echo htmlspecialchars($player['steam_id']); ?>">
                                                <?php echo htmlspecialchars(substr($player['steam_id'], -12)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($player['discord_id']): ?>
                                        <div class="text-gray-400 font-mono text-xs flex items-center">
                                            <i class="fab fa-discord mr-2 text-gray-500"></i>
                                            <span title="<?php echo htmlspecialchars($player['discord_id']); ?>">
                                                <?php echo htmlspecialchars(substr($player['discord_id'], -12)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php 
                                    switch($player['role']) {
                                        case 'admin': echo 'bg-red-100 text-red-800'; break;
                                        case 'moderator': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'user': echo 'bg-gray-100 text-gray-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?>">
                                    <?php 
                                    switch($player['role']) {
                                        case 'admin': echo '<i class="fas fa-crown mr-1"></i>Admin'; break;
                                        case 'moderator': echo '<i class="fas fa-shield mr-1"></i>Moderator'; break;
                                        case 'user': echo '<i class="fas fa-user mr-1"></i>User'; break;
                                        default: echo '<i class="fas fa-user mr-1"></i>User';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php 
                                        switch($player['status']) {
                                            case 'active': echo 'bg-green-100 text-green-800'; break;
                                            case 'banned': echo 'bg-red-100 text-red-800'; break;
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                        <?php 
                                        switch($player['status']) {
                                            case 'active': echo '<i class="fas fa-check mr-1"></i>Active'; break;
                                            case 'banned': echo '<i class="fas fa-ban mr-1"></i>Banned'; break;
                                            case 'pending': echo '<i class="fas fa-clock mr-1"></i>Pending'; break;
                                            default: echo '<i class="fas fa-question mr-1"></i>Unknown';
                                        }
                                        ?>
                                    </span>
                                    <?php if ($is_online): ?>
                                        <div class="ml-2 w-2 h-2 bg-green-400 rounded-full animate-pulse" title="Online Now"></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($player['status'] == 'banned' && $player['banned_until']): ?>
                                    <div class="text-xs text-red-400 mt-1">
                                        <i class="fas fa-clock mr-1"></i>
                                        Until: <?php echo date('M j, Y', strtotime($player['banned_until'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($player['ban_reason']): ?>
                                    <div class="text-xs text-red-300 mt-1" title="<?php echo htmlspecialchars($player['ban_reason']); ?>">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?php echo htmlspecialchars(substr($player['ban_reason'], 0, 30)) . (strlen($player['ban_reason']) > 30 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?php if ($player['last_login']): ?>
                                        <div class="text-white flex items-center">
                                            <i class="fas fa-sign-in-alt mr-2 text-gray-500"></i>
                                            <?php echo date('M j, Y', strtotime($player['last_login'])); ?>
                                        </div>
                                        <div class="text-gray-400 text-xs ml-6">
                                            <?php echo date('g:i A', strtotime($player['last_login'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 flex items-center">
                                            <i class="fas fa-minus mr-2"></i>Never
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($player['last_activity']): ?>
                                        <div class="text-xs text-blue-400 mt-1 flex items-center">
                                            <i class="fas fa-gamepad mr-1"></i>
                                            In-game: <?php echo date('M j', strtotime($player['last_activity'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($player['failed_attempts_today'] > 0): ?>
                                        <div class="text-xs text-red-400 mt-1 flex items-center">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <?php echo $player['failed_attempts_today']; ?> failed attempts
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm space-y-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-users mr-2 text-gray-500"></i>
                                        <span class="text-white"><?php echo $player['character_count']; ?></span>
                                        <span class="text-gray-