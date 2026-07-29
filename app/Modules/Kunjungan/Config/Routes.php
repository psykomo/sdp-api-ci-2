<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Kunjungan module routes — thin-module reference.
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->get('kunjungan', '\App\Modules\Kunjungan\Controllers\Api\Kunjungan::index', [
        'filter' => 'permission:kunjungan.read',
    ]);
    $routes->get('kunjungan/(:num)', '\App\Modules\Kunjungan\Controllers\Api\Kunjungan::show/$1', [
        'filter' => 'permission:kunjungan.read',
    ]);
    $routes->post('kunjungan', '\App\Modules\Kunjungan\Controllers\Api\Kunjungan::create', [
        'filter' => 'permission:kunjungan.write',
    ]);
    $routes->put('kunjungan/(:num)', '\App\Modules\Kunjungan\Controllers\Api\Kunjungan::update/$1', [
        'filter' => 'permission:kunjungan.write',
    ]);
    $routes->patch('kunjungan/(:num)', '\App\Modules\Kunjungan\Controllers\Api\Kunjungan::update/$1', [
        'filter' => 'permission:kunjungan.write',
    ]);
    $routes->delete('kunjungan/(:num)', '\App\Modules\Kunjungan\Controllers\Api\Kunjungan::delete/$1', [
        'filter' => 'permission:kunjungan.delete',
    ]);
});
