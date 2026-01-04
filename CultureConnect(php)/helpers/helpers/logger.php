<?php
/**
 * Logger Helper for CultureConnect
 * Logs security events without sensitive data
 */

class Logger {
    
    private static $log_dir = __DIR__ . '/../logs';
    
    public static function init() {
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
    }
    
    /**
     * Log an event with context (no sensitive data)
     */
    public static function log($event_type, $message, $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $log_file = self::$log_dir . '/' . date('Y-m-d') . '.log';
        
        $log_entry = [
            'timestamp' => $timestamp,
            'event' => $event_type,
            'message' => $message,
            'context' => $context,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $log_line = json_encode($log_entry) . "\n";
        error_log($log_line, 3, $log_file);
    }
    
    /**
     * Log a warning
     */
    public static function warn($event_type, $message, $context = []) {
        self::log("WARN_{$event_type}", $message, $context);
    }
    
    /**
     * Log an error
     */
    public static function error($event_type, $message, $context = []) {
        self::log("ERROR_{$event_type}", $message, $context);
    }
}

// Initialize on include
Logger::init();
?>


---


