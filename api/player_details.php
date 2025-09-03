<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit();
}

$player_id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? 'view';

if (empty($player_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Player ID required']);
    exit();
}

// Get player details
$player_query = "SELECT * FROM user_accounts WHERE id = :id";
$player_stmt = $db->prepare($player_query);
$player_stmt->bindParam(':id', $player_id);
$player_stmt->execute();

if ($player_stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Player not found']);
    exit();
}

$player = $player_stmt->fetch(PDO::FETCH_ASSOC);

if ($action === 'edit') {
    echo json_encode([
        'success' => true,
        'player' => $player
    ]);
    exit();
}

// Get player characters
$characters_query = "SELECT * FROM players WHERE license = :license ORDER BY last_updated DESC";
$characters_stmt = $db->prepare($characters_query);
$characters_stmt->bindParam(':license', $player['license']);
$characters_stmt->execute();
$characters = $characters_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get player logs
$logs_query = "SELECT * FROM player_logs WHERE player_id = :player_id ORDER BY created_at DESC LIMIT 20";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->bindParam(':player_id', $player_id);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get login attempts
$login_attempts_query = "SELECT * FROM login_attempts WHERE username = :username ORDER BY created_at DESC LIMIT 10";
$login_attempts_stmt = $db->prepare($login_attempts_query);
$login_attempts_stmt->bindParam(':username', $player['username']);
$login_attempts_stmt->execute();
$login_attempts = $login_attempts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get support tickets
$tickets_query = "SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
$tickets_stmt = $db->prepare($tickets_query);
$tickets_stmt->bindParam(':user_id', $player_id);
$tickets_stmt->execute();
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_money = 0;
$total_vehicles = 0;
foreach ($characters as $character) {
    $money = json_decode($character['money'], true);
    $total_money += ($money['cash'] ?? 0) + ($money['bank'] ?? 0);
}

// Get vehicle count
$vehicles_query = "SELECT COUNT(*) FROM player_vehicles WHERE license = :license";
$vehicles_stmt = $db->prepare($vehicles_query);
$vehicles_stmt->bindParam(':license', $player['license']);
$vehicles_stmt->execute();
$total_vehicles = $vehicles_stmt->fetchColumn();

// Generate HTML content
$html = '
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Player Info -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Basic Info -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-user text-blue-400 mr-2"></i>Player Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-400 text-sm">Username</p>
                    <p class="text-white font-medium">' . htmlspecialchars($player['username']) . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Email</p>
                    <p class="text-white font-medium">' . htmlspecialchars($player['email']) . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Player ID</p>
                    <p class="text-white font-mono text-sm">' . htmlspecialchars($player['player_id'] ?: 'Not Set') . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">License</p>
                    <p class="text-white font-mono text-sm" title="' . htmlspecialchars($player['license']) . '">' . htmlspecialchars(substr($player['license'], -16)) . '</p>
                </div>';

if ($player['steam_id']) {
    $html .= '
                <div>
                    <p class="text-gray-400 text-sm">Steam ID</p>
                    <p class="text-white font-mono text-sm">' . htmlspecialchars($player['steam_id']) . '</p>
                </div>';
}

if ($player['discord_id']) {
    $html .= '
                <div>
                    <p class="text-gray-400 text-sm">Discord ID</p>
                    <p class="text-white font-mono text-sm">' . htmlspecialchars($player['discord_id']) . '</p>
                </div>';
}

$html .= '
                <div>
                    <p class="text-gray-400 text-sm">Registration Date</p>
                    <p class="text-white font-medium">' . date('M j, Y g:i A', strtotime($player['created_at'])) . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Last Login</p>
                    <p class="text-white font-medium">' . ($player['last_login'] ? date('M j, Y g:i A', strtotime($player['last_login'])) : 'Never') . '</p>
                </div>
            </div>
        </div>
        
        <!-- Characters -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-users text-green-400 mr-2"></i>Characters (' . count($characters) . ')
            </h3>';

if (empty($characters)) {
    $html .= '
            <div class="text-center py-8">
                <i class="fas fa-user-slash text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">No characters created</p>
            </div>';
} else {
    $html .= '<div class="space-y-3">';
    foreach ($characters as $character) {
        $charinfo = json_decode($character['charinfo'], true);
        $money = json_decode($character['money'], true);
        $job = json_decode($character['job'], true);
        
        $html .= '
                <div class="bg-gray-800 rounded-lg p-4 border border-gray-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-white font-semibold">' . htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']) . '</h4>
                            <p class="text-gray-400 text-sm">ID: ' . htmlspecialchars($character['citizenid']) . '</p>
                            <p class="text-gray-400 text-sm">Job: ' . htmlspecialchars($job['label']) . '</p>
                        </div>
                        <div class="text-right">
                            <p class="text-green-400 font-bold">$' . number_format(($money['cash'] ?? 0) + ($money['bank'] ?? 0)) . '</p>
                            <p class="text-gray-400 text-xs">Total Money</p>
                        </div>
                    </div>
                </div>';
    }
    $html .= '</div>';
}

$html .= '
        </div>
        
        <!-- Activity Logs -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-history text-purple-400 mr-2"></i>Admin Action History
            </h3>';

if (empty($logs)) {
    $html .= '
            <div class="text-center py-8">
                <i class="fas fa-clipboard-list text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">No admin actions recorded</p>
            </div>';
} else {
    $html .= '<div class="space-y-3 max-h-64 overflow-y-auto">';
    foreach ($logs as $log) {
        $action_color = match($log['action_type']) {
            'ban' => 'text-red-400',
            'unban' => 'text-green-400',
            'role_change' => 'text-blue-400',
            'password_reset' => 'text-yellow-400',
            'create' => 'text-green-400',
            'delete' => 'text-red-400',
            default => 'text-gray-400'
        };
        
        $action_icon = match($log['action_type']) {
            'ban' => 'fa-ban',
            'unban' => 'fa-user-check',
            'role_change' => 'fa-user-tag',
            'password_reset' => 'fa-key',
            'create' => 'fa-user-plus',
            'delete' => 'fa-trash',
            default => 'fa-edit'
        };
        
        $html .= '
                <div class="bg-gray-800 rounded-lg p-3 border border-gray-600">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas ' . $action_icon . ' ' . $action_color . ' mr-3"></i>
                            <div>
                                <p class="text-white text-sm font-medium">' . ucfirst(str_replace('_', ' ', $log['action_type'])) . '</p>
                                <p class="text-gray-400 text-xs">by ' . htmlspecialchars($log['admin_username']) . '</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-400 text-xs">' . date('M j, Y', strtotime($log['created_at'])) . '</p>
                            <p class="text-gray-500 text-xs">' . date('g:i A', strtotime($log['created_at'])) . '</p>
                        </div>
                    </div>';
        
        if ($log['reason']) {
            $html .= '
                    <div class="mt-2 text-gray-300 text-sm">
                        <i class="fas fa-quote-left mr-2 text-gray-500"></i>
                        ' . htmlspecialchars($log['reason']) . '
                    </div>';
        }
        
        if ($log['old_value'] && $log['new_value']) {
            $html .= '
                    <div class="mt-2 text-xs">
                        <span class="text-red-400">' . htmlspecialchars($log['old_value']) . '</span>
                        <i class="fas fa-arrow-right mx-2 text-gray-500"></i>
                        <span class="text-green-400">' . htmlspecialchars($log['new_value']) . '</span>
                    </div>';
        }
        
        $html .= '</div>';
    }
    $html .= '</div>';
}

$html .= '
        </div>
    </div>
    
    <!-- Sidebar Stats -->
    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-chart-bar text-fivem-primary mr-2"></i>Player Stats
            </h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Characters:</span>
                    <span class="text-white font-bold">' . count($characters) . '</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Total Money:</span>
                    <span class="text-green-400 font-bold">$' . number_format($total_money) . '</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Vehicles:</span>
                    <span class="text-white font-bold">' . $total_vehicles . '</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Support Tickets:</span>
                    <span class="text-white font-bold">' . count($tickets) . '</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Account Age:</span>
                    <span class="text-white font-bold">' . floor((time() - strtotime($player['created_at'])) / 86400) . ' days</span>
                </div>
            </div>
        </div>
        
        <!-- Security Info -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-shield-alt text-red-400 mr-2"></i>Security Info
            </h3>
            <div class="space-y-3">';

// Recent login attempts
$recent_attempts = array_slice($login_attempts, 0, 5);
$failed_attempts = array_filter($recent_attempts, fn($attempt) => !$attempt['success']);

$html .= '
                <div class="bg-gray-800 rounded-lg p-3">
                    <p class="text-gray-400 text-sm mb-2">Recent Login Attempts</p>';

if (empty($recent_attempts)) {
    $html .= '<p class="text-gray-500 text-xs">No login attempts recorded</p>';
} else {
    foreach (array_slice($recent_attempts, 0, 3) as $attempt) {
        $success_class = $attempt['success'] ? 'text-green-400' : 'text-red-400';
        $success_icon = $attempt['success'] ? 'fa-check' : 'fa-times';
        $success_text = $attempt['success'] ? 'Success' : 'Failed';
        
        $html .= '
                    <div class="flex items-center justify-between text-xs mb-1">
                        <div class="flex items-center">
                            <i class="fas ' . $success_icon . ' ' . $success_class . ' mr-2"></i>
                            <span class="text-gray-300">' . $success_text . '</span>
                        </div>
                        <span class="text-gray-500">' . date('M j, g:i A', strtotime($attempt['created_at'])) . '</span>
                    </div>';
    }
}

$html .= '
                </div>
                
                <div class="bg-gray-800 rounded-lg p-3">
                    <p class="text-gray-400 text-sm mb-2">Security Status</p>
                    <div class="space-y-2">';

if (count($failed_attempts) > 3) {
    $html .= '
                        <div class="flex items-center text-red-400 text-xs">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>Multiple failed login attempts</span>
                        </div>';
}

if ($player['status'] == 'banned') {
    $html .= '
                        <div class="flex items-center text-red-400 text-xs">
                            <i class="fas fa-ban mr-2"></i>
                            <span>Account is banned</span>
                        </div>';
}

if (strtotime($player['last_login']) > strtotime('-1 hour')) {
    $html .= '
                        <div class="flex items-center text-green-400 text-xs">
                            <i class="fas fa-circle mr-2"></i>
                            <span>Currently online</span>
                        </div>';
}

$html .= '
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-tools text-yellow-400 mr-2"></i>Quick Actions
            </h3>
            <div class="space-y-3">
                <button onclick="closeModal(\'playerModal\'); openEditModal(' . $player['id'] . ')" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-edit mr-2"></i>Edit Player
                </button>';

if ($player['status'] == 'banned') {
    $html .= '
                <form method="POST" class="w-full">
                    <input type="hidden" name="action" value="unban_player">
                    <input type="hidden" name="player_id" value="' . $player['id'] . '">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors"
                            onclick="return confirm(\'Are you sure you want to unban this player?\')">
                        <i class="fas fa-user-check mr-2"></i>Unban Player
                    </button>
                </form>';
} else {
    $html .= '
                <button onclick="closeModal(\'playerModal\'); openBanModal(' . $player['id'] . ', \'' . htmlspecialchars($player['username']) . '\')" 
                        class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-ban mr-2"></i>Ban Player
                </button>';
}

$html .= '
                <button onclick="resetPlayerPassword(' . $player['id'] . ', \'' . htmlspecialchars($player['username']) . '\')" 
                        class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-key mr-2"></i>Reset Password
                </button>
            </div>
        </div>
    </div>
</div>';

// Support tickets section
if (!empty($tickets)) {
    $html .= '
<div class="mt-6 bg-gray-700 rounded-xl p-6">
    <h3 class="text-lg font-bold text-white mb-4">
        <i class="fas fa-ticket-alt text-yellow-400 mr-2"></i>Recent Support Tickets
    </h3>
    <div class="space-y-3">';
    
    foreach ($tickets as $ticket) {
        $status_color = match($ticket['status']) {
            'open' => 'text-green-400',
            'in_progress' => 'text-yellow-400',
            'closed' => 'text-gray-400',
            default => 'text-gray-400'
        };
        
        $priority_color = match($ticket['priority']) {
            'urgent' => 'bg-red-100 text-red-800',
            'high' => 'bg-yellow-100 text-yellow-800',
            'medium' => 'bg-blue-100 text-blue-800',
            'low' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
        
        $html .= '
        <div class="bg-gray-800 rounded-lg p-3 border border-gray-600">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-white font-medium">' . htmlspecialchars($ticket['subject']) . '</h4>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . $priority_color . '">' . ucfirst($ticket['priority']) . '</span>
                    <span class="' . $status_color . ' text-xs">' . ucfirst(str_replace('_', ' ', $ticket['status'])) . '</span>
                </div>
            </div>
            <p class="text-gray-400 text-xs">' . date('M j, Y g:i A', strtotime($ticket['created_at'])) . '</p>
        </div>';
    }
    
    $html .= '</div></div>';
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'player' => $player
]);
?>