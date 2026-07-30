<?php

namespace Tests\Unit;

use App\Services\ConnectionResolver;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database as DatabaseConfig;
use InvalidArgumentException;

class ConnectionResolverTest extends CIUnitTestCase
{
    public function testSingleTopologyActivateIsNoOpOnDefaultGroup(): void
    {
        $config            = config(DatabaseConfig::class);
        $config->topology  = 'single';
        $config->tenants   = [];
        $before            = $config->defaultGroup;

        $resolver = new ConnectionResolver($config);
        $this->assertTrue($resolver->isSingle());

        $resolver->activateForOrgCode('LP-CIPINANG');

        $this->assertSame($before, $config->defaultGroup);
        $this->assertSame($before, $resolver->activeGroup());
    }

    public function testMultiTopologyActivatesTenantSqliteFile(): void
    {
        $config           = config(DatabaseConfig::class);
        $config->topology = 'multi';
        $config->tenants  = [
            'LP-CIPINANG' => 'tenant_cipinang_test',
            'RT-SALEMBA'  => 'tenant_salemba_test',
        ];
        // Force SQLite template even if mariadb credentials exist in .env.
        $config->defaultGroup           = 'default';
        $config->default['DBDriver']    = 'SQLite3';
        $config->default['database']    = WRITEPATH . 'db' . DIRECTORY_SEPARATOR . 'sdp_api.sqlite';
        $config->mariadb['username']    = '';
        $config->mariadb['hostname']    = '';

        $resolver = new ConnectionResolver($config);
        $this->assertTrue($resolver->isMulti());

        $db = $resolver->activateForOrgCode('LP-CIPINANG');

        $this->assertSame('LP-CIPINANG', $resolver->activeOrgCode());
        $this->assertSame('tenant_LP_CIPINANG', $resolver->activeGroup());
        $this->assertSame('tenant_LP_CIPINANG', $config->defaultGroup);
        $this->assertStringContainsString('tenant_cipinang_test.sqlite', $config->tenant_LP_CIPINANG['database']);
        $this->assertSame($config->tenant_LP_CIPINANG['database'], $db->getDatabase());
    }

    public function testMultiTopologyRejectsUnknownOrgCode(): void
    {
        $config           = config(DatabaseConfig::class);
        $config->topology = 'multi';
        $config->tenants  = ['LP-CIPINANG' => 'tenant_cipinang_test'];

        $resolver = new ConnectionResolver($config);

        $this->expectException(InvalidArgumentException::class);
        $resolver->activateForOrgCode('NO-SUCH-ORG');
    }
}
