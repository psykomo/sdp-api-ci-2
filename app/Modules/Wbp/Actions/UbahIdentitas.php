<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Modules\Wbp\Models\IdentitasModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Modules\Wbp\Support\IdentitasFieldMap;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use RuntimeException;

/**
 * R2 — update identitas on legacy table (no PK change; no sidik cascade).
 */
class UbahIdentitas
{
    public function __construct(
        protected IdentitasModel $identitas = new IdentitasModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
        protected ?WbpQueryService $query = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
        $this->query ??= new WbpQueryService(
            identitas: $this->identitas,
            orgContext: $this->orgContext,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function execute(string $nomorInduk, array $data): array
    {
        // Scope check via query service
        $this->query->findOrFail($nomorInduk);

        $row = IdentitasFieldMap::toDb($data, forUpdate: true);
        unset($row['NOMOR_INDUK'], $row['IS_DELETED']);

        if ($row === []) {
            throw new ValidationException('Validation failed.', [
                'body' => 'No updatable fields provided.',
            ]);
        }

        if (isset($row['NAMA_LENGKAP']) && trim((string) $row['NAMA_LENGKAP']) === '') {
            throw new ValidationException('Validation failed.', [
                'nama_lengkap' => 'Nama lengkap cannot be empty.',
            ]);
        }

        $row['UPDATED'] = date('Y-m-d H:i:s');
        $userId         = $this->orgContext->getUserId();
        if ($userId !== null) {
            $row['UPDATED_BY'] = (string) $userId;
        }

        return $this->unitOfWork->run(function () use ($nomorInduk, $row): array {
            // CI4 Model::update with string PK
            $ok = $this->identitas->update($nomorInduk, $row);
            if ($ok === false) {
                throw new ValidationException(
                    'Failed to update identitas.',
                    $this->identitas->errors() ?: ['database' => 'Update failed.'],
                );
            }

            try {
                return $this->query->findOrFail($nomorInduk);
            } catch (\Throwable) {
                throw new RuntimeException("Updated identitas {$nomorInduk} could not be reloaded.");
            }
        });
    }
}
