<?php

namespace App\Modules\Referensi\Models;

use CodeIgniter\Model;

/**
 * Legacy UPT master: upt.
 */
class UptModel extends Model
{
    protected $table            = 'upt';
    protected $primaryKey       = 'ID_UPT';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'ID_UPT',
        'URAIAN',
        'KANWIL',
        'JENIS',
        'KELAS',
        'DATI2',
    ];
    protected $useTimestamps = false;
}
