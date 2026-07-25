<?php

namespace App\Commands;

use App\Services\ConnectionResolver;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Run migrations against every tenant database (multi topology).
 *
 * Usage:
 *   php spark migrate:tenants
 *   php spark migrate:tenants --seed
 *
 * In single topology this prints guidance to use `php spark migrate --all`.
 */
class MigrateTenants extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'migrate:tenants';
    protected $description = 'Run migrations (and optional seeds) on every multi-topology tenant database.';
    protected $usage       = 'migrate:tenants [options]';
    protected $options     = [
        '--seed' => 'Also run RbacSeeder + DemoAuthSeeder on each tenant after migrate.',
    ];

    public function run(array $params)
    {
        /** @var ConnectionResolver $resolver */
        $resolver = service('connectionResolver');

        if ($resolver->isSingle()) {
            CLI::write('database.topology is "single". Use:', 'yellow');
            CLI::write('  php spark migrate --all', 'green');
            CLI::write('No tenant databases to migrate.', 'yellow');

            return EXIT_SUCCESS;
        }

        $tenants = $resolver->tenants();
        if ($tenants === []) {
            CLI::error('database.topology is "multi" but no database.tenants.* entries are configured.');

            return EXIT_ERROR;
        }

        $withSeed = array_key_exists('seed', $params) || CLI::getOption('seed') !== null;

        CLI::write('Migrating ' . count($tenants) . ' tenant database(s)...', 'yellow');

        $failed = 0;

        foreach ($tenants as $orgCode => $databaseName) {
            CLI::newLine();
            CLI::write("=== {$orgCode} → {$databaseName} ===", 'white');

            try {
                $resolver->activateForOrgCode($orgCode);
                $group = $resolver->activeGroup();

                if ($group === null) {
                    throw new \RuntimeException('ConnectionResolver did not set an active group.');
                }

                CLI::write("Using connection group: {$group}", 'dark_gray');

                $this->call('migrate', [
                    'all' => null,
                    'g'   => $group,
                ]);

                if ($withSeed) {
                    CLI::write('Seeding RbacSeeder + DemoAuthSeeder...', 'yellow');
                    $this->call('db:seed', ['RbacSeeder']);
                    $this->call('db:seed', ['DemoAuthSeeder']);
                }

                CLI::write("OK: {$orgCode}", 'green');
            } catch (Throwable $e) {
                $failed++;
                CLI::error("FAILED: {$orgCode} — " . $e->getMessage());
            }
        }

        CLI::newLine();

        if ($failed > 0) {
            CLI::error("Completed with {$failed} failure(s).");

            return EXIT_ERROR;
        }

        CLI::write('All tenant databases migrated.', 'green');

        return EXIT_SUCCESS;
    }
}
