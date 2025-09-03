<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

$characters = $auth->getUserCharacters($_SESSION['license']);
$selected_character = $_GET['character'] ?? ($characters[0]['citizenid'] ?? '');

$properties = [];
$apartments = [];

if ($selected_character) {
    // Get owned houses
    $houses_query = "SELECT h.*, hl.label, hl.coords, hl.price 
                     FROM player_houses h 
                     LEFT JOIN houselocations hl ON h.house = hl.name 
                     WHERE h.citizenid = :citizenid";
    $houses_stmt = $db->prepare($houses_query);
    $houses_stmt->bindParam(':citizenid', $selected_character);
    $houses_stmt->execute();
    $properties = $houses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get apartments
    $apartments_query = "SELECT * FROM apartments WHERE citizenid = :citizenid";
    $apartments_stmt = $db->prepare($apartments_query);
    $apartments_stmt->bindParam(':citizenid', $selected_character);
    $apartments_stmt->execute();
    $apartments = $apartments_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Properties';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Property Management</h1>
        <p class="text-gray-400">Manage your character's properties and apartments</p>
    </div>
    
    <!-- Character Selection -->
    <?php if (!empty($characters)): ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
            <h2 class="text-lg font-bold text-white mb-4">Select Character</h2>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($characters as $character): 
                    $charinfo = json_decode($character['charinfo'], true);
                    $isSelected = $character['citizenid'] == $selected_character;
                ?>
                    <a href="?character=<?php echo $character['citizenid']; ?>" 
                       class="flex items-center px-4 py-2 rounded-lg border transition-all <?php echo $isSelected ? 'bg-fivem-primary border-fivem-primary text-white' : 'bg-gray-700 border-gray-600 text-gray-300 hover:bg-gray-600'; ?>">
                        <i class="fas fa-user mr-2"></i>
                        <?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Houses -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-home text-blue-400 mr-2"></i>Owned Houses
            </h2>
            
            <?php if (empty($properties)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-home text-6xl text-gray-600 mb-6"></i>
                    <h3 class="text-xl font-bold text-white mb-2">No Houses Owned</h3>
                    <p class="text-gray-400">This character doesn't own any houses</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($properties as $property): 
                        $coords = json_decode($property['coords'], true);
                        $keyholders = json_decode($property['keyholders'], true) ?? [];
                    ?>
                        <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-white font-semibold"><?php echo htmlspecialchars($property['label'] ?? $property['house']); ?></h3>
                                    <p class="text-gray-400 text-sm">Property ID: <?php echo htmlspecialchars($property['house']); ?></p>
                                </div>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                    Owned
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-gray-400 text-sm">Value</p>
                                    <p class="text-green-400 font-bold">${{ echo number_format($property['price'] ?? 0); }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-sm">Key Holders</p>
                                    <p class="text-white font-medium"><?php echo count($keyholders); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($coords): ?>
                                <div class="bg-gray-800 rounded p-3">
                                    <p class="text-gray-400 text-sm mb-1">Location</p>
                                    <p class="text-white font-mono text-sm">
                                        X: <?php echo round($coords['x'], 2); ?>, 
                                        Y: <?php echo round($coords['y'], 2); ?>, 
                                        Z: <?php echo round($coords['z'], 2); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Apartments -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-building text-purple-400 mr-2"></i>Apartments
            </h2>
            
            <?php if (empty($apartments)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-building text-6xl text-gray-600 mb-6"></i>
                    <h3 class="text-xl font-bold text-white mb-2">No Apartments</h3>
                    <p class="text-gray-400">This character doesn't have any apartments</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($apartments as $apartment): ?>
                        <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-white font-semibold"><?php echo htmlspecialchars($apartment['label']); ?></h3>
                                    <p class="text-gray-400 text-sm">Apartment: <?php echo htmlspecialchars($apartment['name']); ?></p>
                                </div>
                                <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs font-medium">
                                    <?php echo ucfirst($apartment['type']); ?>
                                </span>
                            </div>
                            
                            <div class="bg-gray-800 rounded p-3">
                                <p class="text-gray-400 text-sm mb-1">Type</p>
                                <p class="text-white"><?php echo htmlspecialchars(ucwords(str_replace('apartment', 'Apartment ', $apartment['type']))); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>