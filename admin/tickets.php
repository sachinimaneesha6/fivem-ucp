<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/discord.php';
require_once '../config/ticket_config.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

$discord = new DiscordWebhook();

// Handle advanced ticket actions
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = $_POST['ticket_id'] ?? '';
    
    try {
        $db->beginTransaction();
        
        switch ($action) {
            case 'update_status':
                $status = $_POST['status'] ?? '';
                $old_status_query = "SELECT status FROM support_tickets WHERE id = :id";
                $old_status_stmt = $db->prepare($old_status_query);
                $old_status_stmt->bindParam(':id', $ticket_id);
                $old_status_stmt->execute();
                $old_status = $old_status_stmt->fetchColumn();
                
                $query = "UPDATE support_tickets SET status = :status, updated_at = NOW(), last_activity = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $ticket_id);
                
                if ($stmt->execute()) {
                    // Log status change
                    $history_query = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, old_value, new_value, message) 
                                      VALUES (:ticket_id, :user_id, :username, 'status_change', :old_value, :new_value, :message)";
                    $history_stmt = $db->prepare($history_query);
                    $history_stmt->bindValue(':ticket_id', $ticket_id);
                    $history_stmt->bindValue(':user_id', $_SESSION['user_id']);
                    $history_stmt->bindValue(':username', $_SESSION['username']);
                    $history_stmt->bindValue(':old_value', $old_status);
                    $history_stmt->bindValue(':new_value', $status);
                    $history_stmt->bindValue(':message', "Status changed from {$old_status} to {$status}");
                    $history_stmt->execute();
                    
                    $action_message = 'Ticket status updated successfully';
                }
                break;
                
            case 'assign_ticket':
                $assigned_to = $_POST['assigned_to'] ?? '';
                $query = "UPDATE support_tickets SET assigned_to = :assigned_to, assigned_by = :assigned_by, updated_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':assigned_to', $assigned_to);
                $stmt->bindParam(':assigned_by', $_SESSION['user_id']);
                $stmt->bindParam(':id', $ticket_id);
                
                if ($stmt->execute()) {
                    // Get assigned user info
                    $user_query = "SELECT username FROM user_accounts WHERE id = :id";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':id', $assigned_to);
                    $user_stmt->execute();
                    $assigned_user = $user_stmt->fetchColumn();
                    
                    // Log assignment
                    $history_query = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, new_value, message) 
                                      VALUES (:ticket_id, :user_id, :username, 'assignment', :new_value, :message)";
                    $history_stmt = $db->prepare($history_query);
                    $history_stmt->bindValue(':ticket_id', $ticket_id);
                    $history_stmt->bindValue(':user_id', $_SESSION['user_id']);
                    $history_stmt->bindValue(':username', $_SESSION['username']);
                    $history_stmt->bindValue(':new_value', $assigned_user);
                    $history_stmt->bindValue(':message', "Ticket assigned to {$assigned_user}");
                    $history_stmt->execute();
                    
                    $action_message = 'Ticket assigned successfully';
                }
                break;
                
            case 'add_response':
                $response = $_POST['admin_response'] ?? '';
                if (!empty($response)) {
                    $query = "UPDATE support_tickets SET admin_response = :response, status = 'in_progress', updated_at = NOW(), last_activity = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':response', $response);
                    $stmt->bindParam(':id', $ticket_id);
                    
                    if ($stmt->execute()) {
                        // Log response
                        $history_query = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, message) 
                                          VALUES (:ticket_id, :user_id, :username, 'response', :message)";
                        $history_stmt = $db->prepare($history_query);
                        $history_stmt->bindValue(':ticket_id', $ticket_id);
                        $history_stmt->bindValue(':user_id', $_SESSION['user_id']);
                        $history_stmt->bindValue(':username', $_SESSION['username']);
                        $history_stmt->bindValue(':message', 'Added staff response');
                        $history_stmt->execute();
                        
                        $action_message = 'Response added successfully';
                    }
                }
                break;
                
            case 'add_internal_note':
                $note = $_POST['internal_note'] ?? '';
                if (!empty($note)) {
                    $query = "UPDATE support_tickets SET internal_notes = CONCAT(COALESCE(internal_notes, ''), :note), updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $note_with_timestamp = "[" . date('Y-m-d H:i:s') . " - " . $_SESSION['username'] . "] " . $note . "\n\n";
                    $stmt->bindParam(':note', $note_with_timestamp);
                    $stmt->bindParam(':id', $ticket_id);
                    
                    if ($stmt->execute()) {
                        // Log internal note
                        $history_query = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, message, is_internal) 
                                          VALUES (:ticket_id, :user_id, :username, 'internal_note', :message, 1)";
                        $history_stmt = $db->prepare($history_query);
                        $history_stmt->bindValue(':ticket_id', $ticket_id);
                        $history_stmt->bindValue(':user_id', $_SESSION['user_id']);
                        $history_stmt->bindValue(':username', $_SESSION['username']);
                        $history_stmt->bindValue(':message', 'Added internal note');
                        $history_stmt->execute();
                        
                        $action_message = 'Internal note added successfully';
                    }
                }
                break;
                
            case 'use_canned_response':
                $canned_key = $_POST['canned_response'] ?? '';
                $canned_responses = TicketConfig::CANNED_RESPONSES;
                
                if (isset($canned_responses[$canned_key])) {
                    $response = $canned_responses[$canned_key];
                    $query = "UPDATE support_tickets SET admin_response = :response, status = 'in_progress', updated_at = NOW(), last_activity = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':response', $response);
                    $stmt->bindParam(':id', $ticket_id);
                    
                    if ($stmt->execute()) {
                        // Log canned response
                        $history_query = "INSERT INTO ticket_history (ticket_id, user_id, username, action_type, message) 
                                          VALUES (:ticket_id, :user_id, :username, 'response', :message)";
                        $history_stmt = $db->prepare($history_query);
                        $history_stmt->bindValue(':ticket_id', $ticket_id);
                        $history_stmt->bindValue(':user_id', $_SESSION['user_id']);
                        $history_stmt->bindValue(':username', $_SESSION['username']);
                        $history_stmt->bindValue(':message', 'Used canned response: ' . $canned_key);
                        $history_stmt->execute();
                        
                        $action_message = 'Canned response sent successfully';
                    }
                }
                break;
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $action_message = 'Error: ' . $e->getMessage();
    }
}

// Get all tickets with enhanced filters and data
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$assigned_filter = $_GET['assigned'] ?? 'all';

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

if ($category_filter != 'all') {
    $where_conditions[] = "category = :category";
    $params[':category'] = $category_filter;
}

if ($assigned_filter != 'all') {
    $where_conditions[] = "assigned_to = :assigned_to";
    $params[':assigned_to'] = $assigned_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$tickets_query = "SELECT t.*, 
    ta.username as assigned_username,
    tc.username as creator_username,
    (SELECT COUNT(*) FROM ticket_attachments WHERE ticket_id = t.id) as attachment_count,
    (SELECT COUNT(*) FROM ticket_history WHERE ticket_id = t.id AND action_type = 'response') as response_count
    FROM support_tickets t 
    LEFT JOIN user_accounts ta ON t.assigned_to = ta.id 
    LEFT JOIN user_accounts tc ON t.user_id = tc.id 
    $where_clause ORDER BY 
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

// Get staff members for assignment
$staff_query = "SELECT id, username FROM user_accounts WHERE is_admin = 1 AND is_active = 1 ORDER BY username";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->execute();
$staff_members = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticket analytics
$analytics = getTicketAnalytics($db);

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
            <i class="fas fa-headset text-fivem-primary mr-3"></i>Advanced Ticket Management
        </h1>
        <p class="text-gray-400">Manage and respond to user support requests</p>
    </div>
    
    <!-- Analytics Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-ticket-alt text-blue-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Open Tickets</p>
                    <p class="text-2xl font-bold text-white"><?php echo $analytics['open_tickets']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-clock text-yellow-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Avg Response Time</p>
                    <p class="text-2xl font-bold text-white"><?php echo $analytics['avg_response_time']; ?>h</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-500 bg-opacity-20 rounded-lg">
                    <i class="fas fa-check-circle text-green-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-400">Resolved Today</p>
                    <p class="text-2xl font-bold text-white"><?php echo $analytics['resolved_today']; ?></p>
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
                    <p class="text-2xl font-bold text-white"><?php echo $analytics['urgent_tickets']; ?></p>
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
    
    <!-- Enhanced Filters -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-white">
                <i class="fas fa-filter text-blue-400 mr-2"></i>Filter Tickets
            </h3>
            <button onclick="clearFilters()" class="text-gray-400 hover:text-white text-sm transition-colors">
                <i class="fas fa-times mr-1"></i>Clear All
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="text-gray-300 text-sm font-medium block mb-2">Category:</label>
                <select onchange="updateFilters()" id="category-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
                    <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach (TicketConfig::CATEGORIES as $key => $category): ?>
                        <option value="<?php echo $key; ?>" <?php echo $category_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $category['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-gray-300 text-sm font-medium block mb-2">Status:</label>
                <select onchange="updateFilters()" id="status-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <?php foreach (TicketConfig::STATUSES as $key => $status): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $status['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-gray-300 text-sm font-medium block mb-2">Priority:</label>
                <select onchange="updateFilters()" id="priority-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
                    <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <?php foreach (TicketConfig::PRIORITIES as $key => $priority): ?>
                        <option value="<?php echo $key; ?>" <?php echo $priority_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $priority['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-gray-300 text-sm font-medium block mb-2">Assigned To:</label>
                <select onchange="updateFilters()" id="assigned-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
                    <option value="all" <?php echo $assigned_filter == 'all' ? 'selected' : ''; ?>>All Staff</option>
                    <option value="unassigned" <?php echo $assigned_filter == 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                    <?php foreach ($staff_members as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php echo $assigned_filter == $staff['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="exportTickets()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-6">
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?php echo count($tickets); ?></p>
                    <p class="text-xs text-gray-400">Showing</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-red-400"><?php echo count(array_filter($tickets, fn($t) => $t['priority'] == 'urgent')); ?></p>
                    <p class="text-xs text-gray-400">Urgent</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-yellow-400"><?php echo count(array_filter($tickets, fn($t) => $t['assigned_to'] == null)); ?></p>
                    <p class="text-xs text-gray-400">Unassigned</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="refreshTickets()" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded-lg transition-colors">
                    <i class="fas fa-sync-alt"></i>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Tickets -->
    <div class="space-y-6">
        <?php foreach ($tickets as $ticket): ?>
            <?php 
            $categoryInfo = TicketConfig::getCategoryInfo($ticket['category']);
            $priorityInfo = TicketConfig::getPriorityInfo($ticket['priority']);
            $statusInfo = TicketConfig::getStatusInfo($ticket['status']);
            ?>
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 hover:border-gray-600 transition-all" x-data="{ expanded: false, showInternalNotes: false }">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4">
                    <div>
                        <div class="flex items-center mb-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 bg-<?php echo $categoryInfo['color']; ?>-500 bg-opacity-20">
                                <i class="fas <?php echo $categoryInfo['icon']; ?> text-<?php echo $categoryInfo['color']; ?>-400"></i>
                            </div>
                            <h3 class="text-white font-semibold text-lg"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-400">
                            <span class="flex items-center">
                                <i class="fas fa-hashtag mr-1"></i>
                                #<?php echo $ticket['id']; ?>
                            </span>
                            <span>•</span>
                            <span class="flex items-center">
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($ticket['username']); ?>
                            </span>
                            <span>•</span>
                            <span class="flex items-center">
                                <i class="fas fa-calendar mr-1"></i>
                                <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                            </span>
                            <?php if ($ticket['assigned_username']): ?>
                                <span>•</span>
                                <span class="flex items-center text-blue-400">
                                    <i class="fas fa-user-tie mr-1"></i>
                                    <?php echo htmlspecialchars($ticket['assigned_username']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-4 sm:mt-0">
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
                        <?php if ($ticket['attachment_count'] > 0): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                <i class="fas fa-paperclip mr-1"></i>
                                <?php echo $ticket['attachment_count']; ?>
                            </span>
                        <?php endif; ?>
                        <button @click="expanded = !expanded" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': expanded }"></i>
                        </button>
                    </div>
                </div>
                
                <div x-show="expanded" x-transition class="space-y-4">
                    <!-- Original Message -->
                    <div class="bg-gray-700 rounded-lg p-4 border-l-4 border-<?php echo $categoryInfo['color']; ?>-500">
                        <h4 class="text-white font-semibold mb-2">
                            <i class="fas fa-comment-dots mr-2"></i>Original Message
                        </h4>
                        <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                    </div>
                    
                    <!-- Attachments -->
                    <?php
                    $attachments_query = "SELECT * FROM ticket_attachments WHERE ticket_id = :ticket_id ORDER BY uploaded_at DESC";
                    $attachments_stmt = $db->prepare($attachments_query);
                    $attachments_stmt->bindValue(':ticket_id', $ticket['id']);
                    $attachments_stmt->execute();
                    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (!empty($attachments)): ?>
                        <div class="bg-gray-700 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">
                                <i class="fas fa-paperclip mr-2"></i>Attachments
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="flex items-center p-3 bg-gray-600 rounded-lg">
                                        <div class="w-10 h-10 bg-blue-500 bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-<?php echo strpos($attachment['mime_type'], 'image') !== false ? 'image' : 'file'; ?> text-blue-400"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-white text-sm font-medium truncate"><?php echo htmlspecialchars($attachment['original_name']); ?></p>
                                            <p class="text-gray-400 text-xs"><?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB</p>
                                        </div>
                                        <a href="../uploads/tickets/<?php echo htmlspecialchars($attachment['filename']); ?>" 
                                           target="_blank" 
                                           class="text-blue-400 hover:text-blue-300 transition-colors">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Admin Response -->
                    <?php if ($ticket['admin_response']): ?>
                        <div class="bg-blue-500 bg-opacity-20 border-l-4 border-blue-500 rounded-lg p-4">
                            <h4 class="text-blue-400 font-semibold mb-2">
                                <i class="fas fa-user-shield mr-2"></i>Admin Response
                            </h4>
                            <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($ticket['admin_response'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Internal Notes (Staff Only) -->
                    <?php if ($ticket['internal_notes']): ?>
                        <div class="bg-red-500 bg-opacity-10 border-l-4 border-red-500 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-red-400 font-semibold">
                                    <i class="fas fa-eye-slash mr-2"></i>Internal Notes (Staff Only)
                                </h4>
                                <button @click="showInternalNotes = !showInternalNotes" class="text-red-400 hover:text-red-300">
                                    <i class="fas fa-eye" x-show="!showInternalNotes"></i>
                                    <i class="fas fa-eye-slash" x-show="showInternalNotes"></i>
                                </button>
                            </div>
                            <div x-show="showInternalNotes" x-transition>
                                <pre class="text-gray-300 text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['internal_notes']); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Enhanced Admin Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Assignment -->
                        <form method="POST" class="bg-gray-700 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">
                                <i class="fas fa-user-tie mr-2"></i>Assignment
                            </h4>
                            <input type="hidden" name="action" value="assign_ticket">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <div class="flex space-x-2">
                                <select name="assigned_to" class="flex-1 bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>" <?php echo $ticket['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($staff['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    Assign
                                </button>
                            </div>
                        </form>
                        
                        <!-- Status Update -->
                        <form method="POST" class="bg-gray-700 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">
                                <i class="fas fa-tasks mr-2"></i>Update Status
                            </h4>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <div class="flex space-x-2">
                                <select name="status" class="flex-1 bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2">
                                    <?php foreach (TicketConfig::STATUSES as $key => $status): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $ticket['status'] == $key ? 'selected' : ''; ?>>
                                            <?php echo $status['label']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    Update
                                </button>
                            </div>
                        </form>
                        
                        <!-- Add Response -->
                        <form method="POST" class="bg-gray-700 rounded-lg p-4">
                            <h4 class="text-white font-semibold mb-3">
                                <i class="fas fa-reply mr-2"></i>Add Response
                            </h4>
                            <input type="hidden" name="action" value="add_response">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <div class="mb-2">
                                <select onchange="insertCannedResponse(this, <?php echo $ticket['id']; ?>)" class="w-full bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2 mb-2">
                                    <option value="">Select canned response...</option>
                                    <?php foreach (TicketConfig::CANNED_RESPONSES as $key => $response): ?>
                                        <option value="<?php echo $key; ?>"><?php echo ucwords(str_replace('_', ' ', $key)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <textarea name="admin_response" id="response_<?php echo $ticket['id']; ?>" rows="3" placeholder="Type your response..." 
                                      class="w-full bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2 mb-2 resize-none"></textarea>
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg transition-colors">
                                <i class="fas fa-reply mr-2"></i>Send Response
                            </button>
                        </form>
                        
                        <!-- Internal Notes -->
                        <form method="POST" class="bg-gray-700 rounded-lg p-4 md:col-span-2 lg:col-span-1">
                            <h4 class="text-white font-semibold mb-3">
                                <i class="fas fa-sticky-note mr-2"></i>Internal Note
                            </h4>
                            <input type="hidden" name="action" value="add_internal_note">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <textarea name="internal_note" rows="3" placeholder="Staff-only note..." 
                                      class="w-full bg-gray-600 border border-gray-500 text-white rounded-lg px-3 py-2 mb-2 resize-none"></textarea>
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg transition-colors">
                                <i class="fas fa-eye-slash mr-2"></i>Add Note
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function insertCannedResponse(select, ticketId) {
    if (select.value) {
        const responses = <?php echo json_encode(TicketConfig::CANNED_RESPONSES); ?>;
        const textarea = document.getElementById(`response_${ticketId}`);
        if (textarea && responses[select.value]) {
            textarea.value = responses[select.value];
            select.value = '';
        }
    }
}

function updateFilters() {
    const category = document.getElementById('category-filter').value;
    const status = document.getElementById('status-filter').value;
    const priority = document.getElementById('priority-filter').value;
    const assigned = document.getElementById('assigned-filter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('category', category);
    url.searchParams.set('status', status);
    url.searchParams.set('priority', priority);
    url.searchParams.set('assigned', assigned);
    
    window.location.href = url.toString();
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

function refreshTickets() {
    showNotification('Refreshing', 'Updating ticket list...', 'info');
    setTimeout(() => {
        location.reload();
    }, 1000);
}

function exportTickets() {
    showNotification('Export', 'Preparing ticket export...', 'info');
    // Implementation for CSV/PDF export
}
</script>

<?php
function getTicketAnalytics($db) {
    $analytics = [];
    
    // Open tickets
    $open_query = "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'in_progress')";
    $open_stmt = $db->prepare($open_query);
    $open_stmt->execute();
    $analytics['open_tickets'] = $open_stmt->fetchColumn();
    
    // Average response time (in hours)
    $response_query = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) FROM support_tickets WHERE admin_response IS NOT NULL";
    $response_stmt = $db->prepare($response_query);
    $response_stmt->execute();
    $analytics['avg_response_time'] = round($response_stmt->fetchColumn() ?? 0, 1);
    
    // Resolved today
    $resolved_query = "SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()";
    $resolved_stmt = $db->prepare($resolved_query);
    $resolved_stmt->execute();
    $analytics['resolved_today'] = $resolved_stmt->fetchColumn();
    
    // Urgent tickets
    $urgent_query = "SELECT COUNT(*) FROM support_tickets WHERE priority = 'urgent' AND status NOT IN ('resolved', 'closed')";
    $urgent_stmt = $db->prepare($urgent_query);
    $urgent_stmt->execute();
    $analytics['urgent_tickets'] = $urgent_stmt->fetchColumn();
    
    return $analytics;
}
?>

<?php include '../includes/footer.php'; ?>