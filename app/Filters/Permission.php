<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route filter: permission:wbp.read
 * Checks OrgContext permissions populated by OrgScope.
 */
class Permission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = $arguments ?? [];

        if ($required === []) {
            return $request;
        }

        $orgContext = service('orgContext');

        foreach ($required as $permission) {
            if (! $orgContext->hasPermission((string) $permission)) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 403,
                        'message' => 'Forbidden: missing permission ' . $permission,
                    ]);
            }
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
