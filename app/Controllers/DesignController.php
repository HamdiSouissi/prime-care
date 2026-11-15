<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Renders the "/design" living Design System showcase (admin only).
 */
final class DesignController extends Controller
{
    public function index(Request $request): Response
    {
        if ((string) \App\Core\Config::get('app.env', 'development') === 'production') {
            return Response::html(
                \App\Core\View::render('errors/404', ['pageTitle' => 'Page introuvable'], 'layouts/error'),
                404
            );
        }

        $this->authorize('design', 'view');

        return $this->view('design/index', [
            'pageTitle' => 'Design System',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'wideLayout' => true,
        ]);
    }
}
