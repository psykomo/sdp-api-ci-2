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
        'App\Modules\Wbp'       => APPPATH . 'Modules/Wbp',
        'App\Modules\Fasilitas' => APPPATH . 'Modules/Fasilitas',
        'App\Modules\Kunjungan' => APPPATH . 'Modules/Kunjungan',
        'App\Modules\Remisi'    => APPPATH . 'Modules/Remisi',
        'App\Modules\Mutasi'    => APPPATH . 'Modules/Mutasi',
        'App\Modules\Keswat'    => APPPATH . 'Modules/Keswat',
        'App\Modules\Perkara'   => APPPATH . 'Modules/Perkara',
        'App\Modules\Referensi' => APPPATH . 'Modules/Referensi',
        'App\Modules\Laporan'   => APPPATH . 'Modules/Laporan',
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
