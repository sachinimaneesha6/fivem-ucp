<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: dashboard.php');
        exit();
    } else {
        $error_message = 'Invalid username or password';
    }
}

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Login';
include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center gradient-bg">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 border border-gray-700">
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 bg-fivem-primary rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-gamepad text-2xl text-white"></i>
                </div>
                <h2 class="text-3xl font-bold text-white">FiveM UCP</h2>
                <p class="text-gray-400 mt-2">Access your character dashboard</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-300 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-user mr-2"></i>Username
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-fivem-primary focus:border-transparent transition-all"
                           placeholder="Enter your username">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-fivem-primary focus:border-transparent transition-all"
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="forgot_password.php" class="text-fivem-primary hover:text-yellow-500 text-sm font-medium transition-colors">
                    <i class="fas fa-key mr-2"></i>Forgot your password?
                </a>
            </div>
            
            <div class="mt-4 text-center">
                <p class="text-gray-400 text-sm">
                    Need help? Contact an administrator
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>