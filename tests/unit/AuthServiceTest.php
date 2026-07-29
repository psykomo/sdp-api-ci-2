<?php

namespace Tests\Unit;

use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use DomainException;

class AuthServiceTest extends CIUnitTestCase
{
    public function testLoginRequiresEmailAndPassword(): void
    {
        $auth = new AuthService();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Email and password are required.');

        $auth->login('', '');
    }

    public function testLoginRequiresOrganizationCodeInMultiTopology(): void
    {
        $config           = config(\Config\Database::class);
        $config->topology = 'multi';
        $config->tenants  = ['LP-CIPINANG' => 'tenant_cipinang_test'];

        $resolver = new \App\Services\ConnectionResolver($config);
        $auth     = new AuthService(
            resolver: $resolver,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('organization_code is required in multi database topology.');

        $auth->login('user@example.test', 'password');
    }
}
