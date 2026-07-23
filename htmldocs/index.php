<?php

// errors
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php-errors.log');

// Load core app
require __DIR__ . '/../app/app.php';            // core app
require_once __DIR__ . '/../app/appdata.php';        // app data variables
require_once __DIR__ . '/../app/content.php';        // content framework
require_once __DIR__ . '/../app/helpers.php';   // helper functions

$GLOBALS['app_root_path'] = dirname(__DIR__);

// Initialize app (loads config + helpers)
$app = new App($GLOBALS['app_root_path']);
$app->content = new Content($app);

// Make config available to router
$config = $app->config;

// Detect admin domain
$fullHost = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$hostParts = explode('.', $fullHost);
$host = implode('.', array_slice($hostParts, -2));
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminPath = $app->adminPath();
$adminPrefix = $adminPath === '/' ? '/' : $adminPath . '/';

$isAdminDomain = isset($config['site']['admin_domain']) && strtolower($config['site']['admin_domain']) !== '' && strtolower($config['site']['admin_domain']) === $host;

// Log access for non-admin requests
if (!$isAdminDomain) {
    $app->recordAccess($fullHost, $requestPath, $_SERVER['HTTP_REFERER'] ?? null);
}

// Run router
require __DIR__ . '/../app/router.php';
