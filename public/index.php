<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Normalize SCRIPT_NAME when served via root .htaccess / root index.php on XAMPP or cPanel
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if (str_ends_with($scriptName, '/public/index.php')) {
        $baseWithoutPublic = substr($scriptName, 0, -strlen('/public/index.php'));
        $publicPrefix = ($baseWithoutPublic ?: '') . '/public';
        if (!str_starts_with($requestUri, $publicPrefix . '/') && $requestUri !== $publicPrefix) {
            $_SERVER['SCRIPT_NAME'] = ($baseWithoutPublic ?: '') . '/index.php';
            $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        }
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
