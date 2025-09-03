<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/server_config.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? 'status';

switch ($action) {
    case 'status':
        $server_status = ServerConfig::getFiveMServerStatus();
        $server_info = ServerConfig::getServerInfo();
        
        // Get player count from database
        $player_query = "SELECT COUNT(*) as online_count FROM players WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $player_stmt = $db->prepare($player_query);
        $player_stmt->execute();
        $player_data = $player_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'server' => array_merge($server_status, $server_info),
            'players' => [
                'online' => (int)$player_data['online_count'],
                'max' => ServerConfig::MAX_PLAYERS
            ],
            'timestamp' => time()
        ]);
        break;
        
    case 'resources':
        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit();
        }
        
        $resources = ServerConfig::getResourceList();
        echo json_encode([
            'resources' => $resources,
            'count' => count($resources)
        ]);
        break;
        
    case 'players':
        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit();
        }
        
        $players_query = "SELECT 
            citizenid,
            JSON_EXTRACT(charinfo, '$.firstname') as firstname,
            JSON_EXTRACT(charinfo, '$.lastname') as lastname,
            JSON_EXTRACT(position, '$.x') as pos_x,
            JSON_EXTRACT(position, '$.y') as pos_y,
            last_updated
            FROM players 
            WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY last_updated DESC";
        $players_stmt = $db->prepare($players_query);
        $players_stmt->execute();
        $online_players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'players' => $online_players,
            'count' => count($online_players)
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>