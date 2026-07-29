<?php

namespace Tests\Feature;

use Tests\Support\Feature\ApiFeatureTestCase;

/**
 * @internal
 */
final class WbpRegisterFeatureTest extends ApiFeatureTestCase
{
    public function testDaftarWbpBindsActiveOrgAndCreatesAudit(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/wbp', [
                'registration_number' => 'REG-001',
                'full_name'           => 'Budi Santoso',
                'gender'              => 'L',
            ]);

        $response->assertStatus(201);

        $body = $this->jsonBody($response);
        $this->assertSame('success', $body['status']);
        $this->assertSame('REG-001', $body['data']['registration_number']);
        $this->assertSame('Budi Santoso', $body['data']['full_name']);
        $this->assertSame('active', $body['data']['status']);
        $this->assertSame($cipinangId, (int) $body['data']['organization_id']);

        $inmateId = (int) $body['data']['id'];

        $this->seeInDatabase('inmates', [
            'id'                  => $inmateId,
            'organization_id'     => $cipinangId,
            'registration_number' => 'REG-001',
            'status'              => 'active',
        ]);

        $this->seeInDatabase('audit_logs', [
            'action'      => 'wbp.registered',
            'entity_type' => 'wbp',
            'entity_id'   => $inmateId,
        ]);
    }

    public function testRegisterIgnoresClientSuppliedOrganizationId(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');
        $salembaId  = $this->orgId('RT-SALEMBA');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/wbp', [
                'registration_number' => 'REG-SPOOF',
                'full_name'           => 'Spoof Attempt',
                'organization_id'     => $salembaId,
            ]);

        $response->assertStatus(201);

        $body = $this->jsonBody($response);
        $this->assertSame($cipinangId, (int) $body['data']['organization_id']);
        $this->assertNotSame($salembaId, (int) $body['data']['organization_id']);
    }

    public function testRegisterValidationFailureReturns422WithErrors(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/wbp', [
                'full_name' => 'Missing reg number',
            ]);

        $response->assertStatus(422);

        $body = $this->jsonBody($response);
        $this->assertSame('error', $body['status']);
        $this->assertArrayHasKey('errors', $body);
    }

    public function testRegisterRequiresWritePermission(): void
    {
        $token      = $this->login('viewer@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/wbp', [
                'registration_number' => 'REG-VIEW',
                'full_name'           => 'Viewer Cannot Write',
            ]);

        $response->assertStatus(403);
    }

    public function testRegisterRequiresAuth(): void
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/wbp', [
                'registration_number' => 'REG-X',
                'full_name'           => 'No Auth',
            ]);

        $response->assertStatus(401);
    }

    public function testListInmatesIsOrgScoped(): void
    {
        $cipinangToken = $this->login('op@cipinang.test')['token'];
        $cipinangId    = $this->orgId('LP-CIPINANG');
        $salembaToken  = $this->login('op@salemba.test')['token'];
        $salembaId     = $this->orgId('RT-SALEMBA');

        $this->asOrgUser($cipinangToken, $cipinangId)
            ->post('api/v1/wbp', [
                'registration_number' => 'REG-CIP',
                'full_name'           => 'Cipinang Inmate',
            ])
            ->assertStatus(201);

        $this->asOrgUser($salembaToken, $salembaId)
            ->post('api/v1/wbp', [
                'registration_number' => 'REG-SAL',
                'full_name'           => 'Salemba Inmate',
            ])
            ->assertStatus(201);

        $cipList = $this->asOrgUser($cipinangToken, $cipinangId)->get('api/v1/wbp');
        $cipList->assertStatus(200);
        $cipNames = array_column($this->jsonBody($cipList)['data'] ?? [], 'full_name');
        $this->assertContains('Cipinang Inmate', $cipNames);
        $this->assertNotContains('Salemba Inmate', $cipNames);

        $salList = $this->asOrgUser($salembaToken, $salembaId)->get('api/v1/wbp');
        $salList->assertStatus(200);
        $salNames = array_column($this->jsonBody($salList)['data'] ?? [], 'full_name');
        $this->assertContains('Salemba Inmate', $salNames);
        $this->assertNotContains('Cipinang Inmate', $salNames);
    }
}
