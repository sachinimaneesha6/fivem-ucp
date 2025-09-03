<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/discord.php';
require_once 'config/ticket_config.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$discord = new DiscordWebhook();

$auth->requireLogin();

// Create enhanced tickets table
$create_tickets_table = "CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    department VARCHAR(50) DEFAULT 'support',
    status ENUM('open', 'in_progress', 'on_hold', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_to INT NULL,
    assigned_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_response TEXT NULL,
    internal_notes TEXT NULL,
    attachments JSON NULL,
    auto_close_date TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_category (category),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (assigned_to) REFERENCES user_accounts(id) ON DELETE SET NULL
)";
$db->exec($create_tickets_table);

// Create ticket history table
$create_history_table = "CREATE TABLE IF NOT EXISTS ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    action_type ENUM('created', 'status_change', 'priority_change', 'assignment', 'response', 'internal_note', 'attachment', 'closed') NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    message TEXT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
)";
$db->exec($create_history_table);

// Create ticket attachments table
$create_attachments_table = "CREATE TABLE IF NOT EXISTS ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_id (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
)";
$db->exec($create_attachments_table);

$success_message = '';
$error_message = '';

// Handle file upload
function handleFileUpload($ticket_id, $user_id, $db) {
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $file = $_FILES['attachment'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'text/plain', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only images, text files, and PDFs are allowed.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/tickets/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('ticket_' . $ticket_id . '_') . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save to database
        $query = "INSERT INTO ticket_attachments (ticket_id, filename, original_name, file_size, mime_type, uploaded_by) 
                  VALUES (:ticket_id, :filename, :original_name, :file_size, :mime_type, :uploaded_by)";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':ticket_id', $ticket_id);
        $stmt->bindValue(':filename', $filename);
        $stmt->bindValue(':original_name', $file['name']);
        $stmt->bindValue(':file_size', $file['size']);
        $stmt->bindValue(':mime_type', $file['type']);
        $stmt->bindValue(':uploaded_by', $user_id);
        $stmt->execute();
        
        return $filename;
    }
    
    return false;
}

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $category = $_POST['category'] ?? 'general';
    
    if (!empty($subject) && !empty($message)) {
        try {
            $db->beginTransaction();
            
            // Auto-assign ticket
            $assigned_to = TicketConfig::autoAssignTicket($category, $db);
            $categoryInfo = TicketConfig::getCategoryInfo($category);
            
            // Calculate auto-close date
            $auto_close_date = date('Y-m-d H:i:s', strtotime('+' . TicketConfig::AUTO_CLOSE_DAYS . ' days'));
            
            $query = "INSERT INTO support_tickets (user_id, username, subject, message, category, department, priority, assigned_to, auto_close_date) 
                      VALUES (:user_id, :username, :subject, :message, :category, :department, :priority, :assigned_to, :auto_close_date)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':username', $_SESSION['username']);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':department', $categoryInfo['department']);
            $stmt->bindParam(':priority', $priority);
            $stmt->bindParam(':assigned_to', $assigned_to);
            $stmt->bindParam(':auto_close_date', $auto_close_date);
            
            if ($stmt->execute()) {
                $ticket_id = $db->lastInsertId();
                
                // Log ticket creation
                $history_query = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, message) 
                                  VALUES (:ticket_id, :user_id, :username, 'created', :message)";
                $history_stmt = $db->prepare($history_query);
                $history_stmt->bindValue(':ticket_id', $ticket_id);
                $history_stmt->bindValue(':user_id', $_SESSION['user_id']);
                $history_stmt->bindValue(':username', $_SESSION['username']);
                $history_stmt->bindValue(':message', 'Ticket created');
                $history_stmt->execute();
                
                // Handle file upload
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $filename = handleFileUpload($ticket_id, $_SESSION['user_id'], $db);
                        if ($filename) {
                            // Log attachment
                            $attach_history = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, message) 
                                              VALUES (:ticket_id, :user_id, :username, 'attachment', :message)";
                            $attach_stmt = $db->prepare($attach_history);
                            $attach_stmt->bindValue(':ticket_id', $ticket_id);
                            $attach_stmt->bindValue(':user_id', $_SESSION['user_id']);
                            $attach_stmt->bindValue(':username', $_SESSION['username']);
                            $attach_stmt->bindValue(':message', 'Uploaded attachment: ' . $_FILES['attachment']['name']);
                            $attach_stmt->execute();
                        }
                    } catch (Exception $e) {
                        $error_message = 'Ticket created but file upload failed: ' . $e->getMessage();
                    }
                }
                
                $db->commit();
                $success_message = 'Ticket submitted successfully! Ticket #' . $ticket_id;
                
                // Send Discord notification
                $discord->sendTicketNotification($ticket_id, $_SESSION['username'], $subject, $priority);
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Failed to submit ticket: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Please fill in all required fields.';
    }
}

// Get user tickets with enhanced data
$tickets_query = "SELECT t.*, 
    ta.username as assigned_username,
    (SELECT COUNT(*) FROM ticket_attachments WHERE ticket_id = t.id) as attachment_count,
    (SELECT COUNT(*) FROM ticket_history WHERE ticket_id = t.id AND action_type = 'response') as response_count
    FROM support_tickets t 
    LEFT JOIN user_accounts ta ON t.assigned_to = ta.id 
    WHERE t.user_id = :user_id 
    ORDER BY t.created_at DESC";
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
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="category" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-folder mr-2"></i>Category
                        </label>
                        <select id="category" name="category" required
                                class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                                :class="darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'">
                            <?php foreach (TicketConfig::CATEGORIES as $key => $category): ?>
                                <option value="<?php echo $key; ?>">
                                    <?php echo $category['label']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="priority" class="block text-sm font-medium mb-2 theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Priority
                        </label>
                        <select id="priority" name="priority" 
                                class="w-full px-4 py-3 rounded-lg border transition-all focus:ring-2 focus:ring-fivem-primary focus:border-transparent theme-transition"
                                :class="darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'">
                            <?php foreach (TicketConfig::PRIORITIES as $key => $priority): ?>
                                <option value="<?php echo $key; ?>" <?php echo $key == 'medium' ? 'selected' : ''; ?>>
                                    <?php echo $priority['label']; ?>
                                </option>
                            <?php endforeach; ?>
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
                            <input type="file" id="attachment" name="attachment" accept="image/*,.txt,.pdf"
                                   class="w-full px-4 py-3 rounded-lg border transition-all theme-transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-fivem-primary file:text-white hover:file:bg-yellow-500"
                                   :class="darkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'">
                        </div>
                        <p class="text-xs mt-1 theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                            Max 5MB. Supported: Images, Text files, PDFs
                        </p>
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
                        <?php foreach ($tickets as $ticket): 
                            $categoryInfo = TicketConfig::getCategoryInfo($ticket['category']);
                            $priorityInfo = TicketConfig::getPriorityInfo($ticket['priority']);
                            $statusInfo = TicketConfig::getStatusInfo($ticket['status']);
                        ?>
                            <div class="rounded-xl border p-6 transition-all duration-300 hover:shadow-lg theme-transition" 
                                 :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-650' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'"
                                 x-data="{ expanded: false }">
                                <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 bg-<?php echo $categoryInfo['color']; ?>-500 bg-opacity-20">
                                                <i class="fas <?php echo $categoryInfo['icon']; ?> text-<?php echo $categoryInfo['color']; ?>-400"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                                <?php echo htmlspecialchars($ticket['subject']); ?>
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
                                            <?php if ($ticket['assigned_username']): ?>
                                                <span>•</span>
                                                <span class="flex items-center">
                                                    <i class="fas fa-user-tie mr-1"></i>
                                                    Assigned to <?php echo htmlspecialchars($ticket['assigned_username']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $categoryInfo['color']; ?>-100 text-<?php echo $categoryInfo['color']; ?>-800">
                                            <i class="fas <?php echo $categoryInfo['icon']; ?> mr-1"></i>
                                            <?php echo $categoryInfo['label']; ?>
                                        </span>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $priorityInfo['color']; ?>-100 text-<?php echo $priorityInfo['color']; ?>-800">
                                            <?php echo $priorityInfo['label']; ?>
                                        </span>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $statusInfo['color']; ?>-100 text-<?php echo $statusInfo['color']; ?>-800">
                                            <?php echo $statusInfo['label']; ?>
                                        </span>
                                        <button @click="expanded = !expanded" class="transition-colors theme-transition" :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">
                                            <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': expanded }"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Ticket Stats -->
                                <div class="flex items-center space-x-6 mb-4">
                                    <?php if ($ticket['response_count'] > 0): ?>
                                        <div class="flex items-center text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <i class="fas fa-reply text-blue-400 mr-2"></i>
                                            <?php echo $ticket['response_count']; ?> response<?php echo $ticket['response_count'] != 1 ? 's' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($ticket['attachment_count'] > 0): ?>
                                        <div class="flex items-center text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                            <i class="fas fa-paperclip text-green-400 mr-2"></i>
                                            <?php echo $ticket['attachment_count']; ?> attachment<?php echo $ticket['attachment_count'] != 1 ? 's' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex items-center text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                        <i class="fas fa-clock text-yellow-400 mr-2"></i>
                                        Updated <?php echo date('M j', strtotime($ticket['updated_at'])); ?>
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
                                        <div class="rounded-lg p-4 border-l-4 border-blue-500 theme-transition" :class="darkMode ? 'bg-blue-500 bg-opacity-10' : 'bg-blue-50'">
                                            <h4 class="font-semibold mb-2 text-blue-400">
                                                <i class="fas fa-user-shield mr-2"></i>Staff Response
                                            </h4>
                                            <p class="theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'"><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Ticket History -->
                                    <?php
                                    $history_query = "SELECT * FROM ticket_history WHERE ticket_id = :ticket_id AND is_internal = 0 ORDER BY created_at ASC";
                                    $history_stmt = $db->prepare($history_query);
                                    $history_stmt->bindValue(':ticket_id', $ticket['id']);
                                    $history_stmt->execute();
                                    $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <?php if (!empty($history)): ?>
                                        <div class="rounded-lg p-4 theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white border border-gray-200'">
                                            <h4 class="font-semibold mb-3 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                                <i class="fas fa-history mr-2"></i>Ticket History
                                            </h4>
                                            <div class="space-y-3">
                                                <?php foreach ($history as $entry): ?>
                                                    <div class="flex items-start space-x-3">
                                                        <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-<?php echo $entry['action_type'] == 'created' ? 'green' : 'blue'; ?>-500">
                                                            <i class="fas fa-<?php echo $entry['action_type'] == 'created' ? 'plus' : 'edit'; ?> text-white text-xs"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">
                                                                <span class="font-medium"><?php echo htmlspecialchars($entry['username']); ?></span>
                                                                <?php echo htmlspecialchars($entry['message']); ?>
                                                            </p>
                                                            <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                                                                <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Attachments -->
                                    <?php
                                    $attachments_query = "SELECT * FROM ticket_attachments WHERE ticket_id = :ticket_id ORDER BY uploaded_at DESC";
                                    $attachments_stmt = $db->prepare($attachments_query);
                                    $attachments_stmt->bindValue(':ticket_id', $ticket['id']);
                                    $attachments_stmt->execute();
                                    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <?php if (!empty($attachments)): ?>
                                        <div class="rounded-lg p-4 theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white border border-gray-200'">
                                            <h4 class="font-semibold mb-3 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                                <i class="fas fa-paperclip mr-2"></i>Attachments
                                            </h4>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <div class="flex items-center p-3 rounded-lg border transition-colors theme-transition" :class="darkMode ? 'bg-gray-700 border-gray-600 hover:bg-gray-600' : 'bg-gray-100 border-gray-200 hover:bg-gray-200'">
                                                        <div class="w-10 h-10 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                                            <i class="fas fa-<?php echo strpos($attachment['mime_type'], 'image') !== false ? 'image' : 'file'; ?> text-blue-400"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium truncate theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
                                                                <?php echo htmlspecialchars($attachment['original_name']); ?>
                                                            </p>
                                                            <p class="text-xs theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                                                                <?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB
                                                            </p>
                                                        </div>
                                                        <a href="uploads/tickets/<?php echo htmlspecialchars($attachment['filename']); ?>" 
                                                           target="_blank" 
                                                           class="text-blue-400 hover:text-blue-300 transition-colors">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
    
    // Category change handler
    const categorySelect = document.getElementById('category');
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const category = this.value;
            const categories = <?php echo json_encode(TicketConfig::CATEGORIES); ?>;
            const categoryInfo = categories[category];
            
            if (categoryInfo) {
                showNotification('Category Selected', `Your ticket will be routed to the ${categoryInfo.label} department`, 'info');
            }
        });
    }
    
    // File upload validation
    const fileInput = document.getElementById('attachment');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'text/plain', 'application/pdf'];
                
                if (file.size > maxSize) {
                    showNotification('File Too Large', 'Please select a file smaller than 5MB', 'error');
                    this.value = '';
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    showNotification('Invalid File Type', 'Only images, text files, and PDFs are allowed', 'error');
                    this.value = '';
                    return;
                }
                
                showNotification('File Selected', `${file.name} (${(file.size / 1024).toFixed(1)} KB)`, 'success');
            }
        });
    }
});

// Auto-refresh ticket status every 30 seconds
setInterval(async function() {
    if (!document.hidden) {
        try {
            const response = await fetch('api/ticket_status.php');
            if (response.ok) {
                const data = await response.json();
                // Update ticket statuses if changed
                updateTicketStatuses(data);
            }
        } catch (error) {
            console.error('Failed to refresh ticket status:', error);
        }
    }
}, 30000);

function updateTicketStatuses(data) {
    // Implementation for real-time status updates
    if (data.updates && data.updates.length > 0) {
        data.updates.forEach(update => {
            showNotification('Ticket Updated', `Ticket #${update.id} status changed to ${update.status}`, 'info');
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>