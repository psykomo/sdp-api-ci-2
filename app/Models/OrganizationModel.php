<?php

namespace App\Models;

use CodeIgniter\Model;

class OrganizationModel extends Model
{
    protected $table            = 'organizations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = \App\Entities\Organization::class;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'parent_id',
        'code',
        'name',
        'type',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'code' => 'required|max_length[50]|is_unique[organizations.code,id,{id}]',
        'name' => 'required|max_length[255]',
        'type' => 'required|in_list[kanwil,lapas,rutan]',
    ];

    /**
     * @return list<int>
     */
    public function getDescendantIds(int $parentId, bool $includeSelf = true): array
    {
        $ids = $includeSelf ? [$parentId] : [];
        $queue = [$parentId];

        while ($queue !== []) {
            $current = array_shift($queue);
            $children = $this->select('id')
                ->where('parent_id', $current)
                ->findAll();

            foreach ($children as $child) {
                $id = is_object($child) ? (int) $child->id : (int) $child['id'];
                if (! in_array($id, $ids, true)) {
                    $ids[]  = $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }
}
