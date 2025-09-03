<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

$characters = $auth->getUserCharacters($_SESSION['license']);

$page_title = 'Characters';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Your Characters</h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Manage and view your character information</p>
    </div>
    
    <?php if (empty($characters)): ?>
        <div class="rounded-xl border p-12 text-center theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <i class="fas fa-user-plus text-6xl mb-6 theme-transition" :class="darkMode ? 'text-gray-600' : 'text-gray-400'"></i>
            <h3 class="text-xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">No Characters Found</h3>
            <p class="mb-6 theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">You haven't created any characters yet. Join the server to create your first character!</p>
            <div class="rounded-lg p-4 inline-block theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                    <i class="fas fa-info-circle mr-2"></i>
                    Characters are automatically synced when you create them in-game
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($characters as $character): 
                $charinfo = json_decode($character['charinfo'], true);
                $money = json_decode($character['money'], true);
                $job = json_decode($character['job'], true);
                $gang = json_decode($character['gang'], true);
                $metadata = json_decode($character['metadata'], true);
                $position = json_decode($character['position'], true);
            ?>
                <div class="rounded-xl border overflow-hidden card-hover theme-transition" 
                     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                    <div class="bg-gradient-to-r from-fivem-primary to-yellow-500 h-2"></div>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                    <?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?>
                                </h3>
                                <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">ID: <?php echo htmlspecialchars($character['citizenid']); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            </div>
                        </div>
                        
                        <!-- Character Info -->
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Job:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($job['label']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Gang:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($gang['label']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Phone:</span>
                                <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($charinfo['phone']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Money Info -->
                        <div class="rounded-lg p-4 mb-6 theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                            <h4 class="font-semibold mb-3 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Financial Status</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Cash</p>
                                    <p class="text-green-400 font-bold">$<?php echo number_format($money['cash']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Bank</p>
                                    <p class="text-blue-400 font-bold">$<?php echo number_format($money['bank']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex space-x-2">
                            <a href="character_detail.php?id=<?php echo $character['citizenid']; ?>" 
                               class="flex-1 bg-fivem-primary hover:bg-yellow-500 text-white text-center py-2 px-4 rounded-lg font-medium transition-colors">
                                <i class="fas fa-eye mr-2"></i>View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>