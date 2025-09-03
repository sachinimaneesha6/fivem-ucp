<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

// Get comprehensive server statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM players) as total_players,
    (SELECT COUNT(*) FROM user_accounts WHERE is_active = 1) as active_accounts,
    (SELECT COUNT(*) FROM player_vehicles) as total_vehicles,
    (SELECT COUNT(*) FROM apartments) as total_apartments,
    (SELECT COUNT(*) FROM support_tickets WHERE status = 'open') as open_tickets,
    (SELECT COUNT(*) FROM support_tickets WHERE status = 'in_progress') as pending_tickets,
    (SELECT SUM(JSON_EXTRACT(money, '$.bank') + JSON_EXTRACT(money, '$.cash')) FROM players) as total_economy";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$server_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$recent_players_query = "SELECT citizenid, name, last_updated FROM players ORDER BY last_updated DESC LIMIT 10";
$recent_players_stmt = $db->prepare($recent_players_query);
$recent_players_stmt->execute();
$recent_players = $recent_players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent tickets
$recent_tickets_query = "SELECT * FROM support_tickets ORDER BY created_at DESC LIMIT 5";
$recent_tickets_stmt = $db->prepare($recent_tickets_query);
$recent_tickets_stmt->execute();
$recent_tickets = $recent_tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Admin Dashboard';
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
    <!-- Admin Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
            <i class="fas fa-shield-alt text-red-400 mr-3"></i>Admin Dashboard
        </h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Server management and analytics overview</p>
    </div>
    
    <!-- Server Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="rounded-xl p-6 border card-hover theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="flex items-center">
                <div class="p-3 bg-blue-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-users text-blue-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Total Players</p>
                    <p class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $server_stats['total_players']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="rounded-xl p-6 border card-hover theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="flex items-center">
                <div class="p-3 bg-green-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-user-check text-green-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Active Accounts</p>
                    <p class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $server_stats['active_accounts']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="rounded-xl p-6 border card-hover theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-ticket-alt text-yellow-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Open Tickets</p>
                    <p class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $server_stats['open_tickets']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="rounded-xl p-6 border card-hover theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="flex items-center">
                <div class="p-3 bg-purple-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-dollar-sign text-purple-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Total Economy</p>
                    <p class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">$<?php echo number_format($server_stats['total_economy']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Activity -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">
                <i class="fas fa-clock text-blue-400 mr-2"></i>Recent Player Activity
            </h2>
            <div class="space-y-3">
                <?php foreach ($recent_players as $player): ?>
                    <div class="flex items-center justify-between bg-gray-700 rounded-lg p-3">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-fivem-primary rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user text-white text-xs"></i>
                            </div>
                            <div>
                                <p class="text-white font-medium"><?php echo htmlspecialchars($player['name']); ?></p>
                                <p class="text-gray-400 text-sm">ID: <?php echo htmlspecialchars($player['citizenid']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-400 text-sm"><?php echo date('M j', strtotime($player['last_updated'])); ?></p>
                            <p class="text-gray-500 text-xs"><?php echo date('g:i A', strtotime($player['last_updated'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Tickets -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-life-ring text-yellow-400 mr-2"></i>Recent Support Tickets
                </h2>
                <a href="tickets.php" class="text-fivem-primary hover:text-yellow-400 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <?php if (empty($recent_tickets)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-4xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400">No recent tickets</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_tickets as $ticket): ?>
                        <div class="bg-gray-700 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-white font-medium"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                    switch($ticket['priority']) {
                                        case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                        case 'high': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'medium': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                    }
                                ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">By: <?php echo htmlspecialchars($ticket['username']); ?></span>
                                <span class="text-gray-500"><?php echo date('M j, g:i A', strtotime($ticket['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>