<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

// Get user characters
$characters = $auth->getUserCharacters($_SESSION['license']);

// Get user's total statistics
$user_stats_query = "SELECT 
    COUNT(*) as character_count,
    SUM(JSON_EXTRACT(money, '$.bank') + JSON_EXTRACT(money, '$.cash')) as total_money,
    (SELECT COUNT(*) FROM player_vehicles WHERE citizenid IN (SELECT citizenid FROM players WHERE license = :license)) as vehicle_count,
    (SELECT COUNT(*) FROM apartments WHERE citizenid IN (SELECT citizenid FROM players WHERE license = :license)) as apartment_count,
    (SELECT COUNT(*) FROM support_tickets WHERE user_id = :user_id) as ticket_count
    FROM players WHERE license = :license2";
$user_stats_stmt = $db->prepare($user_stats_query);
$user_stats_stmt->bindParam(':license', $_SESSION['license']);
$user_stats_stmt->bindParam(':license2', $_SESSION['license']);
$user_stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stats_stmt->execute();
$user_stats = $user_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get latest character for quick stats
$latest_character = null;
if (!empty($characters)) {
    $latest_character = $characters[0];
}

// Get server statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM players WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as online_players,
    (SELECT COUNT(*) FROM players) as total_players,
    (SELECT COUNT(*) FROM player_vehicles) as total_vehicles,
    (SELECT COUNT(*) FROM apartments) as total_apartments,
    (SELECT COUNT(*) FROM user_accounts WHERE is_active = 1) as active_accounts";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$server_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Dashboard';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="min-h-screen pb-20 lg:pb-8">
    <!-- Hero Section -->
    <div class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-800 opacity-10"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl mb-6 shadow-2xl">
                    <i class="fas fa-crown text-white text-2xl"></i>
                </div>
                <h1 class="text-4xl md:text-5xl font-bold mb-4 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    Welcome back, <span class="bg-gradient-to-r from-fivem-primary to-yellow-500 bg-clip-text text-transparent"><?php echo $_SESSION['username']; ?></span>
                </h1>
                <p class="text-xl theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-600'">
                    Your FiveM command center awaits
                </p>
            </div>
            
            <!-- Quick Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                <div class="group relative overflow-hidden rounded-2xl p-6 theme-transition border shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full -mr-10 -mt-10 opacity-20"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-users text-white text-lg"></i>
                            </div>
                            <span class="text-3xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $user_stats['character_count'] ?? 0; ?></span>
                        </div>
                        <p class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Characters</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Active profiles</p>
                    </div>
                </div>
                
                <div class="group relative overflow-hidden rounded-2xl p-6 theme-transition border shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-green-500 to-green-600 rounded-full -mr-10 -mt-10 opacity-20"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-dollar-sign text-white text-lg"></i>
                            </div>
                            <span class="text-2xl font-bold text-green-500">$<?php echo number_format($user_stats['total_money'] ?? 0); ?></span>
                        </div>
                        <p class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Total Worth</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">All characters</p>
                    </div>
                </div>
                
                <div class="group relative overflow-hidden rounded-2xl p-6 theme-transition border shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full -mr-10 -mt-10 opacity-20"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-car text-white text-lg"></i>
                            </div>
                            <span class="text-3xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $user_stats['vehicle_count'] ?? 0; ?></span>
                        </div>
                        <p class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Vehicles</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Owned fleet</p>
                    </div>
                </div>
                
                <div class="group relative overflow-hidden rounded-2xl p-6 theme-transition border shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full -mr-10 -mt-10 opacity-20"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-home text-white text-lg"></i>
                            </div>
                            <span class="text-3xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $user_stats['apartment_count'] ?? 0; ?></span>
                        </div>
                        <p class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Properties</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Real estate</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Server Status Banner -->
        <div class="mb-8 rounded-2xl border p-6 theme-transition shadow-xl relative overflow-hidden" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-blue-500/10"></div>
            <div class="relative flex flex-col md:flex-row items-center justify-between">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="w-16 h-16 bg-gradient-to-r from-green-500 to-blue-500 rounded-2xl flex items-center justify-center mr-6 shadow-lg">
                        <i class="fas fa-server text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Server Status</h2>
                        <div class="flex items-center mt-1">
                            <div class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                            <span class="text-green-500 font-semibold">Online & Operational</span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-500" data-player-count>0/64</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Players</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-500" data-cpu-usage>25%</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">CPU</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-purple-500" data-memory-usage>45%</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Memory</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-yellow-500">35ms</p>
                        <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Ping</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Main Content Area -->
            <div class="xl:col-span-2 space-y-8">
                <!-- Characters Section -->
                <div class="rounded-2xl border p-8 theme-transition shadow-xl" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                <i class="fas fa-users text-fivem-primary mr-3"></i>Your Characters
                            </h2>
                            <p class="theme-transition mt-1" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Manage your roleplay identities</p>
                        </div>
                        <a href="characters.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
                            View All
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($characters)): ?>
                        <div class="text-center py-16">
                            <div class="w-24 h-24 bg-gradient-to-r from-gray-400 to-gray-500 rounded-full flex items-center justify-center mx-auto mb-6 opacity-50">
                                <i class="fas fa-user-plus text-white text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-3 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">No Characters Found</h3>
                            <p class="theme-transition mb-6" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Create your first character in-game to get started</p>
                            <div class="inline-flex items-center px-4 py-2 rounded-xl theme-transition" :class="darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-700'">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span class="text-sm">Characters sync automatically from the server</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid gap-6">
                            <?php foreach (array_slice($characters, 0, 3) as $index => $character): 
                                $charinfo = json_decode($character['charinfo'], true);
                                $money = json_decode($character['money'], true);
                                $job = json_decode($character['job'], true);
                                $metadata = json_decode($character['metadata'], true);
                            ?>
                                <div class="group relative overflow-hidden rounded-2xl p-6 theme-transition border transition-all duration-300 hover:shadow-xl transform hover:-translate-y-1" 
                                     :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-fivem-primary to-yellow-500"></div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-16 h-16 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center mr-6 shadow-lg">
                                                <i class="fas fa-user text-white text-xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                                    <?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>
                                                </h3>
                                                <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                                    <?php echo htmlspecialchars($job['label']); ?> • ID: <?php echo htmlspecialchars($character['citizenid']); ?>
                                                </p>
                                                <p class="text-xs theme-transition mt-1" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                                                    Last active: <?php echo date('M j, g:i A', strtotime($character['last_updated'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="text-right">
                                            <div class="mb-4">
                                                <p class="text-2xl font-bold text-green-500">$<?php echo number_format($money['bank'] + $money['cash']); ?></p>
                                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Total Money</p>
                                            </div>
                                            <a href="character_detail.php?id=<?php echo $character['citizenid']; ?>" 
                                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105 shadow-md">
                                                <i class="fas fa-eye mr-2"></i>View Details
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Character Status Bars -->
                                    <div class="mt-6 grid grid-cols-3 gap-4">
                                        <div>
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Health</span>
                                                <span class="text-xs font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['isdead'] ? '0' : '100'; ?>%</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full theme-transition" :class="darkMode ? 'bg-gray-600' : 'bg-gray-200'">
                                                <div class="bg-red-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $metadata['isdead'] ? '0' : '100'; ?>%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Hunger</span>
                                                <span class="text-xs font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['hunger']; ?>%</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full theme-transition" :class="darkMode ? 'bg-gray-600' : 'bg-gray-200'">
                                                <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $metadata['hunger']; ?>%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Thirst</span>
                                                <span class="text-xs font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['thirst']; ?>%</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full theme-transition" :class="darkMode ? 'bg-gray-600' : 'bg-gray-200'">
                                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $metadata['thirst']; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions Grid -->
                <div class="rounded-2xl border p-8 theme-transition shadow-xl" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <h2 class="text-2xl font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                        <i class="fas fa-bolt text-yellow-500 mr-3"></i>Quick Actions
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <a href="inventory.php" class="group relative overflow-hidden rounded-xl p-6 theme-transition border transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1" 
                           :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full -mr-8 -mt-8 opacity-20 group-hover:opacity-30 transition-opacity"></div>
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-4 shadow-md">
                                    <i class="fas fa-boxes text-white"></i>
                                </div>
                                <h3 class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Inventory</h3>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Manage items</p>
                            </div>
                        </a>
                        
                        <a href="vehicles.php" class="group relative overflow-hidden rounded-xl p-6 theme-transition border transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1" 
                           :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full -mr-8 -mt-8 opacity-20 group-hover:opacity-30 transition-opacity"></div>
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-4 shadow-md">
                                    <i class="fas fa-car text-white"></i>
                                </div>
                                <h3 class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Vehicles</h3>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Garage access</p>
                            </div>
                        </a>
                        
                        <a href="banking.php" class="group relative overflow-hidden rounded-xl p-6 theme-transition border transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1" 
                           :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-full -mr-8 -mt-8 opacity-20 group-hover:opacity-30 transition-opacity"></div>
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-xl flex items-center justify-center mb-4 shadow-md">
                                    <i class="fas fa-university text-white"></i>
                                </div>
                                <h3 class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Banking</h3>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Finances</p>
                            </div>
                        </a>
                        
                        <a href="map.php" class="group relative overflow-hidden rounded-xl p-6 theme-transition border transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1" 
                           :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-full -mr-8 -mt-8 opacity-20 group-hover:opacity-30 transition-opacity"></div>
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-xl flex items-center justify-center mb-4 shadow-md">
                                    <i class="fas fa-map text-white"></i>
                                </div>
                                <h3 class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Map</h3>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Locations</p>
                            </div>
                        </a>
                        
                        <a href="properties.php" class="group relative overflow-hidden rounded-xl p-6 theme-transition border transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1" 
                           :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-full -mr-8 -mt-8 opacity-20 group-hover:opacity-30 transition-opacity"></div>
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center mb-4 shadow-md">
                                    <i class="fas fa-home text-white"></i>
                                </div>
                                <h3 class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Properties</h3>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Real estate</p>
                            </div>
                        </a>
                        
                        <a href="chat.php" class="group relative overflow-hidden rounded-xl p-6 theme-transition border transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1" 
                           :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-full -mr-8 -mt-8 opacity-20 group-hover:opacity-30 transition-opacity"></div>
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-xl flex items-center justify-center mb-4 shadow-md">
                                    <i class="fas fa-comments text-white"></i>
                                </div>
                                <h3 class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Chat</h3>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Community</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-8">
                <!-- Latest Character Card -->
                <?php if ($latest_character): 
                    $latest_charinfo = json_decode($latest_character['charinfo'], true);
                    $latest_money = json_decode($latest_character['money'], true);
                    $latest_metadata = json_decode($latest_character['metadata'], true);
                    $latest_job = json_decode($latest_character['job'], true);
                ?>
                    <div class="rounded-2xl border p-6 theme-transition shadow-xl relative overflow-hidden" 
                         :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                        <div class="absolute inset-0 bg-gradient-to-br from-fivem-primary/10 to-yellow-500/10"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                    <i class="fas fa-star text-fivem-primary mr-2"></i>Active Character
                                </h3>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-1 animate-pulse"></div>
                                    Online
                                </span>
                            </div>
                            
                            <div class="text-center mb-6">
                                <div class="w-20 h-20 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <i class="fas fa-user text-white text-xl"></i>
                                </div>
                                <h4 class="text-xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                    <?php echo htmlspecialchars($latest_charinfo['firstname'] . ' ' . $latest_charinfo['lastname']); ?>
                                </h4>
                                <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                    <?php echo htmlspecialchars($latest_job['label']); ?>
                                </p>
                                <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                                    ID: <?php echo htmlspecialchars($latest_character['citizenid']); ?>
                                </p>
                            </div>
                            
                            <!-- Financial Overview -->
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="text-center p-4 rounded-xl theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center mx-auto mb-2">
                                        <i class="fas fa-money-bill-wave text-white text-sm"></i>
                                    </div>
                                    <p class="text-lg font-bold text-green-500">$<?php echo number_format($latest_money['cash']); ?></p>
                                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Cash</p>
                                </div>
                                <div class="text-center p-4 rounded-xl theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                                    <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mx-auto mb-2">
                                        <i class="fas fa-university text-white text-sm"></i>
                                    </div>
                                    <p class="text-lg font-bold text-blue-500">$<?php echo number_format($latest_money['bank']); ?></p>
                                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Bank</p>
                                </div>
                            </div>
                            
                            <!-- Status Indicators -->
                            <div class="space-y-3 mb-6">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Health</span>
                                    <div class="flex items-center">
                                        <div class="w-20 h-2 rounded-full mr-2 theme-transition" :class="darkMode ? 'bg-gray-600' : 'bg-gray-200'">
                                            <div class="bg-red-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $latest_metadata['isdead'] ? '0' : '100'; ?>%"></div>
                                        </div>
                                        <span class="text-xs font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                            <?php echo $latest_metadata['isdead'] ? 'Dead' : 'Alive'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Hunger</span>
                                    <div class="flex items-center">
                                        <div class="w-20 h-2 rounded-full mr-2 theme-transition" :class="darkMode ? 'bg-gray-600' : 'bg-gray-200'">
                                            <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $latest_metadata['hunger']; ?>%"></div>
                                        </div>
                                        <span class="text-xs font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $latest_metadata['hunger']; ?>%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Thirst</span>
                                    <div class="flex items-center">
                                        <div class="w-20 h-2 rounded-full mr-2 theme-transition" :class="darkMode ? 'bg-gray-600' : 'bg-gray-200'">
                                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $latest_metadata['thirst']; ?>%"></div>
                                        </div>
                                        <span class="text-xs font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $latest_metadata['thirst']; ?>%</span>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="character_detail.php?id=<?php echo $latest_character['citizenid']; ?>" 
                               class="block w-full bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white text-center py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <i class="fas fa-eye mr-2"></i>View Full Details
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Support & Community -->
                <div class="rounded-2xl border p-6 theme-transition shadow-xl" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <h3 class="text-lg font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                        <i class="fas fa-life-ring text-green-500 mr-2"></i>Support & Community
                    </h3>
                    <div class="space-y-4">
                        <a href="tickets.php" class="flex items-center justify-between p-4 rounded-xl transition-all duration-300 theme-transition transform hover:scale-105" 
                           :class="darkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-100 hover:bg-gray-200'">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-ticket-alt text-white"></i>
                                </div>
                                <div>
                                    <p class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Support Tickets</p>
                                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Get help from staff</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-bold text-yellow-500"><?php echo $user_stats['ticket_count'] ?? 0; ?></span>
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Open</p>
                            </div>
                        </a>
                        
                        <a href="chat.php" class="flex items-center justify-between p-4 rounded-xl transition-all duration-300 theme-transition transform hover:scale-105" 
                           :class="darkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-100 hover:bg-gray-200'">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-comments text-white"></i>
                                </div>
                                <div>
                                    <p class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Community Chat</p>
                                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Connect with players</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                <span class="text-xs ml-2 theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Live</span>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Server Info Widget -->
                <div class="rounded-2xl border p-6 theme-transition shadow-xl" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <h3 class="text-lg font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                        <i class="fas fa-server text-blue-500 mr-2"></i>Server Info
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Total Players:</span>
                            <span class="font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $server_stats['total_players']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Online Now:</span>
                            <span class="font-bold text-green-500" data-player-count><?php echo $server_stats['online_players']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Total Vehicles:</span>
                            <span class="font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo number_format($server_stats['total_vehicles']); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Properties:</span>
                            <span class="font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo number_format($server_stats['total_apartments']); ?></span>
                        </div>
                        
                        <div class="pt-4 border-t theme-transition" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                                    <span class="text-sm font-medium text-green-500">Server Online</span>
                                </div>
                                <span class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Live</span>
                            </div>
                        </div>
                        
                        <?php if ($auth->isAdmin()): ?>
                            <a href="admin/server.php" class="block w-full mt-4 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white text-center py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <i class="fas fa-cog mr-2"></i>Admin Panel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced real-time updates for dashboard
async function updateDashboardData() {
    try {
        const response = await fetch('api/server_status.php');
        const data = await response.json();
        
        if (data.players) {
            const playerCountElements = document.querySelectorAll('[data-player-count]');
            playerCountElements.forEach(el => {
                el.textContent = data.players.online;
            });
        }
        
        if (data.performance) {
            const cpuElements = document.querySelectorAll('[data-cpu-usage]');
            cpuElements.forEach(el => {
                el.textContent = `${data.performance.cpu}%`;
            });
            
            const memoryElements = document.querySelectorAll('[data-memory-usage]');
            memoryElements.forEach(el => {
                el.textContent = `${data.performance.memory}%`;
            });
        }
    } catch (error) {
        console.error('Failed to update dashboard data:', error);
    }
}

// Update every 30 seconds
setInterval(updateDashboardData, 30000);

// Initial update
document.addEventListener('DOMContentLoaded', updateDashboardData);
</script>

<?php include 'includes/footer.php'; ?>