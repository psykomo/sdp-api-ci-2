<?php

namespace App\Modules\Wbp\Services;

use App\Models\OrganizationModel;
use App\Modules\Wbp\Models\IdentitasModel;
use App\Modules\Wbp\Models\PerkaraModel;
use App\Services\OrgContext;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * R1 identitas + R4/R5/R6 registrasi & history reads against legacy schema.
 *
 * Org scoping: organizations.code is treated as legacy ID_UPT for unit orgs.
 * Kanwil (type=kanwil) sees all active identitas in seed (no child UPT map yet).
 */
class WbpQueryService
{
    public function __construct(
        protected IdentitasModel $identitas = new IdentitasModel(),
        protected PerkaraModel $perkara = new PerkaraModel(),
        protected ?OrgContext $orgContext = null,
        protected ?OrganizationModel $organizations = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->organizations ??= model(OrganizationModel::class, false);
    }

    /**
     * R5 — list active history_registrasi rows for a perkara (org-scoped via perkara).
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listHistory(string $idPerkara, int $perPage = 50, int $page = 1): array
    {
        // Scope check (throws 404 if out of org)
        $this->findRegistrasiOrFail($idPerkara);

        $perPage = max(1, min($perPage, 100));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $db = $this->perkara->db;

        $countBuilder = $db->table('history_registrasi')
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETE', 0);
        $total = (int) $countBuilder->countAllResults();

        $rows = $db->table('history_registrasi')
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETE', 0)
            ->orderBy('TGL_ENTRY', 'DESC')
            ->orderBy('ID_HISTORY_REG', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(fn (array $r): array => $this->presentHistorySummary($r), $rows);

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'perPage'   => $perPage,
                'total'     => $total,
                'pageCount' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            ],
        ];
    }

    /**
     * R5 — show one history_registrasi row; must belong to id_perkara and org scope.
     *
     * @return array<string, mixed>
     */
    public function findHistoryOrFail(string $idPerkara, string $idHistoryReg): array
    {
        $this->findRegistrasiOrFail($idPerkara);

        $row = $this->perkara->db->table('history_registrasi')
            ->where('ID_HISTORY_REG', $idHistoryReg)
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETE', 0)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw PageNotFoundException::forPageNotFound(
                "History {$idHistoryReg} not found for perkara {$idPerkara}.",
            );
        }

        return $this->presentHistoryDetail($row);
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    protected function presentHistorySummary(array $r): array
    {
        return [
            'id_history_reg' => $r['ID_HISTORY_REG'] ?? null,
            'id_perkara'     => $r['ID_PERKARA'] ?? null,
            'nomor_induk'    => $r['NOMOR_INDUK'] ?? null,
            'id_upt'         => $r['ID_UPT'] ?? null,
            'id_reg'         => $r['ID_REG'] ?? null,
            'id_status'      => $r['ID_STATUS'] ?? null,
            'id_sub_status'  => $r['ID_SUB_STATUS'] ?? null,
            'is_tahanan'     => isset($r['IS_TAHANAN']) ? (int) $r['IS_TAHANAN'] : null,
            'nmr_reg_gol'    => $r['NMR_REG_GOL'] ?? null,
            'tgl_msk_lapas'  => $r['TGL_MSK_LAPAS'] ?? null,
            'tgl_ekspirasi'  => $r['TGL_EKSPIRASI'] ?? null,
            'tgl_entry'      => $r['TGL_ENTRY'] ?? null,
            'keterangan'     => $r['KETERANGAN'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    protected function presentHistoryDetail(array $r): array
    {
        return [
            'id_history_reg'            => $r['ID_HISTORY_REG'] ?? null,
            'id_perkara'                => $r['ID_PERKARA'] ?? null,
            'id_perkara_parent'         => $r['ID_PERKARA_PARENT'] ?? null,
            'nomor_induk'               => $r['NOMOR_INDUK'] ?? null,
            'id_upt'                    => $r['ID_UPT'] ?? null,
            'id_reg'                    => $r['ID_REG'] ?? null,
            'id_status'                 => $r['ID_STATUS'] ?? null,
            'id_sub_status'             => $r['ID_SUB_STATUS'] ?? null,
            'is_tahanan'                => isset($r['IS_TAHANAN']) ? (int) $r['IS_TAHANAN'] : null,
            'nmr_reg_gol'               => $r['NMR_REG_GOL'] ?? null,
            'nmr_reg_instansi'          => $r['NMR_REG_INSTANSI'] ?? null,
            'tgl_msk_lapas'             => $r['TGL_MSK_LAPAS'] ?? null,
            'tgl_ekspirasi'             => $r['TGL_EKSPIRASI'] ?? null,
            'tgl_ekspirasi_awal'        => $r['TGL_EKSPIRASI_AWAL'] ?? null,
            'tgl_pertama_ditahan'       => $r['TGL_PERTAMA_DITAHAN'] ?? null,
            'tgl_akhir_ditahan'         => $r['TGL_AKHIR_DITAHAN'] ?? null,
            'id_instansi_penyidik'      => $r['ID_INSTANSI_PENYIDIK'] ?? null,
            'id_instansi_penyidik_lain' => $r['ID_INSTANSI_PENYIDIK_LAIN'] ?? null,
            'keterangan'                => $r['KETERANGAN'] ?? null,
            'lokasi_sel'                => $r['LOKASI_SEL'] ?? null,
            'lokasi_dokumen'            => $r['LOKASI_DOKUMEN'] ?? null,
            'tahun_hukuman'             => $r['TAHUN_HUKUMAN'] ?? null,
            'bulan_hukuman'             => $r['BULAN_HUKUMAN'] ?? null,
            'hari_hukuman'              => $r['HARI_HUKUMAN'] ?? null,
            'tgl_entry'                 => $r['TGL_ENTRY'] ?? null,
            'tgl_mg'                    => $r['TGL_MG'] ?? null,
            'is_delete'                 => isset($r['IS_DELETE']) ? (int) $r['IS_DELETE'] : null,
        ];
    }

    /**
     * R6-style list of active perkara (registrasi), org-scoped.
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listRegistrasi(int $perPage = 10, ?string $search = null, int $page = 1): array
    {
        $perPage = max(1, min($perPage, 100));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $idUptFilter = $this->resolveIdUptFilter();

        $countBuilder = $this->perkara->builder()->where('IS_DELETE', 0);
        $listBuilder  = $this->perkara->builder()->where('IS_DELETE', 0);

        if ($idUptFilter !== null) {
            $countBuilder->where('ID_UPT', $idUptFilter);
            $listBuilder->where('ID_UPT', $idUptFilter);
        }

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            foreach ([$countBuilder, $listBuilder] as $b) {
                $b->groupStart()
                    ->like('NOMOR_INDUK', $term)
                    ->orLike('NMR_REG_GOL', $term)
                    ->orLike('ID_PERKARA', $term)
                    ->groupEnd();
            }
        }

        $total = (int) $countBuilder->countAllResults(false);

        $rows = $listBuilder
            ->orderBy('TGL_MSK_LAPAS', 'DESC')
            ->orderBy('ID_PERKARA', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(fn (array $r): array => $this->presentPerkaraSummary($r), $rows);

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'perPage'   => $perPage,
                'total'     => $total,
                'pageCount' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            ],
        ];
    }

    /**
     * Full registrasi detail for one perkara (R4/R6 show).
     *
     * @return array<string, mixed>
     */
    public function findRegistrasiOrFail(string $idPerkara): array
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

        $db = $this->perkara->db;

        $kejahatan = $db->table('kejahatan')
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETED', 0)
            ->orderBy('IS_KEJAHATAN_UTAMA', 'DESC')
            ->orderBy('ID_KEJAHATAN', 'ASC')
            ->get()
            ->getResultArray();

        $hukuman = $db->table('hukuman')
            ->where('ID_PERKARA', $idPerkara)
            ->orderBy('ID_HKMAN', 'ASC')
            ->get()
            ->getRowArray();

        $historyCount = (int) $db->table('history_registrasi')
            ->where('ID_PERKARA', $idPerkara)
            ->where('IS_DELETE', 0)
            ->countAllResults();

        $nomorInduk = (string) ($row['NOMOR_INDUK'] ?? '');
        $identitas  = null;
        if ($nomorInduk !== '') {
            try {
                $identitas = $this->findOrFail($nomorInduk);
                // avoid nesting full perkara list again inside identitas for this shape
                unset($identitas['perkara']);
            } catch (PageNotFoundException) {
                $identitas = null;
            }
        }

        return [
            'id_perkara'              => $row['ID_PERKARA'] ?? null,
            'nomor_induk'             => $row['NOMOR_INDUK'] ?? null,
            'id_upt'                  => $row['ID_UPT'] ?? null,
            'id_reg'                  => $row['ID_REG'] ?? null,
            'id_status'               => $row['ID_STATUS'] ?? null,
            'id_sub_status'           => $row['ID_SUB_STATUS'] ?? null,
            'is_tahanan'              => isset($row['IS_TAHANAN']) ? (int) $row['IS_TAHANAN'] : null,
            'nmr_reg_gol'             => $row['NMR_REG_GOL'] ?? null,
            'nmr_reg_instansi'        => $row['NMR_REG_INSTANSI'] ?? null,
            'tgl_msk_lapas'           => $row['TGL_MSK_LAPAS'] ?? null,
            'tgl_ekspirasi'           => $row['TGL_EKSPIRASI'] ?? null,
            'tgl_ekspirasi_awal'      => $row['TGL_EKSPIRASI_AWAL'] ?? null,
            'tgl_pertama_ditahan'     => $row['TGL_PERTAMA_DITAHAN'] ?? null,
            'tgl_akhir_ditahan'       => $row['TGL_AKHIR_DITAHAN'] ?? null,
            'id_instansi_penyidik'    => $row['ID_INSTANSI_PENYIDIK'] ?? null,
            'id_instansi_penyidik_lain' => $row['ID_INSTANSI_PENYIDIK_LAIN'] ?? null,
            'keterangan'              => $row['KETERANGAN'] ?? null,
            'lokasi_blok'             => $row['LOKASI_BLOK'] ?? null,
            'lokasi_sel'              => $row['LOKASI_SEL'] ?? null,
            'tahun_hukuman'           => $row['TAHUN_HUKUMAN'] ?? null,
            'bulan_hukuman'           => $row['BULAN_HUKUMAN'] ?? null,
            'hari_hukuman'            => $row['HARI_HUKUMAN'] ?? null,
            'history_count'           => $historyCount,
            'kejahatan'               => array_map(static function (array $k): array {
                return [
                    'id_kejahatan'       => $k['ID_KEJAHATAN'] ?? null,
                    'pasal_utama'        => $k['PASAL_UTAMA'] ?? null,
                    'pasal_tambahan'     => $k['PASAL_TAMBAHAN'] ?? null,
                    'uu_kejahatan'       => $k['UU_KEJAHATAN'] ?? null,
                    'id_terminologi'     => $k['ID_TERMINOLOGI'] ?? null,
                    'is_kejahatan_utama' => isset($k['IS_KEJAHATAN_UTAMA']) ? (int) $k['IS_KEJAHATAN_UTAMA'] : null,
                    'noreggol'           => $k['NOREGGOL'] ?? null,
                    'wilayah'            => $k['WILAYAH'] ?? null,
                    'deskripsi'          => $k['DESKRIPSI'] ?? null,
                ];
            }, $kejahatan),
            'hukuman' => $hukuman === null ? null : [
                'id_hkman'         => $hukuman['ID_HKMAN'] ?? null,
                'id_jenis_hukuman' => $hukuman['ID_JENIS_HUKUMAN'] ?? null,
                'tgl_putusan'      => $hukuman['TGL_PUTUSAN'] ?? null,
                'nmr_putusan'      => $hukuman['NMR_PUTUSAN'] ?? null,
                'pasal'            => $hukuman['PASAL'] ?? null,
                'thn_kurung'       => isset($hukuman['THN_KURUNG']) ? (int) $hukuman['THN_KURUNG'] : null,
                'bln_kurung'       => isset($hukuman['BLN_KURUNG']) ? (int) $hukuman['BLN_KURUNG'] : null,
                'hr_kurung'        => isset($hukuman['HR_KURUNG']) ? (int) $hukuman['HR_KURUNG'] : null,
                'denda'            => $hukuman['DENDA'] ?? null,
                'up'               => $hukuman['UP'] ?? null,
                'hakim_ketua'      => $hukuman['HAKIM_KETUA'] ?? null,
                'jaksa'            => $hukuman['JAKSA'] ?? null,
            ],
            'identitas' => $identitas,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(int $perPage = 10, ?string $search = null, int $page = 1): array
    {
        $perPage = max(1, min($perPage, 100));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $idUptFilter = $this->resolveIdUptFilter();

        $countBuilder = $this->identitas->builder()->where('IS_DELETED', 0);
        $listBuilder  = $this->identitas->builder()->where('IS_DELETED', 0);

        if ($idUptFilter !== null) {
            // Include identities with perkara at UPT, or new R2 rows whose NOMOR_INDUK is prefixed with UPT.
            $this->applyUptScopeIncludingNew($countBuilder, $idUptFilter);
            $this->applyUptScopeIncludingNew($listBuilder, $idUptFilter);
        }

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            foreach ([$countBuilder, $listBuilder] as $b) {
                $b->groupStart()
                    ->like('NAMA_LENGKAP', $term)
                    ->orLike('NOMOR_INDUK', $term)
                    ->orLike('NAMA_ALIAS1', $term)
                    ->orLike('NIK', $term)
                    ->groupEnd();
            }
        }

        $total = (int) $countBuilder->countAllResults(false);

        $items = $listBuilder
            ->orderBy('NAMA_LENGKAP', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(fn (array $row): array => $this->presentIdentitas($row), $items);

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'perPage'   => $perPage,
                'total'     => $total,
                'pageCount' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(string $nomorInduk): array
    {
        $row = $this->identitas->builder()
            ->where('NOMOR_INDUK', $nomorInduk)
            ->where('IS_DELETED', 0)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw PageNotFoundException::forPageNotFound("WBP {$nomorInduk} not found.");
        }

        $idUptFilter = $this->resolveIdUptFilter();
        if ($idUptFilter !== null && ! $this->isIdentitasInUptScope((string) $row['NOMOR_INDUK'], $idUptFilter)) {
            throw PageNotFoundException::forPageNotFound("WBP {$nomorInduk} not found.");
        }

        $payload            = $this->presentIdentitas($row);
        $payload['perkara'] = $this->listPerkaraForIdentitas((string) $row['NOMOR_INDUK'], $idUptFilter);

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPerkaraForIdentitas(string $nomorInduk, ?string $idUpt = null): array
    {
        $builder = $this->perkara->builder()
            ->where('NOMOR_INDUK', $nomorInduk)
            ->where('IS_DELETE', 0);

        if ($idUpt !== null) {
            $builder->where('ID_UPT', $idUpt);
        }

        $rows = $builder->orderBy('TGL_MSK_LAPAS', 'DESC')->get()->getResultArray();

        return array_map(fn (array $r): array => $this->presentPerkaraSummary($r), $rows);
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    protected function presentPerkaraSummary(array $r): array
    {
        return [
            'id_perkara'         => $r['ID_PERKARA'] ?? null,
            'nomor_induk'        => $r['NOMOR_INDUK'] ?? null,
            'id_upt'             => $r['ID_UPT'] ?? null,
            'id_reg'             => $r['ID_REG'] ?? null,
            'id_status'          => $r['ID_STATUS'] ?? null,
            'id_sub_status'      => $r['ID_SUB_STATUS'] ?? null,
            'is_tahanan'         => isset($r['IS_TAHANAN']) ? (int) $r['IS_TAHANAN'] : null,
            'nmr_reg_gol'        => $r['NMR_REG_GOL'] ?? null,
            'tgl_msk_lapas'      => $r['TGL_MSK_LAPAS'] ?? null,
            'tgl_ekspirasi'      => $r['TGL_EKSPIRASI'] ?? null,
            'tgl_ekspirasi_awal' => $r['TGL_EKSPIRASI_AWAL'] ?? null,
        ];
    }

    /**
     * When active org code matches a legacy ID_UPT, scope to that unit.
     * Kanwil / unknown codes → no UPT filter (seed pilot).
     */
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

        if ($type === 'kanwil' || $code === '') {
            return null;
        }

        // Unit orgs: code should be legacy ID_UPT (e.g. 093)
        return $code;
    }

    /**
     * @param object $builder Query Builder
     */
    protected function applyUptScopeIncludingNew($builder, string $idUpt): void
    {
        $esc = $this->identitas->db->escape($idUpt);
        $builder->groupStart()
            ->where(
                "NOMOR_INDUK IN (SELECT NOMOR_INDUK FROM perkara WHERE ID_UPT = {$esc} AND IS_DELETE = 0)",
                null,
                false,
            )
            ->orGroupStart()
                ->like('NOMOR_INDUK', $idUpt, 'after')
                ->where(
                    'NOMOR_INDUK NOT IN (SELECT NOMOR_INDUK FROM perkara WHERE IS_DELETE = 0 AND NOMOR_INDUK IS NOT NULL)',
                    null,
                    false,
                )
            ->groupEnd()
            ->groupEnd();
    }

    protected function identitasHasUpt(string $nomorInduk, string $idUpt): bool
    {
        $row = $this->perkara->builder()
            ->select('ID_PERKARA')
            ->where('NOMOR_INDUK', $nomorInduk)
            ->where('ID_UPT', $idUpt)
            ->where('IS_DELETE', 0)
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row !== null;
    }

    /**
     * Unit scope: has active perkara at ID_UPT, or no active perkara yet and
     * NOMOR_INDUK is prefixed with that UPT (new R2 identitas before registrasi).
     */
    protected function isIdentitasInUptScope(string $nomorInduk, string $idUpt): bool
    {
        if ($this->identitasHasUpt($nomorInduk, $idUpt)) {
            return true;
        }

        $activeAnywhere = (int) $this->perkara->builder()
            ->where('NOMOR_INDUK', $nomorInduk)
            ->where('IS_DELETE', 0)
            ->countAllResults();

        if ($activeAnywhere > 0) {
            return false;
        }

        return str_starts_with($nomorInduk, $idUpt);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function presentIdentitas(array $row): array
    {
        return [
            'nomor_induk'         => $row['NOMOR_INDUK'] ?? null,
            'nama_lengkap'        => $row['NAMA_LENGKAP'] ?? null,
            'nama_alias1'         => $row['NAMA_ALIAS1'] ?? null,
            'tanggal_lahir'       => $row['TANGGAL_LAHIR'] ?? null,
            'id_jenis_kelamin'    => $row['ID_JENIS_KELAMIN'] ?? null,
            'id_tempat_lahir'     => $row['ID_TEMPAT_LAHIR'] ?? null,
            'alamat'              => $row['ALAMAT'] ?? null,
            'nik'                 => $row['NIK'] ?? null,
            'id_jenis_agama'      => $row['ID_JENIS_AGAMA'] ?? null,
            'id_jenis_pekerjaan'  => $row['ID_JENIS_PEKERJAAN'] ?? null,
            'id_jenis_warganegara'=> $row['ID_JENIS_WARGANEGARA'] ?? null,
            'telepon'             => $row['TELEPON'] ?? null,
            'is_deleted'          => isset($row['IS_DELETED']) ? (int) $row['IS_DELETED'] : null,
        ];
    }
}
