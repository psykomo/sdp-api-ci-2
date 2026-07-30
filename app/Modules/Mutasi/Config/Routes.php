<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Mutasi routes — M1 golongan now; greenfield unit transfer kept for later M2 rewrite.
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    // M1 — mutasi golongan (static paths before :segment)
    $routes->get('mutasi/golongan/options', '\App\Modules\Mutasi\Controllers\Api\MutasiGolongan::options', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->get('mutasi/golongan', '\App\Modules\Mutasi\Controllers\Api\MutasiGolongan::index', [
        'filter' => 'permission:wbp.read',
    ]);
    $routes->post('mutasi/golongan', '\App\Modules\Mutasi\Controllers\Api\MutasiGolongan::create', [
        'filter' => 'permission:wbp.mutasi',
    ]);
    $routes->get('mutasi/golongan/(:segment)', '\App\Modules\Mutasi\Controllers\Api\MutasiGolongan::show/$1', [
        'filter' => 'permission:wbp.read',
    ]);

    // Greenfield unit transfer (M2 placeholder — not legacy mutasi_upt)
    $routes->post(
        'wbp/(:num)/mutasi',
        '\App\Modules\Mutasi\Controllers\Api\Mutasi::create/$1',
        ['filter' => 'permission:wbp.mutasi'],
    );
});
