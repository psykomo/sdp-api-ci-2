<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->post(
        'wbp/(:num)/mutasi',
        '\App\Modules\Mutasi\Controllers\Api\Mutasi::create/$1',
        ['filter' => 'permission:wbp.mutasi'],
    );
});
