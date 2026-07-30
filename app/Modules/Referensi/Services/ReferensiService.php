<?php

namespace App\Modules\Referensi\Services;

use App\Modules\Referensi\Models\DaftarReferensiModel;
use App\Modules\Referensi\Models\JenisRegistrasiModel;
use App\Modules\Referensi\Models\UptModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * R0 read surface: form lookups against legacy referensi tables.
 */
class ReferensiService
{
    public function __construct(
        protected JenisRegistrasiModel $jenisRegistrasi = new JenisRegistrasiModel(),
        protected DaftarReferensiModel $daftarReferensi = new DaftarReferensiModel(),
        protected UptModel $upt = new UptModel(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listJenisRegistrasi(?bool $activeOnly = true, ?int $isTahanan = null): array
    {
        $builder = $this->jenisRegistrasi->builder();

        if ($activeOnly) {
            $builder->where('IS_ACTIVE', 1);
        }

        if ($isTahanan !== null) {
            $builder->where('IS_TAHANAN', $isTahanan);
        }

        return $builder->orderBy('ID_REG', 'ASC')->get()->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLookupGroups(): array
    {
        $rows = $this->daftarReferensi->builder()
            ->select('GROUPS')
            ->distinct()
            ->where('GROUPS IS NOT NULL')
            ->where('GROUPS !=', '')
            ->orderBy('GROUPS', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_map(static fn (array $r): string => (string) $r['GROUPS'], $rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLookupsByGroup(string $group): array
    {
        $group = trim($group);
        if ($group === '') {
            return [];
        }

        return $this->daftarReferensi->builder()
            ->where('GROUPS', $group)
            ->orderBy('DESKRIPSI', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function findLookupOrFail(string $idLookup): array
    {
        $row = $this->daftarReferensi->find($idLookup);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound("Lookup {$idLookup} not found.");
        }

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUpt(?string $search = null, int $limit = 100): array
    {
        $builder = $this->upt->builder();

        if ($search !== null && $search !== '') {
            $builder->groupStart()
                ->like('ID_UPT', $search)
                ->orLike('URAIAN', $search)
                ->groupEnd();
        }

        return $builder->orderBy('ID_UPT', 'ASC')->limit(max(1, min($limit, 500)))->get()->getResultArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function findUptOrFail(string $idUpt): array
    {
        $row = $this->upt->find($idUpt);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound("UPT {$idUpt} not found.");
        }

        return $row;
    }
}
