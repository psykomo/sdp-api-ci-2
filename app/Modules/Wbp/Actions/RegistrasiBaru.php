<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Models\OrganizationModel;
use App\Modules\Referensi\Models\JenisRegistrasiModel;
use App\Modules\Wbp\Models\HistoryRegistrasiModel;
use App\Modules\Wbp\Models\HukumanModel;
use App\Modules\Wbp\Models\IdentitasModel;
use App\Modules\Wbp\Models\KejahatanModel;
use App\Modules\Wbp\Models\PerkaraModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Modules\Wbp\Support\LegacyIdGenerator;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;

/**
 * R3 — multi-table registrasi create (spine of Registrasi::insert).
 *
 * Writes in one UnitOfWork:
 *  1. perkara
 *  2. history_registrasi
 *  3. kejahatan[] (optional)
 *  4. hukuman (optional single PN-level record)
 *
 * MAP variant: pass is_map=true (stores same spine; MAP-only columns can expand later).
 * Does not run full ekspirasi engine, keputusan multi-level, registrasi_a45, or mutasi_upt.
 */
class RegistrasiBaru
{
    public function __construct(
        protected IdentitasModel $identitas = new IdentitasModel(),
        protected PerkaraModel $perkara = new PerkaraModel(),
        protected HistoryRegistrasiModel $history = new HistoryRegistrasiModel(),
        protected KejahatanModel $kejahatan = new KejahatanModel(),
        protected HukumanModel $hukuman = new HukumanModel(),
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
            identitas: $this->identitas,
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
    public function execute(array $data): array
    {
        $nomorInduk = trim((string) ($data['nomor_induk'] ?? $data['NOMOR_INDUK'] ?? ''));
        $idReg      = trim((string) ($data['id_reg'] ?? $data['ID_REG'] ?? ''));

        if ($nomorInduk === '' || $idReg === '') {
            $errors = [];
            if ($nomorInduk === '') {
                $errors['nomor_induk'] = 'Required.';
            }
            if ($idReg === '') {
                $errors['id_reg'] = 'Required.';
            }
            throw new ValidationException('Validation failed.', $errors);
        }

        // Ensures identitas exists and is in org scope
        $this->query->findOrFail($nomorInduk);

        $jenis = $this->jenisRegistrasi->find($idReg);
        if ($jenis === null) {
            throw new ValidationException('Validation failed.', [
                'id_reg' => "Unknown ID_REG: {$idReg}",
            ]);
        }

        $idUpt = $this->resolveIdUpt($data);
        $isTahanan = (int) ($jenis['IS_TAHANAN'] ?? 0);
        $isMap = ! empty($data['is_map']) || ! empty($data['map']);

        $idStatus    = trim((string) ($data['id_status'] ?? $data['ID_STATUS'] ?? 'STA'));
        $idSubStatus = trim((string) ($data['id_sub_status'] ?? $data['ID_SUB_STATUS'] ?? 'SSA1'));
        $nmrRegGol   = trim((string) ($data['nmr_reg_gol'] ?? $data['NMR_REG_GOL'] ?? ''));

        $userId = $this->orgContext->getUserId();
        $now    = date('Y-m-d H:i:s');
        $today  = date('Y-m-d');

        return $this->unitOfWork->run(function () use (
            $data,
            $nomorInduk,
            $idReg,
            $idUpt,
            $isTahanan,
            $isMap,
            $idStatus,
            $idSubStatus,
            $nmrRegGol,
            $userId,
            $now,
            $today,
        ): array {
            $idPerkara = trim((string) ($data['id_perkara'] ?? $data['ID_PERKARA'] ?? ''));
            if ($idPerkara === '') {
                $idPerkara = $this->ids->withUptPrefix($idUpt, 'perkara', 'ID_PERKARA');
            } elseif ($this->perkara->find($idPerkara) !== null) {
                throw new ValidationException('Validation failed.', [
                    'id_perkara' => "ID_PERKARA {$idPerkara} already exists.",
                ]);
            }

            $perkaraRow = [
                'ID_PERKARA'              => $idPerkara,
                'NOMOR_INDUK'             => $nomorInduk,
                'ID_UPT'                  => $idUpt,
                'ID_REG'                  => $idReg,
                'ID_STATUS'               => $idStatus,
                'ID_SUB_STATUS'           => $idSubStatus,
                'IS_TAHANAN'              => $isTahanan,
                'NMR_REG_GOL'             => $nmrRegGol !== '' ? $nmrRegGol : null,
                'NMR_REG_INSTANSI'        => $data['nmr_reg_instansi'] ?? $data['NMR_REG_INSTANSI'] ?? null,
                'TGL_MSK_LAPAS'           => $data['tgl_msk_lapas'] ?? $data['TGL_MSK_LAPAS'] ?? $today,
                'TGL_EKSPIRASI'           => $data['tgl_ekspirasi'] ?? $data['TGL_EKSPIRASI'] ?? null,
                'TGL_EKSPIRASI_AWAL'      => $data['tgl_ekspirasi_awal'] ?? $data['TGL_EKSPIRASI_AWAL'] ?? null,
                'TGL_PERTAMA_DITAHAN'     => $data['tgl_pertama_ditahan'] ?? $data['TGL_PERTAMA_DITAHAN'] ?? null,
                'TGL_AKHIR_DITAHAN'       => $data['tgl_akhir_ditahan'] ?? $data['TGL_AKHIR_DITAHAN'] ?? null,
                'ID_INSTANSI_PENYIDIK'    => $data['id_instansi_penyidik'] ?? $data['ID_INSTANSI_PENYIDIK'] ?? null,
                'ID_INSTANSI_PENYIDIK_LAIN' => $data['id_instansi_penyidik_lain'] ?? null,
                'KETERANGAN'              => $data['keterangan'] ?? null,
                'LOKASI_BLOK'             => $data['lokasi_blok'] ?? null,
                'LOKASI_SEL'              => $data['lokasi_sel'] ?? null,
                'IS_DELETE'               => 0,
                'IS_DENDA_LUNAS'          => 0,
                'TGL_ENTRY'               => $today,
                'TGL_MG'                  => $today,
                'KONSOLIDASI'             => 0,
                // Do not set ID_USER to API users.id — legacy FK points at `pengguna`.
                'ID_USER'                 => null,
                'APPROVED'                => $now,
                'APPROVED_BY'             => $userId !== null ? (string) $userId : null,
            ];

            // MAP flag kept in keterangan prefix if no dedicated column used
            if ($isMap && empty($perkaraRow['KETERANGAN'])) {
                $perkaraRow['KETERANGAN'] = 'MAP';
            }

            $db = db_connect();
            if ($db->table('perkara')->insert($perkaraRow) === false) {
                throw new ValidationException(
                    'Failed to insert perkara.',
                    ['perkara' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }

            $idHistory = $this->ids->withUptPrefix($idUpt, 'history_registrasi', 'ID_HISTORY_REG');
            $historyRow = [
                'ID_HISTORY_REG'       => $idHistory,
                'ID_PERKARA'           => $idPerkara,
                'NOMOR_INDUK'          => $nomorInduk,
                'ID_UPT'               => $idUpt,
                'ID_REG'               => $idReg,
                'ID_STATUS'            => $idStatus,
                'ID_SUB_STATUS'        => $idSubStatus,
                'IS_TAHANAN'           => $isTahanan,
                'NMR_REG_GOL'          => $nmrRegGol !== '' ? $nmrRegGol : null,
                'TGL_MSK_LAPAS'        => $perkaraRow['TGL_MSK_LAPAS'],
                'TGL_EKSPIRASI'        => $perkaraRow['TGL_EKSPIRASI'],
                'TGL_EKSPIRASI_AWAL'   => $perkaraRow['TGL_EKSPIRASI_AWAL'],
                'TGL_PERTAMA_DITAHAN'  => $perkaraRow['TGL_PERTAMA_DITAHAN'],
                'TGL_AKHIR_DITAHAN'    => $perkaraRow['TGL_AKHIR_DITAHAN'],
                'ID_INSTANSI_PENYIDIK' => $perkaraRow['ID_INSTANSI_PENYIDIK'],
                'IS_DELETE'            => 0,
                'ID_USER'              => null, // FK → pengguna, not API users
                'TGL_ENTRY'            => $today,
                'KONSOLIDASI'          => 0,
            ];
            if ($db->table('history_registrasi')->insert($historyRow) === false) {
                throw new ValidationException(
                    'Failed to insert history_registrasi.',
                    ['history_registrasi' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }

            $kejahatanIn = $data['kejahatan'] ?? [];
            if (is_array($kejahatanIn) && $kejahatanIn !== [] && array_is_list($kejahatanIn) === false) {
                $kejahatanIn = [$kejahatanIn];
            }
            if (! is_array($kejahatanIn)) {
                $kejahatanIn = [];
            }

            $kejahatanOut = [];
            foreach ($kejahatanIn as $i => $k) {
                if (! is_array($k)) {
                    continue;
                }
                $idKej = $this->ids->withUptPrefix($idUpt, 'kejahatan', 'ID_KEJAHATAN');
                $kRow  = [
                    'ID_KEJAHATAN'        => $idKej,
                    'ID_PERKARA'          => $idPerkara,
                    'NOREGGOL'            => $k['noreggol'] ?? $k['NOREGGOL'] ?? $nmrRegGol ?: null,
                    'ID_TERMINOLOGI'      => $k['id_terminologi'] ?? $k['ID_TERMINOLOGI'] ?? null,
                    'IS_KEJAHATAN_UTAMA'  => (int) ($k['is_kejahatan_utama'] ?? $k['IS_KEJAHATAN_UTAMA'] ?? ($i === 0 ? 1 : 0)),
                    'PASAL_UTAMA'         => $k['pasal_utama'] ?? $k['PASAL_UTAMA'] ?? null,
                    'PASAL_TAMBAHAN'      => $k['pasal_tambahan'] ?? null,
                    'UU_KEJAHATAN'        => $k['uu_kejahatan'] ?? $k['UU_KEJAHATAN'] ?? null,
                    'WILAYAH'             => $k['wilayah'] ?? null,
                    'DESKRIPSI'           => $k['deskripsi'] ?? null,
                    'IS_DELETED'          => 0,
                    'KONSOLIDASI'         => 0,
                    'CREATED'              => date('YmdHis'),
                    'CREATED_BY'           => $userId !== null ? (string) $userId : null,
                    'UPDATED'             => date('YmdHis'),
                    'UPDATED_BY'          => $userId !== null ? (string) $userId : null,
                ];
                if ($db->table('kejahatan')->insert($kRow) === false) {
                    throw new ValidationException(
                        'Failed to insert kejahatan.',
                        ['kejahatan' => $db->error()['message'] ?? "Insert failed at index {$i}."],
                    );
                }
                $kejahatanOut[] = [
                    'id_kejahatan'       => $idKej,
                    'pasal_utama'        => $kRow['PASAL_UTAMA'],
                    'is_kejahatan_utama' => $kRow['IS_KEJAHATAN_UTAMA'],
                ];
            }

            $hukumanOut = null;
            $hukumanIn  = $data['hukuman'] ?? null;
            if (is_array($hukumanIn) && $hukumanIn !== []) {
                $idHk = $this->ids->withUptPrefix($idUpt, 'hukuman', 'ID_HKMAN');
                $hRow = [
                    'ID_HKMAN'          => $idHk,
                    'ID_PERKARA'        => $idPerkara,
                    'ID_JENIS_HUKUMAN'  => $hukumanIn['id_jenis_hukuman'] ?? $hukumanIn['ID_JENIS_HUKUMAN'] ?? 'PID',
                    'ID_USER'           => null,
                    'TGL_PUTUSAN'       => $hukumanIn['tgl_putusan'] ?? $hukumanIn['TGL_PUTUSAN'] ?? null,
                    'NMR_PUTUSAN'       => $hukumanIn['nmr_putusan'] ?? $hukumanIn['NMR_PUTUSAN'] ?? null,
                    'PASAL'             => $hukumanIn['pasal'] ?? null,
                    'THN_KURUNG'        => (int) ($hukumanIn['thn_kurung'] ?? $hukumanIn['THN_KURUNG'] ?? 0),
                    'BLN_KURUNG'        => (int) ($hukumanIn['bln_kurung'] ?? $hukumanIn['BLN_KURUNG'] ?? 0),
                    'HR_KURUNG'         => (int) ($hukumanIn['hr_kurung'] ?? $hukumanIn['HR_KURUNG'] ?? 0),
                    'DENDA'             => $hukumanIn['denda'] ?? null,
                    'UP'                => $hukumanIn['up'] ?? null,
                    'HAKIM_KETUA'       => $hukumanIn['hakim_ketua'] ?? null,
                    'JAKSA'             => $hukumanIn['jaksa'] ?? null,
                ];
                if ($db->table('hukuman')->insert($hRow) === false) {
                    throw new ValidationException(
                        'Failed to insert hukuman.',
                        ['hukuman' => $db->error()['message'] ?? 'Insert failed.'],
                    );
                }
                // Mirror summary years on perkara when provided
                $db->table('perkara')->where('ID_PERKARA', $idPerkara)->update([
                    'TAHUN_HUKUMAN' => $hRow['THN_KURUNG'],
                    'BULAN_HUKUMAN' => $hRow['BLN_KURUNG'],
                    'HARI_HUKUMAN'  => $hRow['HR_KURUNG'],
                ]);
                $hukumanOut = [
                    'id_hkman'         => $idHk,
                    'id_jenis_hukuman' => $hRow['ID_JENIS_HUKUMAN'],
                    'thn_kurung'       => $hRow['THN_KURUNG'],
                    'bln_kurung'       => $hRow['BLN_KURUNG'],
                    'hr_kurung'        => $hRow['HR_KURUNG'],
                ];
            }

            $identitas = $this->query->findOrFail($nomorInduk);

            return [
                'id_perkara'      => $idPerkara,
                'id_history_reg'  => $idHistory,
                'nomor_induk'     => $nomorInduk,
                'id_upt'          => $idUpt,
                'id_reg'          => $idReg,
                'is_tahanan'      => $isTahanan,
                'is_map'          => $isMap,
                'nmr_reg_gol'     => $nmrRegGol !== '' ? $nmrRegGol : null,
                'kejahatan'       => $kejahatanOut,
                'hukuman'         => $hukumanOut,
                'identitas'       => $identitas,
            ];
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function resolveIdUpt(array $data): string
    {
        if (! empty($data['id_upt']) || ! empty($data['ID_UPT'])) {
            return (string) ($data['id_upt'] ?? $data['ID_UPT']);
        }

        $orgId = $this->orgContext->getActiveOrgId();
        if ($orgId === null) {
            throw new DomainException('Active organization is required for registrasi.');
        }
        $org = $this->organizations->find($orgId);
        if ($org === null) {
            throw new DomainException('Active organization not found.');
        }
        $code = is_array($org) ? (string) ($org['code'] ?? '') : (string) ($org->code ?? '');
        $type = is_array($org) ? (string) ($org['type'] ?? '') : (string) ($org->type ?? '');

        if ($type === 'kanwil' || $code === '' || ! preg_match('/^\d{2,5}$/', $code)) {
            throw new DomainException(
                'Registrasi requires a unit org (numeric ID_UPT as organizations.code) or explicit id_upt.',
            );
        }

        return $code;
    }
}
