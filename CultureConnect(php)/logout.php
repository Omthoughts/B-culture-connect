<?php
session_start();
require_once __DIR__ . '/core/security.php';

// Destroy session via security manager for consistency
security()->destroySession();

// Redirect to home with gentle closure flag
header('Location: /index.php?energy=renewed', true, 303);
exit;
