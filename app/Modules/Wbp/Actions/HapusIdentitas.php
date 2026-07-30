<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Modules\Wbp\Models\IdentitasModel;
use App\Modules\Wbp\Models\PerkaraModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;

/**
 * R2 soft-delete identitas (IS_DELETED=1 only — no sidik_jari/usertbl cascade).
 */
class HapusIdentitas
{
    public function __construct(
        protected IdentitasModel $identitas = new IdentitasModel(),
        protected PerkaraModel $perkara = new PerkaraModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
        protected ?WbpQueryService $query = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
        $this->query ??= new WbpQueryService(
            identitas: $this->identitas,
            perkara: $this->perkara,
            orgContext: $this->orgContext,
        );
    }

    public function execute(string $nomorInduk): void
    {
        $this->query->findOrFail($nomorInduk);

        $activePerkara = $this->perkara->builder()
            ->where('NOMOR_INDUK', $nomorInduk)
            ->where('IS_DELETE', 0)
            ->countAllResults();

        if ($activePerkara > 0) {
            throw new DomainException(
                "Cannot soft-delete identitas {$nomorInduk}: {$activePerkara} active perkara remain.",
            );
        }

        $this->unitOfWork->run(function () use ($nomorInduk): void {
            $ok = $this->identitas->update($nomorInduk, [
                'IS_DELETED' => 1,
                'UPDATED'    => date('Y-m-d H:i:s'),
                'UPDATED_BY' => $this->orgContext->getUserId() !== null
                    ? (string) $this->orgContext->getUserId()
                    : null,
            ]);
            if ($ok === false) {
                throw new ValidationException(
                    'Failed to soft-delete identitas.',
                    $this->identitas->errors() ?: ['database' => 'Delete failed.'],
                );
            }
        });
    }
}
