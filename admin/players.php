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
try {
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
} catch (Exception $e) {
    // Columns might already exist, continue
}

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
                        $action_message = "Player '{$username}' created successfully with ID: {$player_id_gen}";
                    } else {
                        $error_message = 'Failed to create player';
                    }
                }
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
    $where_conditions[] = "(username LIKE :search OR email LIKE :search OR license LIKE :search)";
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

// Get players with their character data
$players_query = "SELECT u.*, 
    (SELECT COUNT(*) FROM players p WHERE p.license = u.license) as character_count,
    (SELECT MAX(last_updated) FROM players p WHERE p.license = u.license) as last_activity,
    (SELECT citizenid FROM players p WHERE p.license = u.license ORDER BY last_updated DESC LIMIT 1) as latest_character_id
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
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_players,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_players,
    SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as new_today,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as online_now
    FROM user_accounts";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

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
        <p class="text-gray-400">Manage player characters and game data</p>
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
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
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
                    <p class="text-2xl font-bold text-white"><?php echo $stats['inactive_players']; ?></p>
                    <p class="text-xs text-gray-400">Inactive</p>
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
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-search mr-2"></i>Search Players
                </label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Username, email, license..."
                       class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-fivem-primary focus:border-transparent transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    <i class="fas fa-user-tag mr-2"></i>Role
                </label>
                <select name="role" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-fivem-primary">
                    <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
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
                </div>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Player</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">License</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Characters</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Last Activity</th>
                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach ($players as $player): 
                        $is_online = $player['last_login'] && strtotime($player['last_login']) > strtotime('-1 hour');
                        $is_new = strtotime($player['created_at']) > strtotime('-24 hours');
                    ?>
                        <tr class="hover:bg-gray-700 transition-colors <?php echo $is_new ? 'bg-green-500 bg-opacity-5' : ''; ?>">
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
                                            <?php if ($player['is_admin']): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-crown mr-1"></i>Admin
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-gray-400 text-sm"><?php echo htmlspecialchars($player['email']); ?></div>
                                        <div class="text-gray-500 text-xs">
                                            Joined <?php echo date('M j, Y', strtotime($player['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <div class="text-gray-300 font-mono text-xs" title="<?php echo htmlspecialchars($player['license']); ?>">
                                        <?php echo htmlspecialchars(substr($player['license'], -16)); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="text-white font-bold text-lg"><?php echo $player['character_count']; ?></span>
                                    <span class="text-gray-400 text-sm ml-1">characters</span>
                                </div>
                                <?php if ($player['latest_character_id']): ?>
                                    <div class="text-xs text-blue-400">
                                        Latest: <?php echo htmlspecialchars($player['latest_character_id']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $player['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <i class="fas <?php echo $player['is_active'] ? 'fa-check' : 'fa-times'; ?> mr-1"></i>
                                        <?php echo $player['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <?php if ($is_online): ?>
                                        <div class="ml-2 w-2 h-2 bg-green-400 rounded-full animate-pulse" title="Online Now"></div>
                                    <?php endif; ?>
                                </div>
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
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex space-x-2">
                                    <?php if ($player['latest_character_id']): ?>
                                        <button onclick="openPlayerModal('<?php echo htmlspecialchars($player['latest_character_id']); ?>')" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                            <i class="fas fa-eye mr-1"></i>Details
                                        </button>
                                        <a href="../character_detail.php?id=<?php echo htmlspecialchars($player['latest_character_id']); ?>" 
                                           target="_blank"
                                           class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                            <i class="fas fa-external-link-alt mr-1"></i>View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-xs">No characters</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-700 flex items-center justify-between">
                <div class="text-sm text-gray-400">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_players); ?> of <?php echo $total_players; ?> players
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&per_page=<?php echo $per_page; ?>" 
                           class="bg-gray-600 hover:bg-gray-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-chevron-left mr-1"></i>Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&per_page=<?php echo $per_page; ?>" 
                           class="px-3 py-2 rounded-lg text-sm transition-colors <?php echo $i == $page ? 'bg-fivem-primary text-white' : 'bg-gray-600 hover:bg-gray-500 text-white'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&per_page=<?php echo $per_page; ?>" 
                           class="bg-gray-600 hover:bg-gray-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                            Next<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Player Details Modal -->
<div id="playerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 max-w-6xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex items-center justify-between p-6 border-b border-gray-700">
            <h2 class="text-xl font-bold text-white">
                <i class="fas fa-user text-fivem-primary mr-2"></i>Player Details
            </h2>
            <button onclick="closePlayerModal()" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="playerModalContent" class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
            <div class="flex items-center justify-center py-12">
                <i class="fas fa-spinner fa-spin text-fivem-primary text-2xl mr-3"></i>
                <span class="text-white">Loading player details...</span>
            </div>
        </div>
    </div>
</div>

<script>
function changePerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1'); // Reset to first page
    window.location.href = url.toString();
}

function openPlayerModal(citizenid) {
    console.log('🎮 Opening player modal for citizenid:', citizenid);
    
    const modal = document.getElementById('playerModal');
    const content = document.getElementById('playerModalContent');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Show loading state
    content.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <i class="fas fa-spinner fa-spin text-fivem-primary text-2xl mr-3"></i>
            <span class="text-white">Loading player details...</span>
        </div>
    `;
    
    // Load player data
    loadPlayerDetails(citizenid);
}

function closePlayerModal() {
    const modal = document.getElementById('playerModal');
    modal.classList.add('hidden');
}

async function loadPlayerDetails(citizenid) {
    try {
        console.log('📡 Fetching character data for:', citizenid);
        
        const response = await fetch(`../api/character_data.php?citizenid=${encodeURIComponent(citizenid)}`);
        console.log('📊 API Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ API Error Response:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }
        
        const data = await response.json();
        console.log('✅ Character data loaded:', data);
        
        if (data.success) {
            displayPlayerDetails(data);
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
    } catch (error) {
        console.error('💥 Error loading player details:', error);
        
        const content = document.getElementById('playerModalContent');
        content.innerHTML = `
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-red-500 bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Error Loading Player Details</h3>
                <p class="text-gray-400 mb-4">${error.message}</p>
                <button onclick="loadPlayerDetails('${citizenid}')" class="bg-fivem-primary hover:bg-yellow-500 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    <i class="fas fa-redo mr-2"></i>Try Again
                </button>
            </div>
        `;
    }
}

function displayPlayerDetails(data) {
    const character = data.character;
    const vehicles = data.vehicles || [];
    
    console.log('🎨 Displaying character details:', character);
    
    const content = document.getElementById('playerModalContent');
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Character Header -->
            <div class="bg-gray-700 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fas fa-user text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">${character.charinfo.firstname} ${character.charinfo.lastname}</h3>
                            <p class="text-gray-400">Citizen ID: ${character.citizenid}</p>
                            <p class="text-gray-500 text-sm">Last active: ${new Date(character.last_updated).toLocaleDateString()}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-green-400 text-2xl font-bold">$${character.money.total.toLocaleString()}</div>
                        <div class="text-gray-400 text-sm">Total Money</div>
                    </div>
                </div>
            </div>
            
            <!-- Financial Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-money-bill-wave text-green-400"></i>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-green-400">$${character.money.cash.toLocaleString()}</p>
                            <p class="text-xs text-gray-400">Cash</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-university text-blue-400"></i>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-blue-400">$${character.money.bank.toLocaleString()}</p>
                            <p class="text-xs text-gray-400">Bank</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-briefcase text-purple-400"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">${character.job.label}</p>
                            <p class="text-xs text-gray-400">Job</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-users text-red-400"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">${character.gang.label}</p>
                            <p class="text-xs text-gray-400">Gang</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Character Stats -->
            <div class="bg-gray-700 rounded-xl p-6">
                <h4 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-heart text-red-400 mr-2"></i>Character Status
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400 text-sm">Health</span>
                            <span class="text-white font-medium">${character.metadata.isdead ? '0' : '100'}%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full transition-all" style="width: ${character.metadata.isdead ? '0' : '100'}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400 text-sm">Hunger</span>
                            <span class="text-white font-medium">${character.metadata.hunger}%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full transition-all" style="width: ${character.metadata.hunger}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400 text-sm">Thirst</span>
                            <span class="text-white font-medium">${character.metadata.thirst}%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: ${character.metadata.thirst}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400 text-sm">Stress</span>
                            <span class="text-white font-medium">${character.metadata.stress}%</span>
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-2">
                            <div class="bg-yellow-500 h-2 rounded-full transition-all" style="width: ${character.metadata.stress}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Position Information -->
            <div class="bg-gray-700 rounded-xl p-6">
                <h4 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-map-marker-alt text-blue-400 mr-2"></i>Last Known Position
                </h4>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <p class="text-gray-400 text-sm">X Coordinate</p>
                        <p class="text-white font-mono text-lg">${character.position.x}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-400 text-sm">Y Coordinate</p>
                        <p class="text-white font-mono text-lg">${character.position.y}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-400 text-sm">Z Coordinate</p>
                        <p class="text-white font-mono text-lg">${character.position.z}</p>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Section -->
            <div class="bg-gray-700 rounded-xl p-6" x-data="{ inventoryExpanded: false }">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-bold text-white">
                        <i class="fas fa-boxes text-green-400 mr-2"></i>Inventory (${character.inventory.length} items)
                    </h4>
                    <button @click="inventoryExpanded = !inventoryExpanded" class="text-fivem-primary hover:text-yellow-400 transition-colors">
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': inventoryExpanded }"></i>
                    </button>
                </div>
                <div x-show="inventoryExpanded" x-transition class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-2">
                    ${character.inventory.map(item => `
                        <div class="bg-gray-600 rounded-lg p-2 text-center border border-gray-500 hover:border-fivem-primary transition-colors" title="${item.name} (${item.amount})">
                            <div class="w-8 h-8 bg-fivem-primary rounded-lg flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-cube text-white text-xs"></i>
                            </div>
                            <p class="text-xs text-white truncate">${item.name.replace(/_/g, ' ')}</p>
                            <p class="text-xs text-fivem-primary font-bold">${item.amount}</p>
                        </div>
                    `).join('')}
                </div>
                ${character.inventory.length === 0 ? '<p class="text-gray-400 text-center py-4">No items in inventory</p>' : ''}
            </div>
            
            <!-- Vehicles Section -->
            <div class="bg-gray-700 rounded-xl p-6" x-data="{ vehiclesExpanded: false }">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-bold text-white">
                        <i class="fas fa-car text-purple-400 mr-2"></i>Vehicles (${vehicles.length})
                    </h4>
                    <button @click="vehiclesExpanded = !vehiclesExpanded" class="text-fivem-primary hover:text-yellow-400 transition-colors">
                        <i class="fas fa-chevron-down" :class="{ 'rotate-180': vehiclesExpanded }"></i>
                    </button>
                </div>
                <div x-show="vehiclesExpanded" x-transition class="space-y-4">
                    ${vehicles.map(vehicle => `
                        <div class="bg-gray-600 rounded-lg p-4 border border-gray-500">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h5 class="text-white font-semibold">${vehicle.vehicle}</h5>
                                    <p class="text-gray-400 text-sm">Plate: ${vehicle.plate}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${vehicle.state == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                    ${vehicle.state == 1 ? 'Available' : 'Impounded'}
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center">
                                    <p class="text-gray-400 text-xs">Fuel</p>
                                    <div class="w-full bg-gray-700 rounded-full h-2 mt-1">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: ${vehicle.fuel}%"></div>
                                    </div>
                                    <p class="text-white text-xs mt-1">${vehicle.fuel}%</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-xs">Engine</p>
                                    <div class="w-full bg-gray-700 rounded-full h-2 mt-1">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: ${vehicle.engine}%"></div>
                                    </div>
                                    <p class="text-white text-xs mt-1">${vehicle.engine}%</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-xs">Body</p>
                                    <div class="w-full bg-gray-700 rounded-full h-2 mt-1">
                                        <div class="bg-yellow-500 h-2 rounded-full" style="width: ${vehicle.body}%"></div>
                                    </div>
                                    <p class="text-white text-xs mt-1">${vehicle.body}%</p>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                ${vehicles.length === 0 ? '<p class="text-gray-400 text-center py-4">No vehicles owned</p>' : ''}
            </div>
        </div>
    `;
}

// Close modal when clicking outside
document.getElementById('playerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePlayerModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePlayerModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>