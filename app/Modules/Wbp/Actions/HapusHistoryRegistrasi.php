<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Models\OrganizationModel;
use App\Modules\Wbp\Models\HistoryRegistrasiModel;
use App\Modules\Wbp\Models\PerkaraModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Services\OrgContext;
use App\Services\UnitOfWork;

/**
 * R5 — soft-delete history_registrasi (IS_DELETE=1, KONSOLIDASI=1).
 */
class HapusHistoryRegistrasi
{
    public function __construct(
        protected HistoryRegistrasiModel $history = new HistoryRegistrasiModel(),
        protected PerkaraModel $perkara = new PerkaraModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
        protected ?OrganizationModel $organizations = null,
        protected ?WbpQueryService $query = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
        $this->organizations ??= model(OrganizationModel::class, false);
        $this->query ??= new WbpQueryService(
            perkara: $this->perkara,
            orgContext: $this->orgContext,
            organizations: $this->organizations,
        );
    }

    /**
     * @return array{id_history_reg: string, id_perkara: string, is_delete: int}
     */
    public function execute(string $idPerkara, string $idHistoryReg): array
    {
        $this->query->findRegistrasiOrFail($idPerkara);
        $this->query->findHistoryOrFail($idPerkara, $idHistoryReg);

        return $this->unitOfWork->run(function () use ($idPerkara, $idHistoryReg): array {
            $db = db_connect();
            $ok = $db->table('history_registrasi')
                ->where('ID_HISTORY_REG', $idHistoryReg)
                ->where('ID_PERKARA', $idPerkara)
                ->update([
                    'IS_DELETE'   => 1,
                    'KONSOLIDASI' => 1,
                ]);

            if ($ok === false) {
                throw new ValidationException(
                    'Failed to soft-delete history_registrasi.',
                    ['history_registrasi' => $db->error()['message'] ?? 'Update failed.'],
                );
            }

            return [
                'id_history_reg' => $idHistoryReg,
                'id_perkara'     => $idPerkara,
                'is_delete'      => 1,
            ];
        });
    }
}
