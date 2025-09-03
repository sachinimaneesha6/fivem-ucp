<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/discord.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$discord = new DiscordWebhook();

$auth->requireLogin();

// Create tickets table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS support_tickets (
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
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
)";
$db->exec($create_table_query);

$success_message = '';
$error_message = '';

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    if (!empty($subject) && !empty($message)) {
        $query = "INSERT INTO support_tickets (user_id, username, subject, message, priority) VALUES (:user_id, :username, :subject, :message, :priority)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':username', $_SESSION['username']);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':priority', $priority);
        
        if ($stmt->execute()) {
            $success_message = 'Ticket submitted successfully!';
            
            // Send Discord notification
            $ticket_id = $db->lastInsertId();
            $discord->sendTicketNotification($ticket_id, $_SESSION['username'], $subject, $priority);
        } else {
            $error_message = 'Failed to submit ticket. Please try again.';
        }
    } else {
        $error_message = 'Please fill in all required fields.';
    }
}

// Get user tickets
$tickets_query = "SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC";
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
        <h1 class="text-3xl font-bold text-white mb-2">Support Tickets</h1>
        <p class="text-gray-400">Get help from our support team</p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Create New Ticket -->
        <div class="lg:col-span-1">
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-plus text-fivem-primary mr-2"></i>Create New Ticket
                </h2>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-4">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-300 px-4 py-3 rounded-lg mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-300 mb-2">Subject</label>
                        <input type="text" id="subject" name="subject" required
                               class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-fivem-primary focus:border-transparent"
                               placeholder="Brief description of your issue">
                    </div>
                    
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                        <select id="priority" name="priority" 
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-fivem-primary focus:border-transparent">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-300 mb-2">Message</label>
                        <textarea id="message" name="message" rows="6" required
                                  class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-fivem-primary focus:border-transparent resize-none"
                                  placeholder="Describe your issue in detail..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_ticket"
                            class="w-full bg-fivem-primary hover:bg-yellow-500 text-white font-bold py-3 px-4 rounded-lg transition-all duration-200">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Ticket
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Existing Tickets -->
        <div class="lg:col-span-2">
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-ticket-alt text-blue-400 mr-2"></i>Your Tickets
                </h2>
                
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-6xl text-gray-600 mb-6"></i>
                        <h3 class="text-xl font-bold text-white mb-2">No Tickets Yet</h3>
                        <p class="text-gray-400">You haven't submitted any support tickets</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="bg-gray-700 rounded-lg border border-gray-600 p-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4">
                                    <div>
                                        <h3 class="text-white font-semibold text-lg"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                        <p class="text-gray-400 text-sm">
                                            Ticket #<?php echo $ticket['id']; ?> • 
                                            Created <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2 mt-2 sm:mt-0">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php 
                                            switch($ticket['priority']) {
                                                case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'medium': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'high': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                            }
                                            ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php 
                                            switch($ticket['status']) {
                                                case 'open': echo 'bg-green-100 text-green-800'; break;
                                                case 'in_progress': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-800 rounded-lg p-4 mb-4">
                                    <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                                </div>
                                
                                <?php if ($ticket['admin_response']): ?>
                                    <div class="bg-blue-500 bg-opacity-20 border border-blue-500 rounded-lg p-4">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-user-shield text-blue-400 mr-2"></i>
                                            <span class="text-blue-400 font-semibold">Admin Response</span>
                                        </div>
                                        <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>