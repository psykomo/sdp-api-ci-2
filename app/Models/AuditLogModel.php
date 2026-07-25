<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'user_id',
        'organization_id',
        'action',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
        'meta',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected bool $allowEmptyInserts = true;

    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        string $action,
        ?int $userId = null,
        ?int $organizationId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null,
    ): void {
        $request = service('request');

        $this->insert([
            'user_id'         => $userId,
            'organization_id' => $organizationId,
            'action'          => $action,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'ip_address'      => $request->getIPAddress(),
            'user_agent'      => $request->getUserAgent()->getAgentString(),
            'meta'            => $meta !== null ? json_encode($meta) : null,
        ]);
    }
}
