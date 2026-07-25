<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database as DatabaseConfig;
use InvalidArgumentException;
use ReflectionProperty;

/**
 * Binds the request (or CLI step) to the correct database for the active topology.
 *
 * single → no-op; everyone shares Config\Database::$defaultGroup.
 * multi  → org code selects a per-unit database/schema with identical schema.
 *          Kanwil/Pusat also has its own DB; aggregation into it is external.
 */
class ConnectionResolver
{
    private ?string $activeOrgCode = null;

    private ?string $activeGroup = null;

    public function __construct(
        protected ?DatabaseConfig $config = null,
    ) {
        $this->config ??= config(DatabaseConfig::class);
    }

    public function topology(): string
    {
        return $this->config->topology;
    }

    public function isMulti(): bool
    {
        return $this->topology() === 'multi';
    }

    public function isSingle(): bool
    {
        return ! $this->isMulti();
    }

    /**
     * @return array<string, string> org code => database name / sqlite stem
     */
    public function tenants(): array
    {
        return $this->config->tenants;
    }

    /**
     * @return list<string>
     */
    public function tenantCodes(): array
    {
        return array_keys($this->tenants());
    }

    public function activeOrgCode(): ?string
    {
        return $this->activeOrgCode;
    }

    public function activeGroup(): ?string
    {
        return $this->activeGroup;
    }

    /**
     * Resolve the configured database name for an organization code.
     *
     * @throws InvalidArgumentException when topology is multi and the code is unknown
     */
    public function resolveDatabaseName(string $orgCode): string
    {
        $orgCode = strtoupper(trim($orgCode));

        if ($this->isSingle()) {
            $group = $this->config->defaultGroup;
            $cfg   = $this->config->{$group} ?? $this->config->default;

            return (string) ($cfg['database'] ?? '');
        }

        if ($orgCode === '' || ! isset($this->config->tenants[$orgCode])) {
            throw new InvalidArgumentException(
                "Unknown organization code for multi topology: {$orgCode}",
            );
        }

        return $this->config->tenants[$orgCode];
    }

    /**
     * Point the application's default DB connection at the tenant database.
     * No-op when topology is single.
     *
     * @throws InvalidArgumentException when the org code is not mapped
     */
    public function activateForOrgCode(string $orgCode): BaseConnection
    {
        $orgCode = strtoupper(trim($orgCode));

        if ($this->isSingle()) {
            $this->activeOrgCode = $orgCode !== '' ? $orgCode : null;
            $this->activeGroup   = $this->config->defaultGroup;

            return db_connect();
        }

        $databaseName = $this->resolveDatabaseName($orgCode);
        $group        = $this->groupNameFor($orgCode);

        $this->config->{$group}      = $this->buildTenantConfig($databaseName);
        $this->config->defaultGroup  = $group;
        $this->activeOrgCode         = $orgCode;
        $this->activeGroup           = $group;

        // Drop any cached shared handle for this group so credentials/db name apply.
        $this->forgetSharedConnection($group);

        return db_connect($group);
    }

    /**
     * Connection group name used for a tenant (also becomes defaultGroup while active).
     */
    public function groupNameFor(string $orgCode): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', strtoupper(trim($orgCode))) ?? 'UNKNOWN';

        return 'tenant_' . $safe;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTenantConfig(string $databaseName): array
    {
        $baseGroup = $this->templateGroup();
        $base      = $this->config->{$baseGroup};

        if (($base['DBDriver'] ?? '') === 'SQLite3') {
            $base['database'] = $this->sqlitePathFor($databaseName);
        } else {
            $base['database'] = $databaseName;
        }

        // Tenant connections should surface errors during bring-up.
        $base['DBDebug'] = true;

        return $base;
    }

    private function templateGroup(): string
    {
        if ($this->config->defaultGroup === 'mariadb' || ENVIRONMENT === 'production') {
            return 'mariadb';
        }

        if ($this->config->defaultGroup === 'tests') {
            return 'tests';
        }

        // Prefer an explicit mariadb template when already configured for multi
        // against a remote server even in development.
        if (($this->config->mariadb['hostname'] ?? '') !== ''
            && ($this->config->mariadb['username'] ?? '') !== ''
        ) {
            return 'mariadb';
        }

        return 'default';
    }

    private function sqlitePathFor(string $databaseName): string
    {
        if ($databaseName === ':memory:'
            || str_contains($databaseName, DIRECTORY_SEPARATOR)
            || str_ends_with($databaseName, '.sqlite')
        ) {
            return $databaseName;
        }

        return WRITEPATH . 'db' . DIRECTORY_SEPARATOR . $databaseName . '.sqlite';
    }

    private function forgetSharedConnection(string $group): void
    {
        $property = new ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
        $instances = $property->getValue();

        if (isset($instances[$group])) {
            try {
                $instances[$group]->close();
            } catch (\Throwable) {
                // Connection may already be closed.
            }
            unset($instances[$group]);
            $property->setValue(null, $instances);
        }
    }
}
