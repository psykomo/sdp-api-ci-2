<?php

namespace App\Modules\Inmate\Actions;

use App\Exceptions\ValidationException;
use App\Modules\Inmate\Models\InmateModel;
use App\Modules\Inmate\Models\InmateReleaseModel;
use App\Modules\Inmate\Shared\InmateAuditWriter;
use App\Modules\Inmate\Shared\InmateFinder;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;
use RuntimeException;

/**
 * Business process: release (pembebasan) an inmate.
 *
 * Real multi-write use-case:
 *   1. flip the inmate's status to "released"
 *   2. record an immutable release document (basis, date, officer)
 *   3. write an audit trail entry
 *
 * All three succeed or none do — wrapped in a UnitOfWork. Because the wrapper
 * is nest-safe, this same action can later be reused inside a bigger workflow
 * (e.g. a "batch amnesty" process) without changing a line.
 */
class ReleaseInmate
{
    /** Only an active inmate can be released. */
    private const RELEASABLE_STATUSES = ['active'];

    /** Legal basis accepted by the system. */
    private const VALID_RELEASE_TYPES = ['bebas_murni', 'cmb', 'pb', 'cb', 'asimilasi', 'amnesti'];

    public function __construct(
        protected InmateModel $inmates = new InmateModel(),
        protected InmateReleaseModel $releases = new InmateReleaseModel(),
        protected InmateFinder $finder = new InmateFinder(),
        protected InmateAuditWriter $audit = new InmateAuditWriter(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
    }

    /**
     * @param array{release_type: string, release_date: string, decree_number?: string|null, notes?: string|null} $command
     * @return array{inmate: object, release: object}
     *
     * @throws \CodeIgniter\Exceptions\PageNotFoundException when inmate is missing/out of scope
     * @throws DomainException  when a business rule is violated
     * @throws RuntimeException when persistence fails
     */
    public function execute(int $inmateId, array $command): array
    {
        // Scope + existence check first (throws 404 upstream).
        $inmate = $this->finder->findOrFail($inmateId);

        $releaseType = trim((string) ($command['release_type'] ?? ''));
        $releaseDate = trim((string) ($command['release_date'] ?? ''));
        $decree      = isset($command['decree_number']) ? trim((string) $command['decree_number']) : null;
        $notes       = isset($command['notes']) ? trim((string) $command['notes']) : null;

        // Business-rule guards — kept here, not in the controller or model.
        if (! in_array($inmate->status, self::RELEASABLE_STATUSES, true)) {
            throw new DomainException("Inmate is '{$inmate->status}' and cannot be released.");
        }
        if (! in_array($releaseType, self::VALID_RELEASE_TYPES, true)) {
            throw new DomainException('Invalid release type: ' . ($releaseType ?: '(empty)') . '.');
        }
        if ($releaseDate === '') {
            throw new DomainException('Release date is required.');
        }

        $organizationId = (int) $inmate->organization_id;

        return $this->unitOfWork->run(function () use (
            $inmateId,
            $organizationId,
            $releaseType,
            $releaseDate,
            $decree,
            $notes,
        ): array {
            if ($this->inmates->update($inmateId, ['status' => 'released']) === false) {
                throw new ValidationException(
                    'Unable to update inmate status.',
                    $this->inmates->errors(),
                );
            }

            $releaseId = $this->releases->insert([
                'inmate_id'       => $inmateId,
                'organization_id' => $organizationId,
                'release_type'    => $releaseType,
                'release_date'    => $releaseDate,
                'decree_number'   => $decree !== '' ? $decree : null,
                'released_by'     => $this->orgContext->getUserId(),
                'notes'           => $notes !== '' ? $notes : null,
            ]);

            if ($releaseId === false) {
                throw new ValidationException(
                    'Unable to record release.',
                    $this->releases->errors(),
                );
            }

            $this->audit->record('inmate.released', $inmateId, $organizationId, [
                'release_id'   => (int) $releaseId,
                'release_type' => $releaseType,
                'release_date' => $releaseDate,
            ]);

            $inmate  = $this->inmates->find($inmateId);
            $release = $this->releases->find($releaseId);

            if ($inmate === null || $release === null) {
                throw new RuntimeException('Release result could not be reloaded.');
            }

            return ['inmate' => $inmate, 'release' => $release];
        });
    }
}
