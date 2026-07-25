<?php

namespace App\Filters;

use App\Services\ConnectionResolver;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * Authenticates Bearer tokens and seeds OrgContext with the user
 * and allowed organization IDs.
 *
 * In multi topology the tenant database is selected from X-Org-Code
 * *before* token validation (api_tokens live inside each unit DB).
 */
class ApiAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var ConnectionResolver $resolver */
        $resolver = service('connectionResolver');

        if ($resolver->isMulti()) {
            $orgCode = trim($request->getHeaderLine('X-Org-Code'));

            if ($orgCode === '') {
                return service('response')
                    ->setStatusCode(400)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 400,
                        'message' => 'X-Org-Code header is required in multi database topology.',
                    ]);
            }

            try {
                $resolver->activateForOrgCode($orgCode);
            } catch (InvalidArgumentException $e) {
                return service('response')
                    ->setStatusCode(400)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 400,
                        'message' => $e->getMessage(),
                    ]);
            }
        }

        // Fresh instance so models bind to the (possibly just-activated) default DB.
        $auth = service('authService', false)->authenticateBearer(
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
