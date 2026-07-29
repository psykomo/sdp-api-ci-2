<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Modules\Wbp\Models\WbpModel;
use App\Modules\Wbp\Shared\WbpAuditWriter;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;
use RuntimeException;

/**
 * Business process: register (intake) a new inmate into the active unit.
 */
class DaftarWbp
{
    public function __construct(
        protected WbpModel $inmates = new WbpModel(),
        protected WbpAuditWriter $audit = new WbpAuditWriter(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): object
    {
        $activeOrgId = $this->orgContext->getActiveOrgId();
        if ($activeOrgId === null) {
            throw new DomainException('No active organization in context.');
        }

        // Org and initial status are process invariants, not caller input.
        $data['organization_id'] = $activeOrgId;
        $data['status']          = 'active';

        return $this->unitOfWork->run(function () use ($data, $activeOrgId): object {
            $id = $this->inmates->insert($data);
            if ($id === false) {
                throw new ValidationException(
                    'Validation failed.',
                    $this->inmates->errors(),
                );
            }

            $inmate = $this->inmates->find($id);
            if ($inmate === null) {
                throw new RuntimeException('Registered inmate could not be reloaded.');
            }

            $this->audit->record('wbp.registered', (int) $id, $activeOrgId);

            return $inmate;
        });
    }
}
