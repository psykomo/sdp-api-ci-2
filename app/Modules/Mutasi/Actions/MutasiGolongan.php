<?php

namespace App\Modules\Mutasi\Actions;

use App\Exceptions\ValidationException;
use App\Modules\Mutasi\Models\MutasiGolonganModel;
use App\Modules\Referensi\Models\JenisRegistrasiModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Modules\Wbp\Support\LegacyIdGenerator;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;

/**
 * M1 — mutasi golongan (change perkara.ID_REG via mutasi_golongan record).
 *
 * One UnitOfWork:
 *  1. insert mutasi_golongan
 *  2. update perkara (ID_REG, IS_TAHANAN, optional nmr_reg_gol / dates)
 *  3. append history_registrasi snapshot
 *
 * Not in spine: full kejahatan/hukuman form re-entry, SPPT-TI, ekspirasi engine.
 */
class MutasiGolongan
{
    public function __construct(
        protected MutasiGolonganModel $mutasi = new MutasiGolonganModel(),
        protected JenisRegistrasiModel $jenisRegistrasi = new JenisRegistrasiModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
        protected ?WbpQueryService $wbpQuery = null,
        protected ?LegacyIdGenerator $ids = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
        $this->wbpQuery ??= new WbpQueryService(orgContext: $this->orgContext);
        $this->ids ??= new LegacyIdGenerator();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $idPerkara = trim((string) ($data['id_perkara'] ?? $data['ID_PERKARA'] ?? ''));
        $idRegAkhir = trim((string) (
            $data['id_reg_akhir']
            ?? $data['ID_REG_AKHIR']
            ?? $data['mutasi_ke']
            ?? $data['id_reg']
            ?? ''
        ));

        $errors = [];
        if ($idPerkara === '') {
            $errors['id_perkara'] = 'Required.';
        }
        if ($idRegAkhir === '') {
            $errors['id_reg_akhir'] = 'Required (target golongan / mutasi_ke).';
        }
        if ($errors !== []) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $perkara = $this->wbpQuery->findRegistrasiOrFail($idPerkara);
        $idRegAwal = trim((string) (
            $data['id_reg_awal']
            ?? $data['ID_REG_AWAL']
            ?? $perkara['id_reg']
            ?? ''
        ));

        if ($idRegAwal === '') {
            throw new ValidationException('Validation failed.', [
                'id_reg_awal' => 'Cannot determine source ID_REG from perkara.',
            ]);
        }

        if ($idRegAwal === $idRegAkhir) {
            throw new DomainException('id_reg_akhir must differ from current id_reg (id_reg_awal).');
        }

        $jenisAkhir = $this->jenisRegistrasi->find($idRegAkhir);
        if ($jenisAkhir === null) {
            throw new ValidationException('Validation failed.', [
                'id_reg_akhir' => "Unknown ID_REG: {$idRegAkhir}",
            ]);
        }

        $allowAny = ! empty($data['allow_any_reg']);
        if (! $allowAny) {
            $this->assertLevelProgression($idRegAwal, $jenisAkhir);
        }

        $isTahanan = (int) ($jenisAkhir['IS_TAHANAN'] ?? 0);
        $idUpt     = (string) ($perkara['id_upt'] ?? '');
        $today     = date('Y-m-d');
        $tglEfektif = (string) ($data['tgl_efektif'] ?? $data['TGL_EFEKTIF'] ?? $today);
        $userId    = $this->orgContext->getUserId();

        return $this->unitOfWork->run(function () use (
            $data,
            $idPerkara,
            $idRegAwal,
            $idRegAkhir,
            $isTahanan,
            $idUpt,
            $today,
            $tglEfektif,
            $userId,
            $perkara,
        ): array {
            $db = db_connect();

            $idMutasi = trim((string) ($data['id_mutasi_tahanan'] ?? $data['ID_MUTASI_TAHANAN'] ?? ''));
            if ($idMutasi === '') {
                $idMutasi = $this->ids->withUptPrefix(
                    $idUpt !== '' ? $idUpt : '000',
                    'mutasi_golongan',
                    'ID_MUTASI_TAHANAN',
                );
            } elseif ($this->mutasi->find($idMutasi) !== null) {
                throw new ValidationException('Validation failed.', [
                    'id_mutasi_tahanan' => "ID_MUTASI_TAHANAN {$idMutasi} already exists.",
                ]);
            }

            $mutasiRow = [
                'ID_MUTASI_TAHANAN' => $idMutasi,
                'ID_PERKARA'       => $idPerkara,
                'ID_REG_AWAL'      => $idRegAwal,
                'ID_REG_AKHIR'     => $idRegAkhir,
                'NMR_SRT_MG'       => $data['nmr_srt_mg'] ?? $data['NMR_SRT_MG'] ?? null,
                'TGL_SRT_MG'       => $data['tgl_srt_mg'] ?? $data['TGL_SRT_MG'] ?? null,
                'TGL_EFEKTIF'      => $tglEfektif,
                'PENANDATANGAN'    => $data['penandatangan'] ?? $data['PENANDATANGAN'] ?? null,
                'KETERANGAN'       => $data['keterangan']
                    ?? $data['keterangan_mutasi']
                    ?? $data['KETERANGAN']
                    ?? 'API M1 mutasi golongan',
                'TGL_ENTRY'        => $today,
                'ID_USER'          => null, // FK → pengguna, not API users
                'KONSOLIDASI'      => 0,
            ];

            if ($db->table('mutasi_golongan')->insert($mutasiRow) === false) {
                throw new ValidationException(
                    'Failed to insert mutasi_golongan.',
                    ['mutasi_golongan' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }

            $perkaraUpdate = [
                'ID_REG'     => $idRegAkhir,
                'IS_TAHANAN' => $isTahanan,
            ];

            if (array_key_exists('nmr_reg_gol', $data) || array_key_exists('NMR_REG_GOL', $data)) {
                $perkaraUpdate['NMR_REG_GOL'] = $data['nmr_reg_gol'] ?? $data['NMR_REG_GOL'];
            }
            if (array_key_exists('tgl_ekspirasi', $data) || array_key_exists('TGL_EKSPIRASI', $data)) {
                $perkaraUpdate['TGL_EKSPIRASI'] = $data['tgl_ekspirasi'] ?? $data['TGL_EKSPIRASI'];
            }
            if (array_key_exists('tgl_ekspirasi_awal', $data) || array_key_exists('TGL_EKSPIRASI_AWAL', $data)) {
                $perkaraUpdate['TGL_EKSPIRASI_AWAL'] = $data['tgl_ekspirasi_awal'] ?? $data['TGL_EKSPIRASI_AWAL'];
            }
            if (array_key_exists('id_status', $data) || array_key_exists('ID_STATUS', $data)) {
                $perkaraUpdate['ID_STATUS'] = $data['id_status'] ?? $data['ID_STATUS'];
            }
            if (array_key_exists('id_sub_status', $data) || array_key_exists('ID_SUB_STATUS', $data)) {
                $perkaraUpdate['ID_SUB_STATUS'] = $data['id_sub_status'] ?? $data['ID_SUB_STATUS'];
            }

            // Mirror surat mutasi fields on perkara when present (legacy often does)
            if (array_key_exists('nmr_srt_mg', $data) || array_key_exists('NMR_SRT_MG', $data)) {
                $perkaraUpdate['NMR_SRT_MG'] = $data['nmr_srt_mg'] ?? $data['NMR_SRT_MG'];
            }
            if (array_key_exists('tgl_srt_mg', $data) || array_key_exists('TGL_SRT_MG', $data)) {
                $perkaraUpdate['TGL_SRT_MG'] = $data['tgl_srt_mg'] ?? $data['TGL_SRT_MG'];
            }
            $perkaraUpdate['TGL_EFEKTIF'] = $tglEfektif;

            if ($db->table('perkara')->where('ID_PERKARA', $idPerkara)->update($perkaraUpdate) === false) {
                throw new ValidationException(
                    'Failed to update perkara after mutasi.',
                    ['perkara' => $db->error()['message'] ?? 'Update failed.'],
                );
            }

            $fresh = $db->table('perkara')->where('ID_PERKARA', $idPerkara)->get()->getRowArray();
            if ($fresh === null) {
                throw new ValidationException('Perkara missing after mutasi.', ['id_perkara' => $idPerkara]);
            }

            $idHistory = $this->ids->withUptPrefix(
                $idUpt !== '' ? $idUpt : '000',
                'history_registrasi',
                'ID_HISTORY_REG',
            );
            $historyRow = [
                'ID_HISTORY_REG'       => $idHistory,
                'ID_PERKARA'           => $idPerkara,
                'NOMOR_INDUK'          => $fresh['NOMOR_INDUK'] ?? null,
                'ID_UPT'               => $fresh['ID_UPT'] ?? null,
                'ID_REG'               => $fresh['ID_REG'] ?? null,
                'ID_STATUS'            => $fresh['ID_STATUS'] ?? null,
                'ID_SUB_STATUS'        => $fresh['ID_SUB_STATUS'] ?? null,
                'IS_TAHANAN'           => $fresh['IS_TAHANAN'] ?? null,
                'NMR_REG_GOL'          => $fresh['NMR_REG_GOL'] ?? null,
                'TGL_MSK_LAPAS'        => $fresh['TGL_MSK_LAPAS'] ?? null,
                'TGL_EKSPIRASI'        => $fresh['TGL_EKSPIRASI'] ?? null,
                'TGL_EKSPIRASI_AWAL'   => $fresh['TGL_EKSPIRASI_AWAL'] ?? null,
                'TGL_PERTAMA_DITAHAN'  => $fresh['TGL_PERTAMA_DITAHAN'] ?? null,
                'TGL_AKHIR_DITAHAN'    => $fresh['TGL_AKHIR_DITAHAN'] ?? null,
                'ID_INSTANSI_PENYIDIK' => $fresh['ID_INSTANSI_PENYIDIK'] ?? null,
                'NMR_SRT_MG'           => $mutasiRow['NMR_SRT_MG'],
                'TGL_SRT_MG'           => $mutasiRow['TGL_SRT_MG'],
                'TGL_EFEKTIF'          => $tglEfektif,
                'PENANDATANGAN'        => $mutasiRow['PENANDATANGAN'],
                'IS_DELETE'            => 0,
                'ID_USER'              => null,
                'TGL_ENTRY'            => $today,
                'KONSOLIDASI'          => 0,
                'KETERANGAN'           => 'API M1 mutasi golongan ' . $idRegAwal . '→' . $idRegAkhir,
            ];
            if ($db->table('history_registrasi')->insert($historyRow) === false) {
                throw new ValidationException(
                    'Failed to append history_registrasi after mutasi.',
                    ['history_registrasi' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }

            $registrasi = $this->wbpQuery->findRegistrasiOrFail($idPerkara);

            return [
                'id_mutasi_tahanan' => $idMutasi,
                'id_perkara'       => $idPerkara,
                'id_reg_awal'      => $idRegAwal,
                'id_reg_akhir'     => $idRegAkhir,
                'is_tahanan'       => $isTahanan,
                'tgl_efektif'      => $tglEfektif,
                'nmr_srt_mg'       => $mutasiRow['NMR_SRT_MG'],
                'tgl_srt_mg'       => $mutasiRow['TGL_SRT_MG'],
                'penandatangan'    => $mutasiRow['PENANDATANGAN'],
                'keterangan'       => $mutasiRow['KETERANGAN'],
                'id_history_reg'   => $idHistory,
                'registrasi'       => $registrasi,
            ];
        });
    }

    /**
     * @param array<string, mixed> $jenisAkhir
     */
    protected function assertLevelProgression(string $idRegAwal, array $jenisAkhir): void
    {
        $jenisAwal = $this->jenisRegistrasi->find($idRegAwal);
        if ($jenisAwal === null) {
            // Unknown source: still allow target if active-looking
            return;
        }

        $levelAwal  = isset($jenisAwal['LEVEL']) ? (int) $jenisAwal['LEVEL'] : null;
        $levelAkhir = isset($jenisAkhir['LEVEL']) ? (int) $jenisAkhir['LEVEL'] : null;

        if ($levelAwal === null || $levelAkhir === null) {
            return;
        }

        // Legacy pilihan_mutasi: only targets with LEVEL > source LEVEL
        if ($levelAkhir <= $levelAwal) {
            throw new DomainException(
                "id_reg_akhir LEVEL ({$levelAkhir}) must be greater than id_reg_awal LEVEL ({$levelAwal}). "
                . 'Use allow_any_reg=true to override, or GET /api/v1/mutasi/golongan/options?id_perkara=…',
            );
        }
    }
}
