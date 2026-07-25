<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

/**
 * -------------------------------------------------------------------
 * AUTOLOADER CONFIGURATION
 * -------------------------------------------------------------------
 *
 * This file defines the namespaces and class maps so the Autoloader
 * can find the files as needed.
 *
 * NOTE: If you use an identical key in $psr4 or $classmap, then
 *       the values in this file will overwrite the framework's values.
 *
 * NOTE: This class is required prior to Autoloader instantiation,
 *       and does not extend BaseConfig.
 */
class Autoload extends AutoloadConfig
{
    /**
     * -------------------------------------------------------------------
     * Namespaces
     * -------------------------------------------------------------------
     * Module namespaces are registered explicitly so CI4 can auto-discover
     * Config/Routes.php and Database/Migrations under each module.
     *
     * @var array<string, list<string>|string>
     */
    public $psr4 = [
        APP_NAMESPACE              => APPPATH,
        'App\Modules\Inmate'       => APPPATH . 'Modules/Inmate',
        'App\Modules\Facility'     => APPPATH . 'Modules/Facility',
        'App\Modules\Visit'        => APPPATH . 'Modules/Visit',
        'App\Modules\Remission'    => APPPATH . 'Modules/Remission',
        'App\Modules\Transfer'     => APPPATH . 'Modules/Transfer',
        'App\Modules\Medical'      => APPPATH . 'Modules/Medical',
        'App\Modules\Legal'        => APPPATH . 'Modules/Legal',
        'App\Modules\MasterData'   => APPPATH . 'Modules/MasterData',
        'App\Modules\Report'       => APPPATH . 'Modules/Report',
    ];

    /**
     * -------------------------------------------------------------------
     * Class Map
     * -------------------------------------------------------------------
     *
     * @var array<string, string>
     */
    public $classmap = [];

    /**
     * -------------------------------------------------------------------
     * Files
     * -------------------------------------------------------------------
     *
     * @var list<string>
     */
    public $files = [];

    /**
     * -------------------------------------------------------------------
     * Helpers
     * -------------------------------------------------------------------
     *
     * @var list<string>
     */
    public $helpers = [];
}
