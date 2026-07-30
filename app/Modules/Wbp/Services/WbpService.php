<?php

namespace App\Modules\Wbp\Services;

use App\Modules\Wbp\Actions\DaftarHistoryRegistrasi;
use App\Modules\Wbp\Actions\DaftarIdentitas;
use App\Modules\Wbp\Actions\HapusHistoryRegistrasi;
use App\Modules\Wbp\Actions\HapusIdentitas;
use App\Modules\Wbp\Actions\RegistrasiBaru;
use App\Modules\Wbp\Actions\UbahHistoryRegistrasi;
use App\Modules\Wbp\Actions\UbahIdentitas;
use App\Modules\Wbp\Actions\UbahRegistrasi;
use App\Services\OrgContext;
use DomainException;

/**
 * Wbp facade — R1/R2 identitas, R3–R5 registrasi/history, R6 list/show.
 */
class WbpService
{
    protected WbpQueryService $query;

    protected DaftarIdentitas $daftar;

    protected UbahIdentitas $ubah;

    protected HapusIdentitas $hapus;

    protected RegistrasiBaru $registrasiBaru;

    protected UbahRegistrasi $ubahRegistrasi;

    protected DaftarHistoryRegistrasi $daftarHistory;

    protected UbahHistoryRegistrasi $ubahHistory;

    protected HapusHistoryRegistrasi $hapusHistory;

    public function __construct(
        protected ?OrgContext $orgContext = null,
        ?WbpQueryService $query = null,
        ?DaftarIdentitas $daftar = null,
        ?UbahIdentitas $ubah = null,
        ?HapusIdentitas $hapus = null,
        ?RegistrasiBaru $registrasiBaru = null,
        ?UbahRegistrasi $ubahRegistrasi = null,
        ?DaftarHistoryRegistrasi $daftarHistory = null,
        ?UbahHistoryRegistrasi $ubahHistory = null,
        ?HapusHistoryRegistrasi $hapusHistory = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->query  = $query ?? new WbpQueryService(orgContext: $this->orgContext);
        $this->daftar = $daftar ?? new DaftarIdentitas(orgContext: $this->orgContext, query: $this->query);
        $this->ubah   = $ubah ?? new UbahIdentitas(orgContext: $this->orgContext, query: $this->query);
        $this->hapus  = $hapus ?? new HapusIdentitas(orgContext: $this->orgContext, query: $this->query);
        $this->registrasiBaru = $registrasiBaru ?? new RegistrasiBaru(
            orgContext: $this->orgContext,
            query: $this->query,
        );
        $this->ubahRegistrasi = $ubahRegistrasi ?? new UbahRegistrasi(
            orgContext: $this->orgContext,
            query: $this->query,
        );
        $this->daftarHistory = $daftarHistory ?? new DaftarHistoryRegistrasi(
            orgContext: $this->orgContext,
            query: $this->query,
        );
        $this->ubahHistory = $ubahHistory ?? new UbahHistoryRegistrasi(
            orgContext: $this->orgContext,
            query: $this->query,
        );
        $this->hapusHistory = $hapusHistory ?? new HapusHistoryRegistrasi(
            orgContext: $this->orgContext,
            query: $this->query,
        );
    }

    /**
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function list(int $perPage = 10, ?string $search = null, int $page = 1): array
    {
        return $this->query->list($perPage, $search, $page);
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(int|string $id): array
    {
        return $this->query->findOrFail((string) $id);
    }

    /**
     * R2 create identitas.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return $this->daftar->execute($data);
    }

    /**
     * R2 update identitas.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int|string $id, array $data): array
    {
        return $this->ubah->execute((string) $id, $data);
    }

    /**
     * R2 soft-delete identitas (IS_DELETED=1). Blocked if active perkara exist.
     */
    public function delete(int|string $id): void
    {
        $this->hapus->execute((string) $id);
    }

    /**
     * R3 multi-table registrasi create.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRegistrasi(array $data): array
    {
        return $this->registrasiBaru->execute($data);
    }

    /**
     * R4 update registrasi (perkara + optional kejahatan/hukuman + history append).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateRegistrasi(string $idPerkara, array $data): array
    {
        return $this->ubahRegistrasi->execute($idPerkara, $data);
    }

    /**
     * R6 list active registrasi (perkara).
     *
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function listRegistrasi(int $perPage = 10, ?string $search = null, int $page = 1): array
    {
        return $this->query->listRegistrasi($perPage, $search, $page);
    }

    /**
     * R6/R4 show one registrasi by ID_PERKARA.
     *
     * @return array<string, mixed>
     */
    public function findRegistrasiOrFail(string $idPerkara): array
    {
        return $this->query->findRegistrasiOrFail($idPerkara);
    }

    /**
     * R5 list history for a perkara.
     *
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function listHistory(string $idPerkara, int $perPage = 50, int $page = 1): array
    {
        return $this->query->listHistory($idPerkara, $perPage, $page);
    }

    /**
     * R5 show one history row.
     *
     * @return array<string, mixed>
     */
    public function findHistoryOrFail(string $idPerkara, string $idHistoryReg): array
    {
        return $this->query->findHistoryOrFail($idPerkara, $idHistoryReg);
    }

    /**
     * R5 append history snapshot.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createHistory(string $idPerkara, array $data = []): array
    {
        return $this->daftarHistory->execute($idPerkara, $data);
    }

    /**
     * R5 update history row (+ optional shared kejahatan/hukuman).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateHistory(string $idPerkara, string $idHistoryReg, array $data): array
    {
        return $this->ubahHistory->execute($idPerkara, $idHistoryReg, $data);
    }

    /**
     * R5 soft-delete history row.
     *
     * @return array{id_history_reg: string, id_perkara: string, is_delete: int}
     */
    public function deleteHistory(string $idPerkara, string $idHistoryReg): array
    {
        return $this->hapusHistory->execute($idPerkara, $idHistoryReg);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function transferOwnership(int|string $id, array $data): object
    {
        throw new DomainException('Legacy mutasi UPT (M2) is not implemented yet.');
    }
}
