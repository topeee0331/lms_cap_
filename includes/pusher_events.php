<?php
// Pusher Events Handler
require_once 'vendor/autoload.php';

use Pusher\Pusher;

class PusherEvents {
    private $pusher;
    
    public function __construct() {
        $config = require_once 'config/pusher_config.php';
        
        $this->pusher = new Pusher(
            $config['app_key'],
            $config['app_secret'],
            $config['app_id'],
            $config['options']
        );
    }
    
    public function trigger($channel, $event, $data) {
        return $this->pusher->trigger($channel, $event, $data);
    }
    
    public function authenticate($socket_id, $channel) {
        return $this->pusher->socket_auth($channel, $socket_id);
    }
}

