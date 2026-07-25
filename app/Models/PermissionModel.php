<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table            = 'permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['key', 'name', 'description'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * @param list<int> $roleIds
     * @return list<string>
     */
    public function getKeysForRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = $this->db->table('role_permissions rp')
            ->select('permissions.key')
            ->join('permissions', 'permissions.id = rp.permission_id')
            ->whereIn('rp.role_id', $roleIds)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_column($rows, 'key')));
    }
}
