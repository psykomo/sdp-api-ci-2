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

/**
 * R5 — update a history_registrasi row (spine parity with R4 field set).
 *
 * Optionally replaces kejahatan / upserts hukuman on the parent perkara
 * (legacy HistoryRegistrasi::simpan D2/D3 touches the same shared tables).
 */
class UbahHistoryRegistrasi
{
    public function __construct(
        protected HistoryRegistrasiModel $history = new HistoryRegistrasiModel(),
        protected PerkaraModel $perkara = new PerkaraModel(),
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
    public function execute(string $idPerkara, string $idHistoryReg, array $data): array
    {
        // Ensures perkara is in org scope
        $this->query->findRegistrasiOrFail($idPerkara);
        $existing = $this->query->findHistoryOrFail($idPerkara, $idHistoryReg);

        $idReg = trim((string) ($data['id_reg'] ?? $data['ID_REG'] ?? $existing['id_reg'] ?? ''));
        $isTahanan = (int) ($existing['is_tahanan'] ?? 0);
        if ($idReg !== '' && $idReg !== (string) ($existing['id_reg'] ?? '')) {
            $jenis = $this->jenisRegistrasi->find($idReg);
            if ($jenis === null) {
                throw new ValidationException('Validation failed.', [
                    'id_reg' => "Unknown ID_REG: {$idReg}",
                ]);
            }
            $isTahanan = (int) ($jenis['IS_TAHANAN'] ?? 0);
        }

        $idUpt  = (string) ($existing['id_upt'] ?? '');
        $userId = $this->orgContext->getUserId();

        return $this->unitOfWork->run(function () use (
            $idPerkara,
            $idHistoryReg,
            $existing,
            $data,
            $idReg,
            $isTahanan,
            $idUpt,
            $userId,
        ): array {
            $db = db_connect();

            $update = $this->mapHistoryUpdate($data, $idReg, $isTahanan);
            if ($update !== []) {
                if ($db->table('history_registrasi')
                    ->where('ID_HISTORY_REG', $idHistoryReg)
                    ->where('ID_PERKARA', $idPerkara)
                    ->update($update) === false) {
                    throw new ValidationException(
                        'Failed to update history_registrasi.',
                        ['history_registrasi' => $db->error()['message'] ?? 'Update failed.'],
                    );
                }
            }

            // Shared tables on parent perkara (legacy D2/D3)
            if (array_key_exists('kejahatan', $data)) {
                $this->replaceKejahatan($db, $idPerkara, $idUpt, $data['kejahatan'], $userId, $existing, $data);
            }
            if (array_key_exists('hukuman', $data) && is_array($data['hukuman'])) {
                $this->upsertHukuman($db, $idPerkara, $idUpt, $data['hukuman']);
                // Mirror hukuman years onto this history row when provided
                $h = $data['hukuman'];
                $histHuk = [];
                if (array_key_exists('thn_kurung', $h) || array_key_exists('THN_KURUNG', $h)) {
                    $histHuk['TAHUN_HUKUMAN'] = (int) ($h['thn_kurung'] ?? $h['THN_KURUNG'] ?? 0);
                }
                if (array_key_exists('bln_kurung', $h) || array_key_exists('BLN_KURUNG', $h)) {
                    $histHuk['BULAN_HUKUMAN'] = (int) ($h['bln_kurung'] ?? $h['BLN_KURUNG'] ?? 0);
                }
                if (array_key_exists('hr_kurung', $h) || array_key_exists('HR_KURUNG', $h)) {
                    $histHuk['HARI_HUKUMAN'] = (int) ($h['hr_kurung'] ?? $h['HR_KURUNG'] ?? 0);
                }
                if ($histHuk !== []) {
                    $db->table('history_registrasi')
                        ->where('ID_HISTORY_REG', $idHistoryReg)
                        ->update($histHuk);
                }
            }

            return $this->query->findHistoryOrFail($idPerkara, $idHistoryReg);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mapHistoryUpdate(array $data, string $idReg, int $isTahanan): array
    {
        $map = [
            'id_reg'                    => 'ID_REG',
            'id_status'                 => 'ID_STATUS',
            'id_sub_status'             => 'ID_SUB_STATUS',
            'nmr_reg_gol'               => 'NMR_REG_GOL',
            'nmr_reg_instansi'          => 'NMR_REG_INSTANSI',
            'tgl_msk_lapas'             => 'TGL_MSK_LAPAS',
            'tgl_ekspirasi'             => 'TGL_EKSPIRASI',
            'tgl_ekspirasi_awal'        => 'TGL_EKSPIRASI_AWAL',
            'tgl_pertama_ditahan'       => 'TGL_PERTAMA_DITAHAN',
            'tgl_akhir_ditahan'         => 'TGL_AKHIR_DITAHAN',
            'id_instansi_penyidik'      => 'ID_INSTANSI_PENYIDIK',
            'id_instansi_penyidik_lain' => 'ID_INSTANSI_PENYIDIK_LAIN',
            'keterangan'                => 'KETERANGAN',
            'lokasi_sel'                => 'LOKASI_SEL',
            'lokasi_dokumen'            => 'LOKASI_DOKUMEN',
            'tahun_hukuman'             => 'TAHUN_HUKUMAN',
            'bulan_hukuman'             => 'BULAN_HUKUMAN',
            'hari_hukuman'              => 'HARI_HUKUMAN',
        ];

        $out = [];
        foreach ($map as $api => $dbCol) {
            if (array_key_exists($api, $data)) {
                $out[$dbCol] = $data[$api];
            } elseif (array_key_exists($dbCol, $data)) {
                $out[$dbCol] = $data[$dbCol];
            }
        }
        if ($idReg !== '' && (array_key_exists('id_reg', $data) || array_key_exists('ID_REG', $data))) {
            $out['ID_REG']     = $idReg;
            $out['IS_TAHANAN'] = $isTahanan;
        }

        return $out;
    }

    /**
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

        $nmrRegGol = (string) ($data['nmr_reg_gol'] ?? $data['NMR_REG_GOL'] ?? $existing['nmr_reg_gol'] ?? '');
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
                'CREATED_BY'         => $userId !== null ? (string) $userId : null,
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
    protected function upsertHukuman($db, string $idPerkara, string $idUpt, array $hukumanIn): array
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
