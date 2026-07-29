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
            $routes->get('users', 'Api\Users::index', ['filter' => 'permission:user.read']);
            $routes->get('users/(:num)', 'Api\Users::show/$1', ['filter' => 'permission:user.read']);
            $routes->post('users', 'Api\Users::create', ['filter' => 'permission:user.write']);
            $routes->put('users/(:num)', 'Api\Users::update/$1', ['filter' => 'permission:user.write']);
            $routes->patch('users/(:num)', 'Api\Users::update/$1', ['filter' => 'permission:user.write']);
            $routes->delete('users/(:num)', 'Api\Users::delete/$1', ['filter' => 'permission:user.delete']);

            // Feature module routes: App\Modules\*\Config\Routes.php (auto-discovered)
        });
    });
});
