<?php
// scripts/check_db.php
require_once __DIR__ . '/../config/database.php';

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        echo "FAIL: PDO not initialized\n";
        exit(1);
    }

    // Quick query
    $stmt = $pdo->query("SELECT 1 as ok");
    $row = $stmt->fetch();
    if ($row && isset($row['ok'])) {
        echo "OK: database connected\n";
        exit(0);
    }

    echo "FAIL: query failed\n";
    exit(1);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
