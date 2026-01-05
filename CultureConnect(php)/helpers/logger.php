<?php
declare(strict_types=1);
/**
 * Minimal application logger for CultureConnect
 * - Writes to /logs/log-YYYY-MM-DD.log
 * - Does not output details to users
 */

if (!defined('CC_LOGGER_LOADED')) {
    define('CC_LOGGER_LOADED', true);

    $cc_logs_dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($cc_logs_dir)) {
        @mkdir($cc_logs_dir, 0750, true);
    }

    function cc_log(string $level, string $message, array $context = []): void
    {
        global $cc_logs_dir;
        try {
            $date = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $fileDate = (new DateTimeImmutable())->format('Y-m-d');
            $contextStr = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
            $line = sprintf("[%s] [%s] %s%s%s", $date, strtoupper($level), $message, $contextStr, PHP_EOL);
            $logfile = realpath($cc_logs_dir) ? rtrim(realpath($cc_logs_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "log-{$fileDate}.log" : null;
            if ($logfile && @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
            // fallback to PHP error log
            error_log($line);
        } catch (Throwable $e) {
            // never throw from logger
            error_log('Logger failure: ' . $e->getMessage());
        }
    }

    function log_info(string $message, array $context = []): void { cc_log('info', $message, $context); }
    function log_error(string $message, array $context = []): void { cc_log('error', $message, $context); }
    function log_warning(string $message, array $context = []): void { cc_log('warning', $message, $context); }
    function log_debug(string $message, array $context = []): void { cc_log('debug', $message, $context); }

    // PSR-like helper
    if (!function_exists('logger')) {
        function logger(): callable {
            return function(string $level, string $msg, array $ctx = []) { cc_log($level, $msg, $ctx); };
        }
    }
}

// Small PSR-like Logger class for compatibility with code using Logger::warn()
if (!class_exists('Logger')) {
    class Logger {
        public static function log(string $level, string $message, array $context = []) {
            cc_log($level, $message, $context);
        }
        public static function info(string $message, array $context = []) { cc_log('info', $message, $context); }
        public static function warn(string $message, array $context = []) { cc_log('warning', $message, $context); }
        public static function error(string $message, array $context = []) { cc_log('error', $message, $context); }
        public static function debug(string $message, array $context = []) { cc_log('debug', $message, $context); }
    }
}

