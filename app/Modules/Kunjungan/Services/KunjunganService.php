<?php

namespace App\Modules\Kunjungan\Services;

use App\Exceptions\ValidationException;
use App\Modules\Kunjungan\Models\KunjunganModel;
use App\Services\OrgContext;
use CodeIgniter\Exceptions\PageNotFoundException;
use DomainException;

/**
 * Thin-module reference: one service for reads + writes.
 *
 * No Actions / QueryService / Shared — grow only when this class bloats.
 * See docs/ARCHITECTURE.md → Implementation references.
 */
class KunjunganService
{
    public function __construct(
        protected KunjunganModel $visits = new KunjunganModel(),
        protected ?OrgContext $orgContext = null,
    ) {
        $this->orgContext ??= \Config\Services::orgContext();
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

        $builder = $this->visits->whereIn('organization_id', $scoped);

        if ($search) {
            $builder = $builder->groupStart()
                ->like('visitor_name', $search)
                ->orLike('visitor_id_number', $search)
                ->groupEnd();
        }

        $items = $builder->orderBy('visited_at', 'DESC')->paginate($perPage, 'kunjungan');
        $pager = $this->visits->pager;

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $pager->getCurrentPage('kunjungan'),
                'perPage'   => $perPage,
                'total'     => $pager->getTotal('kunjungan'),
                'pageCount' => $pager->getPageCount('kunjungan'),
            ],
        ];
    }

    public function findOrFail(int|string $id): object
    {
        $visit = $this->visits->find($id);

        if ($visit === null || ! $this->isInScope((int) $visit->organization_id)) {
            throw PageNotFoundException::forPageNotFound("Visit with ID {$id} not found.");
        }

        return $visit;
    }

    /**
     * Schedule a visit against the active organization.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): object
    {
        $activeOrgId = $this->orgContext->getActiveOrgId();
        if ($activeOrgId === null) {
            throw new DomainException('No active organization in context.');
        }

        // Process invariants — never trust client org binding.
        $data['organization_id'] = $activeOrgId;
        $data['status']          = $data['status'] ?? 'scheduled';

        $id = $this->visits->insert($data);
        if ($id === false) {
            throw new ValidationException('Validation failed.', $this->visits->errors());
        }

        $visit = $this->visits->find($id);
        if ($visit === null) {
            throw new DomainException('Created visit could not be reloaded.');
        }

        return $visit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): object
    {
        $this->findOrFail($id);

        unset($data['organization_id']);

        if ($this->visits->update($id, $data) === false) {
            throw new ValidationException('Validation failed.', $this->visits->errors());
        }

        $visit = $this->visits->find($id);
        if ($visit === null) {
            throw new DomainException('Updated visit could not be reloaded.');
        }

        return $visit;
    }

    public function delete(int|string $id): void
    {
        $this->findOrFail($id);
        $this->visits->delete($id);
    }

    private function isInScope(int $organizationId): bool
    {
        return in_array($organizationId, $this->orgContext->getScopedOrgIds(), true);
    }
}
