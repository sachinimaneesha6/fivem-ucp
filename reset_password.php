<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$token = $_GET['token'] ?? '';
$success_message = '';
$error_message = '';
$valid_token = false;
$user_data = null;

// Validate token
if (!empty($token)) {
    $token_query = "SELECT pr.*, ua.username, ua.email 
                    FROM password_resets pr 
                    JOIN user_accounts ua ON pr.email = ua.email 
                    WHERE pr.token = :token 
                    AND pr.expires_at > NOW() 
                    AND pr.used = 0 
                    AND ua.is_active = 1";
    $token_stmt = $db->prepare($token_query);
    $token_stmt->bindValue(':token', $token);
    $token_stmt->execute();
    
    if ($token_stmt->rowCount() > 0) {
        $valid_token = true;
        $user_data = $token_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_message = 'Invalid or expired reset token. Please request a new password reset.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $error_message = 'Please enter a new password.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE user_accounts SET password_hash = :password_hash WHERE email = :email";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindValue(':password_hash', $password_hash);
        $update_stmt->bindValue(':email', $user_data['email']);
        
        if ($update_stmt->execute()) {
            // Mark token as used
            $mark_used_query = "UPDATE password_resets SET used = 1 WHERE token = :token";
            $mark_used_stmt = $db->prepare($mark_used_query);
            $mark_used_stmt->bindValue(':token', $token);
            $mark_used_stmt->execute();
            
            $success_message = 'Password updated successfully! You can now login with your new password.';
            $valid_token = false; // Hide form after successful reset
        } else {
            $error_message = 'Failed to update password. Please try again.';
        }
    }
}

$page_title = 'Reset Password';
include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center" 
     :class="darkMode ? 'bg-gray-900' : 'bg-gray-50'">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="rounded-2xl shadow-2xl p-8 border theme-transition" 
             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-2xl flex items-center justify-center mb-4 shadow-lg">
                    <i class="fas fa-lock text-2xl text-white"></i>
                </div>
                <h2 class="text-3xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Reset Password</h2>
                <p class="theme-transition mt-2" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                    <?php echo $valid_token ? 'Enter your new password' : 'Password reset status'; ?>
                </p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
                <div class="text-center">
                    <a href="index.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-bold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Go to Login
                    </a>
                </div>
            <?php elseif ($error_message): ?>
                <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
                <div class="text-center">
                    <a href="forgot_password.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-redo mr-2"></i>
                        Request New Reset
                    </a>
                </div>
            <?php elseif ($valid_token): ?>
                <form method="POST" class="space-y-6" id="resetForm">
                    <div class="bg-blue-500 bg-opacity-20 border border-blue-500 text-blue-400 px-4 py-3 rounded-lg mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>Resetting password for: <strong><?php echo htmlspecialchars($user_data['username']); ?></strong></span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-lock mr-2"></i>New Password
                        </label>
                        <input type="password" id="password" name="password" required minlength="6"
                               class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                               :class="darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'"
                               placeholder="Enter new password">
                        <div class="mt-1">
                            <div id="password-strength" class="h-1 rounded-full transition-all duration-300"></div>
                            <p id="password-feedback" class="text-xs mt-1 theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'"></p>
                        </div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-lock mr-2"></i>Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                               :class="darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'"
                               placeholder="Confirm new password">
                        <div id="password-match" class="mt-1 text-xs"></div>
                    </div>
                    
                    <button type="submit" id="resetButton"
                            class="w-full bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-save mr-2"></i>
                        Update Password
                    </button>
                </form>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const passwordInput = document.getElementById('password');
                    const confirmInput = document.getElementById('confirm_password');
                    const strengthBar = document.getElementById('password-strength');
                    const feedback = document.getElementById('password-feedback');
                    const matchDiv = document.getElementById('password-match');
                    const resetButton = document.getElementById('resetButton');
                    
                    function checkPasswordStrength(password) {
                        let strength = 0;
                        let feedback_text = '';
                        
                        if (password.length >= 6) strength += 1;
                        if (password.length >= 8) strength += 1;
                        if (/[A-Z]/.test(password)) strength += 1;
                        if (/[a-z]/.test(password)) strength += 1;
                        if (/[0-9]/.test(password)) strength += 1;
                        if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                        
                        const colors = ['bg-red-500', 'bg-red-400', 'bg-yellow-500', 'bg-yellow-400', 'bg-green-400', 'bg-green-500'];
                        const texts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
                        
                        strengthBar.className = `h-1 rounded-full transition-all duration-300 ${colors[strength] || 'bg-gray-300'}`;
                        strengthBar.style.width = `${(strength / 6) * 100}%`;
                        feedback.textContent = password.length > 0 ? `Strength: ${texts[strength] || 'Very Weak'}` : '';
                        
                        return strength;
                    }
                    
                    function checkPasswordMatch() {
                        const password = passwordInput.value;
                        const confirm = confirmInput.value;
                        
                        if (confirm.length === 0) {
                            matchDiv.textContent = '';
                            return true;
                        }
                        
                        if (password === confirm) {
                            matchDiv.textContent = '✓ Passwords match';
                            matchDiv.className = 'mt-1 text-xs text-green-400';
                            return true;
                        } else {
                            matchDiv.textContent = '✗ Passwords do not match';
                            matchDiv.className = 'mt-1 text-xs text-red-400';
                            return false;
                        }
                    }
                    
                    function updateButtonState() {
                        const strength = checkPasswordStrength(passwordInput.value);
                        const match = checkPasswordMatch();
                        const minLength = passwordInput.value.length >= 6;
                        
                        resetButton.disabled = !(strength >= 2 && match && minLength);
                    }
                    
                    passwordInput.addEventListener('input', updateButtonState);
                    confirmInput.addEventListener('input', updateButtonState);
                    
                    // Initial check
                    updateButtonState();
                });
                </script>
            <?php else: ?>
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-500 bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times text-red-400 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Invalid Reset Link</h3>
                    <p class="theme-transition mb-6" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">This password reset link is invalid or has expired.</p>
                    <a href="forgot_password.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-redo mr-2"></i>
                        Request New Reset
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>