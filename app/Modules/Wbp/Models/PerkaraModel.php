<?php

namespace App\Modules\Wbp\Models;

use CodeIgniter\Model;

/**
 * Legacy perkara (registration / case header). PK = ID_PERKARA (string).
 */
class PerkaraModel extends Model
{
    protected $table            = 'perkara';
    protected $primaryKey       = 'ID_PERKARA';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'ID_PERKARA',
        'ID_PERKARA_PARENT',
        'NOMOR_INDUK',
        'ID_UPT',
        'ID_REG',
        'ID_STATUS',
        'ID_SUB_STATUS',
        'IS_TAHANAN',
        'NMR_REG_GOL',
        'NMR_REG_INSTANSI',
        'TGL_MSK_LAPAS',
        'TGL_EKSPIRASI',
        'TGL_EKSPIRASI_AWAL',
        'TGL_PERTAMA_DITAHAN',
        'TGL_AKHIR_DITAHAN',
        'TGL_ENTRY',
        'TGL_MG',
        'IS_DELETE',
        'IS_DENDA_LUNAS',
        'IS_UP_LUNAS',
        'IS_RESTITUSI_LUNAS',
        'ID_INSTANSI_PENYIDIK',
        'ID_INSTANSI_PENYIDIK_LAIN',
        'ID_USER',
        'KETERANGAN',
        'LOKASI_BLOK',
        'LOKASI_SEL',
        'TAHUN_HUKUMAN',
        'BULAN_HUKUMAN',
        'HARI_HUKUMAN',
        'KONSOLIDASI',
        'APPROVED',
        'APPROVED_BY',
        'CREATED',
        'CREATED_BY',
        'UPDATED',
        'UPDATED_BY',
    ];
    protected $useTimestamps = false;

    public function activeOnly(): self
    {
        $this->where('IS_DELETE', 0);

        return $this;
    }
}
