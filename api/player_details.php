<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
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

    // First, let's check if the player exists
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

    // Get player characters - handle missing license gracefully
    $characters = [];
    if (!empty($player['license'])) {
        try {
            $characters_query = "SELECT * FROM players WHERE license = :license ORDER BY last_updated DESC";
            $characters_stmt = $db->prepare($characters_query);
            $characters_stmt->bindParam(':license', $player['license']);
            $characters_stmt->execute();
            $characters = $characters_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Characters table might not exist or have different structure
            $characters = [];
        }
    }

    // Get player vehicles - handle gracefully if table doesn't exist
    $vehicles = [];
    if (!empty($player['license'])) {
        try {
            $vehicles_query = "SELECT * FROM player_vehicles WHERE license = :license ORDER BY vehicle ASC";
            $vehicles_stmt = $db->prepare($vehicles_query);
            $vehicles_stmt->bindParam(':license', $player['license']);
            $vehicles_stmt->execute();
            $vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Vehicles table might not exist
            $vehicles = [];
        }
    }

    // Get support tickets
    $tickets = [];
    try {
        $tickets_query = "SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
        $tickets_stmt = $db->prepare($tickets_query);
        $tickets_stmt->bindParam(':user_id', $player_id);
        $tickets_stmt->execute();
        $tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Support tickets table might not exist
        $tickets = [];
    }

    // Calculate stats safely
    $total_money = 0;
    $total_items = 0;
    
    foreach ($characters as $character) {
        try {
            $money = json_decode($character['money'] ?? '{}', true);
            if ($money && is_array($money)) {
                $total_money += ($money['cash'] ?? 0) + ($money['bank'] ?? 0);
            }
            
            $inventory = json_decode($character['inventory'] ?? '[]', true);
            if ($inventory && is_array($inventory)) {
                $total_items += count($inventory);
            }
        } catch (Exception $e) {
            // Skip if JSON is malformed
            continue;
        }
    }

    // Generate HTML content
    $html = generatePlayerDetailsHTML($player, $characters, $vehicles, $tickets, $total_money, $total_items);

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

} catch (Exception $e) {
    error_log("Player details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'debug' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}

function generatePlayerDetailsHTML($player, $characters, $vehicles, $tickets, $total_money, $total_items) {
    ob_start();
    ?>
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
                        <p class="text-white font-medium"><?php echo htmlspecialchars($player['username']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Email</p>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($player['email'] ?? 'Not provided'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">License</p>
                        <p class="text-white font-mono text-xs break-all">
                            <?php echo htmlspecialchars($player['license'] ?? 'Not available'); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Registration</p>
                        <p class="text-white font-medium"><?php echo date('M j, Y g:i A', strtotime($player['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Last Login</p>
                        <p class="text-white font-medium">
                            <?php echo $player['last_login'] ? date('M j, Y g:i A', strtotime($player['last_login'])) : 'Never'; ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo ($player['is_active'] ?? 1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo ($player['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Characters Section -->
            <div class="bg-gray-700 rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-users text-green-400 mr-2"></i>Characters (<?php echo count($characters); ?>)
                </h3>
                
                <?php if (empty($characters)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-user-slash text-4xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400">No characters created</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($characters as $character): 
                            $charinfo = json_decode($character['charinfo'] ?? '{}', true) ?? [];
                            $money = json_decode($character['money'] ?? '{}', true) ?? [];
                            $job = json_decode($character['job'] ?? '{}', true) ?? [];
                            $gang = json_decode($character['gang'] ?? '{}', true) ?? [];
                            $metadata = json_decode($character['metadata'] ?? '{}', true) ?? [];
                            $inventory = json_decode($character['inventory'] ?? '[]', true) ?? [];
                            $position = json_decode($character['position'] ?? '{}', true) ?? [];
                        ?>
                            <div class="bg-gray-800 rounded-lg p-4 border border-gray-600" x-data="{ showInventory: false, showVehicles: false, showDetails: false }">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h4 class="text-white font-semibold text-lg">
                                            <?php echo htmlspecialchars(($charinfo['firstname'] ?? 'Unknown') . ' ' . ($charinfo['lastname'] ?? 'Player')); ?>
                                        </h4>
                                        <p class="text-gray-400 text-sm">ID: <?php echo htmlspecialchars($character['citizenid'] ?? 'N/A'); ?></p>
                                        <p class="text-gray-400 text-sm">Job: <?php echo htmlspecialchars($job['label'] ?? 'Unemployed'); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-green-400 font-bold text-lg">$<?php echo number_format(($money['cash'] ?? 0) + ($money['bank'] ?? 0)); ?></p>
                                        <p class="text-gray-400 text-xs">Total Money</p>
                                        <p class="text-gray-500 text-xs">Last: <?php echo date('M j, g:i A', strtotime($character['last_updated'] ?? 'now')); ?></p>
                                    </div>
                                </div>
                                
                                <!-- Character Stats -->
                                <div class="grid grid-cols-4 gap-4 mb-4">
                                    <div class="text-center p-3 bg-gray-900 rounded-lg">
                                        <div class="w-8 h-8 bg-green-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                            <i class="fas fa-money-bill-wave text-green-400 text-sm"></i>
                                        </div>
                                        <p class="text-green-400 font-bold">$<?php echo number_format($money['cash'] ?? 0); ?></p>
                                        <p class="text-gray-500 text-xs">Cash</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-900 rounded-lg">
                                        <div class="w-8 h-8 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                            <i class="fas fa-university text-blue-400 text-sm"></i>
                                        </div>
                                        <p class="text-blue-400 font-bold">$<?php echo number_format($money['bank'] ?? 0); ?></p>
                                        <p class="text-gray-500 text-xs">Bank</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-900 rounded-lg">
                                        <div class="w-8 h-8 bg-purple-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                            <i class="fas fa-boxes text-purple-400 text-sm"></i>
                                        </div>
                                        <p class="text-purple-400 font-bold"><?php echo count($inventory); ?></p>
                                        <p class="text-gray-500 text-xs">Items</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-900 rounded-lg">
                                        <div class="w-8 h-8 bg-red-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                            <i class="fas fa-heart text-red-400 text-sm"></i>
                                        </div>
                                        <p class="text-white font-bold"><?php echo ($metadata['isdead'] ?? false) ? 'Dead' : 'Alive'; ?></p>
                                        <p class="text-gray-500 text-xs">Status</p>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex space-x-2 mb-4">
                                    <button @click="showDetails = !showDetails" 
                                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors text-sm">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <span x-text="showDetails ? 'Hide Details' : 'Show Details'"></span>
                                    </button>
                                    <button @click="showInventory = !showInventory" 
                                            class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors text-sm">
                                        <i class="fas fa-boxes mr-2"></i>
                                        <span x-text="showInventory ? 'Hide Inventory' : 'Show Inventory (' + <?php echo count($inventory); ?> + ')'"></span>
                                    </button>
                                    <button @click="showVehicles = !showVehicles" 
                                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg transition-colors text-sm">
                                        <i class="fas fa-car mr-2"></i>
                                        <span x-text="showVehicles ? 'Hide Vehicles' : 'Show Vehicles (' + <?php echo count($vehicles); ?> + ')'"></span>
                                    </button>
                                </div>
                                
                                <!-- Character Details -->
                                <div x-show="showDetails" x-transition class="space-y-4">
                                    <div class="bg-gray-900 rounded-lg p-4">
                                        <h5 class="text-white font-semibold mb-3">Character Details</h5>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                            <div>
                                                <p class="text-gray-400 text-xs">Phone</p>
                                                <p class="text-white text-sm"><?php echo htmlspecialchars($charinfo['phone'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 text-xs">Nationality</p>
                                                <p class="text-white text-sm"><?php echo htmlspecialchars($charinfo['nationality'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 text-xs">Birthdate</p>
                                                <p class="text-white text-sm"><?php echo htmlspecialchars($charinfo['birthdate'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 text-xs">Account Number</p>
                                                <p class="text-white text-sm font-mono"><?php echo htmlspecialchars($charinfo['account'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 text-xs">Wallet ID</p>
                                                <p class="text-white text-sm font-mono"><?php echo htmlspecialchars($metadata['walletid'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 text-xs">Blood Type</p>
                                                <p class="text-white text-sm"><?php echo htmlspecialchars($metadata['bloodtype'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Position Info -->
                                        <?php if (!empty($position) && isset($position['x'])): ?>
                                            <div class="mt-4 bg-gray-800 rounded-lg p-3">
                                                <h6 class="text-white font-semibold mb-2">Last Known Position</h6>
                                                <div class="grid grid-cols-3 gap-3 text-center">
                                                    <div>
                                                        <p class="text-gray-400 text-xs">X</p>
                                                        <p class="text-white font-mono text-sm"><?php echo round($position['x'] ?? 0, 2); ?></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-400 text-xs">Y</p>
                                                        <p class="text-white font-mono text-sm"><?php echo round($position['y'] ?? 0, 2); ?></p>
                                                    </div>
                                                    <div>
                                                        <p class="text-gray-400 text-xs">Z</p>
                                                        <p class="text-white font-mono text-sm"><?php echo round($position['z'] ?? 0, 2); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Status Bars -->
                                        <div class="mt-4 space-y-3">
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-gray-400 text-xs">Health</span>
                                                    <span class="text-white text-xs"><?php echo ($metadata['isdead'] ?? false) ? '0' : '100'; ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-600 rounded-full h-2">
                                                    <div class="bg-red-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo ($metadata['isdead'] ?? false) ? '0' : '100'; ?>%"></div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-gray-400 text-xs">Hunger</span>
                                                    <span class="text-white text-xs"><?php echo $metadata['hunger'] ?? 100; ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-600 rounded-full h-2">
                                                    <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $metadata['hunger'] ?? 100; ?>%"></div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-gray-400 text-xs">Thirst</span>
                                                    <span class="text-white text-xs"><?php echo $metadata['thirst'] ?? 100; ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-600 rounded-full h-2">
                                                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $metadata['thirst'] ?? 100; ?>%"></div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-gray-400 text-xs">Stress</span>
                                                    <span class="text-white text-xs"><?php echo $metadata['stress'] ?? 0; ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-600 rounded-full h-2">
                                                    <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $metadata['stress'] ?? 0; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Job & Gang Info -->
                                    <div class="bg-gray-900 rounded-lg p-4">
                                        <h5 class="text-white font-semibold mb-3">Employment & Gang</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="bg-gray-800 rounded-lg p-3">
                                                <div class="flex items-center mb-2">
                                                    <div class="w-8 h-8 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-briefcase text-blue-400 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-white font-medium"><?php echo htmlspecialchars($job['label'] ?? 'Unemployed'); ?></p>
                                                        <p class="text-gray-400 text-xs">Grade: <?php echo htmlspecialchars($job['grade']['name'] ?? 'None'); ?> (Level <?php echo $job['grade']['level'] ?? 0; ?>)</p>
                                                    </div>
                                                </div>
                                                <div class="space-y-1 text-xs">
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-400">Payment:</span>
                                                        <span class="text-green-400 font-medium">$<?php echo number_format($job['payment'] ?? 0); ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-400">On Duty:</span>
                                                        <span class="<?php echo ($job['onduty'] ?? false) ? 'text-green-400' : 'text-red-400'; ?>">
                                                            <?php echo ($job['onduty'] ?? false) ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-400">Boss:</span>
                                                        <span class="<?php echo ($job['isboss'] ?? false) ? 'text-yellow-400' : 'text-gray-400'; ?>">
                                                            <?php echo ($job['isboss'] ?? false) ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="bg-gray-800 rounded-lg p-3">
                                                <div class="flex items-center mb-2">
                                                    <div class="w-8 h-8 bg-red-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-users text-red-400 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-white font-medium"><?php echo htmlspecialchars($gang['label'] ?? 'No Gang'); ?></p>
                                                        <p class="text-gray-400 text-xs">Grade: <?php echo htmlspecialchars($gang['grade']['name'] ?? 'None'); ?></p>
                                                    </div>
                                                </div>
                                                <div class="space-y-1 text-xs">
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-400">Boss:</span>
                                                        <span class="<?php echo ($gang['isboss'] ?? false) ? 'text-yellow-400' : 'text-gray-400'; ?>">
                                                            <?php echo ($gang['isboss'] ?? false) ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Inventory Section -->
                                <div x-show="showInventory" x-transition class="bg-gray-900 rounded-lg p-4">
                                    <h5 class="text-white font-semibold mb-3">
                                        <i class="fas fa-boxes text-purple-400 mr-2"></i>Inventory (<?php echo count($inventory); ?> items)
                                    </h5>
                                    
                                    <?php if (empty($inventory)): ?>
                                        <div class="text-center py-6">
                                            <i class="fas fa-box-open text-3xl text-gray-600 mb-3"></i>
                                            <p class="text-gray-400 text-sm">No items in inventory</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 max-h-64 overflow-y-auto">
                                            <?php 
                                            // Sort inventory by slot
                                            usort($inventory, function($a, $b) {
                                                return ($a['slot'] ?? 0) - ($b['slot'] ?? 0);
                                            });
                                            
                                            foreach ($inventory as $item): 
                                                $item_name = ucwords(str_replace('_', ' ', $item['name'] ?? 'Unknown'));
                                                $item_icon = match($item['type'] ?? 'item') {
                                                    'weapon' => 'fa-gun',
                                                    'food' => 'fa-utensils',
                                                    'drink' => 'fa-glass-water',
                                                    'tool' => 'fa-wrench',
                                                    'key' => 'fa-key',
                                                    'phone' => 'fa-mobile-alt',
                                                    'card' => 'fa-id-card',
                                                    default => 'fa-cube'
                                                };
                                            ?>
                                                <div class="bg-gray-800 border border-gray-600 rounded-lg p-2 text-center hover:border-fivem-primary transition-colors group relative">
                                                    <div class="w-8 h-8 bg-fivem-primary bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-1">
                                                        <i class="fas <?php echo $item_icon; ?> text-fivem-primary text-xs"></i>
                                                    </div>
                                                    <p class="text-white text-xs font-medium truncate" title="<?php echo htmlspecialchars($item_name); ?>">
                                                        <?php echo htmlspecialchars(substr($item_name, 0, 8)); ?>
                                                    </p>
                                                    <p class="text-gray-400 text-xs">×<?php echo $item['amount'] ?? 1; ?></p>
                                                    
                                                    <!-- Tooltip -->
                                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10 border border-gray-600">
                                                        <div class="font-semibold"><?php echo htmlspecialchars($item_name); ?></div>
                                                        <div class="text-gray-400">Slot: <?php echo $item['slot'] ?? 'N/A'; ?> | Amount: <?php echo $item['amount'] ?? 1; ?></div>
                                                        <div class="text-gray-400">Type: <?php echo htmlspecialchars($item['type'] ?? 'item'); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Vehicles Section -->
                                <div x-show="showVehicles" x-transition class="bg-gray-900 rounded-lg p-4">
                                    <h5 class="text-white font-semibold mb-3">
                                        <i class="fas fa-car text-indigo-400 mr-2"></i>Vehicles (<?php echo count($vehicles); ?> owned)
                                    </h5>
                                    
                                    <?php if (empty($vehicles)): ?>
                                        <div class="text-center py-6">
                                            <i class="fas fa-car text-3xl text-gray-600 mb-3"></i>
                                            <p class="text-gray-400 text-sm">No vehicles owned</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-64 overflow-y-auto">
                                            <?php foreach ($vehicles as $vehicle): 
                                                $mods = json_decode($vehicle['mods'] ?? '{}', true) ?? [];
                                            ?>
                                                <div class="bg-gray-800 rounded-lg p-4 border border-gray-600">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <div>
                                                            <h6 class="text-white font-semibold"><?php echo htmlspecialchars($vehicle['vehicle'] ?? 'Unknown Vehicle'); ?></h6>
                                                            <p class="text-gray-400 text-sm">Plate: <?php echo htmlspecialchars($vehicle['plate'] ?? 'N/A'); ?></p>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo ($vehicle['state'] ?? 0) == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                <i class="fas <?php echo ($vehicle['state'] ?? 0) == 1 ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-1"></i>
                                                                <?php echo ($vehicle['state'] ?? 0) == 1 ? 'Available' : 'Impounded'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Vehicle Stats -->
                                                    <div class="grid grid-cols-3 gap-3">
                                                        <div class="text-center p-2 bg-gray-700 rounded">
                                                            <div class="w-6 h-6 bg-blue-500 bg-opacity-20 rounded flex items-center justify-center mx-auto mb-1">
                                                                <i class="fas fa-gas-pump text-blue-400 text-xs"></i>
                                                            </div>
                                                            <p class="text-blue-400 text-sm font-bold"><?php echo $vehicle['fuel'] ?? 100; ?>%</p>
                                                            <p class="text-gray-500 text-xs">Fuel</p>
                                                        </div>
                                                        <div class="text-center p-2 bg-gray-700 rounded">
                                                            <div class="w-6 h-6 bg-green-500 bg-opacity-20 rounded flex items-center justify-center mx-auto mb-1">
                                                                <i class="fas fa-cog text-green-400 text-xs"></i>
                                                            </div>
                                                            <p class="text-green-400 text-sm font-bold"><?php echo round(($vehicle['engine'] ?? 1000)/10); ?>%</p>
                                                            <p class="text-gray-500 text-xs">Engine</p>
                                                        </div>
                                                        <div class="text-center p-2 bg-gray-700 rounded">
                                                            <div class="w-6 h-6 bg-yellow-500 bg-opacity-20 rounded flex items-center justify-center mx-auto mb-1">
                                                                <i class="fas fa-car-crash text-yellow-400 text-xs"></i>
                                                            </div>
                                                            <p class="text-yellow-400 text-sm font-bold"><?php echo round(($vehicle['body'] ?? 1000)/10); ?>%</p>
                                                            <p class="text-gray-500 text-xs">Body</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3 text-xs text-gray-400">
                                                        <div class="flex justify-between">
                                                            <span>Garage:</span>
                                                            <span class="text-white"><?php echo htmlspecialchars($vehicle['garage'] ?? 'None'); ?></span>
                                                        </div>
                                                        <?php if (($vehicle['depotprice'] ?? 0) > 0): ?>
                                                            <div class="flex justify-between">
                                                                <span>Impound Fee:</span>
                                                                <span class="text-red-400 font-medium">$<?php echo number_format($vehicle['depotprice']); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($vehicle['drivingdistance'])): ?>
                                                            <div class="flex justify-between">
                                                                <span>Mileage:</span>
                                                                <span class="text-blue-400"><?php echo number_format($vehicle['drivingdistance']); ?> km</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
                        <span class="text-white font-bold"><?php echo count($characters); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Total Money:</span>
                        <span class="text-green-400 font-bold">$<?php echo number_format($total_money); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Vehicles:</span>
                        <span class="text-white font-bold"><?php echo count($vehicles); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Total Items:</span>
                        <span class="text-purple-400 font-bold"><?php echo $total_items; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Account Age:</span>
                        <span class="text-white font-bold"><?php echo floor((time() - strtotime($player['created_at'])) / 86400); ?> days</span>
                    </div>
                </div>
            </div>
            
            <!-- Support Tickets -->
            <?php if (!empty($tickets)): ?>
                <div class="bg-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-ticket-alt text-yellow-400 mr-2"></i>Recent Tickets
                    </h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($tickets, 0, 3) as $ticket): ?>
                            <div class="bg-gray-800 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-white font-medium text-sm"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                        switch($ticket['status']) {
                                            case 'open': echo 'bg-green-100 text-green-800'; break;
                                            case 'in_progress': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400">#<?php echo $ticket['id']; ?></span>
                                    <span class="text-gray-500"><?php echo date('M j', strtotime($ticket['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="bg-gray-700 rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-tools text-yellow-400 mr-2"></i>Quick Actions
                </h3>
                <div class="space-y-3">
                    <button onclick="editPlayer(<?php echo $player['id']; ?>)" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-edit mr-2"></i>Edit Player
                    </button>
                    
                    <?php if (!($player['is_active'] ?? 1)): ?>
                        <button onclick="togglePlayerStatus(<?php echo $player['id']; ?>, true)" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-user-check mr-2"></i>Activate Player
                        </button>
                    <?php else: ?>
                        <button onclick="togglePlayerStatus(<?php echo $player['id']; ?>, false)" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-user-times mr-2"></i>Deactivate Player
                        </button>
                    <?php endif; ?>
                    
                    <button onclick="resetPlayerPassword(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['username']); ?>')" 
                            class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-key mr-2"></i>Reset Password
                    </button>
                    
                    <button onclick="viewPlayerTickets(<?php echo $player['id']; ?>)" 
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-ticket-alt mr-2"></i>View All Tickets
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>