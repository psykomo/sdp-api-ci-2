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

    public static function inmateService(bool $getShared = false): \App\Modules\Inmate\Services\InmateService
    {
        if ($getShared) {
            return static::getSharedInstance('inmateService');
        }

        return new \App\Modules\Inmate\Services\InmateService();
    }

    public static function transferService(bool $getShared = false): \App\Modules\Transfer\Services\TransferService
    {
        if ($getShared) {
            return static::getSharedInstance('transferService');
        }

        return new \App\Modules\Transfer\Services\TransferService();
    }

    /** Thin-module reference — see App\Modules\Visit. */
    public static function visitService(bool $getShared = false): \App\Modules\Visit\Services\VisitService
    {
        if ($getShared) {
            return static::getSharedInstance('visitService');
        }

        return new \App\Modules\Visit\Services\VisitService();
    }
}
