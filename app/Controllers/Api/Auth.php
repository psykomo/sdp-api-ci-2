<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Services\AuthService;
use CodeIgniter\RESTful\ResourceController;

/**
 * Auth endpoints (public). Issues Bearer API tokens.
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

        $userModel = model(\App\Models\UserModel::class);
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
        $auth  = service('authService');
        $token = $auth->issueToken((int) $userData['id']);

        unset($userData['password_hash']);

        return $this->respond(ApiResponse::success([
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'token_type' => 'Bearer',
            'user'       => $userData,
        ], 'Authenticated'));
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
