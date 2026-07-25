<?php

namespace App\Filters;

use App\Models\OrganizationModel;
use App\Services\ConnectionResolver;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Resolves the active organization for the request.
 *
 * single topology → X-Org-Id (numeric local id), as before.
 * multi topology  → X-Org-Code (DB already activated by ApiAuth); looks up
 *                   the local organizations.id inside that tenant database.
 *                   Optional X-Org-Id must match the row if provided.
 */
class OrgScope implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $orgContext = service('orgContext');

        /** @var ConnectionResolver $resolver */
        $resolver = service('connectionResolver');

        if ($resolver->isMulti()) {
            return $this->resolveMulti($request, $orgContext, $resolver);
        }

        return $this->resolveSingle($request, $orgContext);
    }

    private function resolveSingle(RequestInterface $request, $orgContext)
    {
        $header = trim($request->getHeaderLine('X-Org-Id'));

        if ($header === '' || ! ctype_digit($header)) {
            return service('response')
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 400,
                    'message' => 'X-Org-Id header is required and must be a numeric organization id.',
                ]);
        }

        return $this->applyOrg((int) $header, $orgContext);
    }

    private function resolveMulti(RequestInterface $request, $orgContext, ConnectionResolver $resolver)
    {
        $orgCode = strtoupper(trim($request->getHeaderLine('X-Org-Code')));

        if ($orgCode === '') {
            return service('response')
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 400,
                    'message' => 'X-Org-Code header is required in multi database topology.',
                ]);
        }

        // Prefer the code already activated by ApiAuth when present.
        $activeCode = $resolver->activeOrgCode();
        if ($activeCode !== null && $activeCode !== $orgCode) {
            return service('response')
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 400,
                    'message' => 'X-Org-Code does not match the active tenant database.',
                ]);
        }

        $organization = model(OrganizationModel::class, false)
            ->where('code', $orgCode)
            ->first();

        if ($organization === null) {
            return service('response')
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 404,
                    'message' => "Organization with code {$orgCode} not found in this database.",
                ]);
        }

        $orgId = (int) (is_object($organization) ? $organization->id : $organization['id']);

        $orgIdHeader = trim($request->getHeaderLine('X-Org-Id'));
        if ($orgIdHeader !== '') {
            if (! ctype_digit($orgIdHeader) || (int) $orgIdHeader !== $orgId) {
                return service('response')
                    ->setStatusCode(400)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 400,
                        'message' => 'X-Org-Id does not match X-Org-Code in this database.',
                    ]);
            }
        }

        return $this->applyOrg($orgId, $orgContext);
    }

    private function applyOrg(int $orgId, $orgContext)
    {
        if (! $orgContext->canAccessOrg($orgId)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 403,
                    'message' => 'You do not have access to this organization.',
                ]);
        }

        $permissionService = service('permissionService', false);
        $scoped            = $permissionService->resolveScopedOrgIds($orgId);
        $permissions       = $permissionService->permissionsForUserInOrg(
            (int) $orgContext->getUserId(),
            $orgId,
        );

        $orgContext->setActiveOrgId($orgId);
        $orgContext->setScopedOrgIds($scoped);
        $orgContext->setPermissions($permissions);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
