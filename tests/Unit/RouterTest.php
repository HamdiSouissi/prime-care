<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use RuntimeException;

final class RouterTest
{
    public static function run(callable $assert): void
    {
        $router = new Router();

        $router->get('/users/{id:\d+}', static function (Request $req, string $id): Response {
            return Response::json(['user_id' => (int) $id]);
        }, 'users.show');

        $router->group(['prefix' => '/api/v1'], static function (Router $r): void {
            $r->post('/ping', static function (): Response {
                return Response::json(['pong' => true]);
            }, 'api.ping');
        });

        // 1. Named route URL generation
        $url = $router->routeUrl('users.show', ['id' => 42]);
        $assert($url === '/users/42', 'Router generates named route URL with parameter');

        // 2. Dispatch matching GET route with numeric constraint
        $req = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']);
        $res = $router->dispatch($req);
        $assert($res->statusCode() === 200, 'Router dispatches matching GET route with 200');
        $assert(str_contains($res->body(), '"user_id":42'), 'Router passes extracted parameter to handler');

        // 3. Constraint mismatch -> 404
        $reqInvalid = new Request([], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/users/abc',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $resInvalid = $router->dispatch($reqInvalid);
        $assert($resInvalid->statusCode() === 404, 'Router returns 404 when regex constraint fails');

        // 4. Wrong HTTP verb -> 405
        $reqWrongVerb = new Request([], [], [], [
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/users/42',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $resWrongVerb = $router->dispatch($reqWrongVerb);
        $assert($resWrongVerb->statusCode() === 405, 'Router returns 405 when method is not allowed');

        // 5. Group prefix dispatch
        $reqGroup = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/ping']);
        $resGroup = $router->dispatch($reqGroup);
        $assert($resGroup->statusCode() === 200 && str_contains($resGroup->body(), '"pong":true'), 'Router dispatches grouped prefix route');
    }
}
