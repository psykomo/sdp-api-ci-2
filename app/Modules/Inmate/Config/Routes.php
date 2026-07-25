<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Inmate module routes — mounted under /api/v1 with auth + org scope.
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->get('inmates', '\App\Modules\Inmate\Controllers\Api\Inmates::index', [
        'filter' => 'permission:inmate.read',
    ]);
    $routes->get('inmates/(:num)', '\App\Modules\Inmate\Controllers\Api\Inmates::show/$1', [
        'filter' => 'permission:inmate.read',
    ]);
    $routes->post('inmates', '\App\Modules\Inmate\Controllers\Api\Inmates::create', [
        'filter' => 'permission:inmate.write',
    ]);
    $routes->put('inmates/(:num)', '\App\Modules\Inmate\Controllers\Api\Inmates::update/$1', [
        'filter' => 'permission:inmate.write',
    ]);
    $routes->patch('inmates/(:num)', '\App\Modules\Inmate\Controllers\Api\Inmates::update/$1', [
        'filter' => 'permission:inmate.write',
    ]);
    $routes->delete('inmates/(:num)', '\App\Modules\Inmate\Controllers\Api\Inmates::delete/$1', [
        'filter' => 'permission:inmate.delete',
    ]);
});
