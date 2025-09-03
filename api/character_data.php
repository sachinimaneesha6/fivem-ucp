<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/auth.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    // Check authentication
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    // Check admin privileges
    if (!$auth->isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit();
    }

    $citizenid = $_GET['citizenid'] ?? '';
    
    if (empty($citizenid)) {
        http_response_code(400);
        echo json_encode(['error' => 'Citizenid required']);
        exit();
    }

    // Get character data
    $character_query = "SELECT * FROM players WHERE citizenid = :citizenid";
    $character_stmt = $db->prepare($character_query);
    $character_stmt->bindParam(':citizenid', $citizenid);
    $character_stmt->execute();
    
    if ($character_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Character not found']);
        exit();
    }
    
    $character = $character_stmt->fetch(PDO::FETCH_ASSOC);

    // Safely parse JSON data
    $charinfo = json_decode($character['charinfo'] ?? '{}', true) ?: [];
    $money = json_decode($character['money'] ?? '{"cash":0,"bank":0,"crypto":0}', true) ?: ['cash' => 0, 'bank' => 0, 'crypto' => 0];
    $job = json_decode($character['job'] ?? '{"name":"unemployed","label":"Civilian"}', true) ?: ['name' => 'unemployed', 'label' => 'Civilian'];
    $gang = json_decode($character['gang'] ?? '{"name":"none","label":"No Gang"}', true) ?: ['name' => 'none', 'label' => 'No Gang'];
    $metadata = json_decode($character['metadata'] ?? '{}', true) ?: [];
    $position = json_decode($character['position'] ?? '{"x":0,"y":0,"z":0}', true) ?: ['x' => 0, 'y' => 0, 'z' => 0];
    $inventory = json_decode($character['inventory'] ?? '[]', true) ?: [];

    // Get vehicles for this character
    $vehicles = [];
    try {
        $vehicles_query = "SELECT * FROM player_vehicles WHERE citizenid = :citizenid";
        $vehicles_stmt = $db->prepare($vehicles_query);
        $vehicles_stmt->bindParam(':citizenid', $citizenid);
        $vehicles_stmt->execute();
        $vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching vehicles: " . $e->getMessage());
    }

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

    $response = [
        'success' => true,
        'character' => [
            'citizenid' => $character['citizenid'],
            'name' => $character['name'],
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
            'last_updated' => $character['last_updated']
        ],
        'vehicles' => $processed_vehicles
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Character Data API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
?>