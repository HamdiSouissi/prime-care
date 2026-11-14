<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Global error and exception handler rendering styled Prime Care error pages (403, 404, 419, 429, 500)
 * or JSON API error responses, while logging technical details to storage/logs/.
 */
final class ErrorHandler
{
    /**
     * Register PHP error, exception, and shutdown handlers.
     */
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    /**
     * Convert PHP warnings/notices into ErrorException.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle uncaught exceptions and send the appropriate HTTP response.
     */
    public static function handleException(Throwable $e, ?Request $request = null): Response
    {
        $code = (int) $e->getCode();
        $status = in_array($code, [400, 401, 403, 404, 405, 419, 422, 429, 500], true) ? $code : 500;

        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        $req = $request ?? Request::capture();
        if ($req->wantsJson()) {
            $errCode = match ($status) {
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                419 => 'CSRF_EXPIRED',
                429 => 'TOO_MANY_REQUESTS',
                default => 'SERVER_ERROR',
            };
            $msg = ($status >= 500 && !(bool) Config::get('app.debug', false))
                ? 'Une erreur interne est survenue.'
                : $e->getMessage();
            $resp = Response::jsonError($errCode, $msg, $status);
            if ($request === null) {
                $resp->send();
            }
            return $resp;
        }

        $resp = self::renderErrorPage($status, $e);
        if ($request === null) {
            $resp->send();
        }
        return $resp;
    }

    /**
     * Render a styled error page (403, 404, 419, 429, 500).
     */
    public static function renderErrorPage(int $status, ?Throwable $e = null): Response
    {
        $debug = (bool) Config::get('app.debug', false);
        $titles = [
            403 => 'Accès non autorisé',
            404 => 'Page introuvable',
            405 => 'Action non autorisée',
            419 => 'Session de sécurité expirée',
            429 => 'Trop de tentatives',
            500 => 'Oups, une erreur est survenue',
        ];
        $descriptions = [
            403 => 'Votre profil ne dispose pas des autorisations nécessaires pour consulter cet écran.',
            404 => 'L’écran que vous cherchez a été déplacé ou n’existe pas.',
            405 => 'Cette méthode de requête n’est pas permise sur cette adresse.',
            419 => 'Par mesure de sécurité, le formulaire a expiré. Veuillez actualiser la page et réessayer.',
            429 => 'Par sécurité, l’accès est temporairement limité. Veuillez patienter quelques minutes avant de réessayer.',
            500 => 'Le service a rencontré un problème inattendu. L’incident a été enregistré.',
        ];

        $viewName = in_array($status, [403, 404, 419, 429, 500], true) ? 'errors/' . $status : 'errors/500';
        $viewsDir = (string) Config::get('paths.views', dirname(__DIR__) . '/Views');

        if (is_file($viewsDir . '/' . $viewName . '.php')) {
            $html = View::render($viewName, [
                'pageTitle' => $titles[$status] ?? 'Erreur',
                'statusCode' => $status,
                'title' => $titles[$status] ?? 'Erreur',
                'description' => $descriptions[$status] ?? 'Une erreur est survenue.',
                'exception' => $debug ? $e : null,
            ], 'layouts/error');
            return Response::html($html, $status);
        }

        $fallback = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Erreur ' . $status
            . '</title></head><body><h1>' . ($titles[$status] ?? 'Erreur') . '</h1></body></html>';
        return Response::html($fallback, $status);
    }
}
