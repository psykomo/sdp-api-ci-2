<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Request-scoped multi-org context (user, active org, permissions).
     */
    public static function orgContext(bool $getShared = true): \App\Services\OrgContext
    {
        if ($getShared) {
            return static::getSharedInstance('orgContext');
        }

        return new \App\Services\OrgContext();
    }

    /**
     * Domain services that hold CI Models are intentionally not shared by default.
     * Models retain WHERE/builder state; a long-lived shared instance can leak
     * filters across HTTP calls (feature tests, FPM workers, queue workers).
     * Pass $getShared = true only when you need a deliberate singleton.
     */
    public static function authService(bool $getShared = false): \App\Services\AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        return new \App\Services\AuthService();
    }

    public static function permissionService(bool $getShared = false): \App\Services\PermissionService
    {
        if ($getShared) {
            return static::getSharedInstance('permissionService');
        }

        return new \App\Services\PermissionService();
    }

    public static function userService(bool $getShared = false): \App\Services\UserService
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new \App\Services\UserService();
    }

    public static function unitOfWork(bool $getShared = false): \App\Services\UnitOfWork
    {
        if ($getShared) {
            return static::getSharedInstance('unitOfWork');
        }

        return new \App\Services\UnitOfWork();
    }

    public static function connectionResolver(bool $getShared = true): \App\Services\ConnectionResolver
    {
        if ($getShared) {
            return static::getSharedInstance('connectionResolver');
        }

        return new \App\Services\ConnectionResolver();
    }

    public static function wbpService(bool $getShared = false): \App\Modules\Wbp\Services\WbpService
    {
        if ($getShared) {
            return static::getSharedInstance('wbpService');
        }

        return new \App\Modules\Wbp\Services\WbpService();
    }

    public static function referensiService(bool $getShared = false): \App\Modules\Referensi\Services\ReferensiService
    {
        if ($getShared) {
            return static::getSharedInstance('referensiService');
        }

        return new \App\Modules\Referensi\Services\ReferensiService();
    }

    public static function mutasiService(bool $getShared = false): \App\Modules\Mutasi\Services\MutasiService
    {
        if ($getShared) {
            return static::getSharedInstance('mutasiService');
        }

        return new \App\Modules\Mutasi\Services\MutasiService();
    }

    /** M1 mutasi golongan on legacy mutasi_golongan / perkara. */
    public static function mutasiGolonganService(bool $getShared = false): \App\Modules\Mutasi\Services\MutasiGolonganService
    {
        if ($getShared) {
            return static::getSharedInstance('mutasiGolonganService');
        }

        return new \App\Modules\Mutasi\Services\MutasiGolonganService();
    }

    /** Thin-module reference — see App\Modules\Kunjungan. */
    public static function kunjunganService(bool $getShared = false): \App\Modules\Kunjungan\Services\KunjunganService
    {
        if ($getShared) {
            return static::getSharedInstance('kunjunganService');
        }

        return new \App\Modules\Kunjungan\Services\KunjunganService();
    }
}
