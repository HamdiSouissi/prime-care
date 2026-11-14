<?php

declare(strict_types=1);

/**
 * Prime Care — Front Controller
 */

if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = __DIR__ . $uriPath;
    if ($uriPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/app/Helpers/bootstrap.php';

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

Session::start();

$router = Router::getInstance();
require dirname(__DIR__) . '/routes/web.php';
require dirname(__DIR__) . '/routes/api.php';

$request = Request::capture();

try {
    $response = $router->dispatch($request);
    $response->send();
} catch (Throwable $e) {
    $errorResponse = ErrorHandler::handleException($e, $request);
    $errorResponse->send();
}
