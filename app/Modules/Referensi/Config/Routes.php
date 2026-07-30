<?php

use CodeIgniter\Router\RouteCollection;

/**
 * R0 referensi routes — legacy daftar_referensi / jenis_registrasi / upt.
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', ['filter' => ['apiAuth', 'orgScope']], static function (RouteCollection $routes) {
    $routes->get('referensi/jenis-registrasi', '\App\Modules\Referensi\Controllers\Api\Referensi::jenisRegistrasi', [
        'filter' => 'permission:referensi.read',
    ]);
    $routes->get('referensi/groups', '\App\Modules\Referensi\Controllers\Api\Referensi::groups', [
        'filter' => 'permission:referensi.read',
    ]);
    $routes->get('referensi/lookups', '\App\Modules\Referensi\Controllers\Api\Referensi::lookups', [
        'filter' => 'permission:referensi.read',
    ]);
    $routes->get('referensi/lookups/(:segment)', '\App\Modules\Referensi\Controllers\Api\Referensi::showLookup/$1', [
        'filter' => 'permission:referensi.read',
    ]);
    $routes->get('referensi/upt', '\App\Modules\Referensi\Controllers\Api\Referensi::upt', [
        'filter' => 'permission:referensi.read',
    ]);
    $routes->get('referensi/upt/(:segment)', '\App\Modules\Referensi\Controllers\Api\Referensi::showUpt/$1', [
        'filter' => 'permission:referensi.read',
    ]);
});
