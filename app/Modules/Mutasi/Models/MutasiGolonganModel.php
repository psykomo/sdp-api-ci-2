<?php

namespace App\Modules\Mutasi\Models;

use CodeIgniter\Model;

/**
 * Legacy mutasi_golongan — PK ID_MUTASI_TAHANAN.
 */
class MutasiGolonganModel extends Model
{
    protected $table            = 'mutasi_golongan';
    protected $primaryKey       = 'ID_MUTASI_TAHANAN';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'ID_MUTASI_TAHANAN',
        'ID_PERKARA',
        'NMR_SRT_MG',
        'TGL_SRT_MG',
        'TGL_EFEKTIF',
        'PENANDATANGAN',
        'ID_REG_AWAL',
        'ID_REG_AKHIR',
        'KETERANGAN',
        'TGL_ENTRY',
        'ID_USER',
        'KONSOLIDASI',
        'CREATED',
        'CREATED_BY',
        'UPDATED',
        'UPDATED_BY',
    ];
}
