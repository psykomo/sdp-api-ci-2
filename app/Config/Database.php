<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 *
 * Local development  → SQLite3  (group: default)
 * Production         → MariaDB  (group: mariadb, selected when ENVIRONMENT=production)
 * Tests              → in-memory SQLite3 (group: tests)
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * Deployment topology:
     * - single: one shared database; org isolation via organization_id
     * - multi:  one database/schema per organization code (identical schema)
     *
     * Override via .env: database.topology = single|multi
     */
    public string $topology = 'single';

    /**
     * Multi-topology only: organization code → MariaDB database name
     * (or SQLite filename stem when the active driver is SQLite3).
     *
     * Populated from .env keys `database.tenants.{ORG_CODE}=db_name`
     * because CI4 env injection cannot add new keys to an empty array.
     *
     * @var array<string, string>
     */
    public array $tenants = [];

    /**
     * Local SQLite3 connection (development default).
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => '',
        'username'     => '',
        'password'     => '',
        'database'     => WRITEPATH . 'db' . DIRECTORY_SEPARATOR . 'sdp_api.sqlite',
        'DBDriver'     => 'SQLite3',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => '',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => true,
        'failover'     => [],
        'port'         => 0,
        'foreignKeys'  => true,
        'busyTimeout'  => 5000,
        'synchronous'  => null,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * MariaDB / MySQL production connection.
     *
     * Selected automatically when CI_ENVIRONMENT=production.
     * Override credentials via .env:
     *   database.mariadb.hostname / database / username / password / port
     *
     * @var array<string, mixed>
     */
    public array $mariadb = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => '',
        'password'     => '',
        'database'     => 'sdp_api',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => false,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => true,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * Explicit SQLite group (always available for local tooling / fallback).
     *
     * @var array<string, mixed>
     */
    public array $sqlite = [
        'DSN'          => '',
        'hostname'     => '',
        'username'     => '',
        'password'     => '',
        'database'     => WRITEPATH . 'db' . DIRECTORY_SEPARATOR . 'sdp_api.sqlite',
        'DBDriver'     => 'SQLite3',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => '',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => true,
        'failover'     => [],
        'port'         => 0,
        'foreignKeys'  => true,
        'busyTimeout'  => 5000,
        'synchronous'  => null,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => true,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'synchronous' => null,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->loadTenantsFromEnv();

        $topology = strtolower(trim($this->topology));
        $this->topology = in_array($topology, ['single', 'multi'], true) ? $topology : 'single';

        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';

            return;
        }

        if (ENVIRONMENT === 'production') {
            $this->defaultGroup = 'mariadb';
        }
    }

    /**
     * Read `database.tenants.LP-CIPINANG=lp_cipinang` style env entries into $tenants.
     */
    private function loadTenantsFromEnv(): void
    {
        $prefix = 'database.tenants.';
        $sources = [$_ENV, $_SERVER];

        foreach ($sources as $source) {
            foreach ($source as $key => $value) {
                if (! is_string($key) || ! str_starts_with($key, $prefix)) {
                    continue;
                }

                $code = strtoupper(substr($key, strlen($prefix)));
                if ($code === '' || ! is_scalar($value)) {
                    continue;
                }

                $this->tenants[$code] = trim((string) $value, " \t\n\r\0\x0B'\"");
            }
        }
    }
}
