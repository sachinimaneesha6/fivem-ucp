<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/email.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$emailService = new EmailService();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Create password reset table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
)";
$db->exec($create_table_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $user_query = "SELECT * FROM user_accounts WHERE email = :email AND is_active = 1";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindValue(':email', $email);
        $user_stmt->execute();
        
        if ($user_stmt->rowCount() > 0) {
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token
            $reset_query = "INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)";
            $reset_stmt = $db->prepare($reset_query);
            $reset_stmt->bindValue(':email', $email);
            $reset_stmt->bindValue(':token', $reset_token);
            $reset_stmt->bindValue(':expires_at', $expires_at);
            
            if ($reset_stmt->execute()) {
                // Send email
                if ($emailService->sendPasswordReset($email, $user['username'], $reset_token)) {
                    $success_message = 'Password reset instructions have been sent to your email address.';
                } else {
                    $error_message = 'Failed to send email. Please contact an administrator.';
                }
            } else {
                $error_message = 'Failed to process request. Please try again.';
            }
        } else {
            // Don't reveal if email exists or not for security
            $success_message = 'If an account with that email exists, password reset instructions have been sent.';
        }
    }
}

$page_title = 'Forgot Password';
include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center" 
     :class="darkMode ? 'bg-gray-900' : 'bg-gray-50'">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="rounded-2xl shadow-2xl p-8 border theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center mb-4 shadow-lg">
                    <i class="fas fa-key text-2xl text-white"></i>
                </div>
                <h2 class="text-3xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Forgot Password</h2>
                <p class="theme-transition mt-2" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Enter your email to reset your password</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                        <i class="fas fa-envelope mr-2"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                           :class="darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'"
                           placeholder="Enter your email address">
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Reset Instructions
                </button>
            </form>
            
            <div class="mt-6 text-center space-y-4">
                <div class="flex items-center justify-center">
                    <div class="border-t flex-grow theme-transition" :class="darkMode ? 'border-gray-600' : 'border-gray-300'"></div>
                    <span class="px-4 text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">or</span>
                    <div class="border-t flex-grow theme-transition" :class="darkMode ? 'border-gray-600' : 'border-gray-300'"></div>
                </div>
                
                <a href="index.php" class="inline-flex items-center text-fivem-primary hover:text-yellow-500 font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Login
                </a>
            </div>
            
            <div class="mt-6 text-center">
                <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                    Need help? Contact an administrator
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>