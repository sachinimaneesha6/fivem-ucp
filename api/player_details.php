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

// Get player characters with detailed info
$characters_query = "SELECT * FROM players WHERE license = :license ORDER BY last_updated DESC";
$characters_stmt = $db->prepare($characters_query);
$characters_stmt->bindParam(':license', $player['license']);
$characters_stmt->execute();
$characters = $characters_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get player vehicles
$vehicles_query = "SELECT * FROM player_vehicles WHERE license = :license ORDER BY vehicle ASC";
$vehicles_stmt = $db->prepare($vehicles_query);
$vehicles_stmt->bindParam(':license', $player['license']);
$vehicles_stmt->execute();
$vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get support tickets
$tickets_query = "SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
$tickets_stmt = $db->prepare($tickets_query);
$tickets_stmt->bindParam(':user_id', $player_id);
$tickets_stmt->execute();
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_money = 0;
$total_items = 0;
foreach ($characters as $character) {
    $money = json_decode($character['money'], true);
    $total_money += ($money['cash'] ?? 0) + ($money['bank'] ?? 0);
    
    $inventory = json_decode($character['inventory'], true) ?? [];
    $total_items += count($inventory);
}

// Generate detailed HTML content
$html = '
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Player Info -->
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
                    <p class="text-gray-400 text-sm">License</p>
                    <p class="text-white font-mono text-xs" title="' . htmlspecialchars($player['license']) . '">' . htmlspecialchars(substr($player['license'], -20)) . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Registration</p>
                    <p class="text-white font-medium">' . date('M j, Y g:i A', strtotime($player['created_at'])) . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Last Login</p>
                    <p class="text-white font-medium">' . ($player['last_login'] ? date('M j, Y g:i A', strtotime($player['last_login'])) : 'Never') . '</p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Status</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . ($player['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">
                        ' . ($player['is_active'] ? 'Active' : 'Inactive') . '
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Characters Section -->
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
    $html .= '<div class="space-y-4">';
    foreach ($characters as $character) {
        $charinfo = json_decode($character['charinfo'], true);
        $money = json_decode($character['money'], true);
        $job = json_decode($character['job'], true);
        $metadata = json_decode($character['metadata'], true);
        $inventory = json_decode($character['inventory'], true) ?? [];
        
        $html .= '
                <div class="bg-gray-800 rounded-lg p-4 border border-gray-600" x-data="{ showInventory: false, showDetails: false }">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="text-white font-semibold text-lg">' . htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']) . '</h4>
                            <p class="text-gray-400 text-sm">ID: ' . htmlspecialchars($character['citizenid']) . '</p>
                            <p class="text-gray-400 text-sm">Job: ' . htmlspecialchars($job['label']) . ' (Grade: ' . $job['grade']['level'] . ')</p>
                        </div>
                        <div class="text-right">
                            <p class="text-green-400 font-bold text-lg">$' . number_format(($money['cash'] ?? 0) + ($money['bank'] ?? 0)) . '</p>
                            <p class="text-gray-400 text-xs">Total Money</p>
                            <p class="text-gray-500 text-xs">Last: ' . date('M j, g:i A', strtotime($character['last_updated'])) . '</p>
                        </div>
                    </div>
                    
                    <!-- Character Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <div class="text-center p-3 bg-gray-900 rounded-lg">
                            <div class="w-8 h-8 bg-green-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-money-bill-wave text-green-400 text-sm"></i>
                            </div>
                            <p class="text-green-400 font-bold">$' . number_format($money['cash'] ?? 0) . '</p>
                            <p class="text-gray-500 text-xs">Cash</p>
                        </div>
                        <div class="text-center p-3 bg-gray-900 rounded-lg">
                            <div class="w-8 h-8 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-university text-blue-400 text-sm"></i>
                            </div>
                            <p class="text-blue-400 font-bold">$' . number_format($money['bank'] ?? 0) . '</p>
                            <p class="text-gray-500 text-xs">Bank</p>
                        </div>
                        <div class="text-center p-3 bg-gray-900 rounded-lg">
                            <div class="w-8 h-8 bg-purple-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-boxes text-purple-400 text-sm"></i>
                            </div>
                            <p class="text-purple-400 font-bold">' . count($inventory) . '</p>
                            <p class="text-gray-500 text-xs">Items</p>
                        </div>
                        <div class="text-center p-3 bg-gray-900 rounded-lg">
                            <div class="w-8 h-8 bg-yellow-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-heart text-red-400 text-sm"></i>
                            </div>
                            <p class="text-white font-bold">' . ($metadata['isdead'] ? 'Dead' : 'Alive') . '</p>
                            <p class="text-gray-500 text-xs">Status</p>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-2 mb-4">
                        <button @click="showDetails = !showDetails" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span x-text="showDetails ? \'Hide Details\' : \'Show Details\'"></span>
                        </button>
                        <button @click="showInventory = !showInventory" 
                                class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors text-sm">
                            <i class="fas fa-boxes mr-2"></i>
                            <span x-text="showInventory ? \'Hide Inventory\' : \'Show Inventory\'"></span>
                        </button>
                    </div>
                    
                    <!-- Character Details -->
                    <div x-show="showDetails" x-transition class="space-y-4">
                        <div class="bg-gray-900 rounded-lg p-4">
                            <h5 class="text-white font-semibold mb-3">Character Details</h5>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-gray-400 text-xs">Phone</p>
                                    <p class="text-white text-sm">' . htmlspecialchars($charinfo['phone'] ?? 'N/A') . '</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Nationality</p>
                                    <p class="text-white text-sm">' . htmlspecialchars($charinfo['nationality'] ?? 'N/A') . '</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Birthdate</p>
                                    <p class="text-white text-sm">' . htmlspecialchars($charinfo['birthdate'] ?? 'N/A') . '</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Account Number</p>
                                    <p class="text-white text-sm font-mono">' . htmlspecialchars($charinfo['account'] ?? 'N/A') . '</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Wallet ID</p>
                                    <p class="text-white text-sm font-mono">' . htmlspecialchars($metadata['walletid'] ?? 'N/A') . '</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Blood Type</p>
                                    <p class="text-white text-sm">' . htmlspecialchars($metadata['bloodtype'] ?? 'N/A') . '</p>
                                </div>
                            </div>
                            
                            <!-- Status Bars -->
                            <div class="mt-4 space-y-3">
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-gray-400 text-xs">Health</span>
                                        <span class="text-white text-xs">' . ($metadata['isdead'] ? '0' : '100') . '%</span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full transition-all duration-500" style="width: ' . ($metadata['isdead'] ? '0' : '100') . '%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-gray-400 text-xs">Hunger</span>
                                        <span class="text-white text-xs">' . ($metadata['hunger'] ?? 100) . '%</span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" style="width: ' . ($metadata['hunger'] ?? 100) . '%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-gray-400 text-xs">Thirst</span>
                                        <span class="text-white text-xs">' . ($metadata['thirst'] ?? 100) . '%</span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: ' . ($metadata['thirst'] ?? 100) . '%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-gray-400 text-xs">Stress</span>
                                        <span class="text-white text-xs">' . ($metadata['stress'] ?? 0) . '%</span>
                                    </div>
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: ' . ($metadata['stress'] ?? 0) . '%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Job & Gang Info -->
                        <div class="bg-gray-900 rounded-lg p-4">
                            <h5 class="text-white font-semibold mb-3">Employment & Gang</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

if (isset($job)) {
    $gang = json_decode($character['gang'], true);
    $html .= '
                                <div class="bg-gray-800 rounded-lg p-3">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-briefcase text-blue-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-white font-medium">' . htmlspecialchars($job['label']) . '</p>
                                            <p class="text-gray-400 text-xs">Grade: ' . htmlspecialchars($job['grade']['name']) . ' (Level ' . $job['grade']['level'] . ')</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400">Payment:</span>
                                        <span class="text-green-400 font-medium">$' . number_format($job['payment'] ?? 0) . '</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400">On Duty:</span>
                                        <span class="' . ($job['onduty'] ? 'text-green-400' : 'text-red-400') . '">' . ($job['onduty'] ? 'Yes' : 'No') . '</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400">Boss:</span>
                                        <span class="' . ($job['isboss'] ? 'text-yellow-400' : 'text-gray-400') . '">' . ($job['isboss'] ? 'Yes' : 'No') . '</span>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-800 rounded-lg p-3">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-red-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-users text-red-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-white font-medium">' . htmlspecialchars($gang['label'] ?? 'No Gang') . '</p>
                                            <p class="text-gray-400 text-xs">Grade: ' . htmlspecialchars($gang['grade']['name'] ?? 'None') . '</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400">Boss:</span>
                                        <span class="' . (($gang['isboss'] ?? false) ? 'text-yellow-400' : 'text-gray-400') . '">' . (($gang['isboss'] ?? false) ? 'Yes' : 'No') . '</span>
                                    </div>
                                </div>';
}

$html .= '
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inventory Section -->
                    <div x-show="showInventory" x-transition class="bg-gray-900 rounded-lg p-4">
                        <h5 class="text-white font-semibold mb-3">
                            <i class="fas fa-boxes text-purple-400 mr-2"></i>Inventory (' . count($inventory) . ' items)
                        </h5>';

if (empty($inventory)) {
    $html .= '
                        <div class="text-center py-6">
                            <i class="fas fa-box-open text-3xl text-gray-600 mb-3"></i>
                            <p class="text-gray-400 text-sm">No items in inventory</p>
                        </div>';
} else {
    $html .= '
                        <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 max-h-64 overflow-y-auto">';
    
    // Sort inventory by slot
    usort($inventory, function($a, $b) {
        return ($a['slot'] ?? 0) - ($b['slot'] ?? 0);
    });
    
    foreach ($inventory as $item) {
        $item_name = ucwords(str_replace('_', ' ', $item['name']));
        $item_icon = match($item['type']) {
            'weapon' => 'fa-gun',
            'item' => 'fa-cube',
            'food' => 'fa-utensils',
            'drink' => 'fa-glass-water',
            default => 'fa-cube'
        };
        
        $html .= '
                            <div class="bg-gray-800 border border-gray-600 rounded-lg p-2 text-center hover:border-fivem-primary transition-colors group relative">
                                <div class="w-8 h-8 bg-fivem-primary bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-1">
                                    <i class="fas ' . $item_icon . ' text-fivem-primary text-xs"></i>
                                </div>
                                <p class="text-white text-xs font-medium truncate" title="' . htmlspecialchars($item_name) . '">' . htmlspecialchars(substr($item_name, 0, 8)) . '</p>
                                <p class="text-gray-400 text-xs">×' . $item['amount'] . '</p>
                                
                                <!-- Tooltip -->
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10 border border-gray-600">
                                    <div class="font-semibold">' . htmlspecialchars($item_name) . '</div>
                                    <div class="text-gray-400">Slot: ' . $item['slot'] . ' | Amount: ' . $item['amount'] . '</div>
                                    <div class="text-gray-400">Type: ' . htmlspecialchars($item['type']) . '</div>
                                </div>
                            </div>';
    }
    
    $html .= '</div>';
}

$html .= '
                    </div>
                </div>';
    }
    $html .= '</div>';
}

$html .= '
        </div>
        
        <!-- Vehicles Section -->
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-car text-blue-400 mr-2"></i>Vehicles (' . count($vehicles) . ')
            </h3>';

if (empty($vehicles)) {
    $html .= '
            <div class="text-center py-8">
                <i class="fas fa-car text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">No vehicles owned</p>
            </div>';
} else {
    $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
    foreach ($vehicles as $vehicle) {
        $mods = json_decode($vehicle['mods'], true) ?? [];
        $status_color = $vehicle['state'] == 1 ? 'text-green-400' : 'text-red-400';
        $status_text = $vehicle['state'] == 1 ? 'Available' : 'Impounded';
        $status_icon = $vehicle['state'] == 1 ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        $html .= '
                <div class="bg-gray-800 rounded-lg p-4 border border-gray-600">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="text-white font-semibold">' . htmlspecialchars($vehicle['vehicle']) . '</h4>
                            <p class="text-gray-400 text-sm">Plate: ' . htmlspecialchars($vehicle['plate']) . '</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . ($vehicle['state'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">
                                <i class="fas ' . $status_icon . ' mr-1"></i>' . $status_text . '
                            </span>
                        </div>
                    </div>
                    
                    <!-- Vehicle Stats -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center p-2 bg-gray-900 rounded">
                            <div class="w-6 h-6 bg-blue-500 bg-opacity-20 rounded flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-gas-pump text-blue-400 text-xs"></i>
                            </div>
                            <p class="text-blue-400 text-sm font-bold">' . $vehicle['fuel'] . '%</p>
                            <p class="text-gray-500 text-xs">Fuel</p>
                        </div>
                        <div class="text-center p-2 bg-gray-900 rounded">
                            <div class="w-6 h-6 bg-green-500 bg-opacity-20 rounded flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-cog text-green-400 text-xs"></i>
                            </div>
                            <p class="text-green-400 text-sm font-bold">' . round($vehicle['engine']/10) . '%</p>
                            <p class="text-gray-500 text-xs">Engine</p>
                        </div>
                        <div class="text-center p-2 bg-gray-900 rounded">
                            <div class="w-6 h-6 bg-yellow-500 bg-opacity-20 rounded flex items-center justify-center mx-auto mb-1">
                                <i class="fas fa-car-crash text-yellow-400 text-xs"></i>
                            </div>
                            <p class="text-yellow-400 text-sm font-bold">' . round($vehicle['body']/10) . '%</p>
                            <p class="text-gray-500 text-xs">Body</p>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-xs text-gray-400">
                        <div class="flex justify-between">
                            <span>Garage:</span>
                            <span class="text-white">' . htmlspecialchars($vehicle['garage'] ?? 'None') . '</span>
                        </div>';

if ($vehicle['depotprice'] > 0) {
    $html .= '
                        <div class="flex justify-between">
                            <span>Impound Fee:</span>
                            <span class="text-red-400 font-medium">$' . number_format($vehicle['depotprice']) . '</span>
                        </div>';
}

$html .= '
                    </div>
                </div>';
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
                <i class="fas fa-chart-bar text-fivem-primary mr-2"></i>Player Summary
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
                    <span class="text-white font-bold">' . count($vehicles) . '</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Total Items:</span>
                    <span class="text-purple-400 font-bold">' . $total_items . '</span>
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

if (!$player['is_active']) {
    $html .= '
                <button onclick="togglePlayerStatus(' . $player['id'] . ', true)" 
                        class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-user-check mr-2"></i>Activate Player
                </button>';
} else {
    $html .= '
                <button onclick="togglePlayerStatus(' . $player['id'] . ', false)" 
                        class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-user-times mr-2"></i>Deactivate Player
                </button>';
}

$html .= '
                <button onclick="resetPlayerPassword(' . $player['id'] . ', \'' . htmlspecialchars($player['username']) . '\')" 
                        class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-key mr-2"></i>Reset Password
                </button>
                
                <button onclick="viewPlayerTickets(' . $player['id'] . ')" 
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-ticket-alt mr-2"></i>View Tickets
                </button>
            </div>
        </div>';

// Support tickets section
if (!empty($tickets)) {
    $html .= '
        <div class="bg-gray-700 rounded-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">
                <i class="fas fa-ticket-alt text-yellow-400 mr-2"></i>Recent Support Tickets
            </h3>
            <div class="space-y-3 max-h-64 overflow-y-auto">';
    
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
                <div class="bg-gray-800 rounded-lg p-3 border border-gray-600 hover:border-gray-500 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-white font-medium text-sm">' . htmlspecialchars($ticket['subject']) . '</h4>
                        <div class="flex items-center space-x-1">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ' . $priority_color . '">' . ucfirst($ticket['priority']) . '</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="' . $status_color . '">' . ucfirst(str_replace('_', ' ', $ticket['status'])) . '</span>
                        <span class="text-gray-500">' . date('M j, Y', strtotime($ticket['created_at'])) . '</span>
                    </div>
                </div>';
    }
    
    $html .= '</div></div>';
}

$html .= '
    </div>
</div>';

echo json_encode([
    'success' => true,
    'html' => $html,
    'player' => $player,
    'stats' => [
        'characters' => count($characters),
        'vehicles' => count($vehicles),
        'total_money' => $total_money,
        'total_items' => $total_items,
        'tickets' => count($tickets)
    ]
]);
?>