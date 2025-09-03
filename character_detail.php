<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

$character_id = $_GET['id'] ?? '';

// Get character details
$query = "SELECT * FROM players WHERE citizenid = :citizenid AND license = :license";
$stmt = $db->prepare($query);
$stmt->bindParam(':citizenid', $character_id);
$stmt->bindParam(':license', $_SESSION['license']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header('Location: characters.php');
    exit();
}

$character = $stmt->fetch(PDO::FETCH_ASSOC);
$charinfo = json_decode($character['charinfo'], true);
$money = json_decode($character['money'], true);
$job = json_decode($character['job'], true);
$gang = json_decode($character['gang'], true);
$metadata = json_decode($character['metadata'], true);
$position = json_decode($character['position'], true);
$inventory = json_decode($character['inventory'], true);

// Get character vehicles
$vehicles_query = "SELECT * FROM player_vehicles WHERE citizenid = :citizenid";
$vehicles_stmt = $db->prepare($vehicles_query);
$vehicles_stmt->bindParam(':citizenid', $character_id);
$vehicles_stmt->execute();
$vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $charinfo['firstname'] . ' ' . $charinfo['lastname'];
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Character Header -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="w-16 h-16 bg-fivem-primary rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-2xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">
                        <?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>
                    </h1>
                    <p class="text-gray-400">Citizen ID: <?php echo htmlspecialchars($character['citizenid']); ?></p>
                    <p class="text-sm text-gray-500">Last Updated: <?php echo date('M j, Y g:i A', strtotime($character['last_updated'])); ?></p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="characters.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Character Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4">Character Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-gray-400 text-sm">First Name</label>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($charinfo['firstname']); ?></p>
                    </div>
                    <div>
                        <label class="text-gray-400 text-sm">Last Name</label>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($charinfo['lastname']); ?></p>
                    </div>
                    <div>
                        <label class="text-gray-400 text-sm">Date of Birth</label>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($charinfo['birthdate']); ?></p>
                    </div>
                    <div>
                        <label class="text-gray-400 text-sm">Nationality</label>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($charinfo['nationality']); ?></p>
                    </div>
                    <div>
                        <label class="text-gray-400 text-sm">Phone Number</label>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($charinfo['phone']); ?></p>
                    </div>
                    <div>
                        <label class="text-gray-400 text-sm">Gender</label>
                        <p class="text-white font-medium"><?php echo $charinfo['gender'] == 0 ? 'Male' : 'Female'; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Job & Gang Info -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4">Employment & Affiliation</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-700 rounded-lg p-4">
                        <h3 class="text-white font-semibold mb-3">
                            <i class="fas fa-briefcase text-fivem-primary mr-2"></i>Job
                        </h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Position:</span>
                                <span class="text-white"><?php echo htmlspecialchars($job['label']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Grade:</span>
                                <span class="text-white"><?php echo htmlspecialchars($job['grade']['name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">On Duty:</span>
                                <span class="<?php echo $job['onduty'] ? 'text-green-400' : 'text-red-400'; ?>">
                                    <?php echo $job['onduty'] ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-700 rounded-lg p-4">
                        <h3 class="text-white font-semibold mb-3">
                            <i class="fas fa-users text-red-400 mr-2"></i>Gang
                        </h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Affiliation:</span>
                                <span class="text-white"><?php echo htmlspecialchars($gang['label']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Rank:</span>
                                <span class="text-white"><?php echo htmlspecialchars($gang['grade']['name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Boss:</span>
                                <span class="<?php echo $gang['isboss'] ? 'text-green-400' : 'text-gray-400'; ?>">
                                    <?php echo $gang['isboss'] ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vehicles -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-car text-blue-400 mr-2"></i>Vehicles
                </h2>
                <?php if (empty($vehicles)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-car text-4xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400">No vehicles owned</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="text-white font-semibold"><?php echo htmlspecialchars($vehicle['vehicle']); ?></h4>
                                        <p class="text-gray-400 text-sm">Plate: <?php echo htmlspecialchars($vehicle['plate']); ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $vehicle['state'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $vehicle['state'] == 1 ? 'Available' : 'Impounded'; ?>
                            <div class="bg-gray-700 rounded-lg p-4 border border-gray-600 hover:border-gray-500 transition-all">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <?php 
                                        $item_image_path = "assets/items/" . strtolower($item['name']) . ".png";
                                        $item_image_exists = file_exists($item_image_path);
                                        ?>
                                        
                                        <div class="w-12 h-12 mr-3 flex-shrink-0">
                                            <?php if ($item_image_exists): ?>
                                                <img src="<?php echo $item_image_path; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                     class="w-full h-full object-contain rounded-lg"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="w-full h-full bg-fivem-primary rounded-lg flex items-center justify-center" style="display: none;">
                                                    <i class="fas fa-cube text-white text-sm"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-full h-full bg-fivem-primary rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-cube text-white text-sm"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <h4 class="text-white font-medium"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?></h4>
                                            <p class="text-gray-400 text-sm">Slot: <?php echo $item['slot']; ?> • Type: <?php echo htmlspecialchars($item['type']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="bg-fivem-primary text-white px-3 py-1 rounded-lg text-sm font-bold">
                    </span>
                                </div>
                                
                                <!-- Vehicle Details -->
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="text-center">
                                        <div class="w-8 h-8 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-1">
                                            <i class="fas fa-gas-pump text-blue-400 text-xs"></i>
                                        </div>
                                        <p class="text-gray-400 text-xs">Fuel</p>
                                        <p class="text-white font-bold text-sm"><?php echo $vehicle['fuel']; ?>%</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="w-8 h-8 bg-green-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-1">
                                            <i class="fas fa-cog text-green-400 text-xs"></i>
                                        </div>
                                        <p class="text-gray-400 text-xs">Engine</p>
                                        <p class="text-white font-bold text-sm"><?php echo round($vehicle['engine']/10); ?>%</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="w-8 h-8 bg-yellow-500 bg-opacity-20 rounded-lg flex items-center justify-center mx-auto mb-1">
                                            <i class="fas fa-car-crash text-yellow-400 text-xs"></i>
                                        </div>
                                        <p class="text-gray-400 text-xs">Body</p>
                                        <p class="text-white font-bold text-sm"><?php echo round($vehicle['body']/10); ?>%</p>
                                    </div>
                                </div>
                                
                                <?php if ($vehicle['garage']): ?>
                                    <div class="text-center">
                                        <span class="text-gray-400 text-sm">
                                            <i class="fas fa-warehouse mr-1"></i>
                                            Garage: <?php echo htmlspecialchars($vehicle['garage']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inventory -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-boxes text-fivem-primary mr-2"></i>Inventory
                </h3>
                <?php if (empty($inventory)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-box-open text-4xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400">Empty inventory</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($inventory as $item): ?>
                            <div class="bg-gray-700 rounded-lg p-4 border border-gray-600 hover:border-gray-500 transition-all">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <?php 
                                        $item_image_path = "assets/items/" . strtolower($item['name']) . ".png";
                                        $item_image_exists = file_exists($item_image_path);
                                        ?>
                                        
                                        <div class="w-12 h-12 mr-3 flex-shrink-0">
                                            <?php if ($item_image_exists): ?>
                                                <img src="<?php echo $item_image_path; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                     class="w-full h-full object-contain rounded-lg"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="w-full h-full bg-fivem-primary rounded-lg flex items-center justify-center" style="display: none;">
                                                    <i class="fas fa-cube text-white text-sm"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-full h-full bg-fivem-primary rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-cube text-white text-sm"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <h4 class="text-white font-medium"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?></h4>
                                            <p class="text-gray-400 text-sm">Slot: <?php echo $item['slot']; ?> • Type: <?php echo htmlspecialchars($item['type']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="bg-fivem-primary text-white px-3 py-1 rounded-lg text-sm font-bold">
                                            <?php echo $item['amount']; ?>x
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-bold text-white mb-4">Character Status</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Health:</span>
                        <div class="flex items-center">
                            <div class="w-20 bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo (100 - ($metadata['isdead'] ? 0 : 100)); ?>%"></div>
                            </div>
                            <span class="text-white text-sm"><?php echo $metadata['isdead'] ? 'Dead' : 'Alive'; ?></span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Hunger:</span>
                        <div class="flex items-center">
                            <div class="w-20 bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo $metadata['hunger']; ?>%"></div>
                            </div>
                            <span class="text-white text-sm"><?php echo $metadata['hunger']; ?>%</span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Thirst:</span>
                        <div class="flex items-center">
                            <div class="w-20 bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $metadata['thirst']; ?>%"></div>
                            </div>
                            <span class="text-white text-sm"><?php echo $metadata['thirst']; ?>%</span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Stress:</span>
                        <div class="flex items-center">
                            <div class="w-20 bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo $metadata['stress']; ?>%"></div>
                            </div>
                            <span class="text-white text-sm"><?php echo $metadata['stress']; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Money -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-bold text-white mb-4">Financial Overview</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-money-bill-wave text-green-400 mr-3"></i>
                            <span class="text-gray-400">Cash</span>
                        </div>
                        <span class="text-green-400 font-bold">$<?php echo number_format($money['cash']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-university text-blue-400 mr-3"></i>
                            <span class="text-gray-400">Bank</span>
                        </div>
                        <span class="text-blue-400 font-bold">$<?php echo number_format($money['bank']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-coins text-yellow-400 mr-3"></i>
                            <span class="text-gray-400">Crypto</span>
                        </div>
                        <span class="text-yellow-400 font-bold"><?php echo number_format($money['crypto']); ?></span>
                    </div>
                    <hr class="border-gray-700">
                    <div class="flex justify-between items-center">
                        <span class="text-white font-semibold">Total Worth</span>
                        <span class="text-fivem-primary font-bold text-lg">$<?php echo number_format($money['cash'] + $money['bank']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Location -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h3 class="text-lg font-bold text-white mb-4">Last Known Location</h3>
                <div class="bg-gray-700 rounded-lg p-4 text-center">
                    <i class="fas fa-map-marker-alt text-red-400 text-2xl mb-3"></i>
                    <div class="space-y-1">
                        <p class="text-white font-medium">Coordinates</p>
                        <p class="text-gray-400 text-sm">X: <?php echo round($position['x'], 2); ?></p>
                        <p class="text-gray-400 text-sm">Y: <?php echo round($position['y'], 2); ?></p>
                        <p class="text-gray-400 text-sm">Z: <?php echo round($position['z'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>