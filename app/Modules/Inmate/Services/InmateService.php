<?php

namespace App\Modules\Inmate\Services;

use App\Models\AuditLogModel;
use App\Modules\Inmate\Models\InmateModel;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

/**
 * Inmate business logic — always scoped by OrgContext.
 *
 * Multi-write business processes use UnitOfWork. When called alone they own
 * the transaction; when called from TransferService (or any outer UnitOfWork)
 * they join that larger atomic boundary.
 */
class InmateService
{
    public function __construct(
        protected InmateModel $inmates = new InmateModel(),
        protected ?OrgContext $orgContext = null,
        protected AuditLogModel $audit = new AuditLogModel(),
        protected ?UnitOfWork $unitOfWork = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
    }

    /**
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function list(int $perPage = 10, ?string $search = null): array
    {
        $scoped = $this->orgContext->getScopedOrgIds();
        if ($scoped === []) {
            return [
                'items' => [],
                'meta'  => ['page' => 1, 'perPage' => $perPage, 'total' => 0, 'pageCount' => 0],
            ];
        }

        $builder = $this->inmates->whereIn('organization_id', $scoped);

        if ($search) {
            $builder = $builder->groupStart()
                ->like('full_name', $search)
                ->orLike('registration_number', $search)
                ->orLike('alias_name', $search)
                ->groupEnd();
        }

        $items = $builder->paginate($perPage, 'inmates');
        $pager = $this->inmates->pager;

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $pager->getCurrentPage('inmates'),
                'perPage'   => $perPage,
                'total'     => $pager->getTotal('inmates'),
                'pageCount' => $pager->getPageCount('inmates'),
            ],
        ];
    }

    public function findOrFail(int|string $id): object
    {
        $inmate = $this->inmates->find($id);

        if ($inmate === null || ! $this->isInScope((int) $inmate->organization_id)) {
            throw PageNotFoundException::forPageNotFound("Inmate with ID {$id} not found.");
        }

        return $inmate;
    }

    /**
     * Module-local multi-write process (insert + audit).
     *
     * Safe to call alone or from a larger UnitOfWork.
     *
     * @param array<string, mixed> $data
     * @return object|false
     */
    public function create(array $data): object|false
    {
        $activeOrgId = $this->orgContext->getActiveOrgId();
        if ($activeOrgId === null) {
            return false;
        }

        $data['organization_id'] = $activeOrgId;
        $data['status'] ??= 'active';

        try {
            return $this->unitOfWork->run(function () use ($data, $activeOrgId): object {
                $id = $this->inmates->insert($data);
                if ($id === false) {
                    throw new RuntimeException(
                        'Unable to create inmate: ' . implode(' ', $this->inmates->errors()),
                    );
                }

                $inmate = $this->inmates->find($id);
                if ($inmate === null) {
                    throw new RuntimeException('Created inmate could not be reloaded.');
                }

                $this->audit->record(
                    'inmate.created',
                    $this->orgContext->getUserId(),
                    $activeOrgId,
                    'inmate',
                    (int) $id,
                );

                return $inmate;
            });
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return object|false
     */
    public function update(int|string $id, array $data): object|false
    {
        $this->findOrFail($id);

        unset($data['organization_id']);

        try {
            return $this->unitOfWork->run(function () use ($id, $data): object {
                if ($this->inmates->update($id, $data) === false) {
                    throw new RuntimeException(
                        'Unable to update inmate: ' . implode(' ', $this->inmates->errors()),
                    );
                }

                $this->audit->record(
                    'inmate.updated',
                    $this->orgContext->getUserId(),
                    $this->orgContext->getActiveOrgId(),
                    'inmate',
                    (int) $id,
                );

                $inmate = $this->inmates->find($id);
                if ($inmate === null) {
                    throw new RuntimeException('Updated inmate could not be reloaded.');
                }

                return $inmate;
            });
        } catch (RuntimeException) {
            return false;
        }
    }

    public function delete(int|string $id): void
    {
        $this->findOrFail($id);

        $this->unitOfWork->run(function () use ($id): void {
            $this->inmates->delete($id);

            $this->audit->record(
                'inmate.deleted',
                $this->orgContext->getUserId(),
                $this->orgContext->getActiveOrgId(),
                'inmate',
                (int) $id,
            );
        });
    }

    /**
     * Module-local process that may also run inside TransferService's UnitOfWork.
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
                throw new RuntimeException(
                    'Unable to transfer inmate: ' . implode(' ', $this->inmates->errors()),
                );
            }

            $inmate = $this->inmates->find($id);

            if ($inmate === null) {
                throw new RuntimeException('Transferred inmate could not be reloaded.');
            }

            return $inmate;
        });
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->inmates->errors();
    }

    private function isInScope(int $organizationId): bool
    {
        return in_array($organizationId, $this->orgContext->getScopedOrgIds(), true);
    }
}
