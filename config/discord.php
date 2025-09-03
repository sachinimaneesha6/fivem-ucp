<?php
class DiscordWebhook {
    private $webhook_url;
    
    public function __construct($webhook_url = null) {
        $this->webhook_url = $webhook_url ?: $_ENV['DISCORD_WEBHOOK_URL'] ?? '';
    }
    
    public function sendNotification($title, $description, $color = 0x3498db, $fields = []) {
        if (empty($this->webhook_url)) {
            return false;
        }
        
        $embed = [
            'title' => $title,
            'description' => $description,
            'color' => $color,
            'timestamp' => date('c'),
            'footer' => [
                'text' => 'FiveM UCP',
                'icon_url' => 'https://via.placeholder.com/32x32/f39c12/ffffff?text=F'
            ]
        ];
        
        if (!empty($fields)) {
            $embed['fields'] = $fields;
        }
        
        $data = [
            'embeds' => [$embed]
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($this->webhook_url, false, $context);
        
        return $result !== false;
    }
    
    public function sendTicketNotification($ticket_id, $username, $subject, $priority) {
        $color = match($priority) {
            'urgent' => 0xe74c3c,
            'high' => 0xf39c12,
            'medium' => 0x3498db,
            'low' => 0x95a5a6,
            default => 0x3498db
        };
        
        $fields = [
            [
                'name' => 'Ticket ID',
                'value' => "#$ticket_id",
                'inline' => true
            ],
            [
                'name' => 'Priority',
                'value' => ucfirst($priority),
                'inline' => true
            ],
            [
                'name' => 'User',
                'value' => $username,
                'inline' => true
            ]
        ];
        
        return $this->sendNotification(
            '🎫 New Support Ticket',
            $subject,
            $color,
            $fields
        );
    }
    
    public function sendLoginNotification($username, $ip) {
        $fields = [
            [
                'name' => 'Username',
                'value' => $username,
                'inline' => true
            ],
            [
                'name' => 'IP Address',
                'value' => $ip,
                'inline' => true
            ]
        ];
        
        return $this->sendNotification(
            '🔐 Admin Login',
            'An administrator has logged into the UCP',
            0x2ecc71,
            $fields
        );
    }
}
?>