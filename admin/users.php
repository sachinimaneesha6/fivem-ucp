<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

// Handle user actions
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    switch ($action) {
        case 'toggle_status':
            $query = "UPDATE user_accounts SET is_active = NOT is_active WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $action_message = 'User status updated successfully';
            }
            break;
        case 'make_admin':
            $query = "UPDATE user_accounts SET is_admin = 1 WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $action_message = 'User promoted to admin';
            }
            break;
        case 'remove_admin':
            $query = "UPDATE user_accounts SET is_admin = 0 WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $user_id);
            if ($stmt->execute()) {
                $action_message = 'Admin privileges removed';
            }
            break;
    }
}

// Get all users with their character count
$users_query = "SELECT u.*, 
    (SELECT COUNT(*) FROM players p WHERE p.license = u.license) as character_count,
    (SELECT MAX(last_updated) FROM players p WHERE p.license = u.license) as last_activity
    FROM user_accounts u 
    ORDER BY u.created_at DESC";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'User Management';
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
                    <a href="users.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-users mr-2"></i>Users
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
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">User Management</h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Manage user accounts and permissions</p>
    </div>
    
    <?php if ($action_message): ?>
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Users Table -->
    <div class="rounded-xl border overflow-hidden theme-transition" 
         :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
        <div class="p-6 border-b theme-transition" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
            <h2 class="text-xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                <i class="fas fa-users text-blue-400 mr-2"></i>All Users
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-50'">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Characters</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Last Activity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y theme-transition" :class="darkMode ? 'divide-gray-700' : 'divide-gray-200'">
                    <?php foreach ($users as $user): ?>
                        <tr class="transition-colors theme-transition" :class="darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50'">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-fivem-primary rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $user['character_count']; ?></span>
                                <span class="text-sm ml-1 theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">characters</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                                <?php if ($user['last_activity']): ?>
                                    <?php echo date('M j, Y', strtotime($user['last_activity'])); ?>
                                    <div class="text-sm theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'"><?php echo date('g:i A', strtotime($user['last_activity'])); ?></div>
                                <?php else: ?>
                                    <span class="theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user['is_admin'] ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-blue-400 hover:text-blue-300 transition-colors">
                                            <i class="fas fa-toggle-<?php echo $user['is_active'] ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if (!$user['is_admin']): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="make_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-red-400 hover:text-red-300 transition-colors" title="Make Admin">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="remove_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-gray-400 hover:text-gray-300 transition-colors" title="Remove Admin">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>