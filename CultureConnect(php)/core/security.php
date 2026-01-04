<?php
/**
 * Core security loader and global helpers
 * This file intentionally exposes a few small global helper functions that
 * wrap the SecurityManager. Each helper is declared inside a
 * `if (!function_exists(...))` guard to avoid redeclaration issues when
 * files are included multiple times or in unit tests.
 */

require_once __DIR__ . '/../SecurityManager.php';

// security() -> returns the singleton SecurityManager instance
if (!function_exists('security')) {
    function security() {
        return \Core\Security\SecurityManager::getInstance();
    }
}

// e() -> HTML-escape helper
if (!function_exists('e')) {
    function e($value) {
        return security()->escape($value);
    }
}

// csrf_field() -> returns a hidden input field with a CSRF token
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return security()->csrfField();
    }
}

// csrf_token() -> returns token string
if (!function_exists('csrf_token')) {
    function csrf_token() {
        return security()->generateCSRFToken();
    }
}

// get_client_ip() -> consistent client IP helper
if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        return security()->getClientIP();
    }
}

// Provide a short alias for convenience in templates
if (!function_exists('csrf')) {
    function csrf() {
        return csrf_field();
    }
}

// No closing PHP tag to avoid accidental output
