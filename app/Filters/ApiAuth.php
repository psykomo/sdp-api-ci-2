<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Authenticates Bearer tokens and seeds OrgContext with the user
 * and allowed organization IDs.
 */
class ApiAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('authService')->authenticateBearer(
            $request->getHeaderLine('Authorization'),
        );

        if ($auth === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 401,
                    'message' => 'Unauthorized',
                ]);
        }

        $org = service('orgContext');
        $org->reset();
        $org->setUserId((int) $auth['user']['id']);
        $org->setUser($auth['user']);
        $org->setAllowedOrgIds($auth['allowed_org_ids']);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
