<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Ensure Windows networking environment variables are available under PHP CLI server
if (DIRECTORY_SEPARATOR === '\\') {
    $systemRoot = getenv('SystemRoot') ?: (getenv('SYSTEMROOT') ?: 'C:\\Windows');
    $_SERVER['SystemRoot'] = $systemRoot;
    $_SERVER['SYSTEMROOT'] = $systemRoot;
    $_SERVER['windir'] = $systemRoot;
    $_SERVER['WINDIR'] = $systemRoot;
    $_ENV['SystemRoot'] = $systemRoot;
    $_ENV['windir'] = $systemRoot;
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
