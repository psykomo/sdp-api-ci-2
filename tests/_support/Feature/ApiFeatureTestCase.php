<?php

namespace Tests\Support\Feature;

use App\Modules\Wbp\Services\WbpService;
use App\Services\AuthService;
use App\Services\ConnectionResolver;
use App\Services\OrgContext;
use App\Services\PermissionService;
use App\Services\UnitOfWork;
use App\Services\UserService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use Tests\Support\Database\Seeds\ApiFeatureSeeder;

/**
 * Base for HTTP feature tests against the real API routes + in-memory DB.
 */
abstract class ApiFeatureTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait {
        call as private featureCall;
    }

    protected $migrate  = true;
    protected $refresh  = true;
    protected $seed     = ApiFeatureSeeder::class;
    protected $basePath = TESTPATH . '_support/Database';

    /**
     * App + Wbp migrations (Transfer not required for current feature suite).
     *
     * @var list<string>
     */
    protected $namespace = [
        'App',
        'App\Modules\Wbp',
        'App\Modules\Kunjungan',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetRequestScopedServices();
    }

    /**
     * FeatureTestTrait reuses one PHP process for many HTTP calls. Shared
     * services that hold Models retain WHERE state unless we refresh them.
     * (Production PHP-FPM/CGI resets the container per request.)
     */
    protected function resetRequestScopedServices(): void
    {
        Services::injectMock('orgContext', new OrgContext());
        Services::injectMock('connectionResolver', new ConnectionResolver());
        Services::injectMock('authService', new AuthService());
        Services::injectMock('permissionService', new PermissionService());
        Services::injectMock('userService', new UserService());
        Services::injectMock('wbpService', new WbpService());
        Services::injectMock('kunjunganService', new \App\Modules\Kunjungan\Services\KunjunganService());
        Services::injectMock('unitOfWork', new UnitOfWork());
    }

    public function call(string $method, string $path, ?array $params = null): TestResponse
    {
        $this->resetRequestScopedServices();

        return $this->featureCall($method, $path, $params);
    }

    protected function orgId(string $code): int
    {
        $row = $this->db->table('organizations')->where('code', $code)->get()->getRowArray();
        $this->assertNotNull($row, "Organization {$code} missing from feature seed.");

        return (int) $row['id'];
    }

    protected function roleId(string $key): int
    {
        $row = $this->db->table('roles')->where('key', $key)->get()->getRowArray();
        $this->assertNotNull($row, "Role {$key} missing from feature seed.");

        return (int) $row['id'];
    }

    protected function userIdByEmail(string $email): int
    {
        $row = $this->db->table('users')->where('email', $email)->get()->getRowArray();
        $this->assertNotNull($row, "User {$email} missing from feature seed.");

        return (int) $row['id'];
    }

    /**
     * @return array{token: string, body: array<string, mixed>}
     */
    protected function login(string $email, string $password = 'password'): array
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/auth/login', [
                'email'    => $email,
                'password' => $password,
            ]);

        $response->assertStatus(200);

        $body = json_decode($response->getJSON(), true);
        $this->assertIsArray($body);
        $this->assertSame('success', $body['status'] ?? null);
        $this->assertNotEmpty($body['data']['token'] ?? null);

        return [
            'token' => (string) $body['data']['token'],
            'body'  => $body,
        ];
    }

    /**
     * Authenticated + org-scoped request headers for protected routes.
     *
     * @return $this
     */
    protected function asOrgUser(string $token, int $orgId)
    {
        return $this->withBodyFormat('json')
            ->withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Org-Id'      => (string) $orgId,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonBody(\CodeIgniter\Test\TestResponse $response): array
    {
        $body = json_decode($response->getJSON(), true);
        $this->assertIsArray($body);

        return $body;
    }
}
