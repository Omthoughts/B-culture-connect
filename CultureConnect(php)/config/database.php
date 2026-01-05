<?php
declare(strict_types=1);
/*
 * config/database.php
 * Environment-aware PDO bootstrap. Reads .env if present, otherwise falls back to getenv().
 * Replace .env with secure secrets in production and keep out of VCS.
 */

$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            // Handle quoted values in .env
            if (preg_match('/^"(.*)"$/', $v, $matches)) {
                $v = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $v, $matches)) {
                $v = $matches[1];
            }
            $env[$k] = $v;
        }
    }
}

$dbHost = $env['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$dbName = $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'cultureconnect';
$dbUser = $env['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$dbPass = $env['DB_PASS'] ?? getenv('DB_PASS') ?: '';
$dbPort = $env['DB_PORT'] ?? getenv('DB_PORT') ?: 3306;

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // Log detailed error for developers
    error_log('DB connection failed: ' . $e->getMessage());
    // Provide a generic error message for users
    http_response_code(500);
    die('We are experiencing technical difficulties. Please try again later.');
}

?>
