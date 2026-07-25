<?php

namespace App\Services;

use App\Models\OrganizationModel;
use App\Models\PermissionModel;
use App\Models\UserOrganizationRoleModel;

/**
 * Resolves permissions for a user within an active organization.
 */
class PermissionService
{
    public function __construct(
        protected UserOrganizationRoleModel $userOrgRoles = new UserOrganizationRoleModel(),
        protected PermissionModel $permissions = new PermissionModel(),
        protected OrganizationModel $organizations = new OrganizationModel(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function permissionsForUserInOrg(int $userId, int $orgId): array
    {
        $roleIds = $this->userOrgRoles->getRoleIdsForUserInOrg($userId, $orgId);

        if ($roleIds === []) {
            return [];
        }

        return $this->permissions->getKeysForRoleIds($roleIds);
    }

    /**
     * Expand active org to the set of org IDs used for data queries.
     * Kanwil → self + descendant Lapas/Rutan; unit → self only.
     *
     * @return list<int>
     */
    public function resolveScopedOrgIds(int $activeOrgId): array
    {
        $org = $this->organizations->find($activeOrgId);
        if ($org === null) {
            return [];
        }

        $type = is_object($org) ? ($org->type ?? null) : ($org['type'] ?? null);

        if ($type === 'kanwil') {
            return $this->organizations->getDescendantIds($activeOrgId, includeSelf: true);
        }

        return [$activeOrgId];
    }
}
