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

$user_id = $_SESSION['user_id'];
$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

// Get ticket updates since last check
$updates_query = "SELECT id, status, updated_at FROM support_tickets 
                  WHERE user_id = :user_id AND updated_at > :last_check";
$updates_stmt = $db->prepare($updates_query);
$updates_stmt->bindValue(':user_id', $user_id);
$updates_stmt->bindValue(':last_check', $last_check);
$updates_stmt->execute();
$updates = $updates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get new responses
$responses_query = "SELECT ticket_id FROM ticket_history 
                    WHERE ticket_id IN (SELECT id FROM support_tickets WHERE user_id = :user_id) 
                    AND action_type = 'response' 
                    AND created_at > :last_check";
$responses_stmt = $db->prepare($responses_query);
$responses_stmt->bindValue(':user_id', $user_id);
$responses_stmt->bindValue(':last_check', $last_check);
$responses_stmt->execute();
$new_responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'updates' => $updates,
    'new_responses' => $new_responses,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>