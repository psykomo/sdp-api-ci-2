<?php

namespace App\Modules\Mutasi\Services;

use App\Modules\Mutasi\Actions\MutasiGolongan;
use App\Modules\Mutasi\Models\MutasiGolonganModel;
use App\Modules\Referensi\Models\JenisRegistrasiModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Services\OrgContext;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * M1 facade — mutasi golongan reads + command.
 */
class MutasiGolonganService
{
    public function __construct(
        protected ?OrgContext $orgContext = null,
        protected ?WbpQueryService $wbpQuery = null,
        protected ?MutasiGolongan $action = null,
        protected MutasiGolonganModel $mutasi = new MutasiGolonganModel(),
        protected JenisRegistrasiModel $jenisRegistrasi = new JenisRegistrasiModel(),
    ) {
        $this->orgContext ??= service('orgContext');
        $this->wbpQuery ??= new WbpQueryService(orgContext: $this->orgContext);
        $this->action ??= new MutasiGolongan(
            mutasi: $this->mutasi,
            jenisRegistrasi: $this->jenisRegistrasi,
            orgContext: $this->orgContext,
            wbpQuery: $this->wbpQuery,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return $this->action->execute($data);
    }

    /**
     * Allowed target golongan for a perkara (LEVEL > current), legacy pilihan_mutasi.
     *
     * @return list<array<string, mixed>>
     */
    public function options(string $idPerkara): array
    {
        $perkara = $this->wbpQuery->findRegistrasiOrFail($idPerkara);
        $idRegAwal = (string) ($perkara['id_reg'] ?? '');
        if ($idRegAwal === '') {
            return [];
        }

        $jenisAwal = $this->jenisRegistrasi->find($idRegAwal);
        $levelAwal = $jenisAwal !== null && isset($jenisAwal['LEVEL'])
            ? (int) $jenisAwal['LEVEL']
            : null;

        $builder = $this->jenisRegistrasi->builder()
            ->where('IS_ACTIVE', 1)
            ->where('ID_REG !=', $idRegAwal);

        if ($levelAwal !== null) {
            $builder->where('LEVEL >', $levelAwal);
        }

        $rows = $builder->orderBy('LEVEL', 'ASC')->orderBy('ID_REG', 'ASC')->get()->getResultArray();

        return array_map(static function (array $r): array {
            return [
                'id_reg'     => $r['ID_REG'] ?? null,
                'deskripsi'  => $r['DESKRIPSI'] ?? null,
                'is_tahanan' => isset($r['IS_TAHANAN']) ? (int) $r['IS_TAHANAN'] : null,
                'level'      => isset($r['LEVEL']) ? (int) $r['LEVEL'] : null,
            ];
        }, $rows);
    }

    /**
     * List mutasi_golongan for a perkara (org-scoped via perkara).
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listForPerkara(string $idPerkara, int $perPage = 50, int $page = 1): array
    {
        $this->wbpQuery->findRegistrasiOrFail($idPerkara);

        $perPage = max(1, min($perPage, 100));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $total = (int) $this->mutasi->builder()
            ->where('ID_PERKARA', $idPerkara)
            ->countAllResults();

        $rows = $this->mutasi->builder()
            ->where('ID_PERKARA', $idPerkara)
            ->orderBy('TGL_EFEKTIF', 'DESC')
            ->orderBy('ID_MUTASI_TAHANAN', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(fn (array $r): array => $this->present($r), $rows);

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
    public function findOrFail(string $idMutasi): array
    {
        $row = $this->mutasi->find($idMutasi);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound("Mutasi golongan {$idMutasi} not found.");
        }

        $idPerkara = (string) ($row['ID_PERKARA'] ?? '');
        if ($idPerkara !== '') {
            // Org scope check
            $this->wbpQuery->findRegistrasiOrFail($idPerkara);
        }

        return $this->present($row);
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    protected function present(array $r): array
    {
        return [
            'id_mutasi_tahanan' => $r['ID_MUTASI_TAHANAN'] ?? null,
            'id_perkara'       => $r['ID_PERKARA'] ?? null,
            'id_reg_awal'      => $r['ID_REG_AWAL'] ?? null,
            'id_reg_akhir'     => $r['ID_REG_AKHIR'] ?? null,
            'tgl_efektif'      => $r['TGL_EFEKTIF'] ?? null,
            'nmr_srt_mg'       => $r['NMR_SRT_MG'] ?? null,
            'tgl_srt_mg'       => $r['TGL_SRT_MG'] ?? null,
            'penandatangan'    => $r['PENANDATANGAN'] ?? null,
            'keterangan'       => $r['KETERANGAN'] ?? null,
            'tgl_entry'        => $r['TGL_ENTRY'] ?? null,
        ];
    }
}
