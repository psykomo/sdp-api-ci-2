<?php

namespace App\Modules\Wbp\Actions;

use App\Exceptions\ValidationException;
use App\Models\OrganizationModel;
use App\Modules\Wbp\Models\IdentitasModel;
use App\Modules\Wbp\Services\WbpQueryService;
use App\Modules\Wbp\Support\IdentitasFieldMap;
use App\Services\OrgContext;
use App\Services\UnitOfWork;
use DomainException;
use RuntimeException;

/**
 * R2 — create identitas on legacy table.
 *
 * NOMOR_INDUK: client-supplied or generated as {ID_UPT}{Ymd}{seq:04d}
 * (same shape as legacy createNomorInduk, using active org code as UPT).
 * Does not touch sidik_jari / usertbl (deferred).
 */
class DaftarIdentitas
{
    public function __construct(
        protected IdentitasModel $identitas = new IdentitasModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
        protected ?OrganizationModel $organizations = null,
        protected ?WbpQueryService $query = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
        $this->organizations ??= model(OrganizationModel::class, false);
        $this->query ??= new WbpQueryService(
            identitas: $this->identitas,
            orgContext: $this->orgContext,
            organizations: $this->organizations,
        );
    }

    /**
     * @param array<string, mixed> $data API payload
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $row = IdentitasFieldMap::toDb($data, forUpdate: false);

        $nama = trim((string) ($row['NAMA_LENGKAP'] ?? ''));
        if ($nama === '') {
            throw new ValidationException('Validation failed.', [
                'nama_lengkap' => 'Nama lengkap is required.',
            ]);
        }
        $row['NAMA_LENGKAP'] = $nama;

        $idUpt = $this->resolveIdUpt($data);
        $nomorInduk = trim((string) ($row['NOMOR_INDUK'] ?? $data['nomor_induk'] ?? ''));
        if ($nomorInduk === '') {
            $nomorInduk = $this->generateNomorInduk($idUpt);
        }

        if ($this->identitas->find($nomorInduk) !== null) {
            throw new ValidationException('Validation failed.', [
                'nomor_induk' => "NOMOR_INDUK {$nomorInduk} already exists.",
            ]);
        }

        $row['NOMOR_INDUK'] = $nomorInduk;
        $row['IS_DELETED']  = 0;
        $row['KONSOLIDASI'] = $row['KONSOLIDASI'] ?? '0';
        $row['CREATED']     = date('Y-m-d H:i:s');
        $row['UPDATED']     = date('Y-m-d H:i:s');
        $userId             = $this->orgContext->getUserId();
        if ($userId !== null) {
            $row['CREATED_BY'] = (string) $userId;
            $row['ID_USER']    = (string) $userId;
        }

        // Defaults seen in legacy seed data
        $row['RESIDIVIS']         = $row['RESIDIVIS'] ?? 'RDV0';
        $row['RESIDIVIS_COUNTER'] = $row['RESIDIVIS_COUNTER'] ?? 0;

        return $this->unitOfWork->run(function () use ($row, $nomorInduk): array {
            if ($this->identitas->insert($row) === false) {
                throw new ValidationException(
                    'Failed to insert identitas.',
                    $this->identitas->errors() ?: ['database' => 'Insert failed.'],
                );
            }

            try {
                return $this->query->findOrFail($nomorInduk);
            } catch (\Throwable) {
                throw new RuntimeException("Created identitas {$nomorInduk} could not be reloaded.");
            }
        });
    }

    protected function resolveIdUpt(array $data): string
    {
        if (! empty($data['id_upt'])) {
            return (string) $data['id_upt'];
        }

        $orgId = $this->orgContext->getActiveOrgId();
        if ($orgId === null) {
            throw new DomainException('Active organization is required to generate NOMOR_INDUK.');
        }

        $org = $this->organizations->find($orgId);
        if ($org === null) {
            throw new DomainException('Active organization not found.');
        }

        $code = is_array($org) ? (string) ($org['code'] ?? '') : (string) ($org->code ?? '');
        $type = is_array($org) ? (string) ($org['type'] ?? '') : (string) ($org->type ?? '');

        if ($type === 'kanwil' || $code === '' || ! preg_match('/^\d{2,5}$/', $code)) {
            throw new DomainException(
                'Create identitas requires a unit org (ID_UPT code) or explicit nomor_induk / id_upt. '
                . 'Kanwil cannot auto-generate NOMOR_INDUK.',
            );
        }

        return $code;
    }

    /**
     * Legacy-shaped: {ID_UPT}{Ymd}{####}
     */
    protected function generateNomorInduk(string $idUpt): string
    {
        $prefix = $idUpt . date('Ymd');
        $db     = $this->identitas->db;

        $row = $db->table('identitas')
            ->select('NOMOR_INDUK')
            ->like('NOMOR_INDUK', $prefix, 'after')
            ->orderBy('NOMOR_INDUK', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $seq = 1;
        if ($row !== null && ! empty($row['NOMOR_INDUK'])) {
            $tail = substr((string) $row['NOMOR_INDUK'], strlen($prefix), 4);
            if (ctype_digit($tail)) {
                $seq = (int) $tail + 1;
            }
        }

        if ($seq > 9999) {
            throw new DomainException('Daily NOMOR_INDUK sequence exhausted for this UPT.');
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
