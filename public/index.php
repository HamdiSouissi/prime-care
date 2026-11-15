<?php

declare(strict_types=1);

/**
 * Prime Care — Front Controller
 */

if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = realpath(__DIR__ . $uriPath);
    $publicRoot = realpath(__DIR__);
    if (
        $uriPath !== '/'
        && $staticFile !== false
        && $publicRoot !== false
        && str_starts_with($staticFile, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($staticFile)
    ) {
        $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'mjs' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'webmanifest' => 'application/manifest+json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'html' => 'text/html; charset=utf-8',
        ];
        if (!isset($mimeMap[$ext])) {
            return false;
        }
        header('Content-Type: ' . $mimeMap[$ext]);
        header('X-Content-Type-Options: nosniff');
        if ( basename($staticFile) === 'sw.js') {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Service-Worker-Allowed: /');
        } elseif (str_starts_with($uriPath, '/assets/')) {
            header('Cache-Control: public, max-age=31536000, immutable');
        } else {
            header('Cache-Control: public, max-age=3600');
        }

        $compressible = in_array($ext, ['css', 'js', 'mjs', 'json', 'webmanifest', 'svg', 'html'], true);
        $acceptEnc = (string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        $raw = (string) file_get_contents($staticFile);
        if ($compressible && str_contains($acceptEnc, 'gzip') && function_exists('gzencode')) {
            $gz = gzencode($raw, 6);
            if ($gz !== false) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                header('Content-Length: ' . strlen($gz));
                echo $gz;
                return true;
            }
        }
        header('Content-Length: ' . strlen($raw));
        echo $raw;
        return true;
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
