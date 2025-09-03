<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$ticket_id = $_POST['ticket_id'] ?? '';

if (empty($ticket_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ticket ID required']);
    exit();
}

// Mark ticket as viewed by user
$query = "UPDATE support_tickets 
          SET last_viewed_by_user = NOW(), is_new = 0 
          WHERE id = :ticket_id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':ticket_id', $ticket_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Ticket marked as read'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update ticket']);
}
?>