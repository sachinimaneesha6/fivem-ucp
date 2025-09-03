<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

$characters = $auth->getUserCharacters($_SESSION['license']);
$selected_character = $_GET['character'] ?? ($characters[0]['citizenid'] ?? '');

$character_data = null;
if ($selected_character) {
    $query = "SELECT * FROM players WHERE citizenid = :citizenid AND license = :license";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':citizenid', $selected_character);
    $stmt->bindParam(':license', $_SESSION['license']);
    $stmt->execute();
    $character_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = 'Character Map';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/gta-map.js"></script>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Character Map</h1>
        <p class="text-gray-400">View your character's last known position on the map</p>
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
    
    <?php if ($character_data): 
        $position = json_decode($character_data['position'], true);
        $charinfo = json_decode($character_data['charinfo'], true);
    ?>
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Map -->
            <div class="lg:col-span-3">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-xl font-bold text-white mb-4">
                        <i class="fas fa-map text-red-400 mr-2"></i>Los Santos Map
                    </h2>
                    <div id="map" class="w-full h-96 rounded-lg border border-gray-600"></div>
                </div>
            </div>
            
            <!-- Location Info -->
            <div class="space-y-6">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Current Location</h3>
                    <div class="space-y-3">
                        <div class="bg-gray-700 rounded-lg p-3">
                            <p class="text-gray-400 text-sm">Coordinates</p>
                            <p class="text-white font-mono">X: <?php echo round($position['x'], 2); ?></p>
                            <p class="text-white font-mono">Y: <?php echo round($position['y'], 2); ?></p>
                            <p class="text-white font-mono">Z: <?php echo round($position['z'], 2); ?></p>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-3">
                            <p class="text-gray-400 text-sm">Last Updated</p>
                            <p class="text-white"><?php echo date('M j, Y', strtotime($character_data['last_updated'])); ?></p>
                            <p class="text-gray-400 text-sm"><?php echo date('g:i A', strtotime($character_data['last_updated'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button onclick="centerMap()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-crosshairs mr-2"></i>Center on Character
                        </button>
                        <button onclick="copyCoordinates()" class="w-full bg-gray-600 hover:bg-gray-500 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-copy mr-2"></i>Copy Coordinates
                        </button>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Map Legend</h3>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-red-500 rounded-full mr-3"></div>
                            <span class="text-gray-300 text-sm">Character Location</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-500 rounded-full mr-3"></div>
                            <span class="text-gray-300 text-sm">Points of Interest</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            // Initialize GTA map
            const gtaMap = new GTAMap('map');
            
            // Add character marker
            const characterName = '<?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>';
            const characterPos = gtaMap.addCharacterMarker(<?php echo $position['x']; ?>, <?php echo $position['y']; ?>, characterName);
            
            // Enhanced coordinate debugging
            console.log('🎮 Character GTA coords:', <?php echo $position['x']; ?>, <?php echo $position['y']; ?>);
            console.log('🗺️ Character map position:', characterPos);
            
            // Center on character
            gtaMap.centerOnCharacter();
            
            // Manual calibration helper
            window.testCharacterPosition = function() {
                const gtaX = <?php echo $position['x']; ?>;
                const gtaY = <?php echo $position['y']; ?>;
                console.log('🧪 Testing character position...');
                console.log('In-game coordinates:', gtaX, gtaY);
                console.log('Map should show character at these pixel coordinates:', characterPos);
                
                // Add test marker for comparison
                const testMarker = gtaMap.calibrateMap(gtaX, gtaY, 'Character Test Position');
                setTimeout(() => {
                    gtaMap.map.removeLayer(testMarker);
                }, 5000);
            };
            
            function centerMap() {
                gtaMap.centerOnCharacter();
            }
            
            function copyCoordinates() {
                const coords = `X: <?php echo round($position['x'], 2); ?>, Y: <?php echo round($position['y'], 2); ?>, Z: <?php echo round($position['z'], 2); ?>`;
                navigator.clipboard.writeText(coords).then(() => {
                    showNotification('Coordinates Copied', 'GTA coordinates copied to clipboard!', 'success');
                });
            }
            
            // Enhanced calibration tools
            window.calibrateSpecificLocation = function(gtaX, gtaY, description) {
                console.log(`🎯 Manual calibration for: ${description}`);
                const marker = gtaMap.calibrateMap(gtaX, gtaY, description);
                
                // Show coordinates in console
                const mapCoords = gtaMap.convertGTACoords(gtaX, gtaY);
                console.log(`📊 Expected vs Actual positioning for ${description}:`);
                console.log(`   GTA Coords: (${gtaX}, ${gtaY})`);
                console.log(`   Map Pixels: (${mapCoords[1].toFixed(1)}, ${mapCoords[0].toFixed(1)})`);
                
                return marker;
            };
            
            // Quick test function for your character
            window.testMyCharacter = function() {
                testCharacterPosition();
            }
        </script>
        
        <style>
            .leaflet-container {
                background: var(--map-bg) !important;
                border-radius: 8px;
            }
            
            :root {
                --map-bg: #0f172a;
                --popup-bg: #1f2937;
                --popup-border: #374151;
                --control-bg: #1f2937;
                --control-border: #374151;
                --control-hover: #374151;
            }
            
            .light {
                --map-bg: #f8fafc;
                --popup-bg: #ffffff;
                --popup-border: #e2e8f0;
                --control-bg: #ffffff;
                --control-border: #e2e8f0;
                --control-hover: #f1f5f9;
            }
            
            .character-marker, .location-marker {
                background: none !important;
                border: none !important;
            }
            
            .leaflet-popup-content-wrapper {
                background: var(--popup-bg) !important;
                border-radius: 8px;
                border: 1px solid var(--popup-border);
            }
            
            .leaflet-popup-tip {
                background: var(--popup-bg) !important;
            }
            
            .leaflet-control-zoom a {
                background: var(--control-bg) !important;
                border: 1px solid var(--control-border) !important;
            }
            
            .leaflet-control-zoom a:hover {
                background: var(--control-hover) !important;
            }
            
            .dark .leaflet-popup-content-wrapper {
                color: white;
            }
            
            .light .leaflet-popup-content-wrapper {
                color: #1f2937;
            }
            
            .dark .leaflet-control-zoom a {
                color: white !important;
            }
            
            .light .leaflet-control-zoom a {
                color: #1f2937 !important;
            }
        </style>
    <?php else: ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
            <i class="fas fa-map text-6xl text-gray-600 mb-6"></i>
            <h3 class="text-xl font-bold text-white mb-2">No Character Selected</h3>
            <p class="text-gray-400">Please select a character to view their location</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>