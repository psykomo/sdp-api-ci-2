<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Services\AuthService;
use App\Services\ConnectionResolver;
use CodeIgniter\RESTful\ResourceController;
use InvalidArgumentException;

/**
 * Auth endpoints (public). Issues Bearer API tokens.
 *
 * In multi topology, login requires organization_code so the token is
 * issued against that unit's isolated database.
 */
class Auth extends ResourceController
{
    protected $format = 'json';

    public function login()
    {
        $data = $this->request->getJSON(true) ?? [];

        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->respond(
                ApiResponse::error('Email and password are required.', 422),
                422,
            );
        }

        /** @var ConnectionResolver $resolver */
        $resolver = service('connectionResolver');
        $orgCode  = strtoupper(trim((string) ($data['organization_code'] ?? '')));

        if ($resolver->isMulti()) {
            if ($orgCode === '') {
                return $this->respond(
                    ApiResponse::error('organization_code is required in multi database topology.', 422),
                    422,
                );
            }

            try {
                $resolver->activateForOrgCode($orgCode);
            } catch (InvalidArgumentException $e) {
                return $this->respond(ApiResponse::error($e->getMessage(), 400), 400);
            }
        }

        // Bind to the (possibly just-activated) default connection.
        $userModel = model(\App\Models\UserModel::class, false);
        $user      = $userModel->where('email', strtolower($email))->first();

        if ($user === null) {
            return $this->respond(ApiResponse::error('Invalid credentials.', 401), 401);
        }

        $userData = is_object($user) ? $user->toRawArray() : $user;

        if (($userData['status'] ?? '') !== 'active') {
            return $this->respond(ApiResponse::error('Account is not active.', 403), 403);
        }

        $hash = $userData['password_hash'] ?? null;
        if ($hash === null || $hash === '' || ! password_verify($password, $hash)) {
            return $this->respond(ApiResponse::error('Invalid credentials.', 401), 401);
        }

        /** @var AuthService $auth */
        $auth  = service('authService', false);
        $token = $auth->issueToken((int) $userData['id']);

        unset($userData['password_hash']);

        $payload = [
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'token_type' => 'Bearer',
            'user'       => $userData,
        ];

        if ($resolver->isMulti()) {
            $payload['organization_code'] = $orgCode;
        }

        return $this->respond(ApiResponse::success($payload, 'Authenticated'));
    }

    /**
     * Placeholder for token refresh — swap implementation when using JWT.
     */
    public function refresh()
    {
        return $this->respond(
            ApiResponse::error('Token refresh is not implemented for opaque tokens. Request a new token via login.', 501),
            501,
        );
    }
}
