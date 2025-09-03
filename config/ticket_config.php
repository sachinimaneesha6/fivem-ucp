<?php
class TicketConfig {
    // Ticket Categories and Departments
    const CATEGORIES = [
        'gameplay' => [
            'label' => 'Gameplay Issue',
            'department' => 'support',
            'icon' => 'fa-gamepad',
            'color' => 'blue'
        ],
        'billing' => [
            'label' => 'Billing',
            'department' => 'billing',
            'icon' => 'fa-credit-card',
            'color' => 'green'
        ],
        'ban_appeal' => [
            'label' => 'Ban Appeal',
            'department' => 'moderation',
            'icon' => 'fa-gavel',
            'color' => 'red'
        ],
        'bug_report' => [
            'label' => 'Bug Report',
            'department' => 'development',
            'icon' => 'fa-bug',
            'color' => 'purple'
        ],
        'technical' => [
            'label' => 'Technical Support',
            'department' => 'technical',
            'icon' => 'fa-tools',
            'color' => 'yellow'
        ],
        'general' => [
            'label' => 'General Inquiry',
            'department' => 'support',
            'icon' => 'fa-question-circle',
            'color' => 'gray'
        ]
    ];
    
    // Departments and Auto-Assignment
    const DEPARTMENTS = [
        'support' => [
            'label' => 'General Support',
            'staff' => ['admin', 'support_lead'],
            'auto_assign' => true
        ],
        'billing' => [
            'label' => 'Billing Department',
            'staff' => ['admin', 'billing_manager'],
            'auto_assign' => true
        ],
        'moderation' => [
            'label' => 'Moderation Team',
            'staff' => ['admin', 'head_moderator'],
            'auto_assign' => true
        ],
        'development' => [
            'label' => 'Development Team',
            'staff' => ['admin', 'lead_developer'],
            'auto_assign' => false
        ],
        'technical' => [
            'label' => 'Technical Support',
            'staff' => ['admin', 'tech_lead'],
            'auto_assign' => true
        ]
    ];
    
    // Priority Levels
    const PRIORITIES = [
        'low' => [
            'label' => 'Low',
            'color' => 'gray',
            'response_time' => 72, // hours
            'escalation_time' => 168 // 1 week
        ],
        'medium' => [
            'label' => 'Medium',
            'color' => 'blue',
            'response_time' => 24,
            'escalation_time' => 72
        ],
        'high' => [
            'label' => 'High',
            'color' => 'yellow',
            'response_time' => 8,
            'escalation_time' => 24
        ],
        'urgent' => [
            'label' => 'Urgent',
            'color' => 'red',
            'response_time' => 2,
            'escalation_time' => 8
        ]
    ];
    
    // Status System
    const STATUSES = [
        'open' => [
            'label' => 'Open',
            'color' => 'green',
            'next_statuses' => ['in_progress', 'on_hold', 'closed']
        ],
        'in_progress' => [
            'label' => 'In Progress',
            'color' => 'blue',
            'next_statuses' => ['on_hold', 'resolved', 'closed']
        ],
        'on_hold' => [
            'label' => 'On Hold',
            'color' => 'yellow',
            'next_statuses' => ['in_progress', 'resolved', 'closed']
        ],
        'resolved' => [
            'label' => 'Resolved',
            'color' => 'purple',
            'next_statuses' => ['closed', 'in_progress']
        ],
        'closed' => [
            'label' => 'Closed',
            'color' => 'gray',
            'next_statuses' => ['in_progress'] // Can reopen if needed
        ]
    ];
    
    // Canned Responses
    const CANNED_RESPONSES = [
        'cache_clear' => 'Please clear your FiveM cache and try again. You can do this by deleting the cache folder in your FiveM directory.',
        'restart_game' => 'Please restart your game and try connecting again. If the issue persists, please let us know.',
        'server_restart' => 'We are aware of this issue and will address it during the next server restart.',
        'under_investigation' => 'Thank you for reporting this issue. Our team is currently investigating and will provide an update soon.',
        'resolved_update' => 'This issue has been resolved in our latest update. Please restart your game to apply the changes.',
        'ban_appeal_review' => 'Your ban appeal has been received and is under review. We will respond within 24-48 hours.',
        'billing_processed' => 'Your billing inquiry has been processed. Please check your account for updates.',
        'feature_request' => 'Thank you for your suggestion. We have added it to our development roadmap for consideration.'
    ];
    
    // Auto-close settings
    const AUTO_CLOSE_DAYS = 7; // Days of inactivity before auto-close
    const ESCALATION_ENABLED = true;
    
    public static function getCategoryInfo($category) {
        return self::CATEGORIES[$category] ?? self::CATEGORIES['general'];
    }
    
    public static function getPriorityInfo($priority) {
        return self::PRIORITIES[$priority] ?? self::PRIORITIES['medium'];
    }
    
    public static function getStatusInfo($status) {
        return self::STATUSES[$status] ?? self::STATUSES['open'];
    }
    
    public static function getDepartmentInfo($department) {
        return self::DEPARTMENTS[$department] ?? self::DEPARTMENTS['support'];
    }
    
    public static function autoAssignTicket($category, $db) {
        $categoryInfo = self::getCategoryInfo($category);
        $department = $categoryInfo['department'];
        $departmentInfo = self::getDepartmentInfo($department);
        
        if ($departmentInfo['auto_assign']) {
            // Get available staff from department
            $staff_query = "SELECT id, username FROM user_accounts 
                           WHERE is_admin = 1 AND is_active = 1 
                           ORDER BY RAND() LIMIT 1";
            $staff_stmt = $db->prepare($staff_query);
            $staff_stmt->execute();
            
            if ($staff_stmt->rowCount() > 0) {
                $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
                return $staff['id'];
            }
        }
        
        return null;
    }
}
?>