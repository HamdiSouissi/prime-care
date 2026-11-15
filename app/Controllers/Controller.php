<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Base Controller providing Policy authorization checks, view rendering, and flash helpers.
 */
abstract class Controller
{
    /**
     * Enforce server-side authorization via Policy or throw a 403 exception.
     */
    protected function authorize(string $resource, string $action): void
    {
        if (!Auth::can($resource, $action)) {
            throw new RuntimeException('Vous n’avez pas les droits nécessaires pour cette action.', 403);
        }
    }

    /**
     * Render a view inside a layout and return an HTML Response.
     *
     * @param array<string, mixed> $data
     */
    protected function view(
        string $view,
        array $data = [],
        ?string $layout = 'layouts/app',
        int $status = 200
    ): Response {
        $html = View::render($view, $data, $layout);
        Session::forget('_old_input');
        Session::forget('_validation_errors');
        return Response::html($html, $status);
    }

    /**
     * Redirect to a path.
     */
    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect($path, $status);
    }

    /**
     * Redirect back to a path with flashed old input and validation errors.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $errors
     */
    protected function backWithErrors(string $path, array $input, array $errors, ?string $toastError = null): Response
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['pin'], $input['pin_confirmation']);
        Session::set('_old_input', $input);
        Session::set('_validation_errors', $errors);
        if ($toastError !== null) {
            Session::flash('toast_error', $toastError);
        }
        return Response::redirect($path);
    }
}
