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

$character_id = $_GET['character'] ?? '';

if (empty($character_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Character ID required']);
    exit();
}

// Get character position
$query = "SELECT position FROM players WHERE citizenid = :citizenid AND license = :license";
$stmt = $db->prepare($query);
$stmt->bindParam(':citizenid', $character_id);
$stmt->bindParam(':license', $_SESSION['license']);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $position = json_decode($result['position'], true);
    
    echo json_encode([
        'success' => true,
        'position' => $position
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Character not found']);
}
?>