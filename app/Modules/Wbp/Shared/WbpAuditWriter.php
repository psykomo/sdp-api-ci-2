<?php

namespace App\Modules\Wbp\Shared;

use App\Models\AuditLogModel;
use App\Services\OrgContext;

/**
 * Thin, domain-specific wrapper over the generic AuditLogModel.
 *
 * Every Wbp process logs "who did what to which WBP" the same way, so
 * the entity_type + actor + org plumbing lives here once. Actions just call
 * ->record('wbp.released', $id).
 */
class WbpAuditWriter
{
    public function __construct(
        protected AuditLogModel $audit = new AuditLogModel(),
        protected ?OrgContext $orgContext = null,
    ) {
        $this->orgContext ??= service('orgContext');
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(string $action, int $inmateId, ?int $organizationId = null, ?array $meta = null): void
    {
        $this->audit->record(
            $action,
            $this->orgContext->getUserId(),
            $organizationId ?? $this->orgContext->getActiveOrgId(),
            'wbp',
            $inmateId,
            $meta,
        );
    }
}
