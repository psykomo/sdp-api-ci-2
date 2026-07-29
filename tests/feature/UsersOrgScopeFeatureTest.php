<?php

namespace Tests\Feature;

use Tests\Support\Feature\ApiFeatureTestCase;

/**
 * @internal
 */
final class UsersOrgScopeFeatureTest extends ApiFeatureTestCase
{
    public function testListUsersOnlyReturnsUsersAssignedToScopedOrg(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->get('api/v1/users');

        $response->assertStatus(200);

        $body  = $this->jsonBody($response);
        $emails = array_column($body['data'] ?? [], 'email');

        $this->assertContains('op@cipinang.test', $emails);
        $this->assertContains('admin@cipinang.test', $emails);
        $this->assertContains('viewer@cipinang.test', $emails);
        $this->assertNotContains('op@salemba.test', $emails);
    }

    public function testShowUserFromOtherOrgReturnsNotFound(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');
        $salembaUserId = $this->userIdByEmail('op@salemba.test');

        $response = $this->asOrgUser($token, $cipinangId)
            ->get('api/v1/users/' . $salembaUserId);

        $response->assertStatus(404);

        $body = $this->jsonBody($response);
        $this->assertSame('error', $body['status']);
    }

    public function testShowUserInScopeSucceeds(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');
        $userId     = $this->userIdByEmail('viewer@cipinang.test');

        $response = $this->asOrgUser($token, $cipinangId)
            ->get('api/v1/users/' . $userId);

        $response->assertStatus(200);

        $body = $this->jsonBody($response);
        $this->assertSame('viewer@cipinang.test', $body['data']['email']);
        $this->assertArrayNotHasKey('password_hash', $body['data']);
    }

    public function testCreateUserAssignsToActiveOrgAndIsListed(): void
    {
        $token      = $this->login('admin@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');
        $roleId     = $this->roleId('viewer');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/users', [
                'name'     => 'New Cipinang User',
                'email'    => 'new@cipinang.test',
                'password' => 'secret123',
                'role_id'  => $roleId,
            ]);

        $response->assertStatus(201);

        $body = $this->jsonBody($response);
        $this->assertSame('new@cipinang.test', $body['data']['email']);
        $newId = (int) $body['data']['id'];

        $this->seeInDatabase('user_organization_roles', [
            'user_id'         => $newId,
            'organization_id' => $cipinangId,
            'role_id'         => $roleId,
        ]);

        // Visible in Cipinang list
        $list = $this->asOrgUser($token, $cipinangId)->get('api/v1/users');
        $list->assertStatus(200);
        $emails = array_column($this->jsonBody($list)['data'] ?? [], 'email');
        $this->assertContains('new@cipinang.test', $emails);

        // Not visible when scoped to Salemba (different admin would be needed;
        // Salemba operator has user.read — login as salemba and assert absent).
        $salembaToken = $this->login('op@salemba.test')['token'];
        $salembaId    = $this->orgId('RT-SALEMBA');
        $salembaList  = $this->asOrgUser($salembaToken, $salembaId)->get('api/v1/users');
        $salembaList->assertStatus(200);
        $salembaEmails = array_column($this->jsonBody($salembaList)['data'] ?? [], 'email');
        $this->assertNotContains('new@cipinang.test', $salembaEmails);
    }

    public function testCreateUserRequiresWritePermission(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/users', [
                'name'     => 'Nope',
                'email'    => 'nope@cipinang.test',
                'password' => 'secret123',
                'role_id'  => $this->roleId('viewer'),
            ]);

        $response->assertStatus(403);
    }

    public function testCreateUserRequiresRoleIdAndPassword(): void
    {
        $token      = $this->login('admin@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/users', [
                'name'  => 'Incomplete',
                'email' => 'incomplete@cipinang.test',
            ]);

        $response->assertStatus(422);
    }

    public function testProtectedUsersRequireAuthAndOrgHeader(): void
    {
        $noAuth = $this->withHeaders(['Accept' => 'application/json'])
            ->get('api/v1/users');
        $noAuth->assertStatus(401);

        $token = $this->login('op@cipinang.test')['token'];
        $noOrg = $this->withBodyFormat('json')
            ->withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])
            ->get('api/v1/users');
        $noOrg->assertStatus(400);
    }

    public function testCannotUseOrgOutsideAssignments(): void
    {
        $token     = $this->login('op@cipinang.test')['token'];
        $salembaId = $this->orgId('RT-SALEMBA');

        $response = $this->asOrgUser($token, $salembaId)
            ->get('api/v1/users');

        $response->assertStatus(403);
    }
}
