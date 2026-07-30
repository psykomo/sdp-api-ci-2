<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Models\OrganizationModel;
use App\Modules\Referensi\Models\JenisRegistrasiModel;
use App\Modules\Wbp\Models\HistoryRegistrasiModel;
use App\Modules\Wbp\Models\HukumanModel;
use App\Modules\Wbp\Models\KejahatanModel;
use App\Modules\Wbp\Models\PerkaraModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Modules\Wbp\Support\LegacyIdGenerator;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

/**
 * R4 — update registrasi spine (perkara + optional kejahatan replace + hukuman).
 *
 * Appends a history_registrasi snapshot after successful update (audit trail).
 */
class UbahRegistrasi
{
    public function __construct(
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
    public function execute(string $idPerkara, array $data): array
    {
        $existing = $this->findPerkaraInScope($idPerkara);

        $idReg = trim((string) ($data['id_reg'] ?? $data['ID_REG'] ?? $existing['ID_REG'] ?? ''));
        $isTahanan = (int) ($existing['IS_TAHANAN'] ?? 0);
        if ($idReg !== '' && $idReg !== ($existing['ID_REG'] ?? '')) {
            $jenis = $this->jenisRegistrasi->find($idReg);
            if ($jenis === null) {
                throw new ValidationException('Validation failed.', [
                    'id_reg' => "Unknown ID_REG: {$idReg}",
                ]);
            }
            $isTahanan = (int) ($jenis['IS_TAHANAN'] ?? 0);
        }

        $idUpt = (string) ($existing['ID_UPT'] ?? '');
        $userId = $this->orgContext->getUserId();
        $today  = date('Y-m-d');

        return $this->unitOfWork->run(function () use (
            $idPerkara,
            $existing,
            $data,
            $idReg,
            $isTahanan,
            $idUpt,
            $userId,
            $today,
        ): array {
            $db = db_connect();

            $update = $this->mapPerkaraUpdate($data, $idReg, $isTahanan);
            if ($update !== []) {
                if ($db->table('perkara')->where('ID_PERKARA', $idPerkara)->update($update) === false) {
                    throw new ValidationException(
                        'Failed to update perkara.',
                        ['perkara' => $db->error()['message'] ?? 'Update failed.'],
                    );
                }
            }

            $kejahatanOut = null;
            if (array_key_exists('kejahatan', $data)) {
                $kejahatanOut = $this->replaceKejahatan($db, $idPerkara, $idUpt, $data['kejahatan'], $userId, $existing, $data);
            }

            $hukumanOut = null;
            if (array_key_exists('hukuman', $data) && is_array($data['hukuman'])) {
                $hukumanOut = $this->upsertHukuman($db, $idPerkara, $idUpt, $data['hukuman'], $userId);
            }

            // Reload perkara after updates
            $fresh = $db->table('perkara')->where('ID_PERKARA', $idPerkara)->get()->getRowArray();
            if ($fresh === null) {
                throw new RuntimeException("Perkara {$idPerkara} missing after update.");
            }

            // Append history snapshot (R4 audit; R5 full parity continues this pattern)
            $idHistory = $this->ids->withUptPrefix($idUpt !== '' ? $idUpt : '000', 'history_registrasi', 'ID_HISTORY_REG');
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
                'IS_DELETE'            => 0,
                'ID_USER'              => null,
                'TGL_ENTRY'            => $today,
                'KONSOLIDASI'          => 0,
                'KETERANGAN'           => 'API R4 update',
            ];
            if ($db->table('history_registrasi')->insert($historyRow) === false) {
                throw new ValidationException(
                    'Failed to append history_registrasi.',
                    ['history_registrasi' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }

            return $this->query->findRegistrasiOrFail($idPerkara);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function findPerkaraInScope(string $idPerkara): array
    {
        $row = $this->perkara->builder()
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETE', 0)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw PageNotFoundException::forPageNotFound("Registrasi {$idPerkara} not found.");
        }

        $idUptFilter = $this->resolveIdUptFilter();
        if ($idUptFilter !== null && (string) ($row['ID_UPT'] ?? '') !== $idUptFilter) {
            throw PageNotFoundException::forPageNotFound("Registrasi {$idPerkara} not found.");
        }

        return $row;
    }

    protected function resolveIdUptFilter(): ?string
    {
        $orgId = $this->orgContext->getActiveOrgId();
        if ($orgId === null) {
            return null;
        }
        $org = $this->organizations->find($orgId);
        if ($org === null) {
            return null;
        }
        $type = is_array($org) ? ($org['type'] ?? '') : ($org->type ?? '');
        $code = is_array($org) ? (string) ($org['code'] ?? '') : (string) ($org->code ?? '');
        if ($type === 'kanwil' || $code === '' || ! preg_match('/^\d{2,5}$/', $code)) {
            return null;
        }

        return $code;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mapPerkaraUpdate(array $data, string $idReg, int $isTahanan): array
    {
        $map = [
            'id_reg'                   => 'ID_REG',
            'id_status'                => 'ID_STATUS',
            'id_sub_status'            => 'ID_SUB_STATUS',
            'nmr_reg_gol'              => 'NMR_REG_GOL',
            'nmr_reg_instansi'         => 'NMR_REG_INSTANSI',
            'tgl_msk_lapas'            => 'TGL_MSK_LAPAS',
            'tgl_ekspirasi'            => 'TGL_EKSPIRASI',
            'tgl_ekspirasi_awal'       => 'TGL_EKSPIRASI_AWAL',
            'tgl_pertama_ditahan'      => 'TGL_PERTAMA_DITAHAN',
            'tgl_akhir_ditahan'        => 'TGL_AKHIR_DITAHAN',
            'id_instansi_penyidik'     => 'ID_INSTANSI_PENYIDIK',
            'id_instansi_penyidik_lain'=> 'ID_INSTANSI_PENYIDIK_LAIN',
            'keterangan'               => 'KETERANGAN',
            'lokasi_blok'              => 'LOKASI_BLOK',
            'lokasi_sel'               => 'LOKASI_SEL',
        ];

        $out = [];
        foreach ($map as $api => $db) {
            if (array_key_exists($api, $data)) {
                $out[$db] = $data[$api];
            } elseif (array_key_exists($db, $data)) {
                $out[$db] = $data[$db];
            }
        }
        if ($idReg !== '') {
            $out['ID_REG']     = $idReg;
            $out['IS_TAHANAN'] = $isTahanan;
        }

        return $out;
    }

    /**
     * Soft-delete existing kejahatan and insert replacements when array provided.
     *
     * @param mixed $db
     * @param mixed $kejahatanIn
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    protected function replaceKejahatan($db, string $idPerkara, string $idUpt, mixed $kejahatanIn, ?int $userId, array $existing, array $data): array
    {
        if (! is_array($kejahatanIn)) {
            throw new ValidationException('Validation failed.', [
                'kejahatan' => 'Must be an array.',
            ]);
        }
        if ($kejahatanIn !== [] && array_is_list($kejahatanIn) === false) {
            $kejahatanIn = [$kejahatanIn];
        }

        $db->table('kejahatan')
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETED', 0)
            ->update(['IS_DELETED' => 1, 'UPDATED' => date('YmdHis')]);

        $nmrRegGol = (string) ($data['nmr_reg_gol'] ?? $data['NMR_REG_GOL'] ?? $existing['NMR_REG_GOL'] ?? '');
        $out       = [];
        foreach ($kejahatanIn as $i => $k) {
            if (! is_array($k)) {
                continue;
            }
            $idKej = $this->ids->withUptPrefix($idUpt !== '' ? $idUpt : '000', 'kejahatan', 'ID_KEJAHATAN');
            $kRow  = [
                'ID_KEJAHATAN'       => $idKej,
                'ID_PERKARA'         => $idPerkara,
                'NOREGGOL'           => $k['noreggol'] ?? $k['NOREGGOL'] ?? ($nmrRegGol !== '' ? $nmrRegGol : null),
                'ID_TERMINOLOGI'     => $k['id_terminologi'] ?? $k['ID_TERMINOLOGI'] ?? null,
                'IS_KEJAHATAN_UTAMA' => (int) ($k['is_kejahatan_utama'] ?? ($i === 0 ? 1 : 0)),
                'PASAL_UTAMA'        => $k['pasal_utama'] ?? $k['PASAL_UTAMA'] ?? null,
                'PASAL_TAMBAHAN'     => $k['pasal_tambahan'] ?? null,
                'UU_KEJAHATAN'       => $k['uu_kejahatan'] ?? $k['UU_KEJAHATAN'] ?? null,
                'WILAYAH'            => $k['wilayah'] ?? null,
                'DESKRIPSI'          => $k['deskripsi'] ?? null,
                'IS_DELETED'         => 0,
                'KONSOLIDASI'        => 0,
                'CREATED'             => date('YmdHis'),
                'CREATED_BY'          => $userId !== null ? (string) $userId : null,
                'UPDATED'            => date('YmdHis'),
            ];
            if ($db->table('kejahatan')->insert($kRow) === false) {
                throw new ValidationException(
                    'Failed to insert kejahatan.',
                    ['kejahatan' => $db->error()['message'] ?? "Insert failed at {$i}."],
                );
            }
            $out[] = [
                'id_kejahatan'       => $idKej,
                'pasal_utama'        => $kRow['PASAL_UTAMA'],
                'is_kejahatan_utama' => $kRow['IS_KEJAHATAN_UTAMA'],
            ];
        }

        return $out;
    }

    /**
     * @param mixed $db
     * @param array<string, mixed> $hukumanIn
     * @return array<string, mixed>
     */
    protected function upsertHukuman($db, string $idPerkara, string $idUpt, array $hukumanIn, ?int $userId): array
    {
        $existing = $db->table('hukuman')
            ->where('ID_PERKARA', $idPerkara)
            ->orderBy('ID_HKMAN', 'ASC')
            ->get()
            ->getRowArray();

        $fields = [
            'ID_JENIS_HUKUMAN' => $hukumanIn['id_jenis_hukuman'] ?? $hukumanIn['ID_JENIS_HUKUMAN'] ?? 'PID',
            'TGL_PUTUSAN'      => $hukumanIn['tgl_putusan'] ?? $hukumanIn['TGL_PUTUSAN'] ?? null,
            'NMR_PUTUSAN'      => $hukumanIn['nmr_putusan'] ?? $hukumanIn['NMR_PUTUSAN'] ?? null,
            'PASAL'            => $hukumanIn['pasal'] ?? null,
            'THN_KURUNG'       => (int) ($hukumanIn['thn_kurung'] ?? $hukumanIn['THN_KURUNG'] ?? 0),
            'BLN_KURUNG'       => (int) ($hukumanIn['bln_kurung'] ?? $hukumanIn['BLN_KURUNG'] ?? 0),
            'HR_KURUNG'        => (int) ($hukumanIn['hr_kurung'] ?? $hukumanIn['HR_KURUNG'] ?? 0),
            'DENDA'            => $hukumanIn['denda'] ?? null,
            'UP'               => $hukumanIn['up'] ?? null,
            'HAKIM_KETUA'      => $hukumanIn['hakim_ketua'] ?? null,
            'JAKSA'            => $hukumanIn['jaksa'] ?? null,
        ];

        if ($existing !== null) {
            $db->table('hukuman')->where('ID_HKMAN', $existing['ID_HKMAN'])->update($fields);
            $idHk = $existing['ID_HKMAN'];
        } else {
            $idHk = $this->ids->withUptPrefix($idUpt !== '' ? $idUpt : '000', 'hukuman', 'ID_HKMAN');
            $fields['ID_HKMAN']   = $idHk;
            $fields['ID_PERKARA'] = $idPerkara;
            $fields['ID_USER']    = null;
            if ($db->table('hukuman')->insert($fields) === false) {
                throw new ValidationException(
                    'Failed to insert hukuman.',
                    ['hukuman' => $db->error()['message'] ?? 'Insert failed.'],
                );
            }
        }

        $db->table('perkara')->where('ID_PERKARA', $idPerkara)->update([
            'TAHUN_HUKUMAN' => $fields['THN_KURUNG'],
            'BULAN_HUKUMAN' => $fields['BLN_KURUNG'],
            'HARI_HUKUMAN'  => $fields['HR_KURUNG'],
        ]);

        return [
            'id_hkman'         => $idHk,
            'id_jenis_hukuman' => $fields['ID_JENIS_HUKUMAN'],
            'thn_kurung'       => $fields['THN_KURUNG'],
            'bln_kurung'       => $fields['BLN_KURUNG'],
            'hr_kurung'        => $fields['HR_KURUNG'],
        ];
    }
}
