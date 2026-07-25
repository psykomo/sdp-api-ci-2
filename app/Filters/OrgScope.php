<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Resolves active organization from X-Org-Id header.
 * Must run after ApiAuth. Header value must be in the user's allowed orgs.
 */
class OrgScope implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $orgContext = service('orgContext');
        $header     = trim($request->getHeaderLine('X-Org-Id'));

        if ($header === '' || ! ctype_digit($header)) {
            return service('response')
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 400,
                    'message' => 'X-Org-Id header is required and must be a numeric organization id.',
                ]);
        }

        $orgId = (int) $header;

        if (! $orgContext->canAccessOrg($orgId)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 403,
                    'message' => 'You do not have access to this organization.',
                ]);
        }

        $permissionService = service('permissionService');
        $scoped            = $permissionService->resolveScopedOrgIds($orgId);
        $permissions       = $permissionService->permissionsForUserInOrg(
            (int) $orgContext->getUserId(),
            $orgId,
        );

        $orgContext->setActiveOrgId($orgId);
        $orgContext->setScopedOrgIds($scoped);
        $orgContext->setPermissions($permissions);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
