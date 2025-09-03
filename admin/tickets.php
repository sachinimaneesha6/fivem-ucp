<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

$action_message = '';

// Handle ticket actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = $_POST['ticket_id'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $status = $_POST['status'] ?? '';
            $query = "UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $ticket_id);
            if ($stmt->execute()) {
                $action_message = 'Ticket status updated successfully';
            }
            break;
            
        case 'add_response':
            $response = $_POST['admin_response'] ?? '';
            if (!empty($response)) {
                $query = "UPDATE support_tickets SET admin_response = :response, status = 'in_progress', updated_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':response', $response);
                $stmt->bindParam(':id', $ticket_id);
                if ($stmt->execute()) {
                    $action_message = 'Response added successfully';
                }
            }
            break;
    }
}

// Get all tickets
$tickets_query = "SELECT * FROM support_tickets ORDER BY 
    CASE priority 
        WHEN 'urgent' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END, created_at DESC";
$tickets_stmt = $db->prepare($tickets_query);
$tickets_stmt->execute();
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticket stats
$stats_query = "SELECT 
    COUNT(*) as total_tickets,
    SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as open_tickets,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets
    FROM support_tickets";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$ticket_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Ticket Management';
include '../includes/header.php';
?>

<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <h1 class="text-xl font-bold text-red-400">Admin Panel</h1>
                </div>
                <div class="hidden md:ml-6 md:flex md:space-x-8">
                    <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="tickets.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-ticket-alt mr-2"></i>Tickets
                    </a>
                    <a href="analytics.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                    <a href="server.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-server mr-2"></i>Server
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="../dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to UCP
                </a>
                <a href="../logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">
            <i class="fas fa-headset text-fivem-primary mr-3"></i>Ticket Management
        </h1>
        <p class="text-gray-400">Manage and respond to user support requests</p>
    </div>
    
    <!-- Ticket Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-ticket-alt text-blue-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Total Tickets</p>
                    <p class="text-2xl font-bold text-white"><?php echo $ticket_stats['total_tickets']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-folder-open text-green-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Open Tickets</p>
                    <p class="text-2xl font-bold text-white"><?php echo $ticket_stats['open_tickets']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-gray-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-check-circle text-gray-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Closed Tickets</p>
                    <p class="text-2xl font-bold text-white"><?php echo $ticket_stats['closed_tickets']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-red-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Urgent Tickets</p>
                    <p class="text-2xl font-bold text-white"><?php echo $ticket_stats['urgent_tickets']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($action_message): ?>
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Tickets List -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-xl font-bold text-white mb-6">
            <i class="fas fa-list text-blue-400 mr-2"></i>All Tickets
        </h2>
        
        <?php if (empty($tickets)): ?>
            <div class="text-center py-12">
                <i class="fas fa-inbox text-6xl text-gray-600 mb-6"></i>
                <h3 class="text-xl font-bold text-white mb-2">No Tickets</h3>
                <p class="text-gray-400">No support tickets have been submitted yet</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="bg-gray-700 rounded-lg p-4 border border-gray-600" x-data="{ expanded: false }">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-white font-semibold"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                <p class="text-gray-400 text-sm">
                                    By: <?php echo htmlspecialchars($ticket['username']); ?> • 
                                    Ticket #<?php echo $ticket['id']; ?> • 
                                    <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                    switch($ticket['priority']) {
                                        case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                        case 'high': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'medium': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                    }
                                ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php 
                                    switch($ticket['status']) {
                                        case 'open': echo 'bg-green-100 text-green-800'; break;
                                        case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                                    }
                                ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                <button @click="expanded = !expanded" class="text-gray-400 hover:text-white transition-colors">
                                    <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': expanded }"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div x-show="expanded" x-transition class="space-y-4">
                            <!-- Message -->
                            <div class="bg-gray-800 rounded p-3">
                                <h4 class="text-white font-semibold mb-2">Message:</h4>
                                <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                                
                                <?php if (!empty($ticket['attachment_path'])): ?>
                                    <div class="mt-4 p-3 bg-gray-700 rounded-lg">
                                        <h5 class="text-white font-medium mb-2">
                                            <i class="fas fa-paperclip mr-2"></i>Attachment
                                        </h5>
                                        <div class="flex items-center">
                                            <?php 
                                            $file_ext = strtolower(pathinfo($ticket['attachment_path'], PATHINFO_EXTENSION));
                                            $file_name = basename($ticket['attachment_path']);
                                            $icon_class = match($file_ext) {
                                                'jpg', 'jpeg', 'png', 'gif' => 'fa-image text-green-400',
                                                'pdf' => 'fa-file-pdf text-red-400',
                                                'txt', 'log' => 'fa-file-alt text-blue-400',
                                                default => 'fa-file text-gray-400'
                                            };
                                            ?>
                                            <i class="fas <?php echo $icon_class; ?> mr-2"></i>
                                            <a href="../<?php echo htmlspecialchars($ticket['attachment_path']); ?>" 
                                               target="_blank" 
                                               class="text-fivem-primary hover:text-yellow-500 transition-colors font-medium">
                                                <?php echo htmlspecialchars($file_name); ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Admin Response -->
                            <?php if ($ticket['admin_response']): ?>
                                <div class="bg-blue-500 bg-opacity-20 border-l-4 border-blue-500 rounded p-3">
                                    <h4 class="text-blue-400 font-semibold mb-2">Admin Response:</h4>
                                    <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Admin Actions -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Status Update -->
                                <form method="POST" class="bg-gray-800 rounded p-3">
                                    <h4 class="text-white font-semibold mb-2">Update Status:</h4>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <div class="flex space-x-2">
                                        <select name="status" class="flex-1 bg-gray-600 border border-gray-500 text-white rounded px-3 py-2">
                                            <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                            <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                                            Update
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Add Response -->
                                <form method="POST" class="bg-gray-800 rounded p-3">
                                    <h4 class="text-white font-semibold mb-2">Add Response:</h4>
                                    <input type="hidden" name="action" value="add_response">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <textarea name="admin_response" rows="3" placeholder="Type your response..." 
                                              class="w-full bg-gray-600 border border-gray-500 text-white rounded px-3 py-2 mb-2 resize-none"></textarea>
                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded transition-colors">
                                        <i class="fas fa-reply mr-2"></i>Send Response
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>