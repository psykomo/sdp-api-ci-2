<?php

namespace App\Modules\Wbp\Models;

use CodeIgniter\Model;

class KejahatanModel extends Model
{
    protected $table            = 'kejahatan';
    protected $primaryKey       = 'ID_KEJAHATAN';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'ID_KEJAHATAN',
        'ID_PERKARA',
        'NOREGGOL',
        'ID_TERMINOLOGI',
        'ID_TERMINOLOGI_LAIN',
        'IS_KEJAHATAN_UTAMA',
        'PASAL_UTAMA',
        'PASAL_TAMBAHAN',
        'UU_KEJAHATAN',
        'WILAYAH',
        'DESKRIPSI',
        'IS_DELETED',
        'KONSOLIDASI',
        'CREATED',
        'CREATED_BY',
        'UPDATED',
        'UPDATED_BY',
    ];
}
