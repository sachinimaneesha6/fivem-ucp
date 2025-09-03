<?php
class ServerConfig {
    // FiveM Server Configuration
    const FIVEM_SERVER_IP = '127.0.0.1';
    const FIVEM_SERVER_PORT = 30120;
    const MAX_PLAYERS = 64;
    
    // Discord Integration
    const DISCORD_WEBHOOK_URL = ''; // Add your Discord webhook URL here
    const DISCORD_BOT_TOKEN = ''; // Optional: For advanced Discord integration
    
    // Server Monitoring
    const ENABLE_REAL_TIME_MONITORING = true;
    const MONITORING_INTERVAL = 30; // seconds
    
    // Map Configuration
    const GTA_MAP_IMAGE_URL = 'https://i.imgur.com/KNIH6Ej.png';
    const ENABLE_LOCATION_TRACKING = true;
    
    // Performance Thresholds
    const CPU_WARNING_THRESHOLD = 80;
    const MEMORY_WARNING_THRESHOLD = 85;
    const DISK_WARNING_THRESHOLD = 90;
    
    public static function getFiveMServerStatus() {
        $server_ip = self::FIVEM_SERVER_IP;
        $server_port = self::FIVEM_SERVER_PORT;
        
        // Try to connect to FiveM server
        $connection = @fsockopen($server_ip, $server_port, $errno, $errstr, 5);
        
        if ($connection) {
            fclose($connection);
            return [
                'online' => true,
                'ip' => $server_ip,
                'port' => $server_port
            ];
        }
        
        return [
            'online' => false,
            'ip' => $server_ip,
            'port' => $server_port,
            'error' => $errstr
        ];
    }
    
    public static function getServerInfo() {
        // You can implement FiveM server info API call here
        // This would typically call your FiveM server's info endpoint
        return [
            'name' => 'Your FiveM Server',
            'description' => 'QBCore Roleplay Server',
            'version' => 'QBCore Framework',
            'website' => 'https://yourserver.com',
            'discord' => 'https://discord.gg/yourserver'
        ];
    }
    
    public static function getResourceList() {
        // You can implement this to get actual resource list from your server
        return [
            'qb-core', 'qb-multicharacter', 'qb-spawn', 'qb-apartments',
            'qb-garages', 'qb-vehicleshop', 'qb-banking', 'qb-phone',
            'qb-inventory', 'qb-weapons', 'qb-drugs', 'qb-jobs'
        ];
    }
}
?>