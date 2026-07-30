<?php

namespace App\Modules\Wbp\Models;

use CodeIgniter\Model;

class HistoryRegistrasiModel extends Model
{
    protected $table            = 'history_registrasi';
    protected $primaryKey       = 'ID_HISTORY_REG';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'ID_HISTORY_REG',
        'ID_PERKARA',
        'ID_PERKARA_PARENT',
        'ID_STATUS',
        'ID_SUB_STATUS',
        'ID_REG',
        'ID_UPT',
        'NOMOR_INDUK',
        'IS_TAHANAN',
        'NMR_REG_GOL',
        'NMR_REG_INSTANSI',
        'TGL_MSK_LAPAS',
        'TGL_EKSPIRASI',
        'TGL_EKSPIRASI_AWAL',
        'TGL_PERTAMA_DITAHAN',
        'TGL_AKHIR_DITAHAN',
        'ID_INSTANSI_PENYIDIK',
        'ID_INSTANSI_PENYIDIK_LAIN',
        'IS_DELETE',
        'ID_USER',
        'TGL_ENTRY',
        'KONSOLIDASI',
    ];
}
