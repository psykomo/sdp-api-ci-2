<?php

namespace App\Modules\Referensi\Models;

use CodeIgniter\Model;

/**
 * Legacy lookup catalog: daftar_referensi (Agama, Pekerjaan, …).
 */
class DaftarReferensiModel extends Model
{
    protected $table            = 'daftar_referensi';
    protected $primaryKey       = 'ID_LOOKUP';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'ID_LOOKUP',
        'GROUPS',
        'DESKRIPSI',
        'CATATAN',
        'CONTENT',
        'status_download',
    ];
    protected $useTimestamps = false;
}
