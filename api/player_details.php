<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    // Check authentication
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'debug' => 'User not logged in']);
        exit();
    }

    // Check admin privileges
    if (!$auth->isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required', 'debug' => 'User is not admin']);
        exit();
    }

    $user_id = $_GET['user_id'] ?? '';
    
    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required', 'debug' => 'No user_id parameter provided']);
        exit();
    }

    // Get user account information
    $user_query = "SELECT * FROM user_accounts WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $user_id);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found', 'debug' => "No user found with ID: $user_id"]);
        exit();
    }
    
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Get all characters for this user
    $characters_query = "SELECT * FROM players WHERE license = :license ORDER BY last_updated DESC";
    $characters_stmt = $db->prepare($characters_query);
    $characters_stmt->bindParam(':license', $user_data['license']);
    $characters_stmt->execute();
    $characters = $characters_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each character's data
    $processed_characters = [];
    foreach ($characters as $character) {
        try {
            // Safely parse JSON data
            $charinfo = json_decode($character['charinfo'] ?? '{}', true) ?: [];
            $money = json_decode($character['money'] ?? '{"cash":0,"bank":0,"crypto":0}', true) ?: ['cash' => 0, 'bank' => 0, 'crypto' => 0];
            $job = json_decode($character['job'] ?? '{"name":"unemployed","label":"Civilian"}', true) ?: ['name' => 'unemployed', 'label' => 'Civilian'];
            $gang = json_decode($character['gang'] ?? '{"name":"none","label":"No Gang"}', true) ?: ['name' => 'none', 'label' => 'No Gang'];
            $metadata = json_decode($character['metadata'] ?? '{}', true) ?: [];
            $position = json_decode($character['position'] ?? '{"x":0,"y":0,"z":0}', true) ?: ['x' => 0, 'y' => 0, 'z' => 0];
            $inventory = json_decode($character['inventory'] ?? '[]', true) ?: [];

            // Get vehicles for this character
            $vehicles_query = "SELECT * FROM player_vehicles WHERE citizenid = :citizenid";
            $vehicles_stmt = $db->prepare($vehicles_query);
            $vehicles_stmt->bindParam(':citizenid', $character['citizenid']);
            $vehicles_stmt->execute();
            $vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process vehicles
            $processed_vehicles = [];
            foreach ($vehicles as $vehicle) {
                $processed_vehicles[] = [
                    'vehicle' => $vehicle['vehicle'] ?? 'Unknown',
                    'plate' => $vehicle['plate'] ?? 'Unknown',
                    'garage' => $vehicle['garage'] ?? 'Unknown',
                    'fuel' => (int)($vehicle['fuel'] ?? 100),
                    'engine' => round(($vehicle['engine'] ?? 1000) / 10),
                    'body' => round(($vehicle['body'] ?? 1000) / 10),
                    'state' => (int)($vehicle['state'] ?? 1),
                    'depotprice' => (int)($vehicle['depotprice'] ?? 0)
                ];
            }

            // Process inventory items
            $processed_inventory = [];
            foreach ($inventory as $item) {
                if (isset($item['name']) && isset($item['amount'])) {
                    $processed_inventory[] = [
                        'name' => $item['name'],
                        'amount' => (int)($item['amount'] ?? 1),
                        'slot' => (int)($item['slot'] ?? 1),
                        'type' => $item['type'] ?? 'item',
                        'info' => $item['info'] ?? []
                    ];
                }
            }

            $processed_characters[] = [
                'citizenid' => $character['citizenid'],
                'name' => ($charinfo['firstname'] ?? 'Unknown') . ' ' . ($charinfo['lastname'] ?? 'Unknown'),
                'charinfo' => [
                    'firstname' => $charinfo['firstname'] ?? 'Unknown',
                    'lastname' => $charinfo['lastname'] ?? 'Unknown',
                    'phone' => $charinfo['phone'] ?? 'N/A',
                    'nationality' => $charinfo['nationality'] ?? 'Unknown',
                    'birthdate' => $charinfo['birthdate'] ?? 'Unknown',
                    'gender' => (int)($charinfo['gender'] ?? 0),
                    'account' => $charinfo['account'] ?? 'N/A'
                ],
                'money' => [
                    'cash' => (int)($money['cash'] ?? 0),
                    'bank' => (int)($money['bank'] ?? 0),
                    'crypto' => (int)($money['crypto'] ?? 0),
                    'total' => (int)($money['cash'] ?? 0) + (int)($money['bank'] ?? 0)
                ],
                'job' => [
                    'name' => $job['name'] ?? 'unemployed',
                    'label' => $job['label'] ?? 'Civilian',
                    'grade' => $job['grade'] ?? ['name' => 'Freelancer', 'level' => 0],
                    'payment' => (int)($job['payment'] ?? 0),
                    'isboss' => (bool)($job['isboss'] ?? false),
                    'onduty' => (bool)($job['onduty'] ?? false)
                ],
                'gang' => [
                    'name' => $gang['name'] ?? 'none',
                    'label' => $gang['label'] ?? 'No Gang',
                    'grade' => $gang['grade'] ?? ['name' => 'Member', 'level' => 0],
                    'isboss' => (bool)($gang['isboss'] ?? false)
                ],
                'metadata' => [
                    'hunger' => (int)($metadata['hunger'] ?? 100),
                    'thirst' => (int)($metadata['thirst'] ?? 100),
                    'stress' => (int)($metadata['stress'] ?? 0),
                    'armor' => (int)($metadata['armor'] ?? 0),
                    'isdead' => (bool)($metadata['isdead'] ?? false),
                    'bloodtype' => $metadata['bloodtype'] ?? 'Unknown',
                    'fingerprint' => $metadata['fingerprint'] ?? 'Unknown'
                ],
                'position' => [
                    'x' => round((float)($position['x'] ?? 0), 2),
                    'y' => round((float)($position['y'] ?? 0), 2),
                    'z' => round((float)($position['z'] ?? 0), 2)
                ],
                'inventory' => $processed_inventory,
                'vehicles' => $processed_vehicles,
                'last_updated' => $character['last_updated'] ?? 'Unknown'
            ];
        } catch (Exception $e) {
            error_log("Error processing character {$character['citizenid']}: " . $e->getMessage());
            // Continue with next character instead of failing completely
        }
    }

    // Get user's support tickets
    $tickets = [];
    try {
        $tickets_query = "SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10";
        $tickets_stmt = $db->prepare($tickets_query);
        $tickets_stmt->bindParam(':user_id', $user_id);
        $tickets_stmt->execute();
        $tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching tickets: " . $e->getMessage());
        // Continue without tickets
    }

    // Calculate summary statistics
    $total_money = array_sum(array_column($processed_characters, 'money'));
    $total_vehicles = array_sum(array_map(function($char) { return count($char['vehicles']); }, $processed_characters));
    $total_items = array_sum(array_map(function($char) { return count($char['inventory']); }, $processed_characters));

    $response = [
        'success' => true,
        'user' => [
            'id' => $user_data['id'],
            'username' => $user_data['username'],
            'email' => $user_data['email'],
            'license' => $user_data['license'],
            'is_admin' => (bool)($user_data['is_admin'] ?? false),
            'is_active' => (bool)($user_data['is_active'] ?? true),
            'created_at' => $user_data['created_at'],
            'last_login' => $user_data['last_login']
        ],
        'characters' => $processed_characters,
        'tickets' => $tickets,
        'summary' => [
            'character_count' => count($processed_characters),
            'total_money' => $total_money,
            'total_vehicles' => $total_vehicles,
            'total_items' => $total_items,
            'ticket_count' => count($tickets)
        ],
        'debug' => [
            'user_id' => $user_id,
            'license' => $user_data['license'],
            'characters_found' => count($characters),
            'processed_characters' => count($processed_characters),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Player Details API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'debug' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>