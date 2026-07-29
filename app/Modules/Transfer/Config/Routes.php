<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->post(
        'inmates/(:num)/transfers',
        '\App\Modules\Transfer\Controllers\Api\Transfers::create/$1',
        ['filter' => 'permission:inmate.transfer'],
    );
});
