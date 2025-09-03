<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

$characters = $auth->getUserCharacters($_SESSION['license']);
$selected_character = $_GET['character'] ?? ($characters[0]['citizenid'] ?? '');

$character_inventory = [];
if ($selected_character) {
    $query = "SELECT inventory FROM players WHERE citizenid = :citizenid AND license = :license";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':citizenid', $selected_character);
    $stmt->bindParam(':license', $_SESSION['license']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $character_inventory = json_decode($result['inventory'], true) ?? [];
    }
}

$page_title = 'Inventory';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Character Inventory</h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">View your character's items and equipment</p>
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
    
    <!-- Inventory Grid -->
    <?php if (empty($character_inventory)): ?>
        <div class="rounded-xl border p-12 text-center theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <i class="fas fa-box-open text-6xl mb-6 theme-transition" :class="darkMode ? 'text-gray-600' : 'text-gray-400'"></i>
            <h3 class="text-xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Empty Inventory</h3>
            <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">This character doesn't have any items</p>
        </div>
    <?php else: ?>
        <div class="rounded-xl border p-6 theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-boxes text-fivem-primary mr-2"></i>Items
                </h2>
                <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'"><?php echo count($character_inventory); ?> items</span>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php 
                // Create a 40-slot inventory grid
                for ($slot = 1; $slot <= 50; $slot++): 
                    $item = null;
                    foreach ($character_inventory as $inv_item) {
                        if ($inv_item['slot'] == $slot) {
                            $item = $inv_item;
                            break;
                        }
                    }
                ?>
                    <div class="aspect-square border-2 rounded-lg p-3 flex flex-col items-center justify-center relative group transition-colors theme-transition"
                         :class="darkMode ? 'bg-gray-700 border-gray-600 hover:border-fivem-primary' : 'bg-gray-100 border-gray-300 hover:border-fivem-primary'">
                        <div class="absolute top-1 left-1 text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-400'"><?php echo $slot; ?></div>
                        
                        <?php if ($item): ?>
                            <div class="text-center relative">
                                <?php 
                                $item_image_path = "assets/items/" . strtolower($item['name']) . ".png";
                                $item_image_exists = file_exists($item_image_path);
                                ?>
                                
                                <?php if ($item_image_exists): ?>
                                    <div class="w-12 h-12 mx-auto mb-2 relative">
                                        <img src="<?php echo $item_image_path; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="w-full h-full object-contain rounded-lg shadow-sm"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="w-full h-full bg-fivem-primary rounded-lg flex items-center justify-center" style="display: none;">
                                            <i class="fas fa-cube text-white text-sm"></i>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="w-12 h-12 bg-fivem-primary rounded-lg flex items-center justify-center mb-2 mx-auto shadow-sm">
                                        <i class="fas fa-cube text-white text-sm"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="text-xs font-medium truncate w-full theme-transition leading-tight" 
                                   :class="darkMode ? 'text-white' : 'text-gray-900'" 
                                   title="<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?>
                                </p>
                                
                                <!-- Amount Badge -->
                                <span class="absolute -top-1 -right-1 bg-fivem-primary text-white text-xs px-1.5 py-0.5 rounded-full font-bold shadow-lg border-2 border-white">
                                    <?php echo $item['amount']; ?>
                                </span>
                                
                                <!-- Quality/Rarity Indicator (if available) -->
                                <?php if (isset($item['info']['quality']) && $item['info']['quality'] < 100): ?>
                                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-600 rounded-b-lg overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-red-500 via-yellow-500 to-green-500 transition-all duration-300" 
                                             style="width: <?php echo $item['info']['quality']; ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Tooltip -->
                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-20 whitespace-nowrap theme-transition border shadow-lg"
                                 :class="darkMode ? 'bg-gray-900 text-white border-gray-700' : 'bg-white text-gray-900 border-gray-200 shadow-lg'">
                                <div class="font-semibold mb-1"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['name']))); ?></div>
                                <div class="space-y-1">
                                    <div class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                        <i class="fas fa-hashtag mr-1"></i>Amount: <?php echo $item['amount']; ?>
                                    </div>
                                    <div class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                        <i class="fas fa-tag mr-1"></i>Type: <?php echo htmlspecialchars($item['type']); ?>
                                    </div>
                                    <?php if (isset($item['info']['quality'])): ?>
                                        <div class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <i class="fas fa-star mr-1"></i>Quality: <?php echo $item['info']['quality']; ?>%
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['info']['description'])): ?>
                                        <div class="theme-transition border-t pt-1 mt-1" :class="darkMode ? 'text-gray-300 border-gray-600' : 'text-gray-700 border-gray-300'">
                                            <?php echo htmlspecialchars($item['info']['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Tooltip Arrow -->
                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent theme-transition"
                                     :class="darkMode ? 'border-t-gray-900' : 'border-t-white'"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
            
            <!-- Inventory Stats -->
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                    <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-boxes text-white text-sm"></i>
                    </div>
                    <p class="text-lg font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo count($character_inventory); ?></p>
                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Items</p>
                </div>
                <div class="text-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-weight text-white text-sm"></i>
                    </div>
                    <p class="text-lg font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo array_sum(array_column($character_inventory, 'amount')); ?></p>
                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Total</p>
                </div>
                <div class="text-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                    <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-layer-group text-white text-sm"></i>
                    </div>
                    <p class="text-lg font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo count(array_unique(array_column($character_inventory, 'type'))); ?></p>
                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Types</p>
                </div>
                <div class="text-center p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                    <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-percentage text-white text-sm"></i>
                    </div>
                    <p class="text-lg font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo round((count($character_inventory) / 50) * 100); ?>%</p>
                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Full</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>