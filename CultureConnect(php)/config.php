<?php
/**
 * config.php
 * Central configuration loader for CultureConnect.
 * Included by all main pages to setup the environment.
 */

// 1. Load Application Constants (Paths, URLs, App Name)
require_once __DIR__ . '/config/constants.php';

// 2. Load Database Connection ($pdo)
require_once __DIR__ . '/config/database.php';

// 3. Load Security Core (Handles session_start, CSRF, protection)
require_once __DIR__ . '/core/security.php';

// Optional: Set timezone if not set in php.ini
date_default_timezone_set('UTC'); 
?>