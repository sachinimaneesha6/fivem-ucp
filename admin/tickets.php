<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/discord.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

$discord = new DiscordWebhook();

// Handle ticket actions
$action_message = '';
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

// Get all tickets with filters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

$where_conditions = [];
$params = [];

if ($status_filter != 'all') {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

if ($priority_filter != 'all') {
    $where_conditions[] = "priority = :priority";
    $params[':priority'] = $priority_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$tickets_query = "SELECT * FROM support_tickets $where_clause ORDER BY 
    CASE priority 
        WHEN 'urgent' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END, created_at DESC";
$tickets_stmt = $db->prepare($tickets_query);
foreach ($params as $key => $value) {
    $tickets_stmt->bindValue($key, $value);
}
$tickets_stmt->execute();
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <h1 class="text-3xl font-bold text-white mb-2">Support Ticket Management</h1>
        <p class="text-gray-400">Manage and respond to user support requests</p>
    </div>
    
    <?php if ($action_message): ?>
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-300 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($action_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
        <div class="flex flex-wrap gap-4 items-center">
            <div>
                <label class="text-gray-300 text-sm font-medium mr-2">Status:</label>
                <select onchange="updateFilters()" id="status-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div>
                <label class="text-gray-300 text-sm font-medium mr-2">Priority:</label>
                <select onchange="updateFilters()" id="priority-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
                    <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Tickets -->
    <div class="space-y-6">
        <?php foreach ($tickets as $ticket): ?>
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6" x-data="{ expanded: false }">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4">
                    <div>
                        <h3 class="text-white font-semibold text-lg"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                        <p class="text-gray-400 text-sm">
                            Ticket #<?php echo $ticket['id']; ?> by <?php echo htmlspecialchars($ticket['username']); ?> â€¢ 
                            <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
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
                        <button @click="expanded = !expanded" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fas fa-chevron-down" :class="{ 'rotate-180': expanded }"></i>
                        </button>
                    </div>
                </div>
                
                <div x-show="expanded" x-transition class="space-y-4">
                    <!-- Original Message -->
                    <div class="bg-gray-700 rounded-lg p-4">
                        <h4 class="text-white font-semibold mb-2">Original Message</h4>
                        <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                        
                        <?php if (!empty($ticket['attachment_path'])): ?>
                            <div class="bg-gray-600 rounded-lg p-3 mt-3">
                                <h5 class="text-white font-semibold mb-2">
                                    <i class="fas fa-paperclip text-blue-400 mr-2"></i>Attachment
                                </h5>
                                <div class="flex items-center">
                                    <?php
                                    $attachment_path = $ticket['attachment_path'];
                                    $file_name = basename($attachment_path);
                                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    $file_icon = match($file_ext) {
                                        'jpg', 'jpeg', 'png', 'gif' => 'fa-image',
                                        'pdf' => 'fa-file-pdf', 
                                        'txt', 'log' => 'fa-file-alt',
                                        default => 'fa-file'
                                    };
                                    $file_color = match($file_ext) {
                                        'jpg', 'jpeg', 'png', 'gif' => 'text-green-400',
                                        'pdf' => 'text-red-400',
                                        'txt', 'log' => 'text-blue-400', 
                                        default => 'text-gray-400'
                                    };
                                    ?>
                                    <div class="flex items-center">
                                        <i class="fas <?php echo $file_icon; ?> <?php echo $file_color; ?> mr-2"></i>
                                        <a href="../<?php echo htmlspecialchars($attachment_path); ?>" 
                                           target="_blank" 
                                           class="text-blue-400 hover:text-blue-300 underline">
                                            <?php echo htmlspecialchars($file_name); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Admin Response -->
                    <?php if ($ticket['admin_response']): ?>
                        <div class="bg-blue-500 bg-opacity-20 border border-blue-500 rounded-lg p-4">
                            <h4 class="text-blue-400 font-semibold mb-2">
                                <i class="fas fa-user-shield mr-2"></i>Admin Response
                            </h4>
                            <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Admin Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Status Update -->
                        <form method="POST" class="bg-gray-700 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">Update Status</h4>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <div class="flex space-x-2">
                                <select name="status" class="flex-1 bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2">
                                    <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    Update
                                </button>
                            </div>
                        </form>
                        
                        <!-- Add Response -->
                        <form method="POST" class="bg-gray-700 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">Add Response</h4>
                            <input type="hidden" name="action" value="add_response">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <textarea name="admin_response" rows="3" placeholder="Type your response..." 
                                      class="w-full bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2 mb-2 resize-none"></textarea>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg transition-colors">
                                <i class="fas fa-reply mr-2"></i>Send Response
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function updateFilters() {
    const status = document.getElementById('status-filter').value;
    const priority = document.getElementById('priority-filter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('status', status);
    url.searchParams.set('priority', priority);
    
    window.location.href = url.toString();
}
</script>

<?php include '../includes/footer.php'; ?>