<?php
/**
 * Pusher Configuration
 * Real-time notifications for LMS
 */

class PusherConfig {
    private static $pusher = null;
    private static $isAvailable = null;
    
    // Pusher configuration - you'll need to get these from your Pusher dashboard
    private static $config = [
        'app_id' => '1987729',           // Replace with your Pusher App ID
        'app_key' => 'e6ceab7df8ffc96f7931',         // Replace with your Pusher App Key
        'app_secret' => '8122463c84195db4d32c',   // Replace with your Pusher App Secret
        'cluster' => 'eu',         // Replace with your cluster (e.g., 'us2', 'eu', 'ap1')
        'useTLS' => true,
        'encrypted' => true
    ];
    
    /**
     * Check if Pusher is available
     */
    private static function checkAvailability() {
        if (self::$isAvailable === null) {
            try {
                // Try to load autoloader
                $autoloadPath = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoloadPath)) {
                    require_once $autoloadPath;
                }
                
                // Check if Pusher class exists
                if (class_exists('Pusher\Pusher')) {
                    self::$isAvailable = true;
                } else {
                    self::$isAvailable = false;
                    error_log("Pusher class not found - Pusher integration disabled");
                }
            } catch (Exception $e) {
                self::$isAvailable = false;
                error_log("Failed to check Pusher availability: " . $e->getMessage());
            }
        }
        return self::$isAvailable;
    }
    
    /**
     * Get Pusher instance (singleton pattern)
     */
    public static function getInstance() {
        if (!self::checkAvailability()) {
            return null;
        }
        
        if (self::$pusher === null) {
            try {
                self::$pusher = new Pusher\Pusher(
                    self::$config['app_key'],
                    self::$config['app_secret'],
                    self::$config['app_id'],
                    [
                        'cluster' => self::$config['cluster'],
                        'useTLS' => self::$config['useTLS'],
                        'encrypted' => self::$config['encrypted']
                    ]
                );
            } catch (Exception $e) {
                error_log("Failed to initialize Pusher: " . $e->getMessage());
                self::$isAvailable = false;
                return null;
            }
        }
        return self::$pusher;
    }
    
    /**
     * Get Pusher configuration for JavaScript
     */
    public static function getConfig() {
        return [
            'app_key' => self::$config['app_key'],
            'cluster' => self::$config['cluster'],
            'available' => self::checkAvailability()
        ];
    }
    
    /**
     * Send notification to specific user
     */
    public static function sendNotification($userId, $data) {
        if (!self::checkAvailability()) {
            return false;
        }
        
        try {
            $pusher = self::getInstance();
            if ($pusher === null) {
                return false;
            }
            
            $channel = 'user-' . $userId;
            $result = $pusher->trigger($channel, 'notification', $data);
            return $result;
        } catch (Exception $e) {
            error_log("Pusher error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to all users (admin announcements)
     */
    public static function sendToAll($data) {
        if (!self::checkAvailability()) {
            return false;
        }
        
        try {
            $pusher = self::getInstance();
            if ($pusher === null) {
                return false;
            }
            
            $result = $pusher->trigger('notifications', 'announcement', $data);
            return $result;
        } catch (Exception $e) {
            error_log("Pusher error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to specific role (students, teachers, admins)
     */
    public static function sendToRole($role, $data) {
        if (!self::checkAvailability()) {
            return false;
        }
        
        try {
            $pusher = self::getInstance();
            if ($pusher === null) {
                return false;
            }
            
            $channel = 'role-' . $role;
            $result = $pusher->trigger($channel, 'notification', $data);
            return $result;
        } catch (Exception $e) {
            error_log("Pusher error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if Pusher is working
     */
    public static function isAvailable() {
        return self::checkAvailability();
    }
}
