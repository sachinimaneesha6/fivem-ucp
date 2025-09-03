<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

// Create basic tickets table
$create_tickets_table = "CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    admin_response TEXT NULL,
    last_viewed_by_user TIMESTAMP NULL,
    is_new TINYINT(1) DEFAULT 1,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at)
)";
$db->exec($create_tickets_table);

$success_message = '';
$error_message = '';

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    if (!empty($subject) && !empty($message)) {
        $query = "INSERT INTO support_tickets (user_id, username, subject, message, priority) 
                  VALUES (:user_id, :username, :subject, :message, :priority)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':username', $_SESSION['username']);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':priority', $priority);
        
        if ($stmt->execute()) {
            $ticket_id = $db->lastInsertId();
            $success_message = 'Ticket submitted successfully! Ticket #' . $ticket_id;
        } else {
            $error_message = 'Failed to submit ticket. Please try again.';
        }
    } else {
        $error_message = 'Please fill in all required fields.';
    }
}

// Get user tickets
$tickets_query = "SELECT *, 
    CASE 
        WHEN admin_response IS NOT NULL AND (last_viewed_by_user IS NULL OR updated_at > last_viewed_by_user) THEN 1
        ELSE 0 
    END as has_unread_response,
    CASE 
        WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1
        ELSE 0 
    END as is_new_ticket
    FROM support_tickets 
    WHERE user_id = :user_id 
    ORDER BY has_unread_response DESC, is_new_ticket DESC, created_at DESC";
$tickets_stmt = $db->prepare($tickets_query);
$tickets_stmt->bindParam(':user_id', $_SESSION['user_id']);
$tickets_stmt->execute();
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Support Tickets';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
            <i class="fas fa-life-ring text-fivem-primary mr-3"></i>Support Center
        </h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Get help from our dedicated support team</p>
    </div>
    
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <!-- Create New Ticket -->
        <div class="xl:col-span-1">
            <div class="rounded-xl border p-6 sticky top-8 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <h2 class="text-xl font-bold mb-6 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                    <i class="fas fa-plus text-fivem-primary mr-2"></i>Create New Ticket
                </h2>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-4">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="priority" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Priority
                        </label>
                        <select id="priority" name="priority" 
                                class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                                :class="darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'">
                            <option value="low">Low Priority</option>
                            <option value="medium" selected>Medium Priority</option>
                            <option value="high">High Priority</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="subject" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-heading mr-2"></i>Subject
                        </label>
                        <input type="text" id="subject" name="subject" required maxlength="255"
                               class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                               :class="darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'"
                               placeholder="Brief description of your issue">
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-comment mr-2"></i>Message
                        </label>
                        <textarea id="message" name="message" rows="6" required maxlength="2000"
                                  class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent resize-none theme-transition"
                                  :class="darkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'"
                                  placeholder="Describe your issue in detail..."></textarea>
                        <div class="flex justify-between mt-1">
                            <span class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">Be as detailed as possible</span>
                            <span id="char-count" class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">0/2000</span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="attachment" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-paperclip mr-2"></i>Attachment (Optional)
                        </label>
                        <div class="relative">
                            <input type="file" id="attachment" name="attachment" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.log"
                                   class="hidden"
                                   onchange="handleFileSelect(this)">
                            <label for="attachment" 
                                   class="w-full px-4 py-3 rounded-lg border-2 border-dashed transition-all cursor-pointer flex items-center justify-center hover:border-fivem-primary theme-transition"
                                   :class="darkMode ? 'border-gray-600 bg-gray-700 hover:bg-gray-650 text-gray-300' : 'border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700'">
                                <div class="text-center">
                                    <i class="fas fa-cloud-upload-alt text-2xl mb-2 text-fivem-primary"></i>
                                    <p class="font-medium">Click to upload file</p>
                                    <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                                        Images, PDFs, text files (Max 5MB)
                                    </p>
                                </div>
                            </label>
                        </div>
                        <div id="file-preview" class="mt-2 hidden">
                            <div class="flex items-center justify-between p-3 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                                <div class="flex items-center">
                                    <i class="fas fa-file text-fivem-primary mr-2"></i>
                                    <span id="file-name" class="text-sm font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"></span>
                                    <span id="file-size" class="text-xs ml-2 theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'"></span>
                                </div>
                                <button type="button" onclick="clearFile()" class="text-red-400 hover:text-red-300 transition-colors">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_ticket"
                            class="w-full bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Ticket
                    </button>
                </form>
                
                <!-- Quick Help -->
                <div class="mt-6 p-4 rounded-lg theme-transition" :class="darkMode ? 'bg-gray-700' : 'bg-gray-100'">
                    <h4 class="font-semibold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>Quick Help
                    </h4>
                    <div class="space-y-2 text-sm">
                        <p class="theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">• Check our FAQ before creating a ticket</p>
                        <p class="theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">• Include screenshots for visual issues</p>
                        <p class="theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">• Provide your character ID for gameplay issues</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Tickets -->
        <div class="xl:col-span-2">
            <div class="rounded-xl border p-6 theme-transition" 
                 :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                        <i class="fas fa-ticket-alt text-blue-400 mr-2"></i>Your Tickets
                    </h2>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'"><?php echo count($tickets); ?> total</span>
                    </div>
                </div>
                
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 bg-gradient-to-r from-gray-400 to-gray-500 rounded-full flex items-center justify-center mx-auto mb-6 opacity-50">
                            <i class="fas fa-inbox text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-3 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">No Tickets Yet</h3>
                        <p class="theme-transition mb-6" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">You haven't submitted any support tickets</p>
                        <div class="inline-flex items-center px-4 py-2 rounded-xl theme-transition" :class="darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-700'">
                            <i class="fas fa-arrow-left mr-2"></i>
                            <span class="text-sm">Create your first ticket using the form</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="rounded-xl border p-6 transition-all duration-300 hover:shadow-lg theme-transition relative overflow-hidden
                                 <?php if ($ticket['has_unread_response']): ?>
                                     ring-2 ring-blue-500 ring-opacity-50 animate-pulse-slow
                                 <?php elseif ($ticket['is_new_ticket']): ?>
                                     ring-2 ring-green-500 ring-opacity-50
                                 <?php endif; ?>" 
                                 :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'"
                                 x-data="{ expanded: false }">
                                
                                <!-- Notification Badges -->
                                <?php if ($ticket['has_unread_response']): ?>
                                    <div class="absolute top-4 right-4 z-10">
                                        <div class="flex items-center bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg animate-bounce">
                                            <i class="fas fa-bell mr-1"></i>
                                            New Response
                                        </div>
                                    </div>
                                <?php elseif ($ticket['is_new_ticket']): ?>
                                    <div class="absolute top-4 right-4 z-10">
                                        <div class="flex items-center bg-green-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                                            <i class="fas fa-star mr-1"></i>
                                            New
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Unread Response Glow Effect -->
                                <?php if ($ticket['has_unread_response']): ?>
                                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-xl pointer-events-none"></div>
                                <?php elseif ($ticket['is_new_ticket']): ?>
                                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-xl pointer-events-none"></div>
                                <?php endif; ?>
                                
                                <div class="relative z-10">
                                <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 
                                                 <?php if ($ticket['has_unread_response']): ?>
                                                     bg-blue-500 bg-opacity-30 ring-2 ring-blue-400
                                                 <?php elseif ($ticket['is_new_ticket']): ?>
                                                     bg-green-500 bg-opacity-30 ring-2 ring-green-400
                                                 <?php else: ?>
                                                     bg-blue-500 bg-opacity-20
                                                 <?php endif; ?>">
                                                <i class="fas fa-ticket-alt 
                                                   <?php if ($ticket['has_unread_response']): ?>
                                                       text-blue-300
                                                   <?php elseif ($ticket['is_new_ticket']): ?>
                                                       text-green-300
                                                   <?php else: ?>
                                                       text-blue-400
                                                   <?php endif; ?>"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold theme-transition 
                                                <?php if ($ticket['has_unread_response']): ?>
                                                    text-blue-300 font-bold
                                                <?php elseif ($ticket['is_new_ticket']): ?>
                                                    text-green-300 font-bold
                                                <?php endif; ?>" 
                                                :class="darkMode ? 'text-white' : 'text-gray-900'">
                                                <?php echo htmlspecialchars($ticket['subject']); ?>
                                                <?php if ($ticket['has_unread_response']): ?>
                                                    <i class="fas fa-circle text-blue-400 text-xs ml-2 animate-pulse"></i>
                                                <?php elseif ($ticket['is_new_ticket']): ?>
                                                    <i class="fas fa-circle text-green-400 text-xs ml-2"></i>
                                                <?php endif; ?>
                                            </h3>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <span class="flex items-center">
                                                <i class="fas fa-hashtag mr-1"></i>
                                                Ticket #<?php echo $ticket['id']; ?>
                                            </span>
                                            <span>•</span>
                                            <span class="flex items-center">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                             <?php if ($ticket['has_unread_response']): ?>
                                                 bg-blue-500 text-white shadow-lg animate-pulse-slow
                                             <?php elseif ($ticket['is_new_ticket']): ?>
                                                 bg-green-500 text-white shadow-lg
                                             <?php else: ?>
                                                 <?php 
                                            switch($ticket['priority']) {
                                                case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                                case 'high': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'medium': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                                 ?>
                                             <?php endif; ?>">
                                            <?php if ($ticket['has_unread_response']): ?>
                                                <i class="fas fa-bell mr-1"></i>Response
                                            <?php elseif ($ticket['is_new_ticket']): ?>
                                                <i class="fas fa-star mr-1"></i>New
                                            <?php else: ?>
                                                <?php echo ucfirst($ticket['priority']); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php 
                                            switch($ticket['status']) {
                                                case 'open': echo 'bg-green-100 text-green-800'; break;
                                                case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                        ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                        <button @click="expanded = !expanded" class="transition-colors theme-transition" :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">
                                            <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': expanded }"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div x-show="expanded" x-transition class="space-y-4">
                                    <!-- Original Message -->
                                    <div class="rounded-lg p-4 theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white border border-gray-200'">
                                        <h4 class="font-semibold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                            <i class="fas fa-comment-dots mr-2"></i>Original Message
                                        </h4>
                                        <p class="theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                                    </div>
                                    
                                    <!-- Admin Response -->
                                    <?php if ($ticket['admin_response']): ?>
                                        <div class="rounded-lg p-4 border-l-4 border-blue-500 theme-transition relative
                                             <?php if ($ticket['has_unread_response']): ?>
                                                 ring-2 ring-blue-400 ring-opacity-50 animate-pulse-slow
                                             <?php endif; ?>" 
                                             :class="darkMode ? 'bg-blue-500 bg-opacity-10' : 'bg-blue-50'">
                                            <?php if ($ticket['has_unread_response']): ?>
                                                <div class="absolute top-2 right-2">
                                                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-ping"></div>
                                                    <div class="absolute top-0 w-3 h-3 bg-blue-400 rounded-full"></div>
                                                </div>
                                            <?php endif; ?>
                                            <h4 class="font-semibold mb-2 text-blue-400">
                                                <i class="fas fa-user-shield mr-2"></i>Staff Response
                                                <?php if ($ticket['has_unread_response']): ?>
                                                    <span class="bg-blue-500 text-white px-2 py-1 rounded-full text-xs ml-2 animate-bounce">NEW</span>
                                                <?php endif; ?>
                                            </h4>
                                            <p class="theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'"><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                                            <div class="mt-3 text-xs text-blue-400">
                                                <i class="fas fa-clock mr-1"></i>
                                                Updated: <?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// File upload handling
function handleFileSelect(input) {
    const file = input.files[0];
    const preview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    
    if (file) {
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Please upload images, PDFs, or text files only.');
            input.value = '';
            return;
        }
        
        fileName.textContent = file.name;
        fileSize.textContent = `(${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        preview.classList.remove('hidden');
    }
}

function clearFile() {
    document.getElementById('attachment').value = '';
    document.getElementById('file-preview').classList.add('hidden');
}

// Character counter for message textarea
document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    
    if (messageTextarea && charCount) {
        messageTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = `${length}/2000`;
            
            if (length > 1800) {
                charCount.className = 'text-xs text-red-500 font-bold';
            } else if (length > 1500) {
                charCount.className = 'text-xs text-yellow-500';
            } else {
                charCount.className = 'text-xs theme-transition';
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>