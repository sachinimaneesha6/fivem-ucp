<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

$characters = $auth->getUserCharacters($_SESSION['license']);
$selected_character = $_GET['character'] ?? ($characters[0]['citizenid'] ?? '');

$vehicles = [];
if ($selected_character) {
    $query = "SELECT * FROM player_vehicles WHERE citizenid = :citizenid ORDER BY vehicle ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':citizenid', $selected_character);
    $stmt->execute();
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle vehicle actions (for future server integration)
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $plate = $_POST['plate'] ?? '';
    
    // This would integrate with your FiveM server via API
    switch ($action) {
        case 'spawn':
            $action_message = "Vehicle spawn request sent for plate: $plate";
            break;
        case 'despawn':
            $action_message = "Vehicle despawn request sent for plate: $plate";
            break;
        case 'impound':
            $action_message = "Vehicle impound request sent for plate: $plate";
            break;
    }
}

$page_title = 'Vehicle Management';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Vehicle Management</h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Manage your character's vehicles and garage</p>
    </div>
    
    <!-- Character Selection -->
    <?php if (!empty($characters)): ?>
        <div class="rounded-xl border p-6 mb-8 theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <h2 class="text-lg font-bold mb-4 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Select Character</h2>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($characters as $character): 
                    $charinfo = json_decode($character['charinfo'], true);
                    $isSelected = $character['citizenid'] == $selected_character;
                ?>
                    <a href="?character=<?php echo $character['citizenid']; ?>" 
                       class="flex items-center px-4 py-2 rounded-lg border transition-all theme-transition <?php echo $isSelected ? 'bg-fivem-primary border-fivem-primary text-white' : ''; ?>"
                       :class="<?php echo $isSelected ? '' : 'darkMode ? \'bg-gray-700 border-gray-600 text-gray-300 hover:bg-gray-600\' : \'bg-gray-100 border-gray-300 text-gray-700 hover:bg-gray-200\''; ?>">
                        <i class="fas fa-user mr-2"></i>
                        <?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($action_message): ?>
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Vehicle Grid -->
    <?php if (empty($vehicles)): ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
            <i class="fas fa-car text-6xl text-gray-600 mb-6"></i>
            <h3 class="text-xl font-bold text-white mb-2">No Vehicles Found</h3>
            <p class="text-gray-400">This character doesn't own any vehicles</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($vehicles as $vehicle): 
                $mods = json_decode($vehicle['mods'], true) ?? [];
            ?>
                <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden card-hover">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2"></div>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($vehicle['vehicle']); ?></h3>
                                <p class="text-gray-400 text-sm">Plate: <?php echo htmlspecialchars($vehicle['plate']); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $vehicle['state'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $vehicle['state'] == 1 ? 'Available' : 'Impounded'; ?>
                            </span>
                        </div>
                        
                        <!-- Vehicle Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="text-center">
                                <div class="w-12 h-12 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-gas-pump text-blue-400"></i>
                                </div>
                                <p class="text-gray-400 text-xs">Fuel</p>
                                <p class="text-white font-bold"><?php echo $vehicle['fuel']; ?>%</p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-green-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-cog text-green-400"></i>
                                </div>
                                <p class="text-gray-400 text-xs">Engine</p>
                                <p class="text-white font-bold"><?php echo round($vehicle['engine']/10); ?>%</p>
                            </div>
                            <div class="text-center">
                                <div class="w-12 h-12 bg-yellow-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-car-crash text-yellow-400"></i>
                                </div>
                                <p class="text-gray-400 text-xs">Body</p>
                                <p class="text-white font-bold"><?php echo round($vehicle['body']/10); ?>%</p>
                            </div>
                        </div>
                        
                        <!-- Vehicle Actions -->
                        <div class="flex space-x-2">
                            <?php if ($vehicle['state'] == 1): ?>
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="spawn">
                                    <input type="hidden" name="plate" value="<?php echo $vehicle['plate']; ?>">
                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                                        <i class="fas fa-play mr-2"></i>Spawn
                                    </button>
                                </form>
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="despawn">
                                    <input type="hidden" name="plate" value="<?php echo $vehicle['plate']; ?>">
                                    <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                                        <i class="fas fa-stop mr-2"></i>Despawn
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="unimpound">
                                    <input type="hidden" name="plate" value="<?php echo $vehicle['plate']; ?>">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                                        <i class="fas fa-wrench mr-2"></i>Unimpound ($<?php echo $vehicle['depotprice']; ?>)
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($vehicle['garage']): ?>
                            <div class="mt-4 text-center">
                                <span class="text-gray-400 text-sm">
                                    <i class="fas fa-warehouse mr-1"></i>
                                    Garage: <?php echo htmlspecialchars($vehicle['garage']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>