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

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit();
}

if (strlen($message) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long']);
    exit();
}

// Insert message
$query = "INSERT INTO ucp_chat (user_id, username, message) VALUES (:user_id, :username, :message)";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':username', $_SESSION['username']);
$stmt->bindParam(':message', $message);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message_id' => $db->lastInsertId()
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
}
?>