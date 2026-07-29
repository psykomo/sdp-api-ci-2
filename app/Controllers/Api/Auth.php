<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Services\AuthService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * Auth endpoints (public). Thin HTTP layer over AuthService.
 */
class Auth extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?AuthService $auth = null,
    ) {
        $this->auth ??= Services::authService();
    }

    public function login()
    {
        return $this->apiTry(function () {
            $data = $this->request->getJSON(true) ?? [];

            $payload = $this->auth->login(
                (string) ($data['email'] ?? ''),
                (string) ($data['password'] ?? ''),
                isset($data['organization_code']) ? (string) $data['organization_code'] : null,
            );

            return $this->respond(ApiResponse::success($payload, 'Authenticated'));
        });
    }

    /**
     * Placeholder for token refresh — swap implementation when using JWT.
     */
    public function refresh()
    {
        return $this->respond(
            ApiResponse::error(
                'Token refresh is not implemented for opaque tokens. Request a new token via login.',
                501,
            ),
            501,
        );
    }
}
