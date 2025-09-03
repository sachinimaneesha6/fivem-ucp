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
$bank_statements = [];
$bank_accounts = [];

if ($selected_character) {
    // Get character data
    $query = "SELECT * FROM players WHERE citizenid = :citizenid AND license = :license";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':citizenid', $selected_character);
    $stmt->bindParam(':license', $_SESSION['license']);
    $stmt->execute();
    $character_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get bank statements
    $statements_query = "SELECT * FROM bank_statements WHERE citizenid = :citizenid ORDER BY date DESC LIMIT 50";
    $statements_stmt = $db->prepare($statements_query);
    $statements_stmt->bindParam(':citizenid', $selected_character);
    $statements_stmt->execute();
    $bank_statements = $statements_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get shared bank accounts
    $accounts_query = "SELECT * FROM bank_accounts WHERE JSON_CONTAINS(users, JSON_QUOTE(:citizenid))";
    $accounts_stmt = $db->prepare($accounts_query);
    $accounts_stmt->bindParam(':citizenid', $selected_character);
    $accounts_stmt->execute();
    $bank_accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Banking';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Banking Dashboard</h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Manage your finances and view transaction history</p>
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
    
    <?php if ($character_data): 
        $money = json_decode($character_data['money'], true);
        $charinfo = json_decode($character_data['charinfo'], true);
    ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Account Overview -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-xl font-bold text-white mb-4">
                        <i class="fas fa-wallet text-fivem-primary mr-2"></i>Account Overview
                    </h2>
                    <div class="space-y-4">
                        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-sm">Cash on Hand</p>
                                    <p class="text-white text-2xl font-bold">$<?php echo number_format($money['cash']); ?></p>
                                </div>
                                <i class="fas fa-money-bill-wave text-green-200 text-2xl"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-sm">Bank Balance</p>
                                    <p class="text-white text-2xl font-bold">$<?php echo number_format($money['bank']); ?></p>
                                </div>
                                <i class="fas fa-university text-blue-200 text-2xl"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-yellow-100 text-sm">Crypto Holdings</p>
                                    <p class="text-white text-2xl font-bold"><?php echo number_format($money['crypto']); ?></p>
                                </div>
                                <i class="fas fa-coins text-yellow-200 text-2xl"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gray-700 rounded-lg p-4 border-2 border-fivem-primary">
                            <div class="text-center">
                                <p class="text-gray-400 text-sm">Total Net Worth</p>
                                <p class="text-fivem-primary text-3xl font-bold">$<?php echo number_format($money['cash'] + $money['bank']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Account Info -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Account Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Account Number:</span>
                            <span class="text-white font-mono text-sm"><?php echo htmlspecialchars($charinfo['account']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Account Holder:</span>
                            <span class="text-white"><?php echo htmlspecialchars($charinfo['firstname'] . ' ' . $charinfo['lastname']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Phone:</span>
                            <span class="text-white"><?php echo htmlspecialchars($charinfo['phone']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Shared Accounts -->
                <?php if (!empty($bank_accounts)): ?>
                    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                        <h3 class="text-lg font-bold text-white mb-4">
                            <i class="fas fa-users text-purple-400 mr-2"></i>Shared Accounts
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($bank_accounts as $account): ?>
                                <div class="bg-gray-700 rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="text-white font-semibold"><?php echo htmlspecialchars($account['account_name']); ?></h4>
                                            <p class="text-gray-400 text-sm"><?php echo ucfirst($account['account_type']); ?> Account</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-green-400 font-bold">$<?php echo number_format($account['account_balance']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Transaction History -->
            <div class="lg:col-span-2">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-history text-blue-400 mr-2"></i>Transaction History
                    </h2>
                    
                    <?php if (empty($bank_statements)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-receipt text-6xl text-gray-600 mb-6"></i>
                            <h3 class="text-xl font-bold text-white mb-2">No Transactions</h3>
                            <p class="text-gray-400">No banking activity found for this character</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($bank_statements as $statement): ?>
                                <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-4 <?php echo $statement['statement_type'] == 'deposit' ? 'bg-green-500 bg-opacity-20' : 'bg-red-500 bg-opacity-20'; ?>">
                                                <i class="fas <?php echo $statement['statement_type'] == 'deposit' ? 'fa-arrow-down text-green-400' : 'fa-arrow-up text-red-400'; ?>"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-white font-semibold"><?php echo htmlspecialchars($statement['reason']); ?></h4>
                                                <p class="text-gray-400 text-sm">
                                                    <?php echo ucfirst($statement['statement_type']); ?> • 
                                                    <?php echo date('M j, Y g:i A', strtotime($statement['date'])); ?>
                                                </p>
                                                <p class="text-gray-500 text-xs">Account: <?php echo htmlspecialchars($statement['account_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-lg <?php echo $statement['statement_type'] == 'deposit' ? 'text-green-400' : 'text-red-400'; ?>">
                                                <?php echo $statement['statement_type'] == 'deposit' ? '+' : '-'; ?>$<?php echo number_format($statement['amount']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
            <i class="fas fa-user-slash text-6xl text-gray-600 mb-6"></i>
            <h3 class="text-xl font-bold text-white mb-2">No Character Selected</h3>
            <p class="text-gray-400">Please select a character to view banking information</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>