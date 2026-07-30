<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Wbp routes — R1/R2 identitas + R3/R4/R6 registrasi.
 * Static paths (registrasi) must be registered before wbp/(:segment).
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    // R3/R4/R6 — multi-table registrasi (before wbp/(:segment))
    $routes->get('wbp/registrasi', '\App\Modules\Wbp\Controllers\Api\Registrasi::index', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->get('wbp/registrasi/(:segment)', '\App\Modules\Wbp\Controllers\Api\Registrasi::show/$1', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->post('wbp/registrasi', '\App\Modules\Wbp\Controllers\Api\Registrasi::create', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->put('wbp/registrasi/(:segment)', '\App\Modules\Wbp\Controllers\Api\Registrasi::update/$1', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->patch('wbp/registrasi/(:segment)', '\App\Modules\Wbp\Controllers\Api\Registrasi::update/$1', [
        'filter' => 'permission:wbp.write',
    ]);

    // R1/R2 identitas
    $routes->get('wbp', '\App\Modules\Wbp\Controllers\Api\Wbp::index', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->get('wbp/(:segment)', '\App\Modules\Wbp\Controllers\Api\Wbp::show/$1', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->post('wbp', '\App\Modules\Wbp\Controllers\Api\Wbp::create', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->put('wbp/(:segment)', '\App\Modules\Wbp\Controllers\Api\Wbp::update/$1', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->patch('wbp/(:segment)', '\App\Modules\Wbp\Controllers\Api\Wbp::update/$1', [
        'filter' => 'permission:wbp.write',
    ]);
    $routes->delete('wbp/(:segment)', '\App\Modules\Wbp\Controllers\Api\Wbp::delete/$1', [
        'filter' => 'permission:wbp.delete',
    ]);
});
