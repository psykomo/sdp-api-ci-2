<?php

namespace App\Modules\Inmate\Shared;

use App\Models\AuditLogModel;
use App\Services\OrgContext;

/**
 * Thin, domain-specific wrapper over the generic AuditLogModel.
 *
 * Every Inmate process logs "who did what to which inmate" the same way, so
 * the entity_type + actor + org plumbing lives here once. Actions just call
 * ->record('inmate.released', $id).
 */
class InmateAuditWriter
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
            'inmate',
            $inmateId,
            $meta,
        );
    }
}
