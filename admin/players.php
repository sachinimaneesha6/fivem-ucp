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
                                        <span class="text-gray-400 ml-1">chars</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-sign-in-alt mr-2 text-gray-500"></i>
                                        <span class="text-white"><?php echo $player['total_logins']; ?></span>
                                        <span class="text-gray-400 ml-1">logins</span>
                                    </div>
                                    <?php if ($player['total_playtime'] > 0): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-clock mr-2 text-gray-500"></i>
                                            <span class="text-white"><?php echo round($player['total_playtime'] / 60); ?>h</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-300">
                                <div class="flex space-x-2">
                                    <button onclick="openPlayerModal(<?php echo $player['id']; ?>)" 
                                            class="text-blue-400 hover:text-blue-300 transition-colors p-2 hover:bg-blue-500 hover:bg-opacity-20 rounded-lg" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="openEditModal(<?php echo $player['id']; ?>)" 
                                            class="text-green-400 hover:text-green-300 transition-colors p-2 hover:bg-green-500 hover:bg-opacity-20 rounded-lg" 
                                            title="Edit Player">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($player['status'] == 'banned'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="unban_player">
                                            <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                            <button type="submit" class="text-green-400 hover:text-green-300 transition-colors p-2 hover:bg-green-500 hover:bg-opacity-20 rounded-lg" 
                                                    title="Unban Player" onclick="return confirm('Are you sure you want to unban this player?')">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button onclick="openBanModal(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['username']); ?>')" 
                                                class="text-red-400 hover:text-red-300 transition-colors p-2 hover:bg-red-500 hover:bg-opacity-20 rounded-lg" 
                                                title="Ban Player">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="resetPlayerPassword(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['username']); ?>')" 
                                            class="text-yellow-400 hover:text-yellow-300 transition-colors p-2 hover:bg-yellow-500 hover:bg-opacity-20 rounded-lg" 
                                            title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button onclick="openDeleteModal(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['username']); ?>')" 
                                            class="text-red-500 hover:text-red-400 transition-colors p-2 hover:bg-red-500 hover:bg-opacity-20 rounded-lg" 
                                            title="Delete Player">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-700 bg-gray-750">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-400">
                        Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_players)); ?> of <?php echo number_format($total_players); ?> players
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded-lg transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="px-3 py-2 rounded-lg transition-colors <?php echo $i == $page ? 'bg-fivem-primary text-white' : 'bg-gray-700 hover:bg-gray-600 text-white'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded-lg transition-colors">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Player Detail Modal -->
<div id="playerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-4xl w-full max-h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-user-circle text-blue-400 mr-2"></i>Player Details
                </h3>
                <button onclick="closeModal('playerModal')" class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div id="playerModalContent" class="p-6">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-4"></i>
                <p class="text-gray-400">Loading player details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Player Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full">
        <div class="p-6 border-b border-gray-700">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-edit text-green-400 mr-2"></i>Edit Player
            </h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="edit_player">
            <input type="hidden" name="player_id" id="editPlayerId">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
                    <input type="text" name="username" id="editUsername" required 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-fivem-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" id="editEmail" required 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-fivem-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Steam ID</label>
                    <input type="text" name="steam_id" id="editSteamId" 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-fivem-primary"
                           placeholder="steam:xxxxxxxxx">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Discord ID</label>
                    <input type="text" name="discord_id" id="editDiscordId" 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-fivem-primary"
                           placeholder="123456789012345678">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Role</label>
                        <select name="role" id="editRole" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-fivem-primary">
                            <option value="user">User</option>
                            <option value="moderator">Moderator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                        <select name="status" id="editStatus" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-fivem-primary">
                            <option value="active">Active</option>
                            <option value="banned">Banned</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg font-medium transition-all transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
                <button type="button" onclick="closeModal('editModal')" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Ban Player Modal -->
<div id="banModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full">
        <div class="p-6 border-b border-gray-700">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-ban text-red-400 mr-2"></i>Ban Player
            </h3>
            <p class="text-gray-400 text-sm mt-1">Banning: <span id="banPlayerName" class="text-white font-medium"></span></p>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="ban_player">
            <input type="hidden" name="player_id" id="banPlayerId">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Reason
                    </label>
                    <textarea name="ban_reason" required rows="3" 
                              class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 resize-none focus:ring-2 focus:ring-red-500"
                              placeholder="Enter detailed ban reason..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-clock mr-2"></i>Duration
                    </label>
                    <select name="ban_duration" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-red-500">
                        <option value="1">1 Day</option>
                        <option value="3">3 Days</option>
                        <option value="7">1 Week</option>
                        <option value="14">2 Weeks</option>
                        <option value="30">1 Month</option>
                        <option value="90">3 Months</option>
                        <option value="permanent">Permanent</option>
                    </select>
                </div>
                
                <div class="bg-red-500 bg-opacity-20 border border-red-500 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                        <span class="text-red-300 text-sm font-medium">This action will immediately ban the player from the server</span>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-medium transition-all transform hover:scale-105">
                    <i class="fas fa-ban mr-2"></i>Ban Player
                </button>
                <button type="button" onclick="closeModal('banModal')" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Player Modal -->
<div id="addPlayerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-lg w-full">
        <div class="p-6 border-b border-gray-700">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-user-plus text-green-400 mr-2"></i>Add New Player
            </h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="add_player">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-user mr-2"></i>Username *
                    </label>
                    <input type="text" name="username" required 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500"
                           placeholder="Enter username">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email *
                    </label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500"
                           placeholder="Enter email">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-id-card mr-2"></i>License ID *
                    </label>
                    <input type="text" name="license" required 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500"
                           placeholder="license:xxxxxxxxx">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fab fa-steam mr-2"></i>Steam ID
                    </label>
                    <input type="text" name="steam_id" 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500"
                           placeholder="steam:xxxxxxxxx">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fab fa-discord mr-2"></i>Discord ID
                    </label>
                    <input type="text" name="discord_id" 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500"
                           placeholder="123456789012345678">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-user-tag mr-2"></i>Role
                    </label>
                    <select name="role" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-green-500">
                        <option value="user">User</option>
                        <option value="moderator">Moderator</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <div class="flex space-x-2">
                        <input type="text" name="password" id="newPassword" required 
                               class="flex-1 px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500"
                               placeholder="Auto-generated password">
                        <button type="button" onclick="generatePassword()" class="bg-fivem-primary hover:bg-yellow-500 text-white px-4 py-3 rounded-lg transition-colors">
                            <i class="fas fa-random"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg font-medium transition-all transform hover:scale-105">
                    <i class="fas fa-user-plus mr-2"></i>Add Player
                </button>
                <button type="button" onclick="closeModal('addPlayerModal')" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Player Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-md w-full">
        <div class="p-6 border-b border-gray-700">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-trash text-red-400 mr-2"></i>Delete Player
            </h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="delete_player">
            <input type="hidden" name="player_id" id="deletePlayerId">
            
            <div class="space-y-4">
                <div class="bg-red-500 bg-opacity-20 border border-red-500 rounded-lg p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                        <span class="text-red-300 font-semibold">Permanent Action Warning</span>
                    </div>
                    <p class="text-red-300 text-sm">
                        You are about to permanently delete player: <strong id="deletePlayerName"></strong>
                    </p>
                    <p class="text-red-300 text-sm mt-2">
                        This will remove all account data and cannot be undone.
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Type "DELETE" to confirm
                    </label>
                    <input type="text" name="confirm_delete" required 
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-red-500"
                           placeholder="Type DELETE to confirm">
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-medium transition-all transform hover:scale-105">
                    <i class="fas fa-trash mr-2"></i>Delete Player
                </button>
                <button type="button" onclick="closeModal('deleteModal')" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openPlayerModal(playerId) {
    document.getElementById('playerModal').classList.remove('hidden');
    loadPlayerDetails(playerId);
}

function openEditModal(playerId) {
    // Load player data for editing
    loadPlayerForEdit(playerId);
    document.getElementById('editModal').classList.remove('hidden');
}

function openBanModal(playerId, username) {
    document.getElementById('banPlayerId').value = playerId;
    document.getElementById('banPlayerName').textContent = username;
    document.getElementById('banModal').classList.remove('hidden');
}

function openDeleteModal(playerId, username) {
    document.getElementById('deletePlayerId').value = playerId;
    document.getElementById('deletePlayerName').textContent = username;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function openAddPlayerModal() {
    generatePassword();
    document.getElementById('addPlayerModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('newPassword').value = password;
}

function changePerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function resetPlayerPassword(playerId, username) {
    if (confirm(`Are you sure you want to reset the password for ${username}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="player_id" value="${playerId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

async function loadPlayerDetails(playerId) {
    try {
        const response = await fetch(`../api/player_details.php?id=${playerId}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('playerModalContent').innerHTML = data.html;
        } else {
            document.getElementById('playerModalContent').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-4"></i>
                    <p class="text-red-400">Failed to load player details</p>
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('playerModalContent').innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-4"></i>
                <p class="text-red-400">Error loading player details</p>
            </div>
        `;
    }
}

async function loadPlayerForEdit(playerId) {
    try {
        const response = await fetch(`../api/player_details.php?id=${playerId}&action=edit`);
        const data = await response.json();
        
        if (data.success && data.player) {
            const player = data.player;
            document.getElementById('editPlayerId').value = player.id;
            document.getElementById('editUsername').value = player.username;
            document.getElementById('editEmail').value = player.email;
            document.getElementById('editSteamId').value = player.steam_id || '';
            document.getElementById('editDiscordId').value = player.discord_id || '';
            document.getElementById('editRole').value = player.role;
            document.getElementById('editStatus').value = player.status;
        }
    } catch (error) {
        console.error('Failed to load player for editing:', error);
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0')) {
        e.target.classList.add('hidden');
    }
});

// Auto-refresh player stats every 30 seconds
setInterval(async function() {
    try {
        const response = await fetch('../api/server_status.php');
        const data = await response.json();
        
        if (data.players) {
            // Update online player indicators
            document.querySelectorAll('[data-player-online]').forEach(el => {
                const playerId = el.dataset.playerId;
                // Update online status if available
            });
        }
    } catch (error) {
        console.error('Failed to update player status:', error);
    }
}, 30000);
</script>

<style>
.animate-fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.bg-gray-750 {
    background-color: #374151;
}

/* Enhanced hover effects */
.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

/* Online indicator pulse */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Modal backdrop blur */
.modal-backdrop {
    backdrop-filter: blur(4px);
}
</style>

<?php include '../includes/footer.php'; ?>