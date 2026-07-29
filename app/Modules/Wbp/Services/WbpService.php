<?php

namespace App\Modules\Wbp\Services;

use App\Exceptions\ValidationException;
use App\Modules\Wbp\Actions\DaftarWbp;
use App\Modules\Wbp\Shared\WbpAuditWriter;
use App\Modules\Wbp\Shared\WbpFinder;
use App\Modules\Wbp\Models\WbpModel;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;
use RuntimeException;

/**
 * Facade / composition root for the Wbp module.
 *
 * Public surface for controllers and other modules. Reads go through
 * WbpQueryService; multi-step writes may live in Actions and still be
 * reachable here when other modules need them.
 */
class WbpService
{
    protected WbpModel $inmates;

    protected OrgContext $orgContext;

    protected UnitOfWork $unitOfWork;

    protected WbpQueryService $query;

    protected WbpFinder $finder;

    protected DaftarWbp $register;

    protected WbpAuditWriter $auditWriter;

    public function __construct(
        ?WbpModel $inmates = null,
        ?OrgContext $orgContext = null,
        ?UnitOfWork $unitOfWork = null,
    ) {
        $this->inmates     = $inmates ?? new WbpModel();
        $this->orgContext  = $orgContext ?? service('orgContext');
        $this->unitOfWork  = $unitOfWork ?? service('unitOfWork');
        $this->finder      = new WbpFinder($this->inmates, $this->orgContext);
        $this->auditWriter = new WbpAuditWriter(orgContext: $this->orgContext);
        $this->query       = new WbpQueryService($this->inmates, $this->orgContext, $this->finder);
        $this->register    = new DaftarWbp($this->inmates, $this->auditWriter, $this->orgContext, $this->unitOfWork);
    }

    /**
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function list(int $perPage = 10, ?string $search = null): array
    {
        return $this->query->list($perPage, $search);
    }

    public function findOrFail(int|string $id): object
    {
        return $this->query->findOrFail($id);
    }

    /**
     * Register a new inmate — delegates to the DaftarWbp action.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): object
    {
        return $this->register->execute($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): object
    {
        $this->findOrFail($id);

        unset($data['organization_id']);

        return $this->unitOfWork->run(function () use ($id, $data): object {
            if ($this->inmates->update($id, $data) === false) {
                throw new ValidationException(
                    'Validation failed.',
                    $this->inmates->errors(),
                );
            }

            $this->auditWriter->record('wbp.updated', (int) $id);

            $inmate = $this->inmates->find($id);
            if ($inmate === null) {
                throw new RuntimeException('Updated inmate could not be reloaded.');
            }

            return $inmate;
        });
    }

    public function delete(int|string $id): void
    {
        $this->findOrFail($id);

        $this->unitOfWork->run(function () use ($id): void {
            $this->inmates->delete($id);
            $this->auditWriter->record('wbp.deleted', (int) $id);
        });
    }

    /**
     * Module-local process that may also run inside MutasiService's UnitOfWork.
     *
     * Alone  → starts and commits its own transaction.
     * Nested → joins the outer transfer transaction; no early commit.
     */
    public function transferOwnership(int $id, int $toOrganizationId): object
    {
        return $this->unitOfWork->run(function () use ($id, $toOrganizationId): object {
            $this->findOrFail($id);

            if ($this->inmates->update($id, [
                'organization_id' => $toOrganizationId,
                'status'          => 'active',
            ]) === false) {
                throw new ValidationException(
                    'Unable to transfer inmate.',
                    $this->inmates->errors(),
                );
            }

            $inmate = $this->inmates->find($id);
            if ($inmate === null) {
                throw new RuntimeException('Transferred inmate could not be reloaded.');
            }

            return $inmate;
        });
    }
}
