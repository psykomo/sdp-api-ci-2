<?php

namespace App\Models;

use CodeIgniter\Model;

class UserOrganizationRoleModel extends Model
{
    protected $table            = 'user_organization_roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['user_id', 'organization_id', 'role_id'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * @return list<array<string, mixed>>
     */
    public function getAssignmentsForUser(int $userId): array
    {
        return $this->where('user_id', $userId)->findAll();
    }

    /**
     * @return list<int>
     */
    public function getRoleIdsForUserInOrg(int $userId, int $orgId): array
    {
        $rows = $this->select('role_id')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->findAll();

        return array_map(static fn (array $row): int => (int) $row['role_id'], $rows);
    }
}
