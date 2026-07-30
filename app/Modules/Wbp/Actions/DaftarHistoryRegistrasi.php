<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Models\OrganizationModel;
use App\Modules\Referensi\Models\JenisRegistrasiModel;
use App\Modules\Wbp\Models\HistoryRegistrasiModel;
use App\Modules\Wbp\Models\PerkaraModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Modules\Wbp\Support\LegacyIdGenerator;
use App\Services\OrgContext;
use App\Services\UnitOfWork;

/**
 * R5 — append a history_registrasi snapshot for an existing perkara.
 *
 * Defaults missing fields from the current perkara row (same idea as R3/R4 append).
 */
class DaftarHistoryRegistrasi
{
    public function __construct(
        protected HistoryRegistrasiModel $history = new HistoryRegistrasiModel(),
        protected PerkaraModel $perkara = new PerkaraModel(),
        protected JenisRegistrasiModel $jenisRegistrasi = new JenisRegistrasiModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
        protected ?OrganizationModel $organizations = null,
        protected ?WbpQueryService $query = null,
        protected ?LegacyIdGenerator $ids = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
        $this->organizations ??= model(OrganizationModel::class, false);
        $this->query ??= new WbpQueryService(
            perkara: $this->perkara,
            orgContext: $this->orgContext,
            organizations: $this->organizations,
        );
        $this->ids ??= new LegacyIdGenerator();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function execute(string $idPerkara, array $data = []): array
    {
        $perkara = $this->query->findRegistrasiOrFail($idPerkara);

        $idReg = trim((string) ($data['id_reg'] ?? $data['ID_REG'] ?? $perkara['id_reg'] ?? ''));
        $isTahanan = (int) ($perkara['is_tahanan'] ?? 0);
        if ($idReg !== '' && $idReg !== (string) ($perkara['id_reg'] ?? '')) {
            $jenis = $this->jenisRegistrasi->find($idReg);
            if ($jenis === null) {
                throw new ValidationException('Validation failed.', [
                    'id_reg' => "Unknown ID_REG: {$idReg}",
                ]);
            }
            $isTahanan = (int) ($jenis['IS_TAHANAN'] ?? 0);
        }

        $idUpt = (string) ($perkara['id_upt'] ?? '');
        $today = date('Y-m-d');

        return $this->unitOfWork->run(function () use (
            $idPerkara,
            $perkara,
            $data,
            $idReg,
            $isTahanan,
            $idUpt,
            $today,
        ): array {
            $db = db_connect();

            $idHistory = trim((string) ($data['id_history_reg'] ?? $data['ID_HISTORY_REG'] ?? ''));
            if ($idHistory === '') {
                $idHistory = $this->ids->withUptPrefix(
                    $idUpt !== '' ? $idUpt : '000',
                    'history_registrasi',
                    'ID_HISTORY_REG',
                );
            } elseif ($this->history->find($idHistory) !== null) {
                throw new ValidationException('Validation failed.', [
                    'id_history_reg' => "ID_HISTORY_REG {$idHistory} already exists.",
                ]);
            }

            $row = [
                'ID_HISTORY_REG'          => $idHistory,
                'ID_PERKARA'              => $idPerkara,
                'NOMOR_INDUK'             => $perkara['nomor_induk'] ?? null,
                'ID_UPT'                  => $idUpt !== '' ? $idUpt : ($perkara['id_upt'] ?? null),
                'ID_REG'                  => $idReg !== '' ? $idReg : ($perkara['id_reg'] ?? null),
                'ID_STATUS'               => $data['id_status'] ?? $data['ID_STATUS'] ?? $perkara['id_status'] ?? null,
                'ID_SUB_STATUS'           => $data['id_sub_status'] ?? $data['ID_SUB_STATUS'] ?? $perkara['id_sub_status'] ?? null,
                'IS_TAHANAN'              => $isTahanan,
                'NMR_REG_GOL'             => $data['nmr_reg_gol'] ?? $data['NMR_REG_GOL'] ?? $perkara['nmr_reg_gol'] ?? null,
                'NMR_REG_INSTANSI'        => $data['nmr_reg_instansi'] ?? $data['NMR_REG_INSTANSI'] ?? $perkara['nmr_reg_instansi'] ?? null,
                'TGL_MSK_LAPAS'           => $data['tgl_msk_lapas'] ?? $data['TGL_MSK_LAPAS'] ?? $perkara['tgl_msk_lapas'] ?? null,
                'TGL_EKSPIRASI'           => $data['tgl_ekspirasi'] ?? $data['TGL_EKSPIRASI'] ?? $perkara['tgl_ekspirasi'] ?? null,
                'TGL_EKSPIRASI_AWAL'      => $data['tgl_ekspirasi_awal'] ?? $data['TGL_EKSPIRASI_AWAL'] ?? $perkara['tgl_ekspirasi_awal'] ?? null,
                'TGL_PERTAMA_DITAHAN'     => $data['tgl_pertama_ditahan'] ?? $data['TGL_PERTAMA_DITAHAN'] ?? $perkara['tgl_pertama_ditahan'] ?? null,
                'TGL_AKHIR_DITAHAN'       => $data['tgl_akhir_ditahan'] ?? $data['TGL_AKHIR_DITAHAN'] ?? $perkara['tgl_akhir_ditahan'] ?? null,
                'ID_INSTANSI_PENYIDIK'    => $data['id_instansi_penyidik'] ?? $data['ID_INSTANSI_PENYIDIK'] ?? $perkara['id_instansi_penyidik'] ?? null,
                'ID_INSTANSI_PENYIDIK_LAIN' => $data['id_instansi_penyidik_lain'] ?? $perkara['id_instansi_penyidik_lain'] ?? null,
                'KETERANGAN'              => $data['keterangan'] ?? $data['KETERANGAN'] ?? 'API R5 append',
                'LOKASI_SEL'              => $data['lokasi_sel'] ?? $perkara['lokasi_sel'] ?? null,
                'TAHUN_HUKUMAN'           => $data['tahun_hukuman'] ?? $perkara['tahun_hukuman'] ?? null,
                'BULAN_HUKUMAN'           => $data['bulan_hukuman'] ?? $perkara['bulan_hukuman'] ?? null,
                'HARI_HUKUMAN'            => $data['hari_hukuman'] ?? $perkara['hari_hukuman'] ?? null,
                'IS_DELETE'               => 0,
                'ID_USER'                 => null,
                'TGL_ENTRY'               => $today,
                'TGL_MG'                  => $today,
                'KONSOLIDASI'             => 0,
            ];

            if ($db->table('history_registrasi')->insert($row) === false) {
                throw new ValidationException(
                    'Failed to insert history_registrasi.',
                    ['history_registrasi' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }

            return $this->query->findHistoryOrFail($idPerkara, $idHistory);
        });
    }
}
