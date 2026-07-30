<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 *
 * Development (typical) → MariaDB legacy `db_sdp` via .env (group: default)
 * Optional local        → SQLite3 file (set database.default.DBDriver=SQLite3 in .env)
 * Production            → MariaDB group when ENVIRONMENT=production (if defaultGroup not set)
 * Tests                 → in-memory SQLite3 (group: tests)
 *
 * Shared-schema migration: default points at the same DB as 102sdp CI2
 * (OrbStack: 127.0.0.1:3307). Do not run greenfield `spark migrate` on it
 * unless you intend to alter that database.
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
     * Default connection. Overridden by .env (preferred: MySQLi → legacy db_sdp).
     * Falls back to local SQLite file if .env does not set a driver.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => 'sdp',
        'password'     => 'sdp_local',
        'database'     => 'db_sdp',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3307,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * Named MariaDB group (same target as default in local shared-schema setup).
     * Override via .env: database.mariadb.*
     *
     * @var array<string, mixed>
     */
    public array $mariadb = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => 'sdp',
        'password'     => 'sdp_local',
        'database'     => 'db_sdp',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3307,
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
