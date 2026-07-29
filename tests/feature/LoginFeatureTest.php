<?php

namespace Tests\Feature;

use Tests\Support\Feature\ApiFeatureTestCase;

/**
 * @internal
 */
final class LoginFeatureTest extends ApiFeatureTestCase
{
    public function testLoginSucceedsWithValidCredentials(): void
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/auth/login', [
                'email'    => 'op@cipinang.test',
                'password' => 'password',
            ]);

        $response->assertStatus(200);

        $body = $this->jsonBody($response);
        $this->assertSame('success', $body['status']);
        $this->assertSame('Bearer', $body['data']['token_type']);
        $this->assertNotEmpty($body['data']['token']);
        $this->assertSame('op@cipinang.test', $body['data']['user']['email']);
        $this->assertArrayNotHasKey('password_hash', $body['data']['user']);

        $hash = hash('sha256', $body['data']['token']);
        $this->seeInDatabase('api_tokens', [
            'token_hash' => $hash,
            'user_id'    => $this->userIdByEmail('op@cipinang.test'),
        ]);
    }

    public function testLoginRejectsInvalidPassword(): void
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/auth/login', [
                'email'    => 'op@cipinang.test',
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(401);

        $body = $this->jsonBody($response);
        $this->assertSame('error', $body['status']);
        $this->assertSame('Invalid credentials.', $body['message']);
    }

    public function testLoginRejectsUnknownEmailWithSameMessage(): void
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/auth/login', [
                'email'    => 'missing@example.test',
                'password' => 'password',
            ]);

        $response->assertStatus(401);

        $body = $this->jsonBody($response);
        $this->assertSame('Invalid credentials.', $body['message']);
    }

    public function testLoginRejectsInactiveAccount(): void
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/auth/login', [
                'email'    => 'inactive@cipinang.test',
                'password' => 'password',
            ]);

        $response->assertStatus(401);

        $body = $this->jsonBody($response);
        $this->assertSame('Invalid credentials.', $body['message']);
    }

    public function testLoginRequiresEmailAndPassword(): void
    {
        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('api/v1/auth/login', [
                'email' => '',
            ]);

        $response->assertStatus(422);

        $body = $this->jsonBody($response);
        $this->assertSame('error', $body['status']);
        $this->assertStringContainsString('Email and password', $body['message']);
    }
}
