<?php

namespace Tests\Feature;

use Tests\Support\Feature\ApiFeatureTestCase;

/**
 * Feature coverage for the thin-module reference (Kunjungan).
 *
 * @internal
 */
final class KunjunganThinModuleFeatureTest extends ApiFeatureTestCase
{
    public function testCreateVisitBindsActiveOrgAndIgnoresClientOrgId(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');
        $salembaId  = $this->orgId('RT-SALEMBA');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/kunjungan', [
                'visitor_name'    => 'Siti Aminah',
                'visitor_id_number' => '3174…',
                'visited_at'      => '2026-07-29 10:00:00',
                'notes'           => 'Regular family visit',
                'organization_id' => $salembaId,
            ]);

        $response->assertStatus(201);

        $body = $this->jsonBody($response);
        $this->assertSame('Siti Aminah', $body['data']['visitor_name']);
        $this->assertSame('scheduled', $body['data']['status']);
        $this->assertSame($cipinangId, (int) $body['data']['organization_id']);

        $this->seeInDatabase('visits', [
            'id'              => (int) $body['data']['id'],
            'organization_id' => $cipinangId,
            'visitor_name'    => 'Siti Aminah',
        ]);
    }

    public function testListVisitsIsOrgScoped(): void
    {
        $cipinangToken = $this->login('op@cipinang.test')['token'];
        $cipinangId    = $this->orgId('LP-CIPINANG');
        $salembaToken  = $this->login('op@salemba.test')['token'];
        $salembaId     = $this->orgId('RT-SALEMBA');

        $this->asOrgUser($cipinangToken, $cipinangId)
            ->post('api/v1/kunjungan', [
                'visitor_name' => 'Cipinang Visitor',
                'visited_at'   => '2026-07-29 09:00:00',
            ])
            ->assertStatus(201);

        $this->asOrgUser($salembaToken, $salembaId)
            ->post('api/v1/kunjungan', [
                'visitor_name' => 'Salemba Visitor',
                'visited_at'   => '2026-07-29 11:00:00',
            ])
            ->assertStatus(201);

        $cipList = $this->asOrgUser($cipinangToken, $cipinangId)->get('api/v1/kunjungan');
        $cipList->assertStatus(200);
        $cipNames = array_column($this->jsonBody($cipList)['data'] ?? [], 'visitor_name');
        $this->assertContains('Cipinang Visitor', $cipNames);
        $this->assertNotContains('Salemba Visitor', $cipNames);
    }

    public function testShowOutOfScopeVisitReturnsNotFound(): void
    {
        $salembaToken = $this->login('op@salemba.test')['token'];
        $salembaId    = $this->orgId('RT-SALEMBA');
        $create       = $this->asOrgUser($salembaToken, $salembaId)
            ->post('api/v1/kunjungan', [
                'visitor_name' => 'Only Salemba',
                'visited_at'   => '2026-07-29 12:00:00',
            ]);
        $create->assertStatus(201);
        $visitId = (int) $this->jsonBody($create)['data']['id'];

        $cipinangToken = $this->login('op@cipinang.test')['token'];
        $cipinangId    = $this->orgId('LP-CIPINANG');
        $show          = $this->asOrgUser($cipinangToken, $cipinangId)
            ->get('api/v1/kunjungan/' . $visitId);

        $show->assertStatus(404);
    }

    public function testCreateRequiresWritePermission(): void
    {
        $token      = $this->login('viewer@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/kunjungan', [
                'visitor_name' => 'Nope',
                'visited_at'   => '2026-07-29 08:00:00',
            ]);

        $response->assertStatus(403);
    }

    public function testCreateValidationFailureReturns422(): void
    {
        $token      = $this->login('op@cipinang.test')['token'];
        $cipinangId = $this->orgId('LP-CIPINANG');

        $response = $this->asOrgUser($token, $cipinangId)
            ->post('api/v1/kunjungan', [
                'visitor_name' => 'Missing visited_at',
            ]);

        $response->assertStatus(422);
        $body = $this->jsonBody($response);
        $this->assertArrayHasKey('errors', $body);
    }
}
