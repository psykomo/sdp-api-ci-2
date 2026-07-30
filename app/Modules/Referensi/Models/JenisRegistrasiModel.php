<?php

namespace App\Modules\Referensi\Models;

use CodeIgniter\Model;

/**
 * Legacy master: jenis_registrasi (golongan / jenis reg).
 */
class JenisRegistrasiModel extends Model
{
    protected $table         = 'jenis_registrasi';
    protected $primaryKey    = 'ID_REG';
    protected $useAutoIncrement = false;
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'ID_REG',
        'ID_JENIS_REGISTRASI',
        'DESKRIPSI',
        'LAMA_HUKUMAN',
        'IS_TAHANAN',
        'KETERANGAN',
        'LEVEL',
        'IS_ACTIVE',
        'status_download',
    ];
    protected $useTimestamps = false;
}
