<?php

namespace App\Modules\Wbp\Models;

use CodeIgniter\Model;

/**
 * Legacy identitas (WBP person). PK = NOMOR_INDUK (string).
 */
class IdentitasModel extends Model
{
    protected $table            = 'identitas';
    protected $primaryKey       = 'NOMOR_INDUK';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // legacy flag IS_DELETED, not deleted_at
    protected $allowedFields    = [
        'NOMOR_INDUK',
        'NAMA_LENGKAP',
        'NAMA_ALIAS1',
        'NAMA_ALIAS2',
        'NAMA_ALIAS3',
        'NAMA_KECIL1',
        'NAMA_KECIL2',
        'NAMA_KECIL3',
        'TANGGAL_LAHIR',
        'ID_JENIS_KELAMIN',
        'ID_TEMPAT_LAHIR',
        'ID_TEMPAT_LAHIR_LAIN',
        'ID_TEMPAT_ASAL',
        'ID_TEMPAT_ASAL_LAIN',
        'ID_KOTA',
        'ID_KOTA_LAIN',
        'ALAMAT',
        'ALAMAT_ALTERNATIF',
        'NIK',
        'ID_JENIS_AGAMA',
        'ID_JENIS_AGAMA_LAIN',
        'ID_JENIS_PEKERJAAN',
        'ID_JENIS_PEKERJAAN_LAIN',
        'ID_JENIS_WARGANEGARA',
        'ID_NEGARA_ASING',
        'ID_PROPINSI',
        'ID_PROPINSI_LAIN',
        'ID_JENIS_PENDIDIKAN',
        'ID_JENIS_STATUS_PERKAWINAN',
        'ID_TINGKAT_PENGHASILAN',
        'TELEPON',
        'KODEPOS',
        'NM_AYAH',
        'NM_IBU',
        'TINGGI',
        'BERAT',
        'RESIDIVIS',
        'RESIDIVIS_COUNTER',
        'ID_USER',
        'KONSOLIDASI',
        'KONSOLIDASI_IMAGE',
        'IS_DELETED',
        'CREATED',
        'CREATED_BY',
        'UPDATED',
        'UPDATED_BY',
    ];
    protected $useTimestamps = false;

    /**
     * Active rows only (legacy soft delete).
     */
    public function activeOnly(): self
    {
        $this->where('IS_DELETED', 0);

        return $this;
    }
}
