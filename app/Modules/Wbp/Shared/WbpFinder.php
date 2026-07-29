<?php

namespace App\Modules\Wbp\Shared;

use App\Modules\Wbp\Models\WbpModel;
use App\Services\OrgContext;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Org-scoped lookups shared by every Inmate business process.
 *
 * This is the ONE place that knows "an inmate belongs to the caller only if
 * its organization is inside the caller's scope". Query services and actions
 * reuse it instead of re-implementing the check, so the rule can never drift.
 */
class WbpFinder
{
    public function __construct(
        protected WbpModel $inmates = new WbpModel(),
        protected ?OrgContext $orgContext = null,
    ) {
        $this->orgContext ??= service('orgContext');
    }

    /**
     * @throws PageNotFoundException when the inmate is missing or out of scope
     */
    public function findOrFail(int|string $id): object
    {
        $inmate = $this->inmates->find($id);

        if ($inmate === null || ! $this->isInScope((int) $inmate->organization_id)) {
            throw PageNotFoundException::forPageNotFound("Inmate with ID {$id} not found.");
        }

        return $inmate;
    }

    public function isInScope(int $organizationId): bool
    {
        return in_array($organizationId, $this->orgContext->getScopedOrgIds(), true);
    }
}
