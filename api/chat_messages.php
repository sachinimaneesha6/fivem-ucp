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

$since_id = $_GET['since'] ?? 0;

// Get new messages since the last ID
$query = "SELECT * FROM ucp_chat WHERE id > :since_id ORDER BY created_at ASC LIMIT 20";
$stmt = $db->prepare($query);
$stmt->bindParam(':since_id', $since_id, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
?>