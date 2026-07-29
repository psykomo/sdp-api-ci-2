<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Visit module routes — thin-module reference.
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->get('visits', '\App\Modules\Visit\Controllers\Api\Visits::index', [
        'filter' => 'permission:visit.read',
    ]);
    $routes->get('visits/(:num)', '\App\Modules\Visit\Controllers\Api\Visits::show/$1', [
        'filter' => 'permission:visit.read',
    ]);
    $routes->post('visits', '\App\Modules\Visit\Controllers\Api\Visits::create', [
        'filter' => 'permission:visit.write',
    ]);
    $routes->put('visits/(:num)', '\App\Modules\Visit\Controllers\Api\Visits::update/$1', [
        'filter' => 'permission:visit.write',
    ]);
    $routes->patch('visits/(:num)', '\App\Modules\Visit\Controllers\Api\Visits::update/$1', [
        'filter' => 'permission:visit.write',
    ]);
    $routes->delete('visits/(:num)', '\App\Modules\Visit\Controllers\Api\Visits::delete/$1', [
        'filter' => 'permission:visit.delete',
    ]);
});
