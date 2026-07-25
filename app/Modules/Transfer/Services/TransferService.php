<?php

namespace App\Modules\Transfer\Services;

use App\Models\AuditLogModel;
use App\Models\OrganizationModel;
use App\Modules\Inmate\Services\InmateService;
use App\Modules\Transfer\Models\InmateTransferModel;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;
use RuntimeException;

/**
 * Cross-module use-case orchestrator.
 *
 * Owns the outer UnitOfWork. InmateService::transferOwnership() may start its
 * own UnitOfWork; when called here it joins this outer boundary instead of
 * committing early. Any exception rolls back every write.
 */
class TransferService
{
    public function __construct(
        protected InmateService $inmates = new InmateService(),
        protected InmateTransferModel $transfers = new InmateTransferModel(),
        protected OrganizationModel $organizations = new OrganizationModel(),
        protected AuditLogModel $audit = new AuditLogModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
    }

    /**
     * @param array{to_organization_id: int, reason: string, notes?: string|null} $command
     * @return array{transfer: object, inmate: object}
     */
    public function execute(int $inmateId, array $command): array
    {
        $toOrganizationId = (int) ($command['to_organization_id'] ?? 0);
        $reason            = trim((string) ($command['reason'] ?? ''));
        $notes             = isset($command['notes']) ? trim((string) $command['notes']) : null;

        if ($toOrganizationId <= 0 || $reason === '') {
            throw new DomainException('Destination organization and reason are required.');
        }

        $destination = $this->organizations->find($toOrganizationId);
        if ($destination === null || ! $destination->isUnit() || $destination->status !== 'active') {
            throw new DomainException('Destination must be an active Lapas or Rutan.');
        }

        $destinationIsScoped = in_array(
            $toOrganizationId,
            $this->orgContext->getScopedOrgIds(),
            true,
        );

        if (! $destinationIsScoped && ! $this->orgContext->canAccessOrg($toOrganizationId)) {
            throw new DomainException('Destination organization is outside your permitted scope.');
        }

        $sourceInmate = $this->inmates->findOrFail($inmateId);
        $fromOrganizationId = (int) $sourceInmate->organization_id;

        if ($fromOrganizationId === $toOrganizationId) {
            throw new DomainException('Source and destination organizations must differ.');
        }

        return $this->unitOfWork->run(function () use (
            $inmateId,
            $fromOrganizationId,
            $toOrganizationId,
            $reason,
            $notes,
        ): array {
            $inmate = $this->inmates->transferOwnership($inmateId, $toOrganizationId);

            $transferId = $this->transfers->insert([
                'inmate_id'               => $inmateId,
                'from_organization_id'    => $fromOrganizationId,
                'to_organization_id'      => $toOrganizationId,
                'transferred_by'          => $this->orgContext->getUserId(),
                'reason'                  => $reason,
                'notes'                   => $notes !== '' ? $notes : null,
                'transferred_at'          => date('Y-m-d H:i:s'),
            ]);

            if ($transferId === false) {
                throw new RuntimeException(
                    'Unable to record transfer: ' . implode(' ', $this->transfers->errors()),
                );
            }

            $this->audit->record(
                'inmate.transferred',
                $this->orgContext->getUserId(),
                $fromOrganizationId,
                'inmate_transfer',
                (int) $transferId,
                [
                    'inmate_id'               => $inmateId,
                    'from_organization_id'    => $fromOrganizationId,
                    'to_organization_id'      => $toOrganizationId,
                    'reason'                  => $reason,
                ],
            );

            $transfer = $this->transfers->find($transferId);
            if ($transfer === null) {
                throw new RuntimeException('Transfer record could not be reloaded.');
            }

            return [
                'transfer' => $transfer,
                'inmate'   => $inmate,
            ];
        });
    }
}
