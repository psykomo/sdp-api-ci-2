<?php

namespace App\Modules\Wbp\Services;

use App\Modules\Wbp\Models\WbpModel;
use App\Modules\Wbp\Shared\WbpFinder;
use App\Services\OrgContext;

/**
 * All Inmate reads live here (list, search, show, lookups).
 *
 * Reads never mutate state and never need a UnitOfWork, so keeping them apart
 * from the write processes keeps both sides small as the module grows to
 * hundreds of use-cases.
 */
class WbpQueryService
{
    public function __construct(
        protected WbpModel $inmates = new WbpModel(),
        protected ?OrgContext $orgContext = null,
        protected ?WbpFinder $finder = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->finder ??= new WbpFinder($this->inmates, $this->orgContext);
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

        $items = $builder->paginate($perPage, 'wbp');
        $pager = $this->inmates->pager;

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $pager->getCurrentPage('wbp'),
                'perPage'   => $perPage,
                'total'     => $pager->getTotal('wbp'),
                'pageCount' => $pager->getPageCount('wbp'),
            ],
        ];
    }

    public function findOrFail(int|string $id): object
    {
        return $this->finder->findOrFail($id);
    }
}
