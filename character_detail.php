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
$vehicles_query = "SELECT * FROM player_vehicles WHERE citizenid = :citizenid ORDER BY vehicle ASC";
$vehicles_stmt = $db->prepare($vehicles_query);
$vehicles_stmt->bindParam(':citizenid', $character_id);
$vehicles_stmt->execute();
$vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Vehicle image mapping
function getVehicleImage($vehicleName) {
    // Use local GTA V vehicle images from assets folder
    $vehicleKey = strtolower($vehicleName);
    $imagePath = "assets/vehicles/{$vehicleKey}.png";
    
    // Check if the specific vehicle image exists
    if (file_exists($imagePath)) {
        return $imagePath;
    }
    
    // Fallback to generic vehicle image or placeholder
    $fallbackPath = "assets/vehicles/default.png";
    if (file_exists($fallbackPath)) {
        return $fallbackPath;
    }
    
    // Final fallback - return null to show icon instead
    return null;
}

function getGarageDisplayName($garage) {
    $garageNames = [
        'pillboxgarage' => 'Pillbox Medical Garage',
        'legionsquare' => 'Legion Square Garage',
        'spanishave' => 'Spanish Avenue Garage',
        'caears24' => 'Caesar\'s Garage',
        'lsairport' => 'LS Airport Garage',
        'beachgarage' => 'Vespucci Beach Garage',
        'sandygarage' => 'Sandy Shores Garage',
        'paletogarage' => 'Paleto Bay Garage',
        'motelgarage' => 'Motel Garage',
        'apartment' => 'Apartment Garage'
    ];
    
    return $garageNames[strtolower($garage)] ?? ucwords(str_replace(['garage', '_'], ['Garage', ' '], $garage));
}

$page_title = $charinfo['firstname'] . ' ' . $charinfo['lastname'];
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Character Header -->
    <div class="rounded-xl border p-6 mb-8 theme-transition" 
         :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="w-16 h-16 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-user text-2xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                        <?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>
                    </h1>
                    <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Citizen ID: <?php echo htmlspecialchars($character['citizenid']); ?></p>
                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Last Updated: <?php echo date('M j, Y g:i A', strtotime($character['last_updated'])); ?></p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="characters.php" class="px-4 py-2 rounded-lg transition-colors theme-transition"
                   :class="darkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-900'">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-3 space-y-8">
            <!-- Basic Info -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h2 class="text-xl font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-id-card text-blue-400 mr-2"></i>Character Information
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">First Name</label>
                            <p class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($charinfo['firstname']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Date of Birth</label>
                            <p class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($charinfo['birthdate']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Phone Number</label>
                            <p class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($charinfo['phone']); ?></p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Last Name</label>
                            <p class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($charinfo['lastname']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Nationality</label>
                            <p class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($charinfo['nationality']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Gender</label>
                            <p class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $charinfo['gender'] == 0 ? 'Male' : 'Female'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Job & Gang Info -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h2 class="text-xl font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-briefcase text-purple-400 mr-2"></i>Employment & Affiliation
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rounded-lg p-6 theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                        <h3 class="font-semibold mb-4 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                            <i class="fas fa-briefcase text-fivem-primary mr-2"></i>Job Information
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Position:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($job['label']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Grade:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($job['grade']['name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">On Duty:</span>
                                <span class="font-medium <?php echo $job['onduty'] ? 'text-green-500' : 'text-red-500'; ?>">
                                    <?php echo $job['onduty'] ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Payment:</span>
                                <span class="font-medium text-green-500">$<?php echo number_format($job['payment']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rounded-lg p-6 theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                        <h3 class="font-semibold mb-4 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                            <i class="fas fa-users text-red-400 mr-2"></i>Gang Affiliation
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Gang:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($gang['label']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Rank:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($gang['grade']['name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Boss:</span>
                                <span class="font-medium <?php echo $gang['isboss'] ? 'text-green-500' : 'text-gray-500'; ?>">
                                    <?php echo $gang['isboss'] ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vehicles -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h2 class="text-xl font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-car text-blue-400 mr-2"></i>Vehicle Garage
                    <span class="text-sm font-normal theme-transition ml-2" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">(<?php echo count($vehicles); ?> vehicles)</span>
                </h2>
                
                <?php if (empty($vehicles)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6 theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-200'">
                            <i class="fas fa-car text-3xl theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-400'"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">No Vehicles</h3>
                        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">This character doesn't own any vehicles</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($vehicles as $vehicle): 
                            $mods = json_decode($vehicle['mods'], true) ?? [];
                            $vehicleImage = getVehicleImage($vehicle['vehicle']);
                            $garageDisplay = getGarageDisplayName($vehicle['garage']);
                        ?>
                            <div class="group relative overflow-hidden rounded-2xl border transition-all duration-300 hover:shadow-2xl transform hover:-translate-y-2 theme-transition" 
                                 :class="darkMode ? 'bg-gray-700 border-gray-600 hover:border-fivem-primary' : 'bg-gray-50 border-gray-200 hover:border-fivem-primary'">
                                
                                <!-- Vehicle Image -->
                                <div class="relative h-48 overflow-hidden bg-gradient-to-br from-gray-900 to-gray-800">
                                    <?php if ($vehicleImage): ?>
                                        <img src="<?php echo $vehicleImage; ?>" 
                                             alt="<?php echo htmlspecialchars($vehicle['vehicle']); ?>"
                                             class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110 p-4"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="w-full h-full flex items-center justify-center" style="display: none;">
                                            <div class="w-20 h-20 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center shadow-2xl">
                                                <i class="fas fa-car text-white text-2xl"></i>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <div class="w-20 h-20 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center shadow-2xl">
                                                <i class="fas fa-car text-white text-2xl"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status Badge -->
                                    <div class="absolute top-4 right-4">
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold shadow-xl backdrop-blur-sm <?php echo $vehicle['state'] == 1 ? 'bg-green-500/90 text-white border border-green-400' : 'bg-red-500/90 text-white border border-red-400'; ?>">
                                            <div class="w-2 h-2 rounded-full mr-2 <?php echo $vehicle['state'] == 1 ? 'bg-green-200' : 'bg-red-200'; ?> animate-pulse"></div>
                                            <?php echo $vehicle['state'] == 1 ? 'Available' : 'Impounded'; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Enhanced Gradient Overlay -->
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                                    
                                    <!-- Vehicle Name Overlay -->
                                    <div class="absolute bottom-4 left-4 right-4">
                                        <h3 class="text-xl font-bold text-white mb-1 drop-shadow-lg"><?php echo htmlspecialchars(ucwords($vehicle['vehicle'])); ?></h3>
                                        <div class="flex items-center justify-between">
                                            <p class="text-gray-200 text-sm font-medium">
                                                <i class="fas fa-id-card mr-1"></i>
                                                <?php echo htmlspecialchars($vehicle['plate']); ?>
                                            </p>
                                            <p class="text-gray-200 text-xs">
                                                <i class="fas fa-road mr-1"></i>
                                                <?php echo number_format($vehicle['drivingdistance'] ?? 0); ?> km
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Vehicle Details -->
                                <div class="p-6 space-y-6">
                                    <!-- Parking Location -->
                                    <div class="flex items-center p-4 rounded-xl theme-transition" :class="darkMode ? 'bg-gray-800 border border-gray-600' : 'bg-gray-200 border border-gray-300'">
                                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                                            <i class="fas fa-map-marker-alt text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Parked at</p>
                                            <p class="font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($garageDisplay); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $vehicle['state'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $vehicle['state'] == 1 ? 'In Garage' : 'Impounded'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Condition Bars -->
                                    <div class="grid grid-cols-3 gap-4">
                                        <div class="text-center">
                                            <div class="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-3 bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg transform transition-transform hover:scale-110">
                                                <i class="fas fa-gas-pump text-white text-lg"></i>
                                            </div>
                                            <p class="text-xs font-bold theme-transition mb-2" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">FUEL</p>
                                            <div class="w-full h-3 rounded-full theme-transition shadow-inner" :class="darkMode ? 'bg-gray-600' : 'bg-gray-300'">
                                                <div class="bg-gradient-to-r from-blue-500 to-blue-400 h-3 rounded-full transition-all duration-700 shadow-sm" 
                                                     style="width: <?php echo $vehicle['fuel']; ?>%"></div>
                                            </div>
                                            <p class="text-sm font-bold mt-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $vehicle['fuel']; ?>%</p>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-3 bg-gradient-to-r from-green-500 to-green-600 shadow-lg transform transition-transform hover:scale-110">
                                                <i class="fas fa-cog text-white text-lg"></i>
                                            </div>
                                            <p class="text-xs font-bold theme-transition mb-2" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">ENGINE</p>
                                            <div class="w-full h-3 rounded-full theme-transition shadow-inner" :class="darkMode ? 'bg-gray-600' : 'bg-gray-300'">
                                                <div class="bg-gradient-to-r from-green-500 to-green-400 h-3 rounded-full transition-all duration-700 shadow-sm" 
                                                     style="width: <?php echo round($vehicle['engine']/10); ?>%"></div>
                                            </div>
                                            <p class="text-sm font-bold mt-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo round($vehicle['engine']/10); ?>%</p>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-3 bg-gradient-to-r from-orange-500 to-red-500 shadow-lg transform transition-transform hover:scale-110">
                                                <i class="fas fa-shield-alt text-white text-lg"></i>
                                            </div>
                                            <p class="text-xs font-bold theme-transition mb-2" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">BODY</p>
                                            <div class="w-full h-3 rounded-full theme-transition shadow-inner" :class="darkMode ? 'bg-gray-600' : 'bg-gray-300'">
                                                <div class="bg-gradient-to-r from-orange-500 to-red-500 h-3 rounded-full transition-all duration-700 shadow-sm" 
                                                     style="width: <?php echo round($vehicle['body']/10); ?>%"></div>
                                            </div>
                                            <p class="text-sm font-bold mt-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo round($vehicle['body']/10); ?>%</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Vehicle Actions -->
                                    <div class="flex space-x-3">
                                        <?php if ($vehicle['state'] == 1): ?>
                                            <button onclick="spawnVehicle('<?php echo $vehicle['plate']; ?>', '<?php echo htmlspecialchars($vehicle['vehicle']); ?>')" 
                                                    class="flex-1 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white py-3 px-4 rounded-xl font-bold transition-all duration-300 transform hover:scale-105 hover:shadow-xl active:scale-95">
                                                <i class="fas fa-play mr-2"></i>SPAWN
                                            </button>
                                            <button onclick="despawnVehicle('<?php echo $vehicle['plate']; ?>', '<?php echo htmlspecialchars($vehicle['vehicle']); ?>')" 
                                                    class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-4 rounded-xl font-bold transition-all duration-300 transform hover:scale-105 hover:shadow-xl active:scale-95">
                                                <i class="fas fa-warehouse mr-2"></i>STORE
                                            </button>
                                        <?php else: ?>
                                            <button onclick="unimpoundVehicle('<?php echo $vehicle['plate']; ?>', <?php echo $vehicle['depotprice']; ?>, '<?php echo htmlspecialchars($vehicle['vehicle']); ?>')" 
                                                    class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white py-3 px-4 rounded-xl font-bold transition-all duration-300 transform hover:scale-105 hover:shadow-xl active:scale-95">
                                                <i class="fas fa-wrench mr-2"></i>UNIMPOUND - $<?php echo number_format($vehicle['depotprice']); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inventory -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h2 class="text-xl font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-boxes text-green-400 mr-2"></i>Character Inventory
                    <span class="text-sm font-normal theme-transition ml-2" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">(<?php echo count($inventory); ?> items)</span>
                </h2>
                
                <?php if (empty($inventory)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6 theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-200'">
                            <i class="fas fa-box-open text-3xl theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-400'"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Empty Inventory</h3>
                        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">No items found in character inventory</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <?php foreach ($inventory as $item): ?>
                            <div class="group relative rounded-lg p-3 border transition-all duration-300 hover:shadow-lg theme-transition" 
                                 :class="darkMode ? 'bg-gray-700 border-gray-600 hover:border-fivem-primary' : 'bg-gray-100 border-gray-300 hover:border-fivem-primary'">
                                
                                <!-- Item Image/Icon -->
                                <div class="w-12 h-12 mx-auto mb-2 relative">
                                    <?php 
                                    $item_image_path = "assets/items/" . strtolower($item['name']) . ".png";
                                    $item_image_exists = file_exists($item_image_path);
                                    ?>
                                    
                                    <?php if ($item_image_exists): ?>
                                        <img src="<?php echo $item_image_path; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="w-full h-full object-contain rounded-lg shadow-sm transition-transform duration-300 group-hover:scale-110"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="w-full h-full bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-lg flex items-center justify-center shadow-md" style="display: none;">
                                            <i class="fas fa-cube text-white"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-lg flex items-center justify-center shadow-md">
                                            <i class="fas fa-cube text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Amount Badge -->
                                    <span class="absolute -top-1 -right-1 bg-fivem-primary text-white text-xs px-2 py-1 rounded-full font-bold shadow-lg border-2 border-white">
                                        <?php echo $item['amount']; ?>
                                    </span>
                                </div>
                                
                                <!-- Item Name -->
                                <p class="text-xs font-medium text-center truncate theme-transition" 
                                   :class="darkMode ? 'text-white' : 'text-gray-900'" 
                                   title="<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?>
                                </p>
                                
                                <!-- Slot Number -->
                                <div class="absolute top-1 left-1 text-xs font-bold px-1 py-0.5 rounded theme-transition" 
                                     :class="darkMode ? 'bg-gray-800 text-gray-400' : 'bg-white text-gray-600'"><?php echo $item['slot']; ?></div>
                                
                                <!-- Hover Tooltip -->
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-20 whitespace-nowrap theme-transition border shadow-lg"
                                     :class="darkMode ? 'bg-gray-900 text-white border-gray-700' : 'bg-white text-gray-900 border-gray-200'">
                                    <div class="font-semibold mb-1"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?></div>
                                    <div class="space-y-1">
                                        <div class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <i class="fas fa-hashtag mr-1"></i>Amount: <?php echo $item['amount']; ?>
                                        </div>
                                        <div class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <i class="fas fa-tag mr-1"></i>Type: <?php echo htmlspecialchars($item['type']); ?>
                                        </div>
                                        <div class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <i class="fas fa-layer-group mr-1"></i>Slot: <?php echo $item['slot']; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Tooltip Arrow -->
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent theme-transition"
                                         :class="darkMode ? 'border-t-gray-900' : 'border-t-white'"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Character Status -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h3 class="text-lg font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-heart text-red-400 mr-2"></i>Character Status
                </h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Health</span>
                            <span class="text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['isdead'] ? '0' : '100'; ?>%</span>
                        </div>
                        <div class="w-full h-3 rounded-full theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-300'">
                            <div class="bg-gradient-to-r from-red-500 to-red-400 h-3 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $metadata['isdead'] ? '0' : '100'; ?>%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Hunger</span>
                            <span class="text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['hunger']; ?>%</span>
                        </div>
                        <div class="w-full h-3 rounded-full theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-300'">
                            <div class="bg-gradient-to-r from-orange-500 to-orange-400 h-3 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $metadata['hunger']; ?>%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Thirst</span>
                            <span class="text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['thirst']; ?>%</span>
                        </div>
                        <div class="w-full h-3 rounded-full theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-300'">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-400 h-3 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $metadata['thirst']; ?>%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Stress</span>
                            <span class="text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $metadata['stress']; ?>%</span>
                        </div>
                        <div class="w-full h-3 rounded-full theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-300'">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-400 h-3 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $metadata['stress']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Financial Overview -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h3 class="text-lg font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-wallet text-green-400 mr-2"></i>Financial Status
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-green-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                                <i class="fas fa-money-bill-wave text-white"></i>
                            </div>
                            <span class="font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Cash</span>
                        </div>
                        <span class="text-xl font-bold text-green-500">$<?php echo number_format($money['cash']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                                <i class="fas fa-university text-white"></i>
                            </div>
                            <span class="font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Bank</span>
                        </div>
                        <span class="text-xl font-bold text-blue-500">$<?php echo number_format($money['bank']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                                <i class="fas fa-coins text-white"></i>
                            </div>
                            <span class="font-medium theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Crypto</span>
                        </div>
                        <span class="text-xl font-bold text-yellow-500"><?php echo number_format($money['crypto']); ?></span>
                    </div>
                    
                    <div class="border-t pt-4 theme-transition" :class="darkMode ? 'border-gray-600' : 'border-gray-300'">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Total Net Worth</span>
                            <span class="text-2xl font-bold text-fivem-primary">$<?php echo number_format($money['cash'] + $money['bank']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Last Known Location -->
            <div class="rounded-xl border p-6 theme-transition card-hover" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h3 class="text-lg font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-map-marker-alt text-red-400 mr-2"></i>Last Known Location
                </h3>
                <div class="text-center space-y-4">
                    <div class="w-20 h-20 bg-gradient-to-r from-red-500 to-red-600 rounded-2xl flex items-center justify-center mx-auto shadow-2xl transform transition-transform hover:scale-110">
                        <i class="fas fa-crosshairs text-white text-xl"></i>
                    </div>
                    
                    <div class="rounded-xl p-4 theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                        <p class="text-sm font-bold theme-transition mb-3" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">GPS COORDINATES</p>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="p-2 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white'">
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">X</p>
                                <p class="font-mono text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo round($position['x'], 1); ?></p>
                            </div>
                            <div class="p-2 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white'">
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Y</p>
                                <p class="font-mono text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo round($position['y'], 1); ?></p>
                            </div>
                            <div class="p-2 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white'">
                                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Z</p>
                                <p class="font-mono text-sm font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo round($position['z'], 1); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <a href="map.php?character=<?php echo $character['citizenid']; ?>" 
                           class="block w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white py-3 px-4 rounded-xl font-bold transition-all duration-300 transform hover:scale-105 hover:shadow-xl active:scale-95">
                            <i class="fas fa-map mr-2"></i>VIEW ON MAP
                        </a>
                        <button onclick="copyCoordinates(<?php echo $position['x']; ?>, <?php echo $position['y']; ?>, <?php echo $position['z']; ?>)" 
                                class="w-full bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white py-2 px-4 rounded-lg font-medium transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-copy mr-2"></i>Copy Coords
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Additional Info -->
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h3 class="text-lg font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>Additional Info
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Blood Type:</span>
                        <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($metadata['bloodtype']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Callsign:</span>
                        <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($metadata['callsign']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Wallet ID:</span>
                        <span class="font-mono text-sm theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($metadata['walletid']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Fingerprint:</span>
                        <span class="font-mono text-sm theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($metadata['fingerprint']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Vehicle action functions
function spawnVehicle(plate, vehicleName) {
    showNotification('🚗 Vehicle Spawn', `Spawning ${vehicleName} (${plate})...`, 'info');
    // In real implementation, this would call your FiveM server API
    
    // Add visual feedback
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>SPAWNING...';
    button.disabled = true;
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        showNotification('✅ Vehicle Ready', `${vehicleName} has been spawned successfully!`, 'success');
    }, 2000);
}

function despawnVehicle(plate, vehicleName) {
    showNotification('🏠 Vehicle Storage', `Storing ${vehicleName} (${plate})...`, 'info');
    // In real implementation, this would call your FiveM server API
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>STORING...';
    button.disabled = true;
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        showNotification('✅ Vehicle Stored', `${vehicleName} has been stored in garage!`, 'success');
    }, 1500);
}

function unimpoundVehicle(plate, cost, vehicleName) {
    if (confirm(`Are you sure you want to unimpound ${vehicleName} (${plate}) for $${cost.toLocaleString()}?`)) {
        showNotification('💰 Processing Payment', `Unimpounding ${vehicleName} for $${cost.toLocaleString()}...`, 'warning');
        // In real implementation, this would call your FiveM server API
        
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>PROCESSING...';
        button.disabled = true;
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            showNotification('✅ Vehicle Released', `${vehicleName} has been unimpounded and is now available!`, 'success');
        }, 3000);
    }
}

function copyCoordinates(x, y, z) {
    const coords = `${x.toFixed(2)}, ${y.toFixed(2)}, ${z.toFixed(2)}`;
    navigator.clipboard.writeText(coords).then(() => {
        showNotification('📋 Copied', 'Coordinates copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = coords;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('📋 Copied', 'Coordinates copied to clipboard!', 'success');
    });
}

// Enhanced vehicle card interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to vehicle cards
    const vehicleCards = document.querySelectorAll('.group');
    vehicleCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('button[onclick*="Vehicle"]');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
});
</script>

<style>
/* Enhanced vehicle card animations */
.group {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.group:hover {
    transform: translateY(-8px) scale(1.02);
}

/* Better progress bar animations */
.group:hover .bg-gradient-to-r {
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* Enhanced button feedback */
button {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

button:active {
    transform: scale(0.95);
}

/* Vehicle image hover effects */
.group img {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.group:hover img {
    transform: scale(1.1) rotate(1deg);
}

/* Enhanced shadows for depth */
.group:hover {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.light .group:hover {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
}
</style>

<?php include 'includes/footer.php'; ?>