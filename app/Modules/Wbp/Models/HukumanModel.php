<?php

namespace App\Modules\Wbp\Models;

use CodeIgniter\Model;

class HukumanModel extends Model
{
    protected $table            = 'hukuman';
    protected $primaryKey       = 'ID_HKMAN';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'ID_HKMAN',
        'ID_PERKARA',
        'ID_JENIS_HUKUMAN',
        'ID_USER',
        'TGL_PUTUSAN',
        'NMR_PUTUSAN',
        'PASAL',
        'THN_KURUNG',
        'BLN_KURUNG',
        'HR_KURUNG',
        'DENDA',
        'UP',
        'HAKIM_KETUA',
        'JAKSA',
        'TGL_DIJALANKAN_PTSN',
    ];
}
