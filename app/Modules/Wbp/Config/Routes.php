<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Wbp module routes — mounted under /api/v1 with auth + org scope.
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->get('wbp', '\App\Modules\Wbp\Controllers\Api\Wbp::index', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->get('wbp/(:num)', '\App\Modules\Wbp\Controllers\Api\Wbp::show/$1', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->post('wbp', '\App\Modules\Wbp\Controllers\Api\Wbp::create', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->put('wbp/(:num)', '\App\Modules\Wbp\Controllers\Api\Wbp::update/$1', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->patch('wbp/(:num)', '\App\Modules\Wbp\Controllers\Api\Wbp::update/$1', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->delete('wbp/(:num)', '\App\Modules\Wbp\Controllers\Api\Wbp::delete/$1', [
        'filter' => 'permission:wbp.delete',
    ]);

    // Business process: release (pembebasan). Own controller + permission.
    $routes->post('wbp/(:num)/pembebasan', '\App\Modules\Wbp\Controllers\Api\WbpReleases::create/$1', [
        'filter' => 'permission:wbp.release',
    ]);
});
