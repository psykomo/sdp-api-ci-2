<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ---------------------------------------------------------------------
//  Web Routes (if any are needed)
// ---------------------------------------------------------------------
$routes->get('/', 'Home::index');

// ---------------------------------------------------------------------
//  API Routes — Versioned
// ---------------------------------------------------------------------

$routes->group('api', static function (RouteCollection $routes) {
    // Unversioned health-check (public)
    $routes->get('ping', 'Api\Ping::index');

    $routes->group('v1', static function (RouteCollection $routes) {
        // Public endpoints
        $routes->get('ping', 'Api\Ping::index');
        $routes->post('auth/login', 'Api\Auth::login');
        $routes->post('auth/refresh', 'Api\Auth::refresh');

        // Protected + org-scoped resources
        $routes->group('', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
            $routes->resource('users', [
                'controller' => 'Api\Users',
                'except'     => ['new', 'edit'],
            ]);

            // Feature module routes: App\Modules\*\Config\Routes.php (auto-discovered)
        });
    });
});
