<?php

namespace App\Modules\Inmate\Actions;

use App\Modules\Inmate\Models\InmateModel;
use App\Modules\Inmate\Shared\InmateAuditWriter;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;
use RuntimeException;

/**
 * Business process: register (intake) a new inmate into the active unit.
 *
 * One process = one class with a single execute(). Multi-write, so it runs
 * inside a UnitOfWork and is safe to nest inside a larger transaction.
 */
class RegisterInmate
{
    public function __construct(
        protected InmateModel $inmates = new InmateModel(),
        protected InmateAuditWriter $audit = new InmateAuditWriter(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws DomainException  when there is no active organization in context
     * @throws RuntimeException when persistence/validation fails (carries model errors)
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
                throw new RuntimeException(
                    'Unable to register inmate: ' . implode(' ', $this->inmates->errors()),
                );
            }

            $inmate = $this->inmates->find($id);
            if ($inmate === null) {
                throw new RuntimeException('Registered inmate could not be reloaded.');
            }

            $this->audit->record('inmate.registered', (int) $id, $activeOrgId);

            return $inmate;
        });
    }

    /**
     * Validation errors from the last failed execute(), for HTTP 422 payloads.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->inmates->errors();
    }
}
